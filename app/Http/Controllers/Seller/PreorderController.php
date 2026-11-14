<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\PickupSlot;
use App\Models\Preorder;
use App\Models\Product;
use App\Models\Shop;
use App\Services\PreorderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PreorderController extends Controller
{
    public function __construct(private PreorderService $preorders) {}

    public function index(Request $request): Response
    {
        $shop = $this->shop($request);
        $sold = [...OrderStatus::paidStates(), OrderStatus::PickedUp->value, OrderStatus::Delivered->value, OrderStatus::NotPickedUp->value];

        $list = $shop->preorders()
            ->with('products', 'slots', 'deliveryZones')
            ->withCount(['orders', 'orders as paid_orders' => fn ($q) => $q->whereIn('status', $sold)])
            ->withSum(['orders as revenue' => fn ($q) => $q->whereIn('status', $sold)], 'subtotal')
            ->latest('closes_at')
            ->get();

        return Inertia::render('Seller/Preorders', [
            'shop' => DashboardController::shopPropsFor($shop),
            'preorders' => $list->map(fn (Preorder $po) => [
                'id' => $po->id,
                'title' => $po->title,
                'url' => $po->url(),
                'seed' => $po->id,
                'poster_url' => $po->posterUrl(),
                'story_url' => $po->storyUrl(),
                'stage' => $po->stage(),
                'opens_label' => tanggal($po->opens_at, withTime: true),
                'closes_label' => tanggal($po->closes_at, withTime: true),
                'closes_at' => $po->closes_at->toIso8601String(),
                'pickup_label' => ($s = $po->slots->first()) ? tanggal($s->starts_at) : ($po->delivery_starts_at ? 'Antar '.tanggal($po->delivery_starts_at) : '-'),
                'places' => $po->slots->pluck('place')->unique()->values()->concat($po->delivery_enabled ? $po->deliveryZones->map(fn ($z) => 'Antar '.$z->name) : [])->join(', '),
                'sold' => $po->soldTotal(),
                'quota' => $po->quotaTotal(),
                'paid_orders' => (int) $po->paid_orders,
                'orders_count' => (int) $po->orders_count,
                'revenue' => (int) $po->revenue,
                'menu' => $po->products->reject->is_bundle->pluck('name')->concat($po->products->filter->is_bundle->pluck('name'))->join(', '),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $shop = $this->shop($request);
        $copy = $request->integer('salin') ? $shop->preorders()->with('products.variants', 'products.bundleItems', 'slots', 'deliveryZones')->find($request->integer('salin')) : null;

        return Inertia::render('Seller/PreorderForm', [
            'shop' => DashboardController::shopPropsFor($shop),
            'preorder' => $copy ? $this->formData($copy, asCopy: true) : null,
            'suggestions' => $this->suggestions($shop),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $data = $this->validated($request, $shop);
        $publish = $request->boolean('publish');

        $po = $this->preorders->save($shop, null, $data, $request->user(), $publish);

        return redirect('/seller/po')->with('success', $publish
            ? "PO {$po->title} sudah terbit. Bagikan tautannya ke pembeli."
            : "Draf {$po->title} tersimpan. Pembeli belum bisa melihatnya.");
    }

    public function edit(Request $request, int $preorder): Response
    {
        $shop = $this->shop($request);
        $po = $shop->preorders()->with('products.variants', 'products.bundleItems', 'slots', 'deliveryZones')->findOrFail($preorder);

        return Inertia::render('Seller/PreorderForm', [
            'shop' => DashboardController::shopPropsFor($shop),
            'preorder' => $this->formData($po),
            'suggestions' => $this->suggestions($shop),
        ]);
    }

    public function update(Request $request, int $preorder): RedirectResponse
    {
        $shop = $this->shop($request);
        $po = $shop->preorders()->findOrFail($preorder);
        $data = $this->validated($request, $shop, $po);
        $publish = $request->boolean('publish');

        $po = $this->preorders->save($shop, $po, $data, $request->user(), $publish);

        return redirect('/seller/po')->with('success', match (true) {
            $po->isDraft() => "Draf {$po->title} tersimpan.",
            $publish && $po->wasChanged('status') => "PO {$po->title} sudah terbit. Bagikan tautannya ke pembeli.",
            default => "Perubahan {$po->title} tersimpan.",
        });
    }

    public function close(Request $request, int $preorder): RedirectResponse
    {
        $po = $this->shop($request)->preorders()->findOrFail($preorder);
        $this->preorders->close($po, $request->user());

        return back()->with('success', "PO {$po->title} ditutup. Pesanan yang menunggu bayar masih bisa dibayar sampai batasnya.");
    }

    public function destroy(Request $request, int $preorder): RedirectResponse
    {
        $po = $this->shop($request)->preorders()->findOrFail($preorder);
        $this->preorders->delete($po, $request->user());

        return redirect('/seller/po')->with('success', "PO {$po->title} dihapus.");
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Shop $shop, ?Preorder $po = null): array
    {
        $publish = $request->boolean('publish') || ($po && ! $po->isDraft());

        return $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'slug' => [
                'required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('preorders', 'slug')->where('shop_id', $shop->id)->ignore($po?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'labels' => ['nullable', 'array', 'max:4'],
            'labels.*' => ['nullable', 'string', 'max:24'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'delivery_enabled' => ['boolean'],
            'delivery_date' => ['nullable', 'required_if_accepted:delivery_enabled', 'date_format:Y-m-d'],
            'delivery_start' => ['nullable', 'required_if_accepted:delivery_enabled', 'date_format:H:i'],
            'delivery_end' => ['nullable', 'required_if_accepted:delivery_enabled', 'date_format:H:i', 'after:delivery_start'],
            'zones' => ['nullable', 'required_if_accepted:delivery_enabled', 'array', 'max:20'],
            'zones.*.id' => ['nullable', 'integer'],
            'zones.*.name' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'zones.*.fee' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'story' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'remove_poster' => ['boolean'],
            'remove_story' => ['boolean'],
            'products' => ['required', 'array', 'min:1', 'max:20'],
            'products.*.id' => ['nullable', 'integer'],
            'products.*.name' => ['required', 'string', 'max:60'],
            'products.*.price' => ['required', 'integer', 'min:1000', 'max:1000000'],
            'products.*.quota' => ['required', 'integer', 'min:1', 'max:10000'],
            'products.*.photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'products.*.remove_photo' => ['boolean'],
            'products.*.variants' => ['required', 'array', 'min:1', 'max:10'],
            'products.*.variants.*.id' => ['nullable', 'integer'],
            'products.*.variants.*.name' => ['required', 'string', 'max:40'],
            'products.*.variants.*.price_delta' => ['nullable', 'integer', 'min:-1000000', 'max:1000000'],
            'bundles' => ['nullable', 'array', 'max:10'],
            'bundles.*.id' => ['nullable', 'integer'],
            'bundles.*.name' => ['required', 'string', 'max:60'],
            'bundles.*.price' => ['required', 'integer', 'min:1000', 'max:1000000'],
            'bundles.*.quota' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'bundles.*.items' => ['required', 'array', 'min:1', 'max:10'],
            'bundles.*.items.*.ref' => ['required', 'string', 'regex:/^\d+:\d+$/'],
            'bundles.*.items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
            'slots' => [Rule::requiredIf($publish && ! $request->boolean('delivery_enabled')), 'array', 'max:20'],
            'slots.*.id' => ['nullable', 'integer'],
            'slots.*.place' => ['required', 'string', 'max:80'],
            'slots.*.place_note' => ['nullable', 'string', 'max:160'],
            'slots.*.date' => ['required', 'date_format:Y-m-d'],
            'slots.*.start' => ['required', 'date_format:H:i'],
            'slots.*.end' => ['required', 'date_format:H:i', 'after:slots.*.start'],
        ], [
            'slug.regex' => 'URL PO hanya huruf kecil, angka, dan tanda hubung.',
            'slug.unique' => 'URL ini sudah dipakai PO lain di tokomu.',
            'closes_at.after' => 'Waktu tutup harus setelah waktu buka.',
            'delivery_date.required_if_accepted' => 'Isi tanggal antar.',
            'delivery_start.required_if_accepted' => 'Isi jam mulai antar.',
            'delivery_end.required_if_accepted' => 'Isi jam selesai antar.',
            'delivery_end.after' => 'Jam selesai harus setelah jam mulai.',
            'zones.required_if_accepted' => 'Tambahkan minimal satu area antar.',
            'zones.*.name.required' => 'Isi nama area.',
            'zones.*.name.distinct' => 'Nama area tidak boleh dobel.',
            'products.required' => 'Tambahkan minimal satu menu.',
            'products.*.name.required' => 'Isi nama menu.',
            'products.*.price.required' => 'Isi harga.',
            'products.*.price.min' => 'Harga minimal Rp1.000.',
            'products.*.quota.required' => 'Isi kuota porsi.',
            'products.*.quota.min' => 'Kuota minimal 1 porsi.',
            'products.*.variants.required' => 'Tambahkan minimal satu varian.',
            'products.*.variants.*.name.required' => 'Isi nama varian.',
            'products.*.photo.image' => 'Foto menu harus berupa gambar.',
            'products.*.photo.max' => 'Ukuran foto menu maksimal 4 MB.',
            'bundles.*.name.required' => 'Isi nama paket.',
            'bundles.*.price.required' => 'Isi harga paket.',
            'bundles.*.price.min' => 'Harga paket minimal Rp1.000.',
            'bundles.*.items.required' => 'Pilih isi paket.',
            'bundles.*.items.*.ref.required' => 'Pilih menu untuk isi paket.',
            'slots.required' => 'Tambahkan minimal satu titik ambil, atau aktifkan pengantaran.',
            'slots.*.place.required' => 'Isi nama tempat ambil.',
            'slots.*.date.required' => 'Isi tanggal ambil.',
            'slots.*.start.required' => 'Isi jam mulai.',
            'slots.*.end.required' => 'Isi jam selesai.',
            'slots.*.end.after' => 'Jam selesai harus setelah jam mulai.',
            'poster.image' => 'Poster harus berupa gambar.',
            'poster.max' => 'Ukuran poster maksimal 4 MB.',
            'story.image' => 'Gambar story harus berupa gambar.',
            'story.max' => 'Ukuran gambar story maksimal 6 MB.',
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(Preorder $po, bool $asCopy = false): array
    {
        $tz = config('jajapo.timezone');
        $ordered = $asCopy ? collect() : $po->orders()->join('order_items', 'order_items.order_id', '=', 'orders.id')->get(['order_items.product_id', 'order_items.variant_id']);
        $orderedProducts = $ordered->pluck('product_id')->unique();
        $orderedVariants = $ordered->pluck('variant_id')->unique();
        $usedSlots = $asCopy ? collect() : $po->orders()->whereNotNull('pickup_slot_id')->pluck('pickup_slot_id')->unique();
        $usedZones = $asCopy ? collect() : $po->orders()->whereNotNull('delivery_zone_id')->pluck('delivery_zone_id')->unique();
        $menus = $po->products->reject->is_bundle->values();

        return [
            'id' => $asCopy ? null : $po->id,
            'stage' => $asCopy ? 'draft' : $po->stage(),
            'url' => $asCopy ? null : $po->url(),
            'title' => $po->title,
            'slug' => $asCopy ? $po->slug.'-'.Str::lower(Str::random(3)) : $po->slug,
            'description' => $po->description ?? '',
            'labels' => $po->labels ?? [],
            'opens_at' => $asCopy ? '' : $po->opens_at->setTimezone($tz)->format('Y-m-d\TH:i'),
            'closes_at' => $asCopy ? '' : $po->closes_at->setTimezone($tz)->format('Y-m-d\TH:i'),
            'quota' => $po->quota,
            'delivery_enabled' => $po->delivery_enabled,
            'delivery_date' => $asCopy ? '' : ($po->delivery_starts_at?->setTimezone($tz)->format('Y-m-d') ?? ''),
            'delivery_start' => $po->delivery_starts_at?->setTimezone($tz)->format('H:i') ?? '10:00',
            'delivery_end' => $po->delivery_ends_at?->setTimezone($tz)->format('H:i') ?? '14:00',
            'zones' => $po->deliveryZones->map(fn ($z) => [
                'id' => $asCopy ? null : $z->id,
                'name' => $z->name,
                'fee' => $z->fee,
                'locked' => $usedZones->contains($z->id),
            ])->values(),
            'poster_url' => $asCopy ? null : $po->posterUrl(),
            'story_url' => $asCopy ? null : $po->storyUrl(),
            'products' => $menus->map(fn (Product $p) => [
                'id' => $asCopy ? null : $p->id,
                'name' => $p->name,
                'price' => $p->price,
                'quota' => $p->quota,
                'sold' => $asCopy ? 0 : $p->sold_count,
                'photo_url' => $asCopy ? null : $p->photoUrl(),
                'locked' => $orderedProducts->contains($p->id),
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $asCopy ? null : $v->id,
                    'name' => $v->name,
                    'price_delta' => $v->price_delta,
                    'locked' => $orderedVariants->contains($v->id),
                ])->values(),
            ])->values(),
            // Isi paket menunjuk "menu ke-i:varian ke-j" sesuai urutan di form.
            'bundles' => $po->products->filter->is_bundle->map(fn (Product $b) => [
                'id' => $asCopy ? null : $b->id,
                'name' => $b->name,
                'price' => $b->price,
                'quota' => $b->quota,
                'sold' => $asCopy ? 0 : $b->sold_count,
                'locked' => $orderedProducts->contains($b->id),
                'items' => $b->bundleItems->map(function ($item) use ($menus) {
                    $pi = $menus->search(fn (Product $p) => $p->id === $item->product_id);
                    $vi = $pi === false ? false : $menus[$pi]->variants->search(fn ($v) => $v->id === $item->variant_id);

                    return ['ref' => $pi === false || $vi === false ? '' : "{$pi}:{$vi}", 'qty' => $item->qty];
                })->values(),
            ])->values(),
            'slots' => $po->slots->map(fn (PickupSlot $s) => [
                'id' => $asCopy ? null : $s->id,
                'place' => $s->place,
                'place_note' => $s->place_note ?? '',
                'date' => $asCopy ? '' : $s->starts_at->setTimezone($tz)->format('Y-m-d'),
                'start' => $s->starts_at->setTimezone($tz)->format('H:i'),
                'end' => $s->ends_at->setTimezone($tz)->format('H:i'),
                'locked' => $usedSlots->contains($s->id),
            ])->values(),
        ];
    }

    /**
     * Tempat ambil dan area antar yang pernah dipakai toko ini, untuk saran isian.
     *
     * @return array{places: array<int, string>, zones: array<int, string>}
     */
    private function suggestions(Shop $shop): array
    {
        $ids = $shop->preorders()->pluck('id');

        return [
            'places' => PickupSlot::query()->whereIn('preorder_id', $ids)->distinct()->orderBy('place')->pluck('place')->all(),
            'zones' => DeliveryZone::query()->whereIn('preorder_id', $ids)->distinct()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
