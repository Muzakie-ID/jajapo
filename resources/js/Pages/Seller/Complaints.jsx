import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import ComplaintDetails from '@/Components/ComplaintDetails';
import { rupiah } from '@/lib/format';

export default function Complaints({ shop, complaints }) {
    const open = complaints.filter((c) => c.status === 'open');
    const rest = complaints.filter((c) => c.status !== 'open');

    return (
        <PanelLayout variant="seller" active="complaints" title="Komplain" shop={shop}>
            <div className="flex flex-col gap-4 px-4 pt-4 lg:gap-5 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Komplain</h1>
                    <p className="m-0 text-sm text-muted">
                        Tanggapi dalam 24 jam. Selama komplain berjalan, nominal yang diminta ditahan dari saldo toko. Kalau kamu menolak, super admin yang memutuskan.
                    </p>
                </div>

                <section className="flex flex-col gap-3">
                    <h2 className="m-0 text-base font-bold">Perlu ditanggapi ({open.length})</h2>
                    {open.length === 0 ? (
                        <p className="m-0 rounded-2xl border border-dashed border-[#D3DDD0] px-4 py-6 text-center text-sm text-muted">Tidak ada komplain yang menunggu.</p>
                    ) : (
                        <div className="grid gap-3 lg:grid-cols-2">
                            {open.map((c) => (
                                <PanelCard key={c.id} className="flex flex-col gap-4 p-4">
                                    <ComplaintDetails c={c} />
                                    <Respond c={c} />
                                </PanelCard>
                            ))}
                        </div>
                    )}
                </section>

                {rest.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="m-0 text-base font-bold">Riwayat</h2>
                        <div className="grid gap-3 lg:grid-cols-2">
                            {rest.map((c) => (
                                <PanelCard key={c.id} className="p-4">
                                    <ComplaintDetails c={c} />
                                </PanelCard>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </PanelLayout>
    );
}

function Respond({ c }) {
    const [mode, setMode] = useState(null);
    const approve = useForm({ amount: String(c.amount_requested), note: '' });
    const reject = useForm({ response: '' });

    if (!mode) {
        return (
            <div className="flex gap-2">
                <button type="button" onClick={() => setMode('approve')} className="flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-forest text-sm font-semibold text-white">
                    <Icon name="check" size={18} strokeWidth={2.2} />
                    Setujui refund
                </button>
                <button type="button" onClick={() => setMode('reject')} className="flex h-11 flex-1 items-center justify-center rounded-xl border border-[#DCE3D9] bg-white text-sm font-semibold">
                    Tolak
                </button>
            </div>
        );
    }

    if (mode === 'approve') {
        return (
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    approve.post(`/seller/komplain/${c.id}/setujui`, { preserveScroll: true });
                }}
                className="flex flex-col gap-2.5 rounded-2xl bg-[#F3F8F1] p-3"
            >
                <label className="flex flex-col gap-1.5">
                    <span className="text-[13px] font-semibold text-body">Nominal refund (maks. {rupiah(c.amount_requested)})</span>
                    <span className="flex h-11 items-center gap-1.5 rounded-xl border border-line bg-white px-3">
                        <span className="text-sm text-muted">Rp</span>
                        <input
                            inputMode="numeric"
                            value={approve.data.amount}
                            onChange={(e) => approve.setData('amount', e.target.value.replace(/\D/g, ''))}
                            className="tabular h-full w-full bg-transparent text-sm outline-none"
                        />
                    </span>
                    {approve.errors.amount && <span className="text-xs font-semibold text-danger">{approve.errors.amount}</span>}
                </label>
                <input
                    value={approve.data.note}
                    onChange={(e) => approve.setData('note', e.target.value)}
                    placeholder="Pesan untuk pembeli (opsional)"
                    className="h-11 rounded-xl border border-line bg-white px-3 text-sm outline-none"
                />
                <p className="m-0 text-xs text-muted">Refund dipotong dari saldo toko. Super admin yang mentransfer ke pembeli.</p>
                <div className="flex gap-2">
                    <button type="submit" disabled={approve.processing} className="h-11 flex-1 rounded-xl bg-forest text-sm font-semibold text-white disabled:opacity-60">
                        Setujui {approve.data.amount ? rupiah(Number(approve.data.amount)) : ''}
                    </button>
                    <button type="button" onClick={() => setMode(null)} className="h-11 rounded-xl px-4 text-sm font-semibold text-muted">
                        Batal
                    </button>
                </div>
            </form>
        );
    }

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                reject.post(`/seller/komplain/${c.id}/tolak`, { preserveScroll: true });
            }}
            className="flex flex-col gap-2.5 rounded-2xl bg-[#FBF3F1] p-3"
        >
            <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-semibold text-body">Alasan menolak</span>
                <textarea
                    rows={3}
                    value={reject.data.response}
                    onChange={(e) => reject.setData('response', e.target.value)}
                    placeholder="Contoh: pesanan sudah diambil jam 10.15, tiket di-scan jam itu juga."
                    className="rounded-xl border border-line bg-white px-3 py-2.5 text-sm outline-none"
                />
                {reject.errors.response && <span className="text-xs font-semibold text-danger">{reject.errors.response}</span>}
            </label>
            <div className="flex gap-2">
                <button type="submit" disabled={reject.processing} className="h-11 flex-1 rounded-xl bg-danger text-sm font-semibold text-white disabled:opacity-60">
                    Kirim ke super admin
                </button>
                <button type="button" onClick={() => setMode(null)} className="h-11 rounded-xl px-4 text-sm font-semibold text-muted">
                    Batal
                </button>
            </div>
        </form>
    );
}
