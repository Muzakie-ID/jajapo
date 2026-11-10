import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import StatusPill from '@/Components/StatusPill';
import { rupiah } from '@/lib/format';

const SHOP_STATUS = {
    active: { label: 'Aktif', cls: 'bg-[#DCEFD6] text-[#1F5A2D]' },
    pending: { label: 'Menunggu persetujuan', cls: 'bg-[#FFF1D6] text-amber-ink' },
    suspended: { label: 'Dibekukan', cls: 'bg-[#FCE4E1] text-[#A3281C]' },
    rejected: { label: 'Ditolak', cls: 'bg-[#ECEFEA] text-[#4A564C]' },
};

export function ShopStatus({ status }) {
    const s = SHOP_STATUS[status] || { label: status, cls: 'bg-[#ECEFEA] text-[#4A564C]' };
    return <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ${s.cls}`}>{s.label}</span>;
}

export default function Search({ q, orders, shops, people }) {
    const [term, setTerm] = useState(q || '');
    const total = orders.length + shops.length + people.length;

    return (
        <PanelLayout variant="admin" active="search" title="Cari">
            <div className="flex flex-col gap-5 px-4 pt-4 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Cari</h1>
                    <p className="m-0 text-sm text-muted">Kode pesanan, nama atau email pembeli, nomor WhatsApp, nama toko.</p>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/admin/cari', { q: term }, { preserveState: true });
                    }}
                    className="flex gap-2.5"
                >
                    <label className="flex h-12 flex-1 items-center gap-2.5 rounded-[14px] border border-panel-line bg-white px-3.5 text-muted">
                        <Icon name="search" size={20} />
                        <input
                            autoFocus
                            type="search"
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="JJP-1234, Nadia, 0812..., Danus Dies"
                            aria-label="Kata kunci pencarian"
                            className="min-w-0 flex-1 border-0 bg-transparent text-sm text-ink outline-none"
                        />
                    </label>
                    <button type="submit" className="h-12 rounded-[14px] bg-forest px-5 text-sm font-semibold text-white">
                        Cari
                    </button>
                </form>

                {q && total === 0 && <PanelCard className="p-5 text-center text-sm text-muted">Tidak ada yang cocok dengan "{q}".</PanelCard>}

                {orders.length > 0 && (
                    <Block title="Pesanan" count={orders.length}>
                        {orders.map((o) => (
                            <PanelCard key={o.code} className="flex flex-col gap-1.5 p-4">
                                <span className="flex items-center justify-between gap-2">
                                    <span className="tabular text-sm font-bold">{o.code}</span>
                                    <StatusPill status={o.status} />
                                </span>
                                <span className="text-[13.5px]">
                                    {o.buyer}
                                    {o.phone ? `, ${o.phone}` : ''}
                                </span>
                                <span className="text-[12.5px] text-muted">{o.items}</span>
                                <span className="flex items-center justify-between gap-2 pt-0.5">
                                    <Link href={`/admin/toko/${o.shop_slug}`} className="text-[12.5px] font-semibold text-leaf no-underline">
                                        {o.shop}
                                    </Link>
                                    <span className="tabular text-sm font-bold">{rupiah(o.total)}</span>
                                </span>
                                <span className="text-[12px] text-muted">{o.date}</span>
                            </PanelCard>
                        ))}
                    </Block>
                )}

                {shops.length > 0 && (
                    <Block title="Toko" count={shops.length}>
                        {shops.map((s) => (
                            <Link key={s.slug} href={`/admin/toko/${s.slug}`} className="no-underline">
                                <PanelCard className="flex items-center gap-3 p-4">
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-forest text-sm font-bold text-white">{s.initials}</span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span className="text-[15px] font-bold text-ink">{s.name}</span>
                                        <span className="text-xs text-muted">/{s.slug}</span>
                                    </span>
                                    <ShopStatus status={s.status} />
                                </PanelCard>
                            </Link>
                        ))}
                    </Block>
                )}

                {people.length > 0 && (
                    <Block title="Orang" count={people.length}>
                        {people.map((p) => (
                            <PanelCard key={p.email} className="flex items-center gap-3 p-4">
                                <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="text-[15px] font-bold">
                                        {p.name}
                                        {p.super_admin && <span className="ml-2 rounded-full bg-amber-bg px-2 py-0.5 text-[11px] font-bold text-amber-ink">Super admin</span>}
                                    </span>
                                    <span className="text-xs text-muted">{p.email}</span>
                                </span>
                                <span className="tabular shrink-0 text-[13px] text-muted">{p.orders} pesanan</span>
                            </PanelCard>
                        ))}
                    </Block>
                )}
            </div>
        </PanelLayout>
    );
}

function Block({ title, count, children }) {
    return (
        <section className="flex flex-col gap-2.5">
            <div className="flex items-baseline justify-between">
                <h2 className="m-0 text-[17px] font-bold">{title}</h2>
                <span className="text-[13px] text-muted">{count}</span>
            </div>
            {children}
        </section>
    );
}
