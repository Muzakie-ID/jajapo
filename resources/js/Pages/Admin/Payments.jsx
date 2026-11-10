import { Link } from '@inertiajs/react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Pagination from '@/Components/Pagination';
import FilterChips from '@/Components/FilterChips';
import { rupiah } from '@/lib/format';

const PAYMENT_STATUS = {
    pending: { label: 'Menunggu bayar', cls: 'bg-[#FFF1D6] text-amber-ink' },
    paid: { label: 'Lunas', cls: 'bg-[#DCEFD6] text-[#1F5A2D]' },
    paid_late: { label: 'Bayar telat', cls: 'bg-[#FFF1D6] text-amber-ink' },
    review: { label: 'Nominal tidak cocok', cls: 'bg-[#FCE4E1] text-[#A3281C]' },
    expired: { label: 'Kedaluwarsa', cls: 'bg-[#ECEFEA] text-[#4A564C]' },
    cancelled: { label: 'Dibatalkan', cls: 'bg-[#ECEFEA] text-[#4A564C]' },
};

export default function Payments({ payments, status, counts }) {
    return (
        <PanelLayout variant="admin" active="payments" title="Pembayaran">
            <div className="flex flex-col gap-4 px-4 pt-4 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Pembayaran</h1>
                    <p className="m-0 text-sm text-muted">Semua tagihan QRIS beserta riwayatnya. Yang perlu diputuskan ada di Ringkasan.</p>
                </div>

                <FilterChips
                    path="/admin/pembayaran"
                    value={status}
                    options={[
                        { value: '', label: 'Semua' },
                        { value: 'perlu-dicek', label: 'Perlu dicek', count: counts.attention },
                        { value: 'lunas', label: 'Lunas' },
                        { value: 'menunggu', label: 'Menunggu bayar' },
                        { value: 'gagal', label: 'Kedaluwarsa atau batal' },
                    ]}
                />

                {payments.data.length === 0 && <PanelCard className="p-5 text-center text-sm text-muted">Tidak ada pembayaran.</PanelCard>}

                <div className="flex flex-col gap-2.5">
                    {payments.data.map((p) => {
                        const s = PAYMENT_STATUS[p.status] || { label: p.status, cls: 'bg-[#ECEFEA] text-[#4A564C]' };
                        return (
                            <PanelCard key={p.id} className="flex flex-col gap-1.5 p-4">
                                <span className="flex items-center justify-between gap-2">
                                    <span className="tabular text-sm font-bold">{p.code}</span>
                                    <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ${s.cls}`}>{s.label}</span>
                                </span>
                                <span className="flex items-center justify-between gap-2">
                                    <span className="text-[13.5px]">
                                        {p.buyer} ·{' '}
                                        <Link href={`/admin/toko/${p.shop_slug}`} className="font-semibold text-leaf no-underline">
                                            {p.shop}
                                        </Link>
                                    </span>
                                    <span className="tabular text-sm font-bold">{rupiah(p.amount)}</span>
                                </span>
                                <span className="text-xs text-muted">
                                    Dibuat {p.date}
                                    {p.paid && ` · lunas ${p.paid}`}
                                    {p.method === 'manual' && ' · dilunasi manual'}
                                </span>
                                {p.gateway_ref && <span className="tabular text-[11.5px] text-muted">{p.gateway_ref}</span>}
                            </PanelCard>
                        );
                    })}
                </div>

                <Pagination page={payments} />
            </div>
        </PanelLayout>
    );
}
