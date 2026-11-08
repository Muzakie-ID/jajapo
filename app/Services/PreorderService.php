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
            $delivery = (bool) ($data['delivery_enabled'] ?? false);

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
                'delivery_enabled' => $delivery,
                'delivery_starts_at' => $delivery ? Carbon::parse($data['delivery_date'].' '.$data['delivery_start'], $tz)->utc() : null,
                'delivery_ends_at' => $delivery ? Carbon::parse($data['delivery_date'].' '.$data['delivery_end'], $tz)->utc() : null,
                'status' => $status,
            ]);
            $po->save();

            if (! $creating) {
                Product::query()->where('preorder_id', $po->id)->lockForUpdate()->get();
            }

            $menus = $this->syncProducts($po, $data['products']);
            $this->syncBundles($po, $data['bundles'] ?? [], $menus);
            $this->syncSlots($po, $data['slots'] ?? [], $tz);
            $this->syncZones($po, $delivery ? ($data['zones'] ?? []) : []);

            $sold = (int) $po->products()->where('is_bundle', false)->sum('sold_count');
            if ($po->quota !== null && $po->quota < $sold) {
                throw ValidationException::withMessages(['quota' => "Kuota PO tidak bisa di bawah {$sold} porsi yang sudah dipesan."]);
            }
            if ($po->slots()->doesntExist() && ! ($delivery && $po->deliveryZones()->exists())) {
                throw ValidationException::withMessages(['slots' => 'Tambahkan minimal satu titik ambil, atau aktifkan pengantaran.']);
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

        return $po->fresh(['products.variants', 'slots', 'deliveryZones']);
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

    /**
     * Menu biasa (bukan paket). Mengembalikan menu dan variannya sesuai urutan di form,
     * supaya isi paket bisa menunjuk "menu ke-i, varian ke-j".
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{product: Product, variants: array<int, ProductVariant>}>
     */
    private function syncProducts(Preorder $po, array $rows): array
    {
        $existing = $po->products()->where('is_bundle', false)->with('variants')->get()->keyBy('id');
        $keep = [];
        $map = [];

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

            $map[$i] = ['product' => $product, 'variants' => $this->syncVariants($product, $row['variants'], $i)];
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

        return $map;
    }

    /**
     * Paket: produk is_bundle dengan satu varian "Paket" dan isi yang menunjuk varian menu.
     * Isi paket yang sudah dipesan dikunci; harga dan kuotanya masih boleh diubah.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array{product: Product, variants: array<int, ProductVariant>}>  $menus
     */
    private function syncBundles(Preorder $po, array $rows, array $menus): void
    {
        $existing = $po->products()->where('is_bundle', true)->with('bundleItems', 'variants')->get()->keyBy('id');
        $keep = [];

        foreach (array_values($rows) as $i => $row) {
            $bundle = isset($row['id']) ? $existing->get((int) $row['id']) : null;
            if (isset($row['id']) && ! $bundle) {
                throw ValidationException::withMessages(["bundles.{$i}.id" => 'Paket ini bukan bagian dari PO ini.']);
            }

            $components = [];
            foreach (array_values($row['items']) as $j => $item) {
                [$pi, $vi] = array_map('intval', explode(':', $item['ref']));
                $variant = $menus[$pi]['variants'][$vi] ?? null;
                if (! $variant) {
                    throw ValidationException::withMessages(["bundles.{$i}.items.{$j}.ref" => 'Pilih isi paket dari menu PO ini.']);
                }
                $key = $variant->id;
                $components[$key] = ['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'qty' => ($components[$key]['qty'] ?? 0) + (int) $item['qty']];
            }
            ksort($components);

            $ordered = $bundle && OrderItem::query()->where('product_id', $bundle->id)->exists();
            if ($ordered) {
                $current = $bundle->bundleItems->sortBy('variant_id')->map(fn ($b) => [$b->variant_id, $b->qty])->values()->all();
                $next = collect($components)->map(fn ($c) => [$c['variant_id'], $c['qty']])->values()->all();
                if ($current !== $next) {
                    throw ValidationException::withMessages(["bundles.{$i}.items" => "{$bundle->name} sudah dipesan, jadi isinya tidak bisa diubah. Buat paket baru kalau perlu."]);
                }
            }

            $quota = filled($row['quota'] ?? null) ? (int) $row['quota'] : null;
            if ($bundle && $quota !== null && $quota < $bundle->sold_count) {
                throw ValidationException::withMessages(["bundles.{$i}.quota" => "Sudah dipesan {$bundle->sold_count} paket. Kuota tidak bisa lebih kecil."]);
            }

            $bundle ??= new Product(['preorder_id' => $po->id, 'is_bundle' => true]);
            $bundle->fill(['name' => $row['name'], 'price' => (int) $row['price'], 'quota' => $quota, 'is_bundle' => true, 'sort' => 100 + $i])->save();
            $keep[] = $bundle->id;

            if ($bundle->variants()->doesntExist()) {
                $bundle->variants()->create(['name' => 'Paket', 'price_delta' => 0, 'sort' => 0]);
            }
            if (! $ordered) {
                $bundle->bundleItems()->delete();
                foreach ($components as $c) {
                    $bundle->bundleItems()->create($c);
                }
            }
        }

        $removed = $existing->keys()->diff($keep);
        if ($removed->isNotEmpty()) {
            $orderedId = OrderItem::query()->whereIn('product_id', $removed)->value('product_id');
            if ($orderedId) {
                throw ValidationException::withMessages(['bundles' => $existing->get($orderedId)->name.' sudah dipesan, jadi tidak bisa dihapus. Ubah kuotanya kalau ingin berhenti menjual.']);
            }
            Product::query()->whereIn('id', $removed)->delete();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, ProductVariant>
     */
    private function syncVariants(Product $product, array $rows, int $productIndex): array
    {
        $byIndex = [];
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
            $byIndex[$j] = $variant;
        }

        $removed = $existing->keys()->diff($keep);
        if ($removed->isNotEmpty()) {
            if (OrderItem::query()->whereIn('variant_id', $removed)->exists()) {
                throw ValidationException::withMessages(["products.{$productIndex}.variants" => "Ada varian {$product->name} yang sudah dipesan, jadi tidak bisa dihapus."]);
            }
            ProductVariant::query()->whereIn('id', $removed)->delete();
        }

        return $byIndex;
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

            $values = [
                'place' => trim($row['place']),
                'place_note' => filled($row['place_note'] ?? null) ? trim($row['place_note']) : null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ];
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
                throw ValidationException::withMessages(['slots' => 'Titik ambil yang sudah dipilih pembeli tidak bisa dihapus. Ubah jam atau patokannya saja kalau perlu.']);
            }
            $po->slots()->whereIn('id', $removed)->delete();
        }
    }

    /**
     * Area antar milik PO ini. Mematikan pengantaran sama dengan menghapus semua area,
     * jadi area yang sudah dipesan menahan pengantaran tetap aktif.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncZones(Preorder $po, array $rows): void
    {
        $existing = $po->deliveryZones()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($rows) as $i => $row) {
            $zone = isset($row['id']) ? $existing->get((int) $row['id']) : null;
            if (isset($row['id']) && ! $zone) {
                throw ValidationException::withMessages(["zones.{$i}.id" => 'Area ini bukan bagian dari PO ini.']);
            }

            $values = ['name' => trim($row['name']), 'fee' => (int) ($row['fee'] ?? 0), 'sort' => $i];
            if ($zone) {
                $zone->update($values);
            } else {
                $zone = $po->deliveryZones()->create($values);
            }
            $keep[] = $zone->id;
        }

        $removed = $existing->keys()->diff($keep);
        if ($removed->isNotEmpty()) {
            if (Order::query()->whereIn('delivery_zone_id', $removed)->exists()) {
                throw ValidationException::withMessages(['zones' => 'Area antar yang sudah dipesan tidak bisa dihapus, dan pengantaran tidak bisa dimatikan. Ubah ongkirnya saja kalau perlu.']);
            }
            $po->deliveryZones()->whereIn('id', $removed)->delete();
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
