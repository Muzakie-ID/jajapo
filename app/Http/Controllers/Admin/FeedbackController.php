<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), Feedback::STATUSES, true) ? $request->query('status') : null;
        $kind = array_key_exists((string) $request->query('jenis'), Feedback::KINDS) ? $request->query('jenis') : null;

        $items = Feedback::query()
            ->with('user.shops', 'handler')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->orderByRaw("case status when 'new' then 0 when 'in_progress' then 1 else 2 end")
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Feedback $f) => [
                'id' => $f->id,
                'kind' => $f->kind,
                'message' => $f->message,
                'photo_url' => $f->photoUrl(),
                'page' => $f->page,
                'device' => $f->user_agent,
                'status' => $f->status,
                'note' => $f->admin_note,
                'handler' => $f->handler?->name,
                'handled' => $f->handled_at ? tanggal($f->handled_at, withTime: true) : null,
                'date' => tanggal($f->created_at, withTime: true, withYear: true),
                'user' => [
                    'name' => $f->user->name,
                    'email' => $f->user->email,
                    'phone' => $f->user->phone,
                    'shops' => $f->user->shops->map(fn ($s) => ['name' => $s->name, 'slug' => $s->slug])->values(),
                ],
            ]);

        $counts = Feedback::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return Inertia::render('Admin/Feedback', [
            'items' => $items,
            'status' => $status,
            'jenis' => $kind,
            'kinds' => Feedback::KINDS,
            'counts' => [
                'new' => (int) ($counts[Feedback::STATUS_NEW] ?? 0),
                'in_progress' => (int) ($counts[Feedback::STATUS_IN_PROGRESS] ?? 0),
                'done' => (int) ($counts[Feedback::STATUS_DONE] ?? 0),
            ],
        ]);
    }

    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Feedback::STATUSES)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $feedback->update([
            'status' => $data['status'],
            'admin_note' => $data['note'] ?? $feedback->admin_note,
            'handled_by' => $data['status'] === Feedback::STATUS_NEW ? null : $request->user()->id,
            'handled_at' => $data['status'] === Feedback::STATUS_NEW ? null : now(),
        ]);

        return back()->with('success', 'Status masukan diperbarui.');
    }
}
