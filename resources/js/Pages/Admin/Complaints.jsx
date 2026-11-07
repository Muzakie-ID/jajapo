import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import ComplaintDetails from '@/Components/ComplaintDetails';
import { rupiah } from '@/lib/format';

export default function Complaints({ decide, waiting, done, refunds }) {
    return (
        <PanelLayout variant="admin" active="complaints" title="Komplain dan refund">
            <div className="flex flex-col gap-5 px-4 pt-4 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Komplain dan refund</h1>
                    <p className="m-0 text-sm text-muted">Putuskan komplain yang ditolak penjual atau tidak ditanggapi 24 jam, lalu transfer refund yang sudah disetujui.</p>
                </div>

                <Block title="Refund perlu ditransfer" count={refunds.length} empty="Tidak ada refund yang menunggu transfer.">
                    {refunds.map((r) => (
                        <PanelCard key={r.id} className="flex flex-col gap-3 p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <span className="text-xs text-muted">
                                        {r.code}, {r.shop} · {r.date}
                                    </span>
                                    <span className="text-[15px] font-bold">{r.buyer}</span>
                                    <span className="text-[13px] text-body">{r.reason}</span>
                                </div>
                                <span className="tabular shrink-0 text-lg font-extrabold">{rupiah(r.amount)}</span>
                            </div>
                            <p className="m-0 flex items-center gap-2 rounded-xl bg-[#F6F8F5] px-3 py-2.5 text-[13px] font-semibold">
                                <Icon name="bank" size={18} className="text-leaf" />
                                {r.refund_to || 'Rekening belum ada, hubungi pembeli'}
                            </p>
                            <button
                                type="button"
                                onClick={() =>
                                    window.confirm(`Sudah transfer ${rupiah(r.amount)} ke ${r.refund_to || r.buyer}?`) &&
                                    router.post(`/admin/refund/${r.id}/selesai`, {}, { preserveScroll: true })
                                }
                                className="h-11 rounded-xl bg-forest text-sm font-semibold text-white"
                            >
                                Sudah ditransfer
                            </button>
                        </PanelCard>
                    ))}
                </Block>

                <Block title="Perlu keputusan" count={decide.length} empty="Tidak ada komplain yang perlu diputuskan.">
                    {decide.map((c) => (
                        <PanelCard key={c.id} className="flex flex-col gap-4 p-4">
                            <ComplaintDetails c={c} showShop />
                            <Decide c={c} />
                        </PanelCard>
                    ))}
                </Block>

                <Block title="Menunggu penjual" count={waiting.length} empty="Tidak ada komplain yang menunggu penjual." note="Penjual masih punya waktu menanggapi. Kamu tetap bisa memutuskan kalau perlu.">
                    {waiting.map((c) => (
                        <PanelCard key={c.id} className="flex flex-col gap-4 p-4">
                            <ComplaintDetails c={c} showShop />
                            <Decide c={c} subtle />
                        </PanelCard>
                    ))}
                </Block>

                {done.length > 0 && (
                    <Block title="Selesai" count={done.length}>
                        {done.map((c) => (
                            <PanelCard key={c.id} className="p-4">
                                <ComplaintDetails c={c} showShop />
                            </PanelCard>
                        ))}
                    </Block>
                )}
            </div>
        </PanelLayout>
    );
}

function Block({ title, count, empty, note, children }) {
    return (
        <section className="flex flex-col gap-3">
            <div className="flex flex-col gap-0.5">
                <h2 className="m-0 text-base font-bold">
                    {title} <span className="tabular text-muted">({count})</span>
                </h2>
                {note && count > 0 && <p className="m-0 text-xs text-muted">{note}</p>}
            </div>
            {count === 0 && empty ? (
                <p className="m-0 rounded-2xl border border-dashed border-[#D3DDD0] px-4 py-6 text-center text-sm text-muted">{empty}</p>
            ) : (
                <div className="grid gap-3 lg:grid-cols-2">{children}</div>
            )}
        </section>
    );
}

function Decide({ c, subtle = false }) {
    const [open, setOpen] = useState(!subtle);
    const form = useForm({ amount: String(c.amount_requested), note: '' });

    if (!open) {
        return (
            <button type="button" onClick={() => setOpen(true)} className="h-10 self-start rounded-xl border border-[#DCE3D9] bg-white px-4 text-[13px] font-semibold">
                Putuskan sekarang
            </button>
        );
    }

    const send = (amount) => {
        form.transform((d) => ({ ...d, amount }));
        form.post(`/admin/komplain/${c.id}/putuskan`, { preserveScroll: true });
    };

    return (
        <div className="flex flex-col gap-2.5 rounded-2xl bg-[#F6F8F5] p-3">
            <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-semibold text-body">Nominal refund (maks. {rupiah(c.max_amount)})</span>
                <span className="flex h-11 items-center gap-1.5 rounded-xl border border-line bg-white px-3">
                    <span className="text-sm text-muted">Rp</span>
                    <input
                        inputMode="numeric"
                        value={form.data.amount}
                        onChange={(e) => form.setData('amount', e.target.value.replace(/\D/g, ''))}
                        className="tabular h-full w-full bg-transparent text-sm outline-none"
                    />
                </span>
                {form.errors.amount && <span className="text-xs font-semibold text-danger">{form.errors.amount}</span>}
            </label>
            <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-semibold text-body">Alasan keputusan</span>
                <textarea
                    rows={2}
                    value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                    placeholder="Dibaca pembeli dan penjual."
                    className="rounded-xl border border-line bg-white px-3 py-2.5 text-sm outline-none"
                />
                {form.errors.note && <span className="text-xs font-semibold text-danger">{form.errors.note}</span>}
            </label>
            <div className="flex flex-wrap gap-2">
                <button type="button" disabled={form.processing} onClick={() => send(Number(form.data.amount || 0))} className="h-11 flex-1 rounded-xl bg-forest px-4 text-sm font-semibold text-white disabled:opacity-60">
                    Setujui refund {form.data.amount ? rupiah(Number(form.data.amount)) : ''}
                </button>
                <button type="button" disabled={form.processing} onClick={() => send(0)} className="h-11 flex-1 rounded-xl border border-[#E5C7C2] bg-white px-4 text-sm font-semibold text-danger disabled:opacity-60">
                    Tolak komplain
                </button>
            </div>
        </div>
    );
}
