<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Jejak tindakan penting: siapa melakukan apa, kapan, dan pada objek mana. */
class AuditLogController extends Controller
{
    /** Kelompok aksi untuk filter, supaya admin tidak perlu hafal nama teknisnya. */
    private const GROUPS = [
        'toko' => ['shop.approved', 'shop.rejected', 'shop.suspended', 'shop.reactivated', 'shop.updated'],
        'uang' => ['payout.requested', 'payout.transferred', 'payout.rejected', 'order.mark_paid_manual', 'order.refund_requested', 'refund.transferred'],
        'rekening' => ['bank_account.change_requested', 'bank_account.approved', 'bank_account.rejected', 'bank.created', 'bank.updated', 'bank.deleted'],
        'komplain' => ['complaint.opened', 'complaint.seller_rejected', 'complaint.approved_by_seller', 'complaint.decided_by_admin'],
        'anggota' => ['shop.member_invited', 'shop.member_joined', 'shop.member_removed', 'shop.member_role_changed', 'user.super_admin_granted', 'user.super_admin_revoked'],
        'po' => ['preorder.closed_early', 'preorder.deleted'],
    ];

    public function __invoke(Request $request): Response
    {
        $group = array_key_exists((string) $request->query('jenis'), self::GROUPS) ? $request->query('jenis') : null;
        $term = '%'.mb_strtolower(trim((string) $request->query('q'))).'%';

        $log = AuditLog::query()
            ->with('actor')
            ->when($group, fn ($q) => $q->whereIn('action', self::GROUPS[$group]))
            ->when($request->query('q'), fn ($q) => $q->where(
                fn ($w) => $w->whereRaw('lower(action) like ?', [$term])
                    ->orWhereHas('actor', fn ($a) => $a->whereRaw('lower(users.name) like ?', [$term]))
            ))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'actor' => $a->actor?->name ?? 'Sistem',
                'subject' => class_basename($a->subject_type).' #'.$a->subject_id,
                'changes' => $a->changes,
                'date' => tanggal($a->created_at, withTime: true, withYear: true),
            ]);

        return Inertia::render('Admin/AuditLog', [
            'log' => $log,
            'jenis' => $group,
            'q' => (string) $request->query('q'),
            'groups' => array_keys(self::GROUPS),
        ]);
    }
}
