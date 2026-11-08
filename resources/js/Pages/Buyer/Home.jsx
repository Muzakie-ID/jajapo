import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import BuyerLayout from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';
import NotificationBell from '@/Components/NotificationBell';
import { PlateTile } from '@/Components/Plate';
import { rupiah } from '@/lib/format';
import { closesIn } from '@/lib/time';
import { readCart } from '@/lib/cart';

const CATEGORIES = [
    { value: '', label: 'Semua' },
    { value: 'makanan', label: 'Makanan' },
    { value: 'minuman', label: 'Minuman' },
    { value: 'snack', label: 'Snack' },
    { value: 'antar', label: 'Bisa diantar', icon: 'truck' },
];

function ClosingPill({ closesAt }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-amber-bg py-1 pl-[7px] pr-[9px] text-[11.5px] font-bold text-amber-ink">
            <Icon name="clock" size={13} strokeWidth={2.2} />
            Tutup {closesIn(closesAt)}
        </span>
    );
}

export function CartBar({ href, title, subtitle, amount }) {
    return (
        <Link
            href={href}
            className="flex items-center justify-between gap-3 rounded-[20px] bg-forest py-2.5 pl-[18px] pr-2.5 no-underline shadow-[0_14px_28px_-12px_rgba(31,58,38,0.6)]"
        >
            <span className="flex min-w-0 flex-col gap-0.5">
                <span className="text-[15px] font-bold text-white">{title}</span>
                <span className="truncate text-xs text-[#BFD3BF]">{subtitle}</span>
            </span>
            <span className="tabular flex shrink-0 items-center gap-1 rounded-[14px] bg-sprout py-[11px] pl-4 pr-3 text-[15px] font-bold text-[#12301B]">
                {amount}
                <Icon name="chevronRight" size={18} strokeWidth={2.2} />
            </span>
        </Link>
    );
}

