<?php

namespace Tests\Feature;

use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use RuntimeException;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    private function fakeGoogle(string $id, string $email, string $name, bool $verified = true): void
    {
        $user = (new SocialiteUser)->setRaw(['email_verified' => $verified])->map([
            'id' => $id, 'email' => $email, 'name' => $name, 'avatar' => 'https://example.test/a.png',
        ]);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_login_creates_a_new_buyer_account(): void
    {
        $this->fakeGoogle('g-1', 'Nadia.Putri@mail.ugm.ac.id', 'Nadia Putri');
        $this->get('/auth/google/callback')->assertRedirect('/');
        $this->assertAuthenticated();
        $nadia = User::where('google_id', 'g-1')->firstOrFail();
        $this->assertSame('nadia.putri@mail.ugm.ac.id', $nadia->email);
        $this->assertNotNull($nadia->email_verified_at);
    }

    public function test_google_login_links_an_existing_account_by_email_and_opens_the_seller_panel(): void
    {
        $seller = $this->seller();
        $this->shopFor($seller);
        $this->fakeGoogle('g-2', strtoupper($seller->email), 'Nama Google Lain');
        $this->get('/auth/google/callback')->assertRedirect('/seller');
        $this->assertSame('g-2', $seller->fresh()->google_id);
        $this->assertSame('Raka Pratama', $seller->fresh()->name);
        $this->assertSame(1, User::count());
    }

    public function test_unverified_google_email_is_refused(): void
    {
        $this->fakeGoogle('g-3', 'palsu@example.com', 'Palsu', verified: false);

        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_super_admin_command_grants_and_refuses_to_remove_the_last_one(): void
    {
        $user = $this->buyer();

        $this->artisan('jajapo:super-admin', ['email' => 'tidak.ada@mail.ugm.ac.id'])->assertFailed();
        $this->artisan('jajapo:super-admin', ['email' => strtoupper($user->email)])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_super_admin);

        $this->artisan('jajapo:super-admin', ['email' => $user->email, '--cabut' => true])->assertFailed();
        $this->assertTrue($user->fresh()->is_super_admin);
    }

    public function test_demo_login_and_fake_gateway_are_unavailable_in_production(): void
    {
        $user = $this->buyer();
        $this->app['env'] = 'production';

        // Tombol masuk cepat tidak boleh muncul sama sekali, bukan hanya rutenya yang ditutup.
        $this->get('/login')->assertOk()->assertInertia(fn ($p) => $p->has('demoUsers', 0));

        $this->withoutMiddleware()
            ->post("/dev/login/{$user->id}")->assertNotFound();

        config(['services.payment_gateway.driver' => 'fake']);
        $this->app->forgetInstance(PaymentGateway::class);
        $this->expectException(RuntimeException::class);
        app(PaymentGateway::class);
    }
}
