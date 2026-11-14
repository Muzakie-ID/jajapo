import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import StatusPill from '@/Components/StatusPill';
import Pagination from '@/Components/Pagination';
import { pillLabel, rupiah } from '@/lib/format';

const GROUPS = [
    { value: '', label: 'Semua', key: 'semua' },
    { value: 'disiapkan', label: 'Menunggu disiapkan' },
    { value: 'siap', label: 'Siap diambil' },
    { value: 'diantar', label: 'Diantar' },
    { value: 'selesai', label: 'Selesai' },
    { value: 'tidak-diambil', label: 'Tidak diambil' },
    { value: 'menunggu-bayar', label: 'Menunggu bayar' },
    { value: 'batal', label: 'Batal atau refund' },
];

const selectCls = 'h-11 min-w-0 cursor-pointer rounded-xl border border-panel-line bg-white px-3 text-sm text-ink outline-none';

export default function Orders({ shop, orders, filters, counts, preorders, places }) {
    const [term, setTerm] = useState(filters.q || '');
    const showPo = filters.po === 'semua';

    const go = (changes) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== null && v !== ''));
        router.get('/seller/pesanan', query, { preserveScroll: true, preserveState: true });
    };

    return (
        <PanelLayout variant="seller" active="orders" title="Pesanan" shop={shop}>
            <div className="flex flex-col gap-4 px-4 pt-4 lg:gap-5 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Pesanan</h1>
                    <p className="m-0 text-sm text-muted">Semua pesanan tokomu. Pilih satu untuk melihat rinciannya.</p>
                </div>

                <PanelCard className="flex flex-col gap-3 p-3.5 lg:p-4">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            go({ q: term.trim() });
                        }}
                        className="flex gap-2"
                    >
                        <label className="flex h-11 min-w-0 flex-1 items-center gap-2.5 rounded-xl border border-panel-line bg-white px-3.5 text-muted">
                            <Icon name="search" size={18} />
                            <input
                                type="search"
                                value={term}
                                onChange={(e) => setTerm(e.target.value)}
                                placeholder="Kode pesanan, nama, atau nomor WhatsApp"
                                aria-label="Cari pesanan"
                                className="min-w-0 flex-1 border-0 bg-transparent text-sm text-ink outline-none"
                            />
                        </label>
                        <button type="submit" className="h-11 rounded-xl bg-forest px-4 text-sm font-semibold text-white">
                            Cari
                        </button>
                    </form>
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <select aria-label="Pilih PO" value={filters.po} onChange={(e) => go({ po: e.target.value, tempat: null })} className={selectCls}>
                            <option value="semua">Semua PO</option>
                            {preorders.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.title}
                                    {p.open ? ' (sedang buka)' : ''}
                                </option>
                            ))}
                        </select>
                        <select aria-label="Cara terima" value={filters.cara || ''} onChange={(e) => go({ cara: e.target.value })} className={selectCls}>
                            <option value="">Ambil dan antar</option>
                            <option value="ambil">Ambil sendiri</option>
                            <option value="antar">Diantar</option>
                        </select>
                        <select
                            aria-label="Titik ambil atau area antar"
                            value={filters.tempat || ''}
                            onChange={(e) => go({ tempat: e.target.value })}
                            disabled={places.length === 0}
                            className={`${selectCls} disabled:cursor-not-allowed disabled:opacity-60`}
                        >
                            <option value="">{places.length ? 'Semua tempat' : 'Pilih satu PO untuk filter tempat'}</option>
                            {places.map((p) => (
                                <option key={p.value} value={p.value}>
                                    {p.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </PanelCard>

                <div className="-mr-4 flex gap-2 overflow-x-auto pr-4 [scrollbar-width:none]">
                    {GROUPS.map((g) => {
                        const on = (filters.status || '') === g.value;
                        const n = counts[g.key || g.value] ?? 0;
                        return (
                            <button
                                key={g.value || 'all'}
                                type="button"
                                aria-pressed={on}
                                onClick={() => go({ status: g.value })}
                                className={`flex h-10 shrink-0 items-center gap-1.5 rounded-full px-3.5 text-[13px] font-semibold ${on ? 'bg-forest text-white' : 'border border-panel-line bg-white text-ink'}`}
                            >
                                {g.label}
                                <span className={`tabular rounded-full px-1.5 text-[11px] ${on ? 'bg-white/20' : 'bg-[#EDF1EB]'}`}>{n}</span>
                            </button>
                        );
                    })}
                </div>

                {orders.data.length === 0 ? (
                    <PanelCard className="flex flex-col items-center gap-2 p-8 text-center">
                        <Icon name="list" size={26} className="text-leaf" />
                        <span className="text-[15px] font-bold">Tidak ada pesanan</span>
                        <span className="text-[13px] text-muted">{filters.q ? `Tidak ada yang cocok dengan "${filters.q}".` : 'Belum ada pesanan dengan filter ini.'}</span>
                    </PanelCard>
                ) : (
                    <PanelCard className="overflow-hidden">
                        <div className="hidden lg:block">
                            <div className="grid grid-cols-[0.75fr_1.2fr_1.3fr_1.4fr_0.8fr_0.95fr] gap-3 bg-panel-soft px-[18px] py-2.5 text-[12.5px] font-semibold text-muted">
                                <span>Kode</span>
                                <span>Pembeli</span>
                                <span>Pesanan</span>
                                <span>Ambil atau antar</span>
                                <span className="text-right">Untuk toko</span>
                                <span>Status</span>
                            </div>
                            {orders.data.map((o) => (
                                <Link
                                    key={o.code}
                                    href={`/seller/pesanan/${o.code}`}
                                    className="grid grid-cols-[0.75fr_1.2fr_1.3fr_1.4fr_0.8fr_0.95fr] items-center gap-3 border-t border-[#EDF1EB] px-[18px] py-3 text-[13.5px] text-ink no-underline hover:bg-panel-soft hover:text-ink"
                                >
                                    <span className="flex flex-col gap-0.5">
                                        <span className="tabular font-bold">{o.code}</span>
                                        {o.paid && <span className="text-xs text-muted">{o.paid}</span>}
                                    </span>
                                    <span className="flex min-w-0 flex-col gap-0.5">
                                        <span className="truncate">{o.buyer}</span>
                                        {o.phone && <span className="tabular text-xs text-muted">{o.phone}</span>}
                                    </span>
                                    <span className="flex min-w-0 flex-col gap-0.5">
                                        <span className="text-body">{o.items}</span>
                                        {showPo && <span className="truncate text-xs text-muted">{o.preorder}</span>}
                                    </span>
                                    <span className="flex min-w-0 flex-col gap-0.5">
                                        <span className="text-body">{o.place}</span>
                                        {o.schedule && <span className="text-xs text-muted">{o.schedule}</span>}
                                    </span>
                                    <span className="tabular text-right font-semibold">{rupiah(o.amount)}</span>
                                    <span>
                                        <StatusPill status={o.status} label={pillLabel(o)} />
                                    </span>
                                </Link>
                            ))}
                        </div>

                        <div className="flex flex-col lg:hidden">
                            {orders.data.map((o) => (
                                <Link key={o.code} href={`/seller/pesanan/${o.code}`} className="flex flex-col gap-1.5 border-t border-[#EDF1EB] px-4 py-3.5 text-ink no-underline first:border-t-0 hover:text-ink">
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="tabular text-sm font-bold">{o.code}</span>
                                        <StatusPill status={o.status} label={pillLabel(o)} />
                                    </span>
                                    <span className="text-[13.5px]">
                                        <span className="font-semibold">{o.buyer}</span>, {o.items}
                                    </span>
                                    <span className="flex items-end justify-between gap-2">
                                        <span className="flex min-w-0 flex-col text-[12.5px] text-muted">
                                            <span>{o.place}</span>
                                            {o.schedule && <span>{o.schedule}</span>}
                                            {showPo && <span className="truncate">{o.preorder}</span>}
                                        </span>
                                        <span className="tabular shrink-0 text-sm font-bold">{rupiah(o.amount)}</span>
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </PanelCard>
                )}

                <Pagination page={orders} />
            </div>
        </PanelLayout>
    );
}
