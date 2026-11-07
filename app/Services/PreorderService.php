<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Preorder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Membuat dan mengubah PO beserta menu, varian, dan slot ambil.
 *
 * Menu, varian, atau slot yang sudah punya pesanan tidak boleh dihapus,
 * dan kuota tidak boleh turun di bawah porsi yang sudah terjual.
 */
class PreorderService
{
    /**
     * @param  array<string, mixed>  $data  data yang sudah lolos validasi controller
     */
    public function save(Shop $shop, ?Preorder $po, array $data, User $actor, bool $publish): Preorder
    {
        $tz = config('jajapo.timezone');
        $opensAt = Carbon::parse($data['opens_at'], $tz)->utc();
        $closesAt = Carbon::parse($data['closes_at'], $tz)->utc();

        $creating = $po === null;
        $wasDraft = $creating || $po->isDraft();

        if ($wasDraft && $publish && $closesAt->lte(now())) {
            throw ValidationException::withMessages(['closes_at' => 'Waktu tutup sudah lewat. Pilih waktu yang akan datang.']);
        }

        $po = DB::transaction(function () use ($shop, $po, $data, $opensAt, $closesAt, $creating, $wasDraft, $publish, $tz) {
            $po ??= new Preorder(['shop_id' => $shop->id]);

            $status = match (true) {
                ! $wasDraft => $po->status,
                $publish => Preorder::STATUS_ACTIVE,
                default => Preorder::STATUS_DRAFT,
            };

            $po->fill([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'labels' => array_values(array_filter($data['labels'] ?? [], 'filled')) ?: null,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'quota' => $data['quota'] ?? null,
                'delivery_enabled' => (bool) ($data['delivery_enabled'] ?? false),
                'delivery_fee' => ($data['delivery_enabled'] ?? false) ? (int) ($data['delivery_fee'] ?? 0) : 0,
                'status' => $status,
            ]);
            $po->save();

            if (! $creating) {
                Product::query()->where('preorder_id', $po->id)->lockForUpdate()->get();
            }

            $this->syncProducts($po, $data['products']);
            $this->syncSlots($po, $data['slots'] ?? [], $tz);

            $sold = (int) $po->products()->sum('sold_count');
            if ($po->quota !== null && $po->quota < $sold) {
                throw ValidationException::withMessages(['quota' => "Kuota PO tidak bisa di bawah {$sold} porsi yang sudah dipesan."]);
            }
            if (! $po->delivery_enabled && $po->slots()->doesntExist()) {
                throw ValidationException::withMessages(['slots' => 'Tambahkan minimal satu slot ambil, atau aktifkan antar ke fakultas.']);
            }

            return $po;
        });

        $this->storeImage($po, 'poster_path', $data['poster'] ?? null, $data['remove_poster'] ?? false);
        $this->storeImage($po, 'story_image_path', $data['story'] ?? null, $data['remove_story'] ?? false);

        $action = match (true) {
            $creating && $publish => 'preorder.published',
            $creating => 'preorder.drafted',
            $wasDraft && $publish => 'preorder.published',
            default => 'preorder.updated',
        };
        AuditLog::record($actor, $action, $po, ['title' => $po->title]);

        return $po->fresh(['products.variants', 'slots.point']);
    }

    /** Menutup PO lebih awal. Pesanan yang sudah dibuat tetap bisa dibayar sampai batasnya. */
    public function close(Preorder $po, User $actor): void
    {
        if ($po->isDraft() || $po->closes_at->lte(now())) {
            return;
        }

        $po->update(['closes_at' => now()]);
        AuditLog::record($actor, 'preorder.closed_early', $po);
    }

