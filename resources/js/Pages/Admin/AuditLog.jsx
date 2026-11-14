import { router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import Pagination from '@/Components/Pagination';
import FilterChips from '@/Components/FilterChips';

const LABEL = {
    'shop.approved': 'Toko disetujui',
    'shop.rejected': 'Pendaftaran toko ditolak',
    'shop.suspended': 'Toko dibekukan',
    'shop.reactivated': 'Toko diaktifkan kembali',
    'shop.updated': 'Pengaturan toko diubah',
    'shop.member_invited': 'Anggota diundang',
    'shop.member_joined': 'Anggota bergabung',
    'shop.member_removed': 'Anggota dikeluarkan',
    'shop.member_role_changed': 'Peran anggota diubah',
    'bank_account.change_requested': 'Ganti rekening diajukan',
    'bank_account.approved': 'Rekening disetujui',
    'bank_account.rejected': 'Rekening ditolak',
    'bank.created': 'Pilihan bank ditambah',
    'bank.updated': 'Pilihan bank diubah',
    'bank.deleted': 'Pilihan bank dihapus',
    'payout.requested': 'Pencairan diajukan',
    'payout.transferred': 'Pencairan ditransfer',
    'payout.rejected': 'Pencairan ditolak',
    'order.mark_paid_manual': 'Pesanan dilunasi manual',
    'order.refund_requested': 'Refund diajukan',
    'refund.transferred': 'Refund ditransfer',
    'complaint.opened': 'Komplain dibuka',
    'complaint.seller_rejected': 'Komplain ditolak penjual',
    'complaint.approved_by_seller': 'Komplain disetujui penjual',
    'complaint.decided_by_admin': 'Komplain diputuskan super admin',
    'preorder.closed_early': 'PO ditutup lebih awal',
    'preorder.deleted': 'PO dihapus',
    'user.super_admin_granted': 'Super admin diberikan',
    'user.super_admin_revoked': 'Super admin dicabut',
    'platform.settings_updated': 'Pengaturan platform diubah',
};

const GROUP_LABEL = { toko: 'Toko', uang: 'Uang', rekening: 'Rekening', komplain: 'Komplain', anggota: 'Anggota', po: 'PO' };

export default function AuditLog({ log, jenis, q, groups }) {
    const [term, setTerm] = useState(q || '');

    return (
        <PanelLayout variant="admin" active="log" title="Jejak tindakan">
            <div className="flex flex-col gap-4 px-4 pt-4 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Jejak tindakan</h1>
                    <p className="m-0 text-sm text-muted">Catatan siapa melakukan apa. Dipakai saat menelusuri keluhan atau selisih uang.</p>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/admin/log', { ...(term ? { q: term } : {}), ...(jenis ? { jenis } : {}) }, { preserveScroll: true });
                    }}
                    className="flex gap-2.5"
                >
                    <label className="flex h-11 flex-1 items-center gap-2.5 rounded-[14px] border border-panel-line bg-white px-3.5 text-muted">
                        <Icon name="search" size={18} />
                        <input
                            type="search"
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Nama pelaku atau nama tindakan"
                            aria-label="Cari jejak tindakan"
                            className="min-w-0 flex-1 border-0 bg-transparent text-sm text-ink outline-none"
                        />
                    </label>
                    <button type="submit" className="h-11 rounded-[14px] bg-forest px-4 text-sm font-semibold text-white">
                        Cari
                    </button>
                </form>

                <FilterChips
                    path="/admin/log"
                    param="jenis"
                    value={jenis}
                    options={[{ value: '', label: 'Semua' }, ...groups.map((g) => ({ value: g, label: GROUP_LABEL[g] || g }))]}
                />

                {log.data.length === 0 && <PanelCard className="p-5 text-center text-sm text-muted">Belum ada tindakan tercatat.</PanelCard>}

                {log.data.length > 0 && (
                    <PanelCard className="flex flex-col divide-y divide-[#EDF1EB] px-4">
                        {log.data.map((a) => (
                            <div key={a.id} className="flex flex-col gap-0.5 py-3">
                                <span className="flex flex-wrap items-baseline justify-between gap-2">
                                    <span className="text-[14px] font-semibold">{LABEL[a.action] || a.action}</span>
                                    <span className="text-xs text-muted">{a.date}</span>
                                </span>
                                <span className="text-[12.5px] text-muted">
                                    {a.actor} · {a.subject}
                                </span>
                                {a.changes && (
                                    <span className="tabular mt-0.5 rounded-lg bg-[#F6F8F5] px-2.5 py-1.5 text-[12px] text-body">{summarize(a.changes)}</span>
                                )}
                            </div>
                        ))}
                    </PanelCard>
                )}

                <Pagination page={log} />
            </div>
        </PanelLayout>
    );
}

function summarize(changes) {
    return Object.entries(changes)
        .map(([k, v]) => `${k}: ${typeof v === 'object' && v !== null ? JSON.stringify(v) : v}`)
        .join(' · ');
}