export default function Home({ points, filters, closingSoon, preorders, pendingOrder }) {
    const [q, setQ] = useState(filters.q || '');
    const [cart, setCart] = useState(null);
    useEffect(() => setCart(readCart()), []);

    const apply = (patch) =>
        router.get('/', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    const pointName = points.find((p) => String(p.id) === String(filters.titik))?.name || 'Semua titik';

    let bottomBar = null;
    if (pendingOrder) {
        bottomBar = (
            <CartBar
                href={`/bayar/${pendingOrder.code}`}
                title="Selesaikan pembayaran"
                subtitle={`${pendingOrder.title}, ${pendingOrder.shop_name}`}
                amount={rupiah(pendingOrder.total)}
            />
        );
    } else if (cart && cart.count > 0) {
        bottomBar = (
            <CartBar
                href={cart.url}
                title={`${cart.count} item di keranjang`}
                subtitle={`${cart.title}, ${cart.shopName}`}
                amount={rupiah(cart.total)}
            />
        );
    }

    return (
        <BuyerLayout title="Beranda" active="home" bottomBar={bottomBar}>
            <header className="sticky top-0 z-10 -mx-5 -mt-4 flex items-center justify-between gap-3 bg-mint px-5 pt-4 pb-2">
                <label className="relative flex flex-col gap-0.5">
                    <span className="text-xs text-muted">Ambil di</span>
                    <span className="flex min-h-8 items-center gap-1.5 text-[17px] font-bold">
                        <Icon name="pin" size={18} strokeWidth={2} className="text-leaf" />
                        {pointName}
                        <Icon name="chevronDown" size={16} strokeWidth={2} className="text-muted" />
                    </span>
                    <select
                        aria-label="Pilih titik ambil"
                        value={filters.titik || ''}
                        onChange={(e) => apply({ titik: e.target.value || undefined })}
                        className="absolute inset-0 cursor-pointer opacity-0"
                    >
                        <option value="">Semua titik</option>
                        {points.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                </label>
                <NotificationBell />
            </header>

            <form
                className="mt-[18px] flex gap-2.5"
                onSubmit={(e) => {
                    e.preventDefault();
                    apply({ q: q || undefined });
                }}
            >
                <label className="flex h-12 flex-1 items-center gap-2.5 rounded-[14px] border border-line bg-white px-3.5 text-muted">
                    <Icon name="search" size={20} />
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Cari menu atau toko"
                        aria-label="Cari menu atau toko"
                        className="min-w-0 flex-1 border-0 bg-transparent text-sm text-ink outline-none"
                    />
                </label>
                <button
                    type="submit"
                    aria-label="Cari"
                    className="flex h-12 w-12 items-center justify-center rounded-[14px] bg-[#DCEBD7] text-leaf"
                >
                    <Icon name="sliders" size={20} strokeWidth={1.9} />
                </button>
            </form>

            <div className="-mr-5 mt-[18px] flex gap-2 overflow-x-auto pr-5 [scrollbar-width:none]">
                {CATEGORIES.map((c) => {
                    const on = (filters.kategori || '') === c.value;
                    return (
                        <button
                            key={c.value || 'all'}
                            type="button"
                            aria-pressed={on}
                            onClick={() => apply({ kategori: c.value || undefined })}
                            className={`flex h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold ${
                                on ? 'bg-leaf text-white' : 'border border-line bg-white text-ink'
                            }`}
                        >
                            {c.icon && <Icon name={c.icon} size={16} />}
                            {c.label}
                        </button>
                    );
                })}
            </div>

            {closingSoon.length > 0 && (
                <section className="mt-[18px] flex flex-col gap-3">
                    <div className="flex items-baseline justify-between">
                        <h2 className="m-0 text-[17px] font-bold">Segera tutup</h2>
                        <a href="#semua" className="text-[13px] font-semibold no-underline">
                            Lihat semua
                        </a>
                    </div>
                    <div className="-mr-5 flex gap-3 overflow-x-auto pr-5 [scrollbar-width:none]">
                        {closingSoon.map((p) => (
                            <Link
                                key={p.id}
                                href={p.url}
                                className="flex w-[232px] shrink-0 flex-col gap-2.5 rounded-[20px] border border-line bg-white p-2.5 text-ink no-underline"
                            >
                                <div className="relative overflow-hidden rounded-[14px]">
                                    <PlateTile seed={p.seed} image={p.poster_url} className="h-28 w-full" plateSize={104} />
                                    <span className="absolute left-2 top-2">
                                        <ClosingPill closesAt={p.closes_at} />
                                    </span>
                                </div>
                                <div className="flex flex-col gap-0.5 px-1">
                                    <span className="text-[15px] font-bold">{p.title}</span>
                                    <span className="text-xs text-muted">{p.shop_name}</span>
                                </div>
                                <div className="flex items-center justify-between px-1 pb-1">
                                    <span className="tabular text-[15px] font-bold">{rupiah(p.price_from)}</span>
                                    <span className="text-xs text-muted">Sisa {p.remaining} porsi</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>
            )}

            <section id="semua" className="mt-[18px] flex flex-col gap-3">
                <div className="flex items-baseline justify-between">
                    <h2 className="m-0 text-[17px] font-bold">Semua PO aktif</h2>
                    <span className="text-[13px] text-muted">{preorders.length} PO</span>
                </div>
                {preorders.length === 0 && (
                    <div className="rounded-[18px] border border-dashed border-[#B9C9B5] bg-white p-5 text-center text-sm text-muted">
                        Belum ada PO yang cocok. Coba titik ambil atau kategori lain.
                    </div>
                )}
                {preorders.map((p) => (
                    <Link
                        key={p.id}
                        href={p.url}
                        className="flex items-center gap-3 rounded-[18px] border border-line bg-white p-2.5 text-ink no-underline"
                    >
                        <PlateTile seed={p.seed} image={p.poster_url} className="h-[72px] w-[72px] shrink-0 rounded-[14px]" plateSize={62} />
                        <div className="flex min-w-0 flex-1 flex-col gap-[3px]">
                            <span className="text-[15px] font-bold">{p.title}</span>
                            <span className="text-xs text-muted">{p.shop_name}</span>
                            <span className="flex items-center gap-[5px] text-xs text-muted">
                                <Icon name="pin" size={14} strokeWidth={2} className="text-leaf" />
                                {p.pickup_label}
                            </span>
                        </div>
                        <div className="flex flex-col items-end gap-1.5">
                            <span className="tabular text-[15px] font-bold">{rupiah(p.price_from)}</span>
                            <span className="text-[11.5px] font-semibold text-muted">Tutup {closesIn(p.closes_at)}</span>
                        </div>
                    </Link>
                ))}
            </section>
        </BuyerLayout>
    );
}
