<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Preorder;
use App\Models\Shop;
use App\Models\User;
use App\Payments\PaymentGateway;
use App\Services\ComplaintService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PayoutService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data demo yang sama dengan desain di canvas Jajapo UI.
 * Login lokal: lihat halaman /login (tombol masuk cepat hanya muncul di APP_ENV=local).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Data demo selalu memakai gateway tiruan, apa pun driver di .env.
        config(['services.payment_gateway.driver' => 'fake']);
        app()->forgetInstance(PaymentGateway::class);
        $orders = app(OrderService::class);
        $payouts = app(PayoutService::class);

        $admin = User::create(['name' => 'Admin Jajapo', 'email' => 'admin@jajapo.test', 'password' => 'password']);
        $admin->forceFill(['is_super_admin' => true])->save();

        $raka = User::create(['name' => 'Raka Pratama', 'email' => 'raka.pratama@mail.ugm.ac.id', 'password' => 'password', 'phone' => '081234560001']);
        $laras = User::create(['name' => 'Laras Wening', 'email' => 'laras.wening@mail.ugm.ac.id', 'password' => 'password', 'phone' => '081234560002']);
        $yoga = User::create(['name' => 'Yoga Prasetyo', 'email' => 'yoga.prasetyo@mail.ugm.ac.id', 'password' => 'password']);
        $dina = User::create(['name' => 'Dina Kartika', 'email' => 'dina.kartika@mail.ugm.ac.id', 'password' => 'password']);

        $buyers = collect(['Nadia Putri', 'Bima Aditya', 'Salsa Rahma', 'Fikri Hidayat', 'Aulia Zahra', 'Dimas Saputra', 'Citra Lestari', 'Hanif Rasyid'])
            ->map(fn ($name, $i) => User::create([
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).'@mail.ugm.ac.id',
                'password' => 'password',
                'phone' => '08571234'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]));

        $ftp = $this->shop('Danus Dies FTP', 'danus-dies-ftp', $raka, 'BNI', '0421889048', 'Raka Pratama');
        $sv = $this->shop('Panitia Pensi SV', 'pensi-sv', $laras, 'BRI', '0937112230', 'Laras Wening');
        $teknik = $this->shop('Danus PSDM Teknik', 'psdm-teknik', $yoga, 'Mandiri', '1370002210', 'Yoga Prasetyo');
        $pending = Shop::create(['name' => 'Danus Wisuda Biologi', 'slug' => 'wisuda-biologi', 'status' => Shop::STATUS_PENDING, 'created_by' => $dina->id]);
        $pending->members()->attach($dina->id, ['role' => 'admin']);

        $pickupDay = now(config('jajapo.timezone'))->next(Carbon::SATURDAY);

        $mac = $this->preorder($ftp, [
            'slug' => 'mac-n-cheese-panggang', 'title' => 'Mac n Cheese Panggang', 'category' => 'makanan',
            'description' => 'Makaroni dipanggang dengan saus keju cheddar dan mozarela. Dimasak pagi hari sebelum jam ambil, jadi masih hangat saat kamu terima.',
            'labels' => ['Halal', 'Mengandung susu', 'Mengandung gluten'],
            'closes_at' => now()->addHours(4)->addMinutes(12), 'delivery_enabled' => true,
            'delivery_starts_at' => $pickupDay->copy()->setTime(10, 0)->utc(), 'delivery_ends_at' => $pickupDay->copy()->setTime(14, 0)->utc(),
        ], [['Mac n Cheese Panggang', 12000, 60, [['Original', 0], ['Extra keju', 3000], ['Smoked beef', 5000]]]],
            [['Lobi KPTU FTP', 'Meja panitia depan musala', $pickupDay->copy()->setTime(10, 0), 2], ['Taman Code DTK', 'Bangku bawah pohon ketapang', $pickupDay->copy()->setTime(13, 0), 2]],
            [['FTP', 0], ['FEB', 3000], ['Fakultas Teknik', 3000], ['FMIPA', 4000]]);

        // Draf yang belum diterbitkan, supaya tab Draf di panel penjual ada isinya.
        $this->preorder($ftp, [
            'slug' => 'es-teh-leci', 'title' => 'Es Teh Leci 500 ml', 'category' => 'minuman', 'status' => Preorder::STATUS_DRAFT,
            'opens_at' => now()->addDays(2), 'closes_at' => now()->addDays(4),
        ], [['Es Teh Leci 500 ml', 8000, 80, [['Reguler', 0], ['Less sugar', 0]]]],
            [['Lobi KPTU FTP', null, $pickupDay->copy()->addDays(7)->setTime(12, 0), 2]]);

        $sago = $this->preorder($sv, [
            'slug' => 'mango-sago', 'title' => 'Mango Sago 350 ml', 'category' => 'minuman',
            'description' => 'Mango sago dengan potongan mangga harum manis dan sagu mutiara.', 'labels' => ['Halal', 'Mengandung susu'],
            'closes_at' => now()->addHours(2)->addMinutes(40),
        ], [['Mango Sago 350 ml', 10000, 40, [['Reguler', 0], ['Extra mangga', 2000]]]],
            [['Selasar DEB SV', 'Sebelah ruang himpunan', $pickupDay->copy()->setTime(9, 0), 3]]);

        $this->preorder($teknik, [
            'slug' => 'risol-mayo', 'title' => 'Risol Mayo isi 5', 'category' => 'snack',
            'description' => 'Risol isi smoked beef, telur, dan mayones. Satu kotak isi 5.', 'labels' => ['Halal', 'Mengandung telur'],
            'closes_at' => now(config('jajapo.timezone'))->addDay()->setTime(20, 0)->utc(),
        ], [['Risol Mayo isi 5', 15000, 50, [['Original', 0], ['Pedas', 0]]]],
            [['Kantin Teknik', 'Meja paling ujung dekat parkiran', $pickupDay->copy()->setTime(13, 0), 2]]);

        $this->preorder($teknik, [
            'slug' => 'es-kopi-susu-aren', 'title' => 'Es Kopi Susu Aren', 'category' => 'minuman',
            'description' => 'Kopi susu dengan gula aren, 250 ml.', 'labels' => ['Halal'],
            'closes_at' => now(config('jajapo.timezone'))->addDays(2)->setTime(18, 0)->utc(),
        ], [['Es Kopi Susu Aren', 9000, 80, [['Reguler', 0], ['Less sugar', 0]]]],
            [['Lobi DTK', null, $pickupDay->copy()->setTime(8, 0), 2]]);

        // Pesanan contoh untuk PO Mac n Cheese, mengikuti tabel di desain panel penjual.
        $macVariants = $mac->products->first()->variants->keyBy('name');
        $slots = $mac->slots;
        $samples = [
            [0, 'Original', 2, $slots[0]->id, 'pay'],
            [1, 'Extra keju', 1, $slots[1]->id, 'pending'],
            [2, 'Original', 3, null, 'pay'],
            [3, 'Smoked beef', 1, $slots[0]->id, 'expire'],
            [4, 'Extra keju', 2, $slots[0]->id, 'pay'],
            [5, 'Original', 1, $slots[1]->id, 'cancel'],
            [6, 'Original', 4, $slots[0]->id, 'pay'],
            [7, 'Smoked beef', 2, $slots[1]->id, 'pay'],
        ];
        foreach ($samples as [$buyerIndex, $variant, $qty, $slotId, $outcome]) {
            $order = $orders->checkout(
                $buyers[$buyerIndex], $mac, [['variant_id' => $macVariants[$variant]->id, 'qty' => $qty]],
                $slotId ? 'pickup' : 'delivery', $slotId, $slotId ? null : 'Gedung Pertamina lantai 2, ruang 2.3',
                zoneId: $slotId ? null : $mac->deliveryZones->firstWhere('name', 'FEB')->id,
            );
            match ($outcome) {
                'pay' => $orders->markPaid($order),
                'expire' => $orders->expire($order),
                'cancel' => $orders->cancelByBuyer($order),
                default => null,
            };
        }

        $sagoVariant = $sago->products->first()->variants->first();
        foreach ([0, 2, 6] as $i) {
            $orders->markPaid($orders->checkout($buyers[$i], $sago, [['variant_id' => $sagoVariant->id, 'qty' => 2]], 'pickup', $sago->slots->first()->id));
        }

        // PO lama Danus Dies FTP yang sudah selesai diambil, jadi ada saldo tersedia dan riwayat pencairan.
        $old = $this->preorder($ftp, [
            'slug' => 'risol-mayo-oktober', 'title' => 'Risol Mayo Oktober', 'category' => 'snack', 'labels' => ['Halal'],
            'opens_at' => now()->subDays(12), 'closes_at' => now()->addHour(),
        ], [['Risol Mayo isi 5', 15000, 60, [['Original', 0]]]],
            [['Lobi KPTU FTP', null, now(config('jajapo.timezone'))->addDay()->setTime(10, 0), 2]]);
        $oldVariant = $old->products->first()->variants->first();
        foreach ([0, 1, 2, 3, 4, 5] as $i) {
            $order = $orders->checkout($buyers[$i], $old, [['variant_id' => $oldVariant->id, 'qty' => 4]], 'pickup', $old->slots->first()->id);
            $orders->markPaid($order);
            $orders->markPickedUp($order->fresh(), $raka);
        }
        $old->update(['closes_at' => now()->subDays(5), 'status' => Preorder::STATUS_CLOSED]);

        // Dua komplain contoh: satu menunggu penjual, satu sudah ditolak penjual dan menunggu super admin.
        $complaints = app(ComplaintService::class);
        $oldOrders = $old->orders()->with('preorder.slots', 'shop')->orderBy('id')->get();
        $complaints->open($oldOrders[0], $buyers[0], [
            'reason' => 'missing_item', 'description' => 'Di kotak cuma ada 3 risol, harusnya 5. Sudah saya foto pas buka.',
            'amount' => 6000, 'refund_to' => 'BCA 7710023345 a.n. '.$buyers[0]->name,
        ]);
        $disputed = $complaints->open($oldOrders[1], $buyers[1], [
            'reason' => 'bad_quality', 'description' => 'Risolnya sudah dingin dan kulitnya lembek waktu diambil jam 11.',
            'amount' => 15000, 'refund_to' => 'DANA 085712340002 a.n. '.$buyers[1]->name,
        ]);
        $complaints->sellerRejects($disputed, $raka, 'Risol dibuat pagi itu juga, dan pembeli mengambil di akhir slot. Kami sudah mengingatkan di grup.');

        $first = $payouts->request($ftp, 180000, $raka);
        $payouts->markTransferred($first, $admin);
        $payouts->request($ftp, 50000, $raka);

        // Satu pembayaran yang masuk setelah pesanan kedaluwarsa, untuk kartu "Perlu dicek" super admin.
        $late = Order::query()->where('status', OrderStatus::Expired)->with('payment')->first();
        $late?->payment?->update(['status' => PaymentService::LATE, 'paid_at' => now()]);
    }

    private function shop(string $name, string $slug, User $owner, string $bank, string $number, string $holder): Shop
    {
        $shop = Shop::create([
            'name' => $name, 'slug' => $slug, 'status' => Shop::STATUS_ACTIVE,
            'created_by' => $owner->id, 'approved_at' => now(), 'whatsapp' => $owner->phone,
        ]);
        $shop->members()->attach($owner->id, ['role' => 'admin']);
        BankAccount::create([
            'shop_id' => $shop->id, 'bank' => $bank, 'account_number' => $number,
            'account_name' => $holder, 'verified_at' => now(),
        ]);

        return $shop;
    }

    /**
     * @param  array<int, array{0:string,1:int,2:int,3:array<int, array{0:string,1:int}>}>  $products
     * @param  array<int, array{0:string,1:?string,2:Carbon,3:int}>  $slots  tempat, patokan, mulai, lama (jam)
     * @param  array<int, array{0:string,1:int}>  $zones  area antar dan ongkirnya
     */
    private function preorder(Shop $shop, array $data, array $products, array $slots, array $zones = []): Preorder
    {
        $preorder = $shop->preorders()->create([
            'opens_at' => now()->subDays(4),
            'status' => Preorder::STATUS_ACTIVE,
            ...$data,
        ]);
        foreach ($products as $i => [$name, $price, $quota, $variants]) {
            $product = $preorder->products()->create(['name' => $name, 'price' => $price, 'quota' => $quota, 'sort' => $i]);
            foreach ($variants as $j => [$variantName, $delta]) {
                $product->variants()->create(['name' => $variantName, 'price_delta' => $delta, 'sort' => $j]);
            }
        }
        foreach ($slots as [$place, $note, $start, $hours]) {
            $preorder->slots()->create([
                'place' => $place,
                'place_note' => $note,
                'starts_at' => $start->copy()->utc(),
                'ends_at' => $start->copy()->addHours($hours)->utc(),
            ]);
        }

        foreach ($zones as $i => [$name, $fee]) {
            $preorder->deliveryZones()->create(['name' => $name, 'fee' => $fee, 'sort' => $i]);
        }

        return $preorder->load('shop', 'products.variants', 'slots', 'deliveryZones');
    }
}
