import { Link } from '@inertiajs/react';
import BuyerLayout from '@/Layouts/BuyerLayout';
import StatusPill from '@/Components/StatusPill';
import { rupiah } from '@/lib/format';

export default function Orders({ orders }) {
    return (
        <BuyerLayout title="Pesanan saya" active="orders">
            <h1 className="m-0 mb-4 text-2xl font-bold tracking-[-0.01em]">Pesanan saya</h1>
            {orders.length === 0 ? (
                <div className="flex flex-col items-center gap-3 rounded-[20px] border border-dashed border-[#B9C9B5] bg-white p-6 text-center">
                    <p className="m-0 text-sm text-muted">Belum ada pesanan. Pilih PO yang masih buka di beranda.</p>
                    <Link href="/" className="rounded-[14px] bg-leaf px-4 py-2.5 text-sm font-bold text-white no-underline hover:text-white">
                        Lihat PO yang buka
                    </Link>
                </div>
            ) : (
                <div className="flex flex-col gap-2.5">
                    {orders.map((o) => (
                        <Link key={o.code} href={o.href} className="flex flex-col gap-1.5 rounded-2xl border border-line bg-white px-3.5 py-3 text-ink no-underline hover:text-ink">
                            <span className="flex items-center justify-between gap-2">
                                <span className="text-[15px] font-bold">{o.title}</span>
                                <StatusPill status={o.status} />
                            </span>
                            <span className="text-[13px] text-body">
                                {o.shop_name}. {o.items}
                            </span>
                            <span className="flex items-center justify-between gap-2">
                                <span className="text-xs text-muted">
                                    {o.code}, {o.date_label}
                                </span>
                                <span className="tabular text-sm font-bold">{rupiah(o.total)}</span>
                            </span>
                        </Link>
                    ))}
                </div>
            )}
        </BuyerLayout>
    );
}
