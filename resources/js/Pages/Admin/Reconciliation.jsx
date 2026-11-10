import { router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import { rupiah } from '@/lib/format';

export default function Reconciliation({ date, dateLabel, today, day, history, callbacks, badCallbacks }) {
    const [from, setFrom] = useState(today.slice(0, 8) + '01');
    const [to, setTo] = useState(today);
    const matched = day.diff === 0;
    const maxDay = Math.max(1, ...history.map((h) => h.payments));

    return (
        <PanelLayout variant="admin" active="recon" title="Rekonsiliasi">
            <div className="flex flex-col gap-5 px-4 pt-4 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Rekonsiliasi</h1>
                    <p className="m-0 text-sm text-muted">
                        Buku besar Jajapo dibandingkan dengan pembayaran lunas. Cocokkan juga dengan laporan settlement di dashboard gateway sebelum menutup hari.
                    </p>
                </div>

                <PanelCard className="flex flex-col gap-4 p-4 lg:p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <label className="flex items-center gap-2.5 text-[13px] font-semibold">
                            Tanggal
                            <input
                                type="date"
                                value={date}
                                max={today}
                                onChange={(e) => router.get('/admin/rekonsiliasi', { tanggal: e.target.value }, { preserveScroll: true })}
                                className="h-11 rounded-xl border border-panel-line bg-white px-3 text-sm text-ink outline-none"
                            />
                        </label>
                        <span
                            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-bold ${
                                matched ? 'bg-[#DCEFD6] text-[#1F5A2D]' : 'bg-[#FCE4E1] text-[#A3281C]'
                            }`}
                        >
                            <Icon name={matched ? 'check' : 'info'} size={15} strokeWidth={2.4} />
                            {matched ? 'Cocok' : `Selisih ${rupiah(Math.abs(day.diff))}`}
                        </span>
                    </div>

                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <Tile label="Buku besar Jajapo" value={rupiah(day.ledger)} />
                        <Tile label="Pembayaran lunas" value={rupiah(day.payments)} />
                        <Tile label="Pesanan lunas" value={day.orders} />
                        <Tile label="Pendapatan kode unik" value={rupiah(day.revenue)} />
                    </div>
                    <p className="m-0 text-xs text-muted">Angka di atas untuk {dateLabel}.</p>
                </PanelCard>

                <section className="flex flex-col gap-2.5">
                    <h2 className="m-0 text-[17px] font-bold">14 hari terakhir</h2>
                    <PanelCard className="flex flex-col divide-y divide-[#EDF1EB] px-4">
                        {history.map((h) => (
                            <button
                                key={h.date}
                                type="button"
                                onClick={() => router.get('/admin/rekonsiliasi', { tanggal: h.date }, { preserveScroll: true })}
                                className="flex items-center gap-3 py-2.5 text-left"
                            >
                                <span className="w-24 shrink-0 text-[12.5px] text-muted">{h.label}</span>
                                <span className="h-2 flex-1 overflow-hidden rounded-full bg-[#EDF1EB]">
                                    <span className="block h-full rounded-full bg-leaf" style={{ width: `${Math.round((h.payments / maxDay) * 100)}%` }} />
                                </span>
                                <span className="tabular w-28 shrink-0 text-right text-[13px] font-semibold">{rupiah(h.payments)}</span>
                                <span className={`w-20 shrink-0 text-right text-xs font-bold ${h.diff === 0 ? 'text-muted' : 'text-danger'}`}>
                                    {h.diff === 0 ? 'cocok' : rupiah(h.diff)}
                                </span>
                            </button>
                        ))}
                    </PanelCard>
                </section>

                <section className="flex flex-col gap-2.5">
                    <div className="flex items-baseline justify-between gap-2">
                        <h2 className="m-0 text-[17px] font-bold">Callback gateway</h2>
                        {badCallbacks > 0 && <span className="text-[13px] font-semibold text-danger">{badCallbacks} tanda tangan ditolak</span>}
                    </div>
                    {callbacks.length === 0 && <PanelCard className="p-4 text-[13px] text-muted">Belum ada callback masuk.</PanelCard>}
                    {callbacks.length > 0 && (
                        <PanelCard className="flex flex-col divide-y divide-[#EDF1EB] px-4">
                            {callbacks.map((c) => (
                                <span key={c.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5">
                                    <span className="text-[13.5px] font-semibold">{c.type}</span>
                                    {c.code && <span className="tabular text-[12.5px] text-body">{c.code}</span>}
                                    <span className="flex-1" />
                                    {!c.valid && <span className="rounded-full bg-[#FCE4E1] px-2 py-0.5 text-[11px] font-bold text-[#A3281C]">Tanda tangan salah</span>}
                                    {c.valid && !c.processed && <span className="rounded-full bg-[#ECEFEA] px-2 py-0.5 text-[11px] font-bold text-[#4A564C]">Tidak diproses</span>}
                                    <span className="text-xs text-muted">{c.received}</span>
                                </span>
                            ))}
                        </PanelCard>
                    )}
                </section>

                <section className="flex flex-col gap-2.5">
                    <h2 className="m-0 text-[17px] font-bold">Laporan pendapatan</h2>
                    <PanelCard className="flex flex-wrap items-end gap-3 p-4">
                        <label className="flex flex-col gap-1 text-[12.5px] text-muted">
                            Dari
                            <input type="date" value={from} max={to} onChange={(e) => setFrom(e.target.value)} className="h-11 rounded-xl border border-panel-line bg-white px-3 text-sm text-ink outline-none" />
                        </label>
                        <label className="flex flex-col gap-1 text-[12.5px] text-muted">
                            Sampai
                            <input type="date" value={to} min={from} max={today} onChange={(e) => setTo(e.target.value)} className="h-11 rounded-xl border border-panel-line bg-white px-3 text-sm text-ink outline-none" />
                        </label>
                        <a
                            href={`/admin/rekonsiliasi/ekspor?dari=${from}&sampai=${to}`}
                            className="flex h-11 items-center gap-2 rounded-xl bg-forest px-4 text-sm font-semibold text-white no-underline hover:text-white"
                        >
                            <Icon name="download" size={17} />
                            Unduh Excel
                        </a>
                        <span className="w-full text-xs text-muted">Berisi pesanan lunas, uang masuk, dan pendapatan kode unik per hari.</span>
                    </PanelCard>
                </section>
            </div>
        </PanelLayout>
    );
}

function Tile({ label, value }) {
    return (
        <span className="flex flex-col gap-1 rounded-xl bg-[#F6F8F5] px-3 py-2.5">
            <span className="text-[12.5px] text-muted">{label}</span>
            <span className="tabular text-lg font-bold">{value}</span>
        </span>
    );
}
