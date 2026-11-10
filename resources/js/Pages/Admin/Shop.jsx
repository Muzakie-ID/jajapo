import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import StatusPill from '@/Components/StatusPill';
import { ShopStatus } from '@/Pages/Admin/Search';
import { rupiah } from '@/lib/format';

const ACTION_LABEL = {
    'shop.approved': 'Toko disetujui',
    'shop.rejected': 'Pendaftaran ditolak',
    'shop.suspended': 'Toko dibekukan',
    'shop.reactivated': 'Toko diaktifkan kembali',
    'shop.updated': 'Pengaturan toko diubah',
    'bank_account.requested': 'Ganti rekening diajukan',
    'bank_account.approved': 'Rekening disetujui',
    'bank_account.rejected': 'Rekening ditolak',
};

export default function Shop({ shop, balance, totals, members, account, pendingAccount, preorders, orders, payouts, log }) {
    const [reason, setReason] = useState('');
    const [asking, setAsking] = useState(false);

    return (
        <PanelLayout variant="admin" active="shops" title={shop.name}>
            <div className="flex flex-col gap-5 px-4 pt-4 lg:px-8 lg:pt-7">
                <Link href="/admin" className="flex items-center gap-1.5 text-[13px] font-semibold text-leaf no-underline">
                    <Icon name="arrowLeft" size={16} strokeWidth={2.2} />
                    Ringkasan
                </Link>

                <div className="flex flex-wrap items-center gap-3">
                    <span className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-forest text-lg font-bold text-white">
                        {shop.logo_url ? <img src={shop.logo_url} alt="" className="h-full w-full object-cover" /> : shop.initials}
                    </span>
                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">{shop.name}</h1>
                        <span className="flex flex-wrap items-center gap-2 text-[13px] text-muted">
                            <ShopStatus status={shop.status} />
                            <a href={shop.url} target="_blank" rel="noreferrer" className="font-semibold text-leaf no-underline">
                                /{shop.slug}
                            </a>
                        </span>
                    </div>
                </div>

                {shop.status === 'suspended' && (
                    <PanelCard className="flex flex-col gap-2 border-[#F0C8C2] bg-[#FDF2F0] p-4">
                        <span className="text-[13px] font-bold text-[#A3281C]">Dibekukan {shop.suspended}</span>
                        <span className="text-[13px] text-body">Alasan: {shop.suspend_reason}</span>
                        <button
                            type="button"
                            onClick={() => router.post(`/admin/toko/${shop.id}/aktifkan`, {}, { preserveScroll: true })}
                            className="mt-1 h-11 self-start rounded-xl bg-forest px-5 text-sm font-semibold text-white"
                        >
                            Aktifkan kembali
                        </button>
                    </PanelCard>
                )}

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Tile label="Saldo tersedia" value={rupiah(balance.available)} />
                    <Tile label="Ditahan komplain" value={rupiah(balance.held)} />
                    <Tile label="Omzet toko" value={rupiah(totals.revenue)} sub={`${totals.orders} pesanan`} />
                    <Tile label="Sudah dicairkan" value={rupiah(totals.paid_out)} sub={`${totals.complaints} komplain`} />
                </div>

                <div className="flex flex-col gap-5 lg:flex-row lg:items-start">
                    <div className="flex min-w-0 flex-col gap-5 lg:flex-[1.4]">
                        <Section title="PO" count={totals.preorders}>
                            {preorders.length === 0 && <Empty>Belum ada PO.</Empty>}
                            {preorders.map((p) => (
                                <PanelCard key={p.id} className="flex items-center justify-between gap-3 p-4">
                                    <span className="flex min-w-0 flex-col gap-0.5">
                                        <span className="truncate text-[15px] font-bold">{p.title}</span>
                                        <span className="text-xs text-muted">Tutup {p.closes}</span>
                                    </span>
                                    <span className="flex shrink-0 items-center gap-2.5">
                                        <span className="tabular text-[13px] font-semibold">{p.orders} pesanan</span>
                                        <StatusPill status={p.status} />
                                    </span>
                                </PanelCard>
                            ))}
                        </Section>

                        <Section title="Pesanan terbaru" count={orders.length}>
                            {orders.length === 0 && <Empty>Belum ada pesanan.</Empty>}
                            {orders.map((o) => (
                                <PanelCard key={o.code} className="flex flex-col gap-1 p-4">
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="tabular text-sm font-bold">{o.code}</span>
                                        <StatusPill status={o.status} />
                                    </span>
                                    <span className="text-[13.5px]">
                                        {o.buyer}, {o.item}
                                    </span>
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="text-[12.5px] text-muted">{o.slot}</span>
                                        <span className="tabular text-sm font-bold">{rupiah(o.amount)}</span>
                                    </span>
                                </PanelCard>
                            ))}
                        </Section>

                        <Section title="Jejak tindakan" count={log.length}>
                            {log.length === 0 && <Empty>Belum ada tindakan tercatat.</Empty>}
                            {log.length > 0 && (
                                <PanelCard className="flex flex-col divide-y divide-[#EDF1EB] px-4">
                                    {log.map((a, i) => (
                                        <span key={i} className="flex flex-col gap-0.5 py-2.5">
                                            <span className="text-[13.5px] font-semibold">{ACTION_LABEL[a.action] || a.action}</span>
                                            <span className="text-xs text-muted">
                                                {a.actor} · {a.date}
                                            </span>
                                            {a.changes?.reason && <span className="text-[12.5px] text-body">Alasan: {a.changes.reason}</span>}
                                        </span>
                                    ))}
                                </PanelCard>
                            )}
                        </Section>
                    </div>

                    <div className="flex min-w-0 flex-col gap-5 lg:flex-1">
                        <Section title="Pembuat dan anggota" count={members.length}>
                            <PanelCard className="flex flex-col divide-y divide-[#EDF1EB] px-4">
                                {members.map((m) => (
                                    <span key={m.email} className="flex items-center justify-between gap-2 py-2.5">
                                        <span className="flex min-w-0 flex-col gap-0.5">
                                            <span className="truncate text-[13.5px] font-semibold">{m.name}</span>
                                            <span className="truncate text-xs text-muted">{m.email}</span>
                                        </span>
                                        <span className="shrink-0 text-xs font-bold text-muted">{m.creator ? 'Pembuat' : m.role === 'admin' ? 'Admin' : 'Anggota'}</span>
                                    </span>
                                ))}
                            </PanelCard>
                        </Section>

                        <Section title="Rekening">
                            <PanelCard className="flex flex-col gap-2 p-4">
                                {account ? (
                                    <>
                                        <span className="text-[13.5px] font-semibold">{account.label}</span>
                                        <span className="text-xs text-muted">
                                            a.n. {account.name}
                                            {account.verified ? ' · terverifikasi' : ' · belum terverifikasi'}
                                        </span>
                                    </>
                                ) : (
                                    <span className="text-[13px] text-muted">Belum ada rekening.</span>
                                )}
                                {pendingAccount && (
                                    <span className="mt-1 rounded-xl bg-[#FFF8E8] px-3 py-2 text-[12.5px]">
                                        Menunggu persetujuan: {pendingAccount.label} a.n. {pendingAccount.name}
                                    </span>
                                )}
                            </PanelCard>
                        </Section>

                        <Section title="Pencairan" count={payouts.length}>
                            {payouts.length === 0 && <Empty>Belum ada pengajuan.</Empty>}
                            {payouts.length > 0 && (
                                <PanelCard className="flex flex-col divide-y divide-[#EDF1EB] px-4">
                                    {payouts.map((p) => (
                                        <span key={p.id} className="flex items-center justify-between gap-2 py-2.5">
                                            <span className="flex min-w-0 flex-col gap-0.5">
                                                <span className="tabular text-[13.5px] font-semibold">{rupiah(p.amount)}</span>
                                                <span className="truncate text-xs text-muted">{p.date}</span>
                                            </span>
                                            <StatusPill status={p.status} />
                                        </span>
                                    ))}
                                </PanelCard>
                            )}
                        </Section>

                        <Section title="Data toko">
                            <PanelCard className="flex flex-col gap-1.5 p-4 text-[13px]">
                                <Row label="Dibuat" value={shop.created} />
                                {shop.approved && <Row label="Disetujui" value={shop.approved} />}
                                <Row label="Pembuat" value={`${shop.creator.name} (${shop.creator.email})`} />
                                {shop.instagram && <Row label="Instagram" value={shop.instagram} />}
                                {shop.whatsapp && <Row label="WhatsApp" value={shop.whatsapp} />}
                            </PanelCard>
                        </Section>

                        {shop.status === 'active' &&
                            (asking ? (
                                <PanelCard className="flex flex-col gap-2.5 p-4">
                                    <span className="text-[13px] font-semibold">Alasan pembekuan</span>
                                    <textarea
                                        autoFocus
                                        rows={3}
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        maxLength={200}
                                        placeholder="Misalnya: menjual di luar ketentuan kampus."
                                        className="w-full rounded-xl border border-panel-line bg-white px-3 py-2.5 text-sm text-ink outline-none"
                                    />
                                    <span className="flex gap-2">
                                        <button
                                            type="button"
                                            disabled={reason.trim().length === 0}
                                            onClick={() => router.post(`/admin/toko/${shop.id}/bekukan`, { reason }, { preserveScroll: true })}
                                            className="h-11 flex-1 rounded-xl bg-danger text-sm font-semibold text-white disabled:opacity-40"
                                        >
                                            Bekukan toko
                                        </button>
                                        <button type="button" onClick={() => setAsking(false)} className="h-11 flex-1 rounded-xl border border-panel-line text-sm font-semibold">
                                            Batal
                                        </button>
                                    </span>
                                </PanelCard>
                            ) : (
                                <button type="button" onClick={() => setAsking(true)} className="h-11 rounded-xl border border-[#F0C8C2] text-sm font-semibold text-danger">
                                    Bekukan toko
                                </button>
                            ))}
                    </div>
                </div>
            </div>
        </PanelLayout>
    );
}

function Tile({ label, value, sub }) {
    return (
        <PanelCard className="flex flex-col gap-1 p-4">
            <span className="text-[12.5px] text-muted">{label}</span>
            <span className="tabular text-lg font-bold lg:text-xl">{value}</span>
            {sub && <span className="text-xs text-muted">{sub}</span>}
        </PanelCard>
    );
}

function Section({ title, count, children }) {
    return (
        <section className="flex flex-col gap-2.5">
            <div className="flex items-baseline justify-between">
                <h2 className="m-0 text-[17px] font-bold">{title}</h2>
                {count !== undefined && <span className="text-[13px] text-muted">{count}</span>}
            </div>
            {children}
        </section>
    );
}

function Empty({ children }) {
    return <PanelCard className="p-4 text-[13px] text-muted">{children}</PanelCard>;
}

function Row({ label, value }) {
    return (
        <span className="flex justify-between gap-3">
            <span className="shrink-0 text-muted">{label}</span>
            <span className="text-right font-semibold">{value}</span>
        </span>
    );
}
