<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\PickupPoint;
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
            ->with('products', 'slots.point')
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
                'pickup_label' => ($s = $po->slots->first()) ? tanggal($s->starts_at) : ($po->delivery_enabled ? 'Antar saja' : '-'),
                'sold' => $po->soldTotal(),
                'quota' => $po->quotaTotal(),
                'paid_orders' => (int) $po->paid_orders,
                'orders_count' => (int) $po->orders_count,
                'revenue' => (int) $po->revenue,
                'menu' => $po->products->pluck('name')->join(', '),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $shop = $this->shop($request);
        $copy = $request->integer('salin') ? $shop->preorders()->with('products.variants', 'slots')->find($request->integer('salin')) : null;

        return Inertia::render('Seller/PreorderForm', [
            'shop' => DashboardController::shopPropsFor($shop),
            'preorder' => $copy ? $this->formData($copy, asCopy: true) : null,
            'pickupPoints' => $this->pickupPoints(),
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
        $po = $shop->preorders()->with('products.variants', 'slots')->findOrFail($preorder);

        return Inertia::render('Seller/PreorderForm', [
            'shop' => DashboardController::shopPropsFor($shop),
            'preorder' => $this->formData($po),
            'pickupPoints' => $this->pickupPoints(),
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
        $points = PickupPoint::query()->where('is_active', true)->pluck('id');

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
            'delivery_fee' => ['nullable', 'required_if_accepted:delivery_enabled', 'integer', 'min:0', 'max:50000'],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'story' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'remove_poster' => ['boolean'],
            'remove_story' => ['boolean'],
            'products' => ['required', 'array', 'min:1', 'max:20'],
            'products.*.id' => ['nullable', 'integer'],
            'products.*.name' => ['required', 'string', 'max:60'],
            'products.*.price' => ['required', 'integer', 'min:1000', 'max:1000000'],
            'products.*.quota' => ['required', 'integer', 'min:1', 'max:10000'],
            'products.*.variants' => ['required', 'array', 'min:1', 'max:10'],
            'products.*.variants.*.id' => ['nullable', 'integer'],
            'products.*.variants.*.name' => ['required', 'string', 'max:40'],
            'products.*.variants.*.price_delta' => ['nullable', 'integer', 'min:-1000000', 'max:1000000'],
            'slots' => [Rule::requiredIf($publish && ! $request->boolean('delivery_enabled')), 'array', 'max:20'],
            'slots.*.id' => ['nullable', 'integer'],
            'slots.*.pickup_point_id' => ['required', 'integer', Rule::in($points)],
            'slots.*.date' => ['required', 'date_format:Y-m-d'],
            'slots.*.start' => ['required', 'date_format:H:i'],
            'slots.*.end' => ['required', 'date_format:H:i', 'after:slots.*.start'],
        ], [
            'slug.regex' => 'Alamat PO hanya huruf kecil, angka, dan tanda hubung.',
            'slug.unique' => 'Alamat ini sudah dipakai PO lain di tokomu.',
            'closes_at.after' => 'Waktu tutup harus setelah waktu buka.',
            'delivery_fee.required_if_accepted' => 'Isi ongkir antar.',
            'products.required' => 'Tambahkan minimal satu menu.',
            'products.*.name.required' => 'Isi nama menu.',
            'products.*.price.required' => 'Isi harga.',
            'products.*.price.min' => 'Harga minimal Rp1.000.',
            'products.*.quota.required' => 'Isi kuota porsi.',
            'products.*.quota.min' => 'Kuota minimal 1 porsi.',
            'products.*.variants.required' => 'Tambahkan minimal satu varian.',
            'products.*.variants.*.name.required' => 'Isi nama varian.',
            'slots.required' => 'Tambahkan minimal satu slot ambil, atau aktifkan antar ke fakultas.',
            'slots.*.pickup_point_id.required' => 'Pilih titik ambil.',
            'slots.*.pickup_point_id.in' => 'Titik ambil ini tidak tersedia.',
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
            'delivery_fee' => $po->delivery_fee,
            'poster_url' => $asCopy ? null : $po->posterUrl(),
            'story_url' => $asCopy ? null : $po->storyUrl(),
            'products' => $po->products->map(fn (Product $p) => [
                'id' => $asCopy ? null : $p->id,
                'name' => $p->name,
                'price' => $p->price,
                'quota' => $p->quota,
                'sold' => $asCopy ? 0 : $p->sold_count,
                'locked' => $orderedProducts->contains($p->id),
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $asCopy ? null : $v->id,
                    'name' => $v->name,
                    'price_delta' => $v->price_delta,
                    'locked' => $orderedVariants->contains($v->id),
                ])->values(),
            ])->values(),
            'slots' => $asCopy ? [] : $po->slots->map(fn (PickupSlot $s) => [
                'id' => $s->id,
                'pickup_point_id' => $s->pickup_point_id,
                'date' => $s->starts_at->setTimezone($tz)->format('Y-m-d'),
                'start' => $s->starts_at->setTimezone($tz)->format('H:i'),
                'end' => $s->ends_at->setTimezone($tz)->format('H:i'),
                'locked' => $usedSlots->contains($s->id),
            ])->values(),
        ];
    }

    /** @return array<int, array{id:int, name:string, faculty:?string}> */
    private function pickupPoints(): array
    {
        return PickupPoint::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'faculty'])->toArray();
    }
}
