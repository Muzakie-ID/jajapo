<?php

namespace Tests\Feature;

use App\Models\ShopInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class ShopMemberTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    public function test_admin_invites_a_ugm_email_and_the_invitee_joins_with_that_account(): void
    {
        $owner = $this->seller();
        $shop = $this->shopFor($owner);

        $this->actingAs($owner)->post('/seller/anggota/undang', ['email' => 'teman@gmail.com', 'role' => 'member'])->assertSessionHasErrors('email');
        $this->actingAs($owner)->post('/seller/anggota/undang', ['email' => 'Laras.Wening@mail.ugm.ac.id', 'role' => 'member'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post('/seller/anggota/undang', ['email' => 'laras.wening@mail.ugm.ac.id', 'role' => 'member'])->assertSessionHasNoErrors();
        $this->assertSame(1, ShopInvitation::count());
        $invitation = ShopInvitation::firstOrFail();
        $this->assertSame('laras.wening@mail.ugm.ac.id', $invitation->email);

        auth()->logout();
        $this->get("/undangan/{$invitation->token}")->assertRedirect('/login');

        $stranger = $this->seller('Yoga Prasetyo');
        $this->actingAs($stranger)->get("/undangan/{$invitation->token}")->assertInertia(fn ($p) => $p->where('problem', 'wrong_user'));
        $this->actingAs($stranger)->post("/undangan/{$invitation->token}")->assertForbidden();

        $laras = $this->seller('Laras Wening');
        $this->actingAs($laras)->get("/undangan/{$invitation->token}")->assertInertia(fn ($p) => $p->where('problem', null)->where('invitation.shop', $shop->name));
        $this->actingAs($laras)->post("/undangan/{$invitation->token}")->assertRedirect('/seller');

        $this->assertSame('member', $shop->members()->find($laras->id)->pivot->role);
        $this->actingAs($laras)->get('/seller')->assertOk();
        $this->actingAs($laras)->get('/seller/anggota')->assertForbidden();
        $this->actingAs($laras)->post("/undangan/{$invitation->token}")->assertForbidden();
        $this->actingAs($laras)->get("/undangan/{$invitation->token}")->assertInertia(fn ($p) => $p->where('problem', 'used'));
    }

    public function test_expired_and_cancelled_invitations_cannot_be_used(): void
    {
        $owner = $this->seller();
        $this->shopFor($owner);
        $laras = $this->seller('Laras Wening');

        $this->actingAs($owner)->post('/seller/anggota/undang', ['email' => $laras->email, 'role' => 'admin']);
        $invitation = ShopInvitation::firstOrFail();

        $this->travel(8)->days();
        $this->actingAs($laras)->get("/undangan/{$invitation->token}")->assertInertia(fn ($p) => $p->where('problem', 'expired'));
        $this->actingAs($laras)->post("/undangan/{$invitation->token}")->assertForbidden();
        $this->travelBack();

        $this->actingAs($owner)->delete("/seller/anggota/undangan/{$invitation->id}")->assertRedirect();
        $this->actingAs($laras)->get("/undangan/{$invitation->token}")->assertInertia(fn ($p) => $p->where('problem', 'missing'));
    }

    public function test_admin_changes_roles_and_removes_members_but_not_the_creator(): void
    {
        $owner = $this->seller();
        $shop = $this->shopFor($owner);
        $laras = $this->seller('Laras Wening');
        $shop->members()->attach($laras->id, ['role' => 'member']);

        $this->actingAs($owner)->post("/seller/anggota/{$laras->id}/peran", ['role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame('admin', $shop->members()->find($laras->id)->pivot->role);

        // Admin lain tidak bisa menurunkan atau mengeluarkan pembuat toko.
        $this->actingAs($laras)->post("/seller/anggota/{$owner->id}/peran", ['role' => 'member'])->assertSessionHas('error');
        $this->actingAs($laras)->delete("/seller/anggota/{$owner->id}")->assertSessionHas('error');
        $this->assertSame('admin', $shop->members()->find($owner->id)->pivot->role);

        $this->actingAs($owner)->delete("/seller/anggota/{$laras->id}")->assertRedirect();
        $this->assertFalse($shop->members()->whereKey($laras->id)->exists());
        $this->actingAs($laras)->get('/seller')->assertRedirect('/seller/daftar');

        $other = $this->shopFor($this->seller('Yoga Prasetyo'), 'psdm-teknik');
        $this->actingAs($owner)->delete('/seller/anggota/'.$other->created_by)->assertNotFound();
    }
}
