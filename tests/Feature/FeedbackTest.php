<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Notifications\AdminAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsShops;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use BuildsShops, RefreshDatabase;

    public function test_a_buyer_sends_feedback_with_a_screenshot_and_super_admins_are_notified(): void
    {
        Storage::fake('public');
        Notification::fake();
        $admin = $this->admin();
        $buyer = $this->buyer();

        $this->actingAs($buyer)->get('/masukan?dari=/pesanan')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Feedback')->where('from', '/pesanan')->has('kinds', 3));

        $this->actingAs($buyer)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0')
            ->post('/masukan', [
                'kind' => 'bug',
                'message' => 'Setelah bayar QRIS, tiket tidak muncul.',
                'photo' => UploadedFile::fake()->image('layar.png'),
                'from' => '/pesanan',
            ])
            ->assertRedirect('/pesanan')
            ->assertSessionHas('success');

        $feedback = Feedback::sole();
        $this->assertSame($buyer->id, $feedback->user_id);
        $this->assertSame('/pesanan', $feedback->page);
        $this->assertStringContainsString('Android', $feedback->user_agent);
        $this->assertSame(Feedback::STATUS_NEW, $feedback->status);
        Storage::disk('public')->assertExists($feedback->photo_path);

        Notification::assertSentTo($admin, AdminAlert::class, fn ($n) => $n->kind() === 'feedback' && $n->url() === '/admin/masukan');
        Notification::assertNotSentTo($buyer, AdminAlert::class);
    }

    public function test_the_return_address_cannot_point_outside_jajapo(): void
    {
        $buyer = $this->buyer();

        foreach (['https://jahat.example', '//jahat.example', '/\\jahat.example'] as $evil) {
            $this->actingAs($buyer)->get('/masukan?dari='.urlencode($evil))
                ->assertInertia(fn ($p) => $p->where('from', null));

            $this->actingAs($buyer)->post('/masukan', ['kind' => 'idea', 'message' => 'Tolong tambah fitur jadwal ambil sendiri.', 'from' => $evil])
                ->assertRedirect('/akun');
        }
        $this->assertSame(0, Feedback::query()->whereNotNull('page')->count());
    }

    public function test_feedback_is_validated_and_needs_login(): void
    {
        $this->get('/masukan')->assertRedirect('/login');
        $this->post('/masukan', ['kind' => 'bug', 'message' => 'Ada yang error di sini.'])->assertRedirect('/login');

        $this->actingAs($this->buyer())->post('/masukan', ['kind' => 'spam', 'message' => 'pendek'])
            ->assertSessionHasErrors(['kind', 'message']);
        $this->assertSame(0, Feedback::count());
    }

    public function test_super_admin_lists_filters_and_resolves_feedback(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $this->shopFor($seller);
        $old = Feedback::create(['user_id' => $seller->id, 'kind' => 'idea', 'message' => 'Ekspor rekap per hari.', 'status' => Feedback::STATUS_DONE]);
        $new = Feedback::create(['user_id' => $seller->id, 'kind' => 'bug', 'message' => 'Scan tiket lambat sekali.']);

        $this->actingAs($admin)->get('/admin/masukan')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/Feedback')
                ->where('items.data.0.id', $new->id)
                ->where('items.data.1.id', $old->id)
                ->where('items.data.0.user.shops.0.name', $seller->shops->first()->name)
                ->where('counts.new', 1)
                ->where('counts.done', 1));

        $this->actingAs($admin)->get('/admin/masukan?status=new')
            ->assertInertia(fn ($p) => $p->has('items.data', 1)->where('items.data.0.id', $new->id));
        $this->actingAs($admin)->get('/admin/masukan?jenis=idea')
            ->assertInertia(fn ($p) => $p->has('items.data', 1)->where('items.data.0.id', $old->id));

        $this->actingAs($admin)->get('/admin')->assertInertia(fn ($p) => $p->where('panelBadges.feedback', 1));

        $this->actingAs($admin)->post("/admin/masukan/{$new->id}/status", ['status' => 'done', 'note' => 'Sudah diperbaiki.'])
            ->assertRedirect()->assertSessionHas('success');
        $new->refresh();
        $this->assertSame(Feedback::STATUS_DONE, $new->status);
        $this->assertSame('Sudah diperbaiki.', $new->admin_note);
        $this->assertSame($admin->id, $new->handled_by);
        $this->assertNotNull($new->handled_at);

        $this->actingAs($admin)->post("/admin/masukan/{$new->id}/status", ['status' => 'new']);
        $this->assertNull($new->fresh()->handled_by);
        $this->assertSame('Sudah diperbaiki.', $new->fresh()->admin_note);
    }

    public function test_sellers_and_buyers_cannot_open_the_admin_feedback_page(): void
    {
        $seller = $this->seller();
        $this->shopFor($seller);
        $feedback = Feedback::create(['user_id' => $seller->id, 'kind' => 'bug', 'message' => 'Ada error di halaman PO.']);

        $this->actingAs($seller)->get('/admin/masukan')->assertForbidden();
        $this->actingAs($seller)->post("/admin/masukan/{$feedback->id}/status", ['status' => 'done'])->assertForbidden();
        $this->assertSame(Feedback::STATUS_NEW, $feedback->fresh()->status);
    }
}
