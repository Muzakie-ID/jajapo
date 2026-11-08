import { Link, useForm } from '@inertiajs/react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import StatusPill from '@/Components/StatusPill';
import { rupiah } from '@/lib/format';

export default function Balance({ shop, available, held, heldOrders, minPayout, account, payouts, ledger }) {
    const form = useForm({ amount: available >= minPayout ? String(available) : '' });
    const canRequest = available >= minPayout && account?.verified;

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({ amount: Number(String(data.amount).replace(/\D/g, '')) }));
        form.post('/seller/saldo/pencairan', {
            preserveScroll: true,
            onSuccess: (page) => {
                const left = page.props.available;
                form.setData('amount', left >= minPayout ? String(left) : '');
            },
        });
    };

    return (
        <PanelLayout variant="seller" active="balance" title="Saldo dan pencairan" shop={shop}>
            <div className="flex flex-col gap-4 px-4 pt-4 lg:gap-5 lg:px-8 lg:pt-7">
                <div className="flex items-center justify-between gap-3">
                    <Link href="/seller" aria-label="Kembali ke beranda toko" className="flex h-11 w-11 items-center justify-center rounded-full border border-panel-line bg-white text-[#141A16] lg:hidden">
                        <Icon name="arrowLeft" size={20} strokeWidth={1.9} />
                    </Link>
                    <div className="flex flex-col gap-1">
                        <h1 className="m-0 text-[17px] font-bold lg:text-[28px] lg:tracking-[-0.02em]">Saldo dan pencairan</h1>
                        <p className="m-0 hidden max-w-[640px] text-sm text-muted lg:block">
                            Uang dari pesanan lunas masuk ke saldo tertahan, lalu pindah ke saldo tersedia setelah pesanan diambil.
                        </p>
                    </div>
                    <span className="w-11 lg:hidden" />
                </div>

                <div className="flex flex-col gap-4 lg:flex-row lg:items-stretch lg:gap-5">
                    <section className="flex flex-col gap-4 rounded-[22px] bg-forest p-[18px] text-white lg:flex-[1.3] lg:p-5">
                        <div className="flex flex-col gap-1">
                            <h2 className="m-0 text-[13px] font-semibold text-[#BFD3BF]">Saldo tersedia</h2>
                            <span className="tabular text-[32px] font-bold tracking-[-0.02em]">{rupiah(available)}</span>
                        </div>
                        {shop.role !== 'admin' ? (
                            <p className="m-0 rounded-xl bg-white/10 px-3.5 py-3 text-[13px] text-[#D7E5D7]">Pencairan hanya bisa diajukan admin toko.</p>
                        ) : (
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-2.5">
                            <label className="flex flex-[1_1_180px] flex-col gap-1.5">
                                <span className="text-[12.5px] font-semibold text-[#D7E5D7]">Nominal pencairan</span>
                                <span className="flex h-[46px] items-center gap-1.5 rounded-xl bg-white px-3.5 text-sm font-semibold text-muted">
                                    Rp
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        value={form.data.amount}
                                        onChange={(e) => form.setData('amount', e.target.value.replace(/\D/g, ''))}
                                        aria-describedby="min-cair"
                                        className="tabular min-w-0 flex-1 border-0 bg-transparent text-[15px] font-bold text-[#141A16] outline-none"
                                    />
                                </span>
                            </label>
                            <button type="submit" disabled={!canRequest || form.processing} className="h-[46px] rounded-xl bg-sprout px-[18px] text-sm font-bold text-[#12301B] disabled:opacity-60">
                                Ajukan pencairan
                            </button>
                        </form>
                        )}
                        {form.errors.amount && <span className="rounded-lg bg-white px-3 py-2 text-[13px] font-semibold text-danger">{form.errors.amount}</span>}
                        <span id="min-cair" className="text-[12.5px] leading-normal text-[#D7E5D7]">
                            Minimal {rupiah(minPayout)}. Diproses paling lambat 1x24 jam hari kerja, biaya transfer ditanggung Jajapo.
                        </span>
                    </section>

                    <PanelCard className="flex flex-col gap-2 p-4 lg:flex-1 lg:p-5">
                        <div className="flex items-baseline justify-between gap-2 lg:flex-col lg:items-start">
                            <h2 className="m-0 text-[13px] font-semibold text-muted lg:text-sm">Saldo tertahan</h2>
                            <span className="tabular text-xl font-bold lg:text-[30px] lg:tracking-[-0.02em]">{rupiah(held)}</span>
                        </div>
                        <p className="m-0 text-[12.5px] leading-normal text-body lg:text-[13px]">
                            Dari {heldOrders} pesanan. Pindah ke saldo tersedia setelah pesanan diambil, atau otomatis H+1 setelah tanggal ambil kalau tidak ada komplain.
                        </p>
                    </PanelCard>

                    <PanelCard className="flex flex-col gap-3 p-4 lg:flex-1 lg:p-5">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="m-0 text-[13px] font-semibold text-muted lg:text-sm">Rekening pencairan</h2>
                            {account?.verified && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-[#DCEFD6] py-[3px] pl-[7px] pr-[9px] text-xs font-bold text-[#1F5A2D]">
                                    <Icon name="check" size={13} strokeWidth={2.4} />
                                    Terverifikasi
                                </span>
                            )}
                        </div>
                        {account ? (
                            <div className="flex items-center gap-3">
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#F0F3EE] text-forest">
                                    <Icon name="bank" size={22} />
                                </span>
                                <span className="flex flex-col gap-0.5">
                                    <span className="tabular text-[15px] font-bold">{account.label}</span>
                                    <span className="text-[13px] text-body">a.n. {account.name}</span>
                                </span>
                            </div>
                        ) : (
                            <p className="m-0 text-sm text-muted">Belum ada rekening.</p>
                        )}
                        <p className="m-0 text-[12.5px] leading-normal text-muted">
                            Rekening harus atas nama pembuat toko. Ganti rekening perlu disetujui ulang super admin.
                            {shop.role === 'admin' && (
                                <>
                                    {' '}
                                    <Link href="/seller/pengaturan" className="font-semibold text-leaf">
                                        Ganti rekening
                                    </Link>
                                </>
                            )}
                        </p>
                    </PanelCard>
                </div>

                <PanelCard className="overflow-hidden">
                    <div className="flex items-baseline justify-between gap-3 px-[18px] py-4">
                        <h2 className="m-0 text-[17px] font-bold">Riwayat pencairan</h2>
                        <span className="text-[13px] text-muted">{payouts.length} pengajuan</span>
                    </div>
                    {payouts.length === 0 && <p className="m-0 border-t border-[#EDF1EB] px-[18px] py-4 text-sm text-muted">Belum ada pencairan.</p>}
                    {payouts.map((p) => (
                        <div key={p.id} className="flex flex-wrap items-center justify-between gap-3 border-t border-[#EDF1EB] px-[18px] py-3">
                            <span className="flex flex-col gap-0.5">
                                <span className="tabular text-[15px] font-bold">{rupiah(p.amount)}</span>
                                <span className="text-xs text-muted">
                                    {p.date}, {p.account}
                                </span>
                                {p.note && <span className="text-xs text-danger">{p.note}</span>}
                            </span>
                            <span className="flex flex-col items-end gap-1">
                                <StatusPill status={p.status} />
                                {p.proof_url ? (
                                    <a href={p.proof_url} target="_blank" rel="noopener" className="text-[12.5px] font-semibold no-underline">
                                        Lihat bukti
                                    </a>
                                ) : p.status === 'requested' ? (
                                    <span className="text-[12.5px] text-muted">Menunggu transfer</span>
                                ) : null}
                            </span>
                        </div>
                    ))}
                </PanelCard>

                <PanelCard className="overflow-hidden">
                    <div className="flex flex-wrap items-baseline justify-between gap-3 px-[18px] py-4">
                        <h2 className="m-0 text-[17px] font-bold">Mutasi saldo</h2>
                        <span className="text-[13px] text-muted">Setiap uang masuk dan keluar tercatat satu baris</span>
                    </div>
                    {ledger.map((l, i) => (
                        <div key={i} className="flex items-center justify-between gap-3 border-t border-[#EDF1EB] px-[18px] py-3 text-[13.5px]">
                            <span className="flex min-w-0 flex-col gap-0.5">
                                <span className="truncate">{l.desc}</span>
                                <span className="text-xs text-muted">
                                    {l.time}, saldo {l.bucket.toLowerCase()}
                                </span>
                            </span>
                            <span className={`tabular shrink-0 font-bold ${l.amount > 0 ? 'text-[#1F5A2D]' : ''}`}>
                                {l.amount > 0 ? '+' : ''}
                                {rupiah(l.amount)}
                            </span>
                        </div>
                    ))}
                </PanelCard>
            </div>
        </PanelLayout>
    );
}
