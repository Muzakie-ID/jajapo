import { Link, usePage } from '@inertiajs/react';
import BuyerLayout, { TopBar } from '@/Layouts/BuyerLayout';
import { PlateTile } from '@/Components/Plate';
import Icon from '@/Components/Icon';
import { rupiah } from '@/lib/format';
import { closesIn } from '@/lib/time';

export default function Shop({ shop, open, past }) {
    const { appHost, auth } = usePage().props;
    const ownShop = auth.user?.shops?.some((s) => s.slug === shop.slug);
    return (
        <BuyerLayout title={shop.name} active="shops">
            <TopBar back="/toko" />
            <section className="flex items-center gap-3.5 rounded-3xl border border-line bg-white p-4">
                <span className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-forest text-lg font-bold text-white">
                    {shop.logo_url ? <img src={shop.logo_url} alt="" className="h-full w-full object-cover" /> : shop.initials}
                </span>
                <div className="flex min-w-0 flex-col gap-1">
                    <h1 className="m-0 text-xl font-bold">{shop.name}</h1>
                    <span className="text-[13px] text-muted">{appHost}/{shop.slug}</span>
                    {shop.instagram && (
                        <a href={`https://instagram.com/${shop.instagram}`} target="_blank" rel="noreferrer" className="text-[13px] font-semibold no-underline">
                            @{shop.instagram}
                        </a>
                    )}
                </div>
            </section>
            {!ownShop && (
                <Link
                    href="/obrolan/mulai"
                    method="post"
                    as="button"
                    data={{ toko: shop.slug }}
                    className="mt-3 flex h-12 w-full items-center justify-center gap-2 rounded-2xl border border-line bg-white text-sm font-bold text-ink"
                >
                    <Icon name="chat" size={20} className="text-leaf" />
                    Chat penjual
                </Link>
            )}

            <h2 className="mb-3 mt-5 text-[17px] font-bold">PO yang sedang buka</h2>
            {open.length === 0 && <p className="m-0 text-sm text-muted">Belum ada PO yang buka. Cek lagi nanti.</p>}
            <div className="flex flex-col gap-3">
                {open.map((p) => (
                    <Link key={p.id} href={p.url} className="flex items-center gap-3 rounded-[18px] border border-line bg-white p-2.5 text-ink no-underline hover:text-ink">
                        <PlateTile seed={p.seed} image={p.poster_url} className="h-[72px] w-[72px] shrink-0 rounded-[14px]" plateSize={62} />
                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                            <span className="text-[15px] font-bold">{p.title}</span>
                            <span className="text-xs text-muted">Ambil {p.pickup_label}</span>
                        </div>
                        <div className="flex flex-col items-end gap-1.5">
                            <span className="tabular text-[15px] font-bold">{rupiah(p.price_from)}</span>
                            <span className="text-[11.5px] font-semibold text-muted">Tutup {closesIn(p.closes_at)}</span>
                        </div>
                    </Link>
                ))}
            </div>

            {past.length > 0 && (
                <>
                    <h2 className="mb-3 mt-6 text-[17px] font-bold">Riwayat PO</h2>
                    <div className="overflow-hidden rounded-[18px] border border-line bg-white">
                        {past.map((p, i) => (
                            <div key={p.title + i} className={`flex items-center justify-between gap-3 px-4 py-3 ${i ? 'border-t border-[#E6EDE3]' : ''}`}>
                                <span className="flex flex-col gap-0.5">
                                    <span className="text-sm font-semibold">{p.title}</span>
                                    <span className="text-xs text-muted">Tutup {p.closed_label}</span>
                                </span>
                                <span className="text-xs text-muted">{p.sold} pesanan</span>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </BuyerLayout>
    );
}
