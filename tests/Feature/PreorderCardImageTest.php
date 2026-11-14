<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

/** Kartu PO menampilkan nama makanan, jadi gambarnya foto menu; poster PO hanya cadangan. */
class PreorderCardImageTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    public function test_card_uses_the_menu_photo_then_the_poster(): void
    {
        $shop = $this->shopFor($this->seller());
        $po = $this->openPreorder($shop);
        $po->update(['poster_path' => 'posters/banner.jpg']);

        // Belum ada foto menu: poster dipakai supaya kartu tidak kosong.
        $this->get('/')->assertInertia(fn ($p) => $p->where('preorders.0.image_url', fn ($url) => str_ends_with($url, '/storage/posters/banner.jpg')));

        // Ada foto menu: foto menu yang dipakai, bukan poster.
        $po->products()->first()->update(['photo_path' => 'products/mac.jpg']);
        $this->get('/')->assertInertia(fn ($p) => $p->where('preorders.0.image_url', fn ($url) => str_ends_with($url, '/storage/products/mac.jpg')));
        $this->get('/'.$shop->slug)->assertInertia(fn ($p) => $p->where('open.0.image_url', fn ($url) => str_ends_with($url, '/storage/products/mac.jpg')));
    }
}
