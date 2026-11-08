<?php

namespace Tests\Concerns;

use App\Models\BankAccount;
use App\Models\Preorder;
use App\Models\Shop;
use App\Models\User;

trait BuildsShops
{
    protected function seller(string $name = 'Raka Pratama'): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@mail.ugm.ac.id',
            'phone' => '081234567890',
        ]);
    }

    protected function buyer(): User
    {
        return User::factory()->create(['phone' => '085712345678']);
    }

    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    protected function shopFor(User $owner, string $slug = 'danus-dies-ftp'): Shop
    {
        $shop = Shop::create([
            'name' => 'Toko '.$slug,
            'slug' => $slug,
            'status' => Shop::STATUS_ACTIVE,
            'created_by' => $owner->id,
            'approved_at' => now(),
            'whatsapp' => '081234567890',
        ]);
        $shop->members()->attach($owner->id, ['role' => 'admin']);
        BankAccount::create([
            'shop_id' => $shop->id,
            'bank' => 'BNI',
            'account_number' => '0421889048',
            'account_name' => $owner->name,
            'verified_at' => now(),
        ]);

        return $shop;
    }

    /** PO buka dengan satu produk (kuota $quota) dan dua varian: Original Rp12.000, Extra keju Rp15.000. */
    protected function openPreorder(Shop $shop, int $quota = 60, string $slug = 'mac-n-cheese'): Preorder
    {
        $po = $shop->preorders()->create([
            'slug' => $slug,
            'title' => 'Mac n Cheese Panggang',
            'category' => 'makanan',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addHours(4),
            'delivery_enabled' => true,
            'delivery_starts_at' => now()->addDays(2),
            'delivery_ends_at' => now()->addDays(2)->addHours(4),
            'status' => Preorder::STATUS_ACTIVE,
        ]);
        $product = $po->products()->create(['name' => 'Mac n Cheese Panggang', 'price' => 12000, 'quota' => $quota]);
        $product->variants()->create(['name' => 'Original', 'price_delta' => 0, 'sort' => 0]);
        $product->variants()->create(['name' => 'Extra keju', 'price_delta' => 3000, 'sort' => 1]);
        $po->deliveryZones()->create(['name' => 'FEB', 'fee' => 3000]);
        $po->slots()->create(['place' => 'Lobi KPTU FTP', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2)]);

        return $po->load('shop', 'products.variants', 'slots', 'deliveryZones');
    }

    protected function variant(Preorder $po, string $name = 'Original'): int
    {
        return $po->products->first()->variants->firstWhere('name', $name)->id;
    }
}