    /** Draf atau PO tanpa pesanan boleh dihapus. */
    public function delete(Preorder $po, User $actor): void
    {
        if ($po->orders()->exists()) {
            throw ValidationException::withMessages(['preorder' => 'PO yang sudah punya pesanan tidak bisa dihapus. Tutup saja PO-nya.']);
        }

        $paths = array_filter([$po->poster_path, $po->story_image_path]);
        AuditLog::record($actor, 'preorder.deleted', $po, ['title' => $po->title]);
        $po->delete();
        Storage::disk('public')->delete($paths);
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function syncProducts(Preorder $po, array $rows): void
    {
        $existing = $po->products()->with('variants')->get()->keyBy('id');
        $keep = [];

        foreach (array_values($rows) as $i => $row) {
            $product = isset($row['id']) ? $existing->get((int) $row['id']) : null;
            if (isset($row['id']) && ! $product) {
                throw ValidationException::withMessages(["products.{$i}.id" => 'Menu ini bukan bagian dari PO ini.']);
            }

            $quota = (int) $row['quota'];
            if ($product && $quota < $product->sold_count) {
                throw ValidationException::withMessages(["products.{$i}.quota" => "Sudah dipesan {$product->sold_count} porsi. Kuota tidak bisa lebih kecil."]);
            }

            $product ??= new Product(['preorder_id' => $po->id]);
            $product->fill([
                'name' => $row['name'],
                'price' => (int) $row['price'],
                'quota' => $quota,
                'sort' => $i,
            ])->save();
            $keep[] = $product->id;

            $this->syncVariants($product, $row['variants'], $i);
        }

        $removed = $existing->keys()->diff($keep);
        if ($removed->isNotEmpty()) {
            $orderedId = OrderItem::query()->whereIn('product_id', $removed)->value('product_id');
            if ($orderedId) {
                $name = $existing->get($orderedId)->name;
                throw ValidationException::withMessages(['products' => "{$name} sudah dipesan, jadi tidak bisa dihapus. Ubah kuotanya kalau ingin berhenti menjual."]);
            }
            Product::query()->whereIn('id', $removed)->delete();
        }
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function syncVariants(Product $product, array $rows, int $productIndex): void
    {
        $existing = $product->exists ? $product->variants()->get()->keyBy('id') : collect();
        $keep = [];

        foreach (array_values($rows) as $j => $row) {
            $variant = isset($row['id']) ? $existing->get((int) $row['id']) : null;
            if (isset($row['id']) && ! $variant) {
                throw ValidationException::withMessages(["products.{$productIndex}.variants.{$j}.id" => 'Varian ini bukan bagian dari menu ini.']);
            }

            $delta = (int) ($row['price_delta'] ?? 0);
            if ($product->price + $delta < 1000) {
                throw ValidationException::withMessages(["products.{$productIndex}.variants.{$j}.price_delta" => 'Harga varian minimal Rp1.000.']);
            }

            $variant ??= new ProductVariant(['product_id' => $product->id]);
            $variant->fill(['name' => $row['name'], 'price_delta' => $delta, 'sort' => $j])->save();
            $keep[] = $variant->id;
        }

        $removed = $existing->keys()->diff($keep);
        if ($removed->isNotEmpty()) {
            if (OrderItem::query()->whereIn('variant_id', $removed)->exists()) {
                throw ValidationException::withMessages(["products.{$productIndex}.variants" => "Ada varian {$product->name} yang sudah dipesan, jadi tidak bisa dihapus."]);
            }
            ProductVariant::query()->whereIn('id', $removed)->delete();
        }
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function syncSlots(Preorder $po, array $rows, string $tz): void
    {
        $existing = $po->slots()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($rows) as $i => $row) {
            $slot = isset($row['id']) ? $existing->get((int) $row['id']) : null;
            if (isset($row['id']) && ! $slot) {
                throw ValidationException::withMessages(["slots.{$i}.id" => 'Slot ini bukan bagian dari PO ini.']);
            }

            $startsAt = Carbon::parse($row['date'].' '.$row['start'], $tz)->utc();
            $endsAt = Carbon::parse($row['date'].' '.$row['end'], $tz)->utc();

            $values = ['pickup_point_id' => (int) $row['pickup_point_id'], 'starts_at' => $startsAt, 'ends_at' => $endsAt];
            if ($slot) {
                $slot->update($values);
            } else {
                $slot = $po->slots()->create($values);
            }
            $keep[] = $slot->id;
        }

        $removed = $existing->keys()->diff($keep);
        if ($removed->isNotEmpty()) {
            if (Order::query()->whereIn('pickup_slot_id', $removed)->exists()) {
                throw ValidationException::withMessages(['slots' => 'Slot yang sudah dipilih pembeli tidak bisa dihapus. Ubah jamnya saja kalau perlu.']);
            }
            $po->slots()->whereIn('id', $removed)->delete();
        }
    }

    private function storeImage(Preorder $po, string $column, mixed $file, bool $remove): void
    {
        $old = $po->{$column};

        if ($file instanceof UploadedFile) {
            $po->update([$column => $file->store("preorders/{$po->id}", 'public')]);
        } elseif ($remove) {
            $po->update([$column => null]);
        } else {
            return;
        }

        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }
}
