import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import { rupiah } from '@/lib/format';

const RANGES = [
    ['hari-ini', 'Hari ini'],
    ['7-hari', '7 hari'],
    ['30-hari', '30 hari'],
];

export default function Overview({ range, dateLabel, kpis, gateway, queue, attention, pendingShops, pendingAccounts, recon }) {
    const [payoutDialog, setPayoutDialog] = useState(null);
    const rangeLabel = RANGES.find(([v]) => v === range)?.[1].toLowerCase();
    const kpiTiles = [
        { label: 'Transaksi lunas', value: rupiah(kpis.paid_total), sub: `${kpis.paid_count} pesanan dari ${kpis.paid_shops} toko` },
        { label: 'Pendapatan kode unik', value: rupiah(kpis.revenue), sub: `Rata-rata ${rupiah(kpis.avg_code)} per pesanan` },
        { label: 'Toko aktif', value: kpis.active_shops, sub: `${kpis.pending_shops} menunggu persetujuan`, warn: kpis.pending_shops > 0 },
        { label: 'Pencairan menunggu', value: rupiah(kpis.payout_total), sub: `${kpis.payout_count} pengajuan`, warn: kpis.payout_count > 0 },
    ];
    const diff = recon.ledger - recon.payments;
    const setRange = (value) => router.get('/admin', { rentang: value }, { preserveScroll: true, preserveState: true });
    const post = (url, message) => {
        if (!message || window.confirm(message)) router.post(url, {}, { preserveScroll: true });
    };

    return (
        <PanelLayout
            variant="admin"
            active="overview"
            title="Ringkasan platform"
            badges={{ payouts: kpis.payout_count || null, payments: attention.length || null, shops: kpis.pending_shops + pendingAccounts.length || null }}
        >
            <header className="flex flex-col gap-3.5 bg-night px-4 pb-16 pt-4 text-white lg:hidden">
                <div className="flex items-center justify-between gap-2">
                    <span className="flex items-center gap-2">
                        <span className="text-xl font-extrabold tracking-[-0.03em]">jajapo</span>
                        <span className="rounded-full bg-[#FFE2A8] px-[9px] py-[3px] text-[11.5px] font-bold text-[#5C3A00]">Super admin</span>
                    </span>
                </div>
                <div className="flex items-end justify-between gap-3">
                    <div className="flex flex-col gap-0.5">
                        <h1 className="m-0 text-[22px] font-bold">Ringkasan platform</h1>
                        <span className="text-[13px] text-[#A9BDA8]">{rangeLabel}</span>
                    </div>
                    <label className="relative flex h-9 items-center gap-1 rounded-[10px] border border-[#2C4733] pl-3 pr-2.5 text-[13px] font-semibold text-[#E6EFE4]">
                        Ganti rentang
                        <Icon name="chevronDown" size={16} strokeWidth={2} />
                        <select aria-label="Rentang waktu" value={range} onChange={(e) => setRange(e.target.value)} className="absolute inset-0 opacity-0">
                            {RANGES.map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
                <GatewayLine gateway={gateway} dark />
            </header>

            <div className="-mt-11 flex flex-col gap-4 px-4 lg:mt-0 lg:gap-5 lg:px-8 lg:pt-7">
                <div className="hidden flex-wrap items-end justify-between gap-4 lg:flex">
                    <div className="flex flex-col gap-1">
                        <h1 className="m-0 text-[28px] font-bold tracking-[-0.02em]">Ringkasan platform</h1>
                        <p className="m-0 text-sm text-muted">
                            {dateLabel}. Angka di bawah untuk {rangeLabel}.
                        </p>
                    </div>
                    <div role="group" aria-label="Rentang waktu" className="flex rounded-xl bg-[#E7ECE5] p-[3px]">
                        {RANGES.map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                aria-pressed={range === value}
                                onClick={() => setRange(value)}
                                className={`h-[38px] rounded-[9px] px-3.5 text-[13px] ${range === value ? 'bg-white font-bold shadow-[0_1px_2px_rgba(20,26,22,0.08)]' : 'font-semibold text-body'}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="hidden rounded-[14px] border border-panel-line bg-white px-4 py-3 lg:block">
                    <GatewayLine gateway={gateway} />
                </div>

                <section aria-label="Angka utama" className="grid grid-cols-2 gap-2.5 lg:grid-cols-4 lg:gap-px lg:overflow-hidden lg:rounded-[18px] lg:border lg:border-panel-line lg:bg-panel-line">
                    {kpiTiles.map((k) => (
                        <div key={k.label} className="flex flex-col gap-1 rounded-2xl border border-panel-line bg-white px-3.5 py-3 shadow-[0_6px_16px_-10px_rgba(20,26,22,0.25)] lg:rounded-none lg:border-0 lg:px-5 lg:py-[18px] lg:shadow-none">
                            <span className="text-xs text-muted lg:text-[13px]">{k.label}</span>
                            <span className="tabular text-lg font-bold tracking-[-0.01em] lg:text-[28px] lg:tracking-[-0.02em]">{k.value}</span>
                            <span className={`text-[11.5px] lg:text-[12.5px] ${k.warn ? 'font-bold text-amber-ink' : 'text-muted'}`}>{k.sub}</span>
                        </div>
                    ))}
                </section>

                <div className="flex flex-col gap-5 lg:flex-row lg:items-start">
                    <PanelCard id="pencairan" className="min-w-0 overflow-hidden lg:flex-[2]">
                        <div className="flex flex-wrap items-baseline justify-between gap-3 px-[18px] py-4">
                            <h2 className="m-0 text-[17px] font-bold">Antrean pencairan</h2>
                            <span className="text-[13px] text-muted">Terlama di atas. Batas proses 1x24 jam hari kerja.</span>
                        </div>
                        {queue.length === 0 && <p className="m-0 border-t border-[#EDF1EB] px-[18px] py-4 text-sm text-muted">Tidak ada pencairan yang menunggu.</p>}
                        {queue.map((q) => (
                            <div key={q.id} className="flex flex-col gap-2 border-t border-[#EDF1EB] px-[18px] py-3 lg:grid lg:grid-cols-[1.4fr_0.9fr_1.6fr_1fr_auto] lg:items-center lg:gap-3">
                                <span className="flex items-baseline justify-between gap-2 lg:block">
                                    <span className="text-sm font-bold">{q.shop}</span>
                                    <span className="tabular text-[15px] font-bold lg:hidden">{rupiah(q.amount)}</span>
                                </span>
                                <span className="tabular hidden text-right text-[13.5px] font-bold lg:block">{rupiah(q.amount)}</span>
                                <span className="flex flex-col gap-px text-[12.5px] lg:text-[13.5px]">
                                    <span>{q.bank}</span>
                                    <span className="text-xs text-muted">a.n. {q.owner}</span>
                                </span>
                                <span className="text-[12.5px] text-body lg:text-[13.5px]">Diajukan {q.date}</span>
                                <button
                                    type="button"
                                    onClick={() => setPayoutDialog(q)}
                                    aria-label={`Proses pencairan ${q.shop} ${rupiah(q.amount)}`}
                                    className="h-11 rounded-xl border border-[#C9D8C4] bg-[#F3F8F1] px-3 text-[13px] font-bold text-forest lg:h-[38px]"
                                >
                                    Tandai ditransfer
                                </button>
                            </div>
                        ))}
                        <p className="m-0 border-t border-[#EDF1EB] px-[18px] pb-4 pt-3 text-[12.5px] text-muted">
                            Transfer manual ke rekening terverifikasi atas nama pembuat toko, lalu unggah bukti transfer. Biaya transfer ditanggung platform.
                        </p>
                    </PanelCard>

                    <div className="flex min-w-0 flex-col gap-5 lg:flex-1">
                        <PanelCard id="pembayaran" className="flex flex-col gap-3 px-[18px] py-4">
                            <h2 className="m-0 text-[17px] font-bold">Pembayaran perlu dicek</h2>
                            {attention.length === 0 && <p className="m-0 text-sm text-muted">Semua pembayaran sudah tercatat.</p>}
                            {attention.map((a) => (
                                <AttentionCard key={a.code} item={a} post={post} />
                            ))}
                        </PanelCard>

                        <PanelCard id="toko" className="flex flex-col gap-1 px-[18px] py-4">
                            <div className="flex items-baseline justify-between gap-2 pb-2">
                                <h2 className="m-0 text-[17px] font-bold">Toko menunggu persetujuan</h2>
                                <span className="text-[13px] text-muted">{pendingShops.length} toko</span>
                            </div>
                            {pendingShops.length === 0 && <p className="m-0 text-sm text-muted">Tidak ada pendaftaran baru.</p>}
                            {pendingShops.map((s) => (
                                <div key={s.id} className="flex flex-col gap-2.5 border-t border-[#EDF1EB] py-3">
                                    <div className="flex items-center gap-3">
                                        <span className="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-[10px] bg-[#F0F3EE] text-[13px] font-bold text-forest">{s.initials}</span>
                                        <span className="flex min-w-0 flex-1 flex-col gap-px">
                                            <span className="text-sm font-bold">{s.name}</span>
                                            <span className="truncate text-xs text-muted">{s.email}</span>
                                            {s.bank && (
                                                <span className="text-xs text-muted">
                                                    {s.bank}, a.n. {s.account_name}
                                                </span>
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex gap-2">
                                        <button type="button" onClick={() => post(`/admin/toko/${s.id}/setujui`, `Setujui ${s.name}? Rekeningnya ikut terverifikasi.`)} className="h-10 flex-1 rounded-[10px] bg-forest text-[13px] font-semibold text-white">
                                            Setujui
                                        </button>
                                        <button type="button" onClick={() => post(`/admin/toko/${s.id}/tolak`, `Tolak pendaftaran ${s.name}?`)} className="h-10 flex-1 rounded-[10px] border border-[#E5C7C2] bg-white text-[13px] font-semibold text-danger">
                                            Tolak
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </PanelCard>

                        {pendingAccounts.length > 0 && (
                            <PanelCard className="flex flex-col gap-1 px-[18px] py-4">
                                <div className="flex items-baseline justify-between gap-2 pb-2">
                                    <h2 className="m-0 text-[17px] font-bold">Ganti rekening</h2>
                                    <span className="text-[13px] text-muted">{pendingAccounts.length} pengajuan</span>
                                </div>
                                {pendingAccounts.map((a) => (
                                    <div key={a.id} className="flex flex-col gap-2.5 border-t border-[#EDF1EB] py-3">
                                        <div className="flex items-start gap-3">
                                            <span className="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-[10px] bg-[#F0F3EE] text-[13px] font-bold text-forest">{a.initials}</span>
                                            <span className="flex min-w-0 flex-1 flex-col gap-1 text-xs">
                                                <span className="text-sm font-bold">{a.shop}</span>
                                                <span className="text-muted">Pembuat toko: {a.owner}, diajukan {a.date}</span>
                                                <span className="text-muted line-through">{a.old}</span>
                                                <span className="font-semibold text-ink">{a.new}</span>
                                            </span>
                                        </div>
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() => post(`/admin/rekening/${a.id}/setujui`, `Setujui rekening ${a.new} untuk ${a.shop}? Pencairan berikutnya ke rekening ini.`)}
                                                className="h-10 flex-1 rounded-[10px] bg-forest text-[13px] font-semibold text-white"
                                            >
                                                Setujui
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    const note = window.prompt(`Alasan menolak rekening ${a.shop}:`);
                                                    if (note) router.post(`/admin/rekening/${a.id}/tolak`, { note }, { preserveScroll: true });
                                                }}
                                                className="h-10 flex-1 rounded-[10px] border border-[#E5C7C2] bg-white text-[13px] font-semibold text-danger"
                                            >
                                                Tolak
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </PanelCard>
                        )}
                    </div>
                </div>

                <PanelCard id="rekonsiliasi" className="flex flex-col gap-3.5 px-[18px] pb-[18px] pt-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="m-0 text-[17px] font-bold">Rekonsiliasi hari ini</h2>
                        {diff === 0 ? (
                            <span className="inline-flex items-center gap-[5px] rounded-full bg-[#DCEFD6] py-1 pl-2 pr-[11px] text-[13px] font-bold text-[#1F5A2D]">
                                <Icon name="check" size={14} strokeWidth={2.4} />
                                Cocok
                            </span>
                        ) : (
                            <span className="rounded-full bg-[#FCE4E1] px-3 py-1 text-[13px] font-bold text-danger">Ada selisih</span>
                        )}
                    </div>
                    <dl className="m-0 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <ReconCell label="Buku besar Jajapo" value={rupiah(recon.ledger)} />
                        <ReconCell label="Pembayaran lunas tercatat" value={rupiah(recon.payments)} />
                        <ReconCell label="Selisih" value={rupiah(diff)} />
                    </dl>
                    <p className="m-0 text-xs text-muted">Bandingkan juga dengan laporan settlement di dashboard payment gateway sebelum menutup hari.</p>
                </PanelCard>
            </div>

            {payoutDialog && <PayoutDialog payout={payoutDialog} onClose={() => setPayoutDialog(null)} />}
        </PanelLayout>
    );
}

function GatewayLine({ gateway, dark }) {
    const connected = Boolean(gateway.last_callback) || gateway.driver === 'fake';
    return (
        <span role="status" className={`flex flex-wrap items-center gap-2 text-[12.5px] lg:text-sm ${dark ? 'text-[#D2E0D0]' : ''}`}>
            <span aria-hidden="true" className={`h-2 w-2 rounded-full ${connected ? 'bg-[#5FD07F] shadow-[0_0_0_3px_rgba(95,208,127,0.25)]' : 'bg-[#E0A93B]'}`} />
            <span className={dark ? '' : 'font-bold'}>{gateway.driver === 'fake' ? 'Gateway tiruan (mode pengembangan)' : 'Payment gateway terhubung'}</span>
            <span className={dark ? '' : 'text-muted'}>{gateway.last_callback ? `Callback terakhir masuk ${gateway.last_callback}.` : 'Belum ada callback masuk.'}</span>
        </span>
    );
}

function AttentionCard({ item, post }) {
    const kinds = {
        waiting: { tone: 'border-[#F1DDB2] bg-[#FFF8EA]', label: 'Callback belum masuk', labelCls: 'text-amber-ink' },
        late: { tone: 'border-panel-line bg-panel-soft', label: 'Bayar setelah kedaluwarsa', labelCls: 'text-[#4A564C]' },
        review: { tone: 'border-[#E5C7C2] bg-[#FDF3F1]', label: 'Nominal tidak cocok', labelCls: 'text-danger' },
    };
    const k = kinds[item.kind];
    return (
        <div className={`flex flex-col gap-2.5 rounded-[14px] border p-3 ${k.tone}`}>
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="tabular text-sm font-bold">
                    {item.code}, {rupiah(item.amount)}
                </span>
                <span className={`text-xs font-bold ${k.labelCls}`}>{k.label}</span>
            </div>
            <span className="text-[12.5px] text-body">
                {item.shop}.{item.expires_label ? ` ${item.expires_label}.` : ''}
            </span>
            <div className="flex flex-wrap gap-2">
                {item.kind === 'late' ? (
                    <>
                        <button type="button" onClick={() => post(`/admin/pesanan/${item.code}/lunasi`, `Lunasi ${item.code}? Kuota dipakai lagi kalau masih ada.`)} className="h-[38px] rounded-[10px] bg-forest px-3 text-[13px] font-semibold text-white">
                            Lunasi pesanan
                        </button>
                        <button type="button" onClick={() => post(`/admin/pesanan/${item.code}/refund`, `Catat refund ${rupiah(item.amount)} untuk ${item.code}?`)} className="h-[38px] rounded-[10px] border border-[#E5C7C2] bg-white px-3 text-[13px] font-semibold text-danger">
                            Refund ke pembeli
                        </button>
                    </>
                ) : (
                    <>
                        <button type="button" onClick={() => post(`/admin/pesanan/${item.code}/cek-status`)} className="h-[38px] rounded-[10px] border border-[#DCE3D9] bg-white px-3 text-[13px] font-semibold">
                            Cek status di gateway
                        </button>
                        <button type="button" onClick={() => post(`/admin/pesanan/${item.code}/lunasi`, `Tandai ${item.code} lunas manual? Pastikan uangnya sudah masuk.`)} className="h-[38px] rounded-[10px] bg-forest px-3 text-[13px] font-semibold text-white">
                            Tandai lunas
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}

function ReconCell({ label, value }) {
    return (
        <div className="flex flex-col gap-1 rounded-[14px] bg-panel-soft px-4 py-3.5">
            <dt className="text-[13px] text-muted">{label}</dt>
            <dd className="tabular m-0 text-[22px] font-bold">{value}</dd>
        </div>
    );
}

function PayoutDialog({ payout, onClose }) {
    const transfer = useForm({ proof: null });
    const reject = useForm({ note: '' });
    const [mode, setMode] = useState('transfer');

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center" onClick={onClose}>
            <div role="dialog" aria-modal="true" aria-label="Proses pencairan" className="w-full max-w-md rounded-t-[26px] bg-white p-5 sm:rounded-[22px]" onClick={(e) => e.stopPropagation()}>
                <div className="mb-4 flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h2 className="m-0 text-lg font-bold">
                            {payout.shop}, {rupiah(payout.amount)}
                        </h2>
                        <span className="text-[13px] text-body">
                            {payout.bank}, a.n. {payout.owner}
                        </span>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Tutup" className="flex h-10 w-10 items-center justify-center rounded-full border border-panel-line">
                        <Icon name="x" size={18} />
                    </button>
                </div>

                {mode === 'transfer' ? (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            transfer.post(`/admin/pencairan/${payout.id}/transfer`, { forceFormData: true, preserveScroll: true, onSuccess: onClose });
                        }}
                        className="flex flex-col gap-3"
                    >
                        <label className="flex flex-col gap-1.5">
                            <span className="text-[13px] font-semibold text-body">Bukti transfer (foto atau screenshot)</span>
                            <input type="file" accept="image/*" onChange={(e) => transfer.setData('proof', e.target.files[0] || null)} className="text-sm" />
                            {transfer.errors.proof && <span className="text-xs font-semibold text-danger">{transfer.errors.proof}</span>}
                        </label>
                        <button type="submit" disabled={transfer.processing} className="h-12 rounded-[14px] bg-forest text-sm font-bold text-white">
                            Sudah saya transfer
                        </button>
                        <button type="button" onClick={() => setMode('reject')} className="h-11 text-sm font-semibold text-danger">
                            Tolak pencairan ini
                        </button>
                    </form>
                ) : (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            reject.post(`/admin/pencairan/${payout.id}/tolak`, { preserveScroll: true, onSuccess: onClose });
                        }}
                        className="flex flex-col gap-3"
                    >
                        <label className="flex flex-col gap-1.5">
                            <span className="text-[13px] font-semibold text-body">Alasan penolakan</span>
                            <textarea
                                rows={3}
                                value={reject.data.note}
                                onChange={(e) => reject.setData('note', e.target.value)}
                                placeholder="Contoh: nama rekening tidak sama dengan pembuat toko"
                                className="rounded-[14px] border border-panel-line px-3.5 py-3 text-sm outline-none"
                            />
                            {reject.errors.note && <span className="text-xs font-semibold text-danger">{reject.errors.note}</span>}
                        </label>
                        <button type="submit" disabled={reject.processing} className="h-12 rounded-[14px] bg-danger text-sm font-bold text-white">
                            Tolak dan kembalikan saldo
                        </button>
                        <button type="button" onClick={() => setMode('transfer')} className="h-11 text-sm font-semibold text-body">
                            Kembali
                        </button>
                    </form>
                )}
            </div>
        </div>
    );
}
