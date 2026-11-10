import { Link } from '@inertiajs/react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import StatusPill from '@/Components/StatusPill';
import Pagination from '@/Components/Pagination';
import FilterChips from '@/Components/FilterChips';
import { rupiah } from '@/lib/format';

export default function Payouts({ payouts, status, totals }) {
    return (
        <PanelLayout variant="admin" active="payouts" title="Pencairan">
            <div className="flex flex-col gap-4 px-4 pt-4 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Pencairan</h1>
                    <p className="m-0 text-sm text-muted">
                        Menunggu transfer {rupiah(totals.requested)} · sudah ditransfer {rupiah(totals.done)}. Batas proses 1×24 jam hari kerja.
                    </p>
                </div>

                <FilterChips
                    path="/admin/pencairan"
                    value={status}
                    options={[
                        { value: '', label: 'Semua' },
                        { value: 'requested', label: 'Menunggu transfer' },
                        { value: 'done', label: 'Sudah ditransfer' },
                        { value: 'rejected', label: 'Ditolak' },
                    ]}
                />

                {payouts.data.length === 0 && <PanelCard className="p-5 text-center text-sm text-muted">Tidak ada pencairan.</PanelCard>}

                <div className="flex flex-col gap-2.5">
                    {payouts.data.map((p) => (
                        <PanelCard key={p.id} className="flex flex-col gap-1.5 p-4">
                            <span className="flex items-center justify-between gap-2">
                                <Link href={`/admin/toko/${p.shop_slug}`} className="text-[15px] font-bold text-ink no-underline">
                                    {p.shop}
                                </Link>
                                <StatusPill status={p.status} />
                            </span>
                            <span className="flex items-center justify-between gap-2">
                                <span className="text-[13px] text-body">
                                    {p.bank} a.n. {p.owner}
                                </span>
                                <span className="tabular text-base font-bold">{rupiah(p.amount)}</span>
                            </span>
                            <span className="text-xs text-muted">
                                Diajukan {p.date}
                                {p.done && ` · diproses ${p.done}${p.by ? ` oleh ${p.by}` : ''}`}
                            </span>
                            {p.note && <span className="text-[12.5px] text-body">Catatan: {p.note}</span>}
                            {p.proof_url && (
                                <a href={p.proof_url} target="_blank" rel="noreferrer" className="text-[12.5px] font-semibold text-leaf no-underline">
                                    Lihat bukti transfer
                                </a>
                            )}
                        </PanelCard>
                    ))}
                </div>

                <Pagination page={payouts} />
            </div>
        </PanelLayout>
    );
}
