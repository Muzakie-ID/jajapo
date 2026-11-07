import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '@/Components/Icon';
import { rupiah } from '@/lib/format';

/** Laporan pertanggungjawaban PO. Tampil sebagai kertas A4; tombol Cetak memakai dialog cetak browser (bisa simpan PDF). */
export default function Lpj({ report: r, preparer }) {
    const t = r.totals;
    const [signers, setSigners] = useState({ ketua: '', bendahara: preparer });

    return (
        <div className="min-h-screen bg-[#E9EEE7] py-0 text-[#141A16] sm:py-6 print:bg-white print:py-0">
            <Head title={`LPJ ${r.title}`} />
            <style>{'@page { size: A4; margin: 14mm; } @media print { html, body { background: #fff !important; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }'}</style>

            <div className="mx-auto flex max-w-[820px] flex-wrap items-center gap-2 px-4 pb-4 pt-4 sm:px-0 sm:pt-0 print:hidden">
                <Link href="/seller/po" className="flex h-11 items-center gap-2 rounded-xl border border-[#D3DDD0] bg-white px-4 text-sm font-semibold text-ink no-underline">
                    <Icon name="arrowLeft" size={18} />
                    Kembali
                </Link>
                <span className="flex-1" />
                <a href={`/seller/po/${r.id}/ekspor`} className="flex h-11 items-center gap-2 rounded-xl border border-[#D3DDD0] bg-white px-4 text-sm font-semibold text-ink no-underline">
                    <Icon name="download" size={18} />
                    Unduh Excel
                </a>
                <button type="button" onClick={() => window.print()} className="flex h-11 items-center gap-2 rounded-xl bg-forest px-4 text-sm font-semibold text-white">
                    <Icon name="receipt" size={18} />
                    Cetak atau simpan PDF
                </button>
            </div>

            <article className="mx-auto flex max-w-[820px] flex-col gap-6 bg-white px-5 py-7 text-[13px] leading-[1.5] shadow-[0_8px_30px_rgba(20,26,22,0.08)] sm:px-12 sm:py-10 print:max-w-none print:p-0 print:shadow-none">
                <header className="flex flex-col gap-1 border-b-2 border-forest pb-4">
                    <span className="text-xs font-semibold uppercase tracking-[0.08em] text-leaf">Laporan pertanggungjawaban penjualan</span>
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.01em]">{r.title}</h1>
                    <span className="text-sm text-body">{r.shop_name}</span>
                    <span className="text-xs text-muted">
                        Periode PO {r.period}. Dibuat {r.printed_at} dari data Jajapo.
                    </span>
                </header>

                <section className="flex flex-col gap-3">
                    <h2 className="m-0 text-[15px] font-bold">1. Ringkasan</h2>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <Stat label="Pesanan lunas" value={t.orders} />
                        <Stat label="Pembeli" value={t.buyers} />
                        <Stat label="Porsi terjual" value={t.portions} />
                        <Stat label="Pendapatan bersih" value={rupiah(t.net)} strong />
                    </div>
                    <table className="w-full border-collapse">
                        <tbody>
                            <Line label="Penjualan menu" value={rupiah(t.menu)} />
                            <Line label="Ongkir" value={rupiah(t.delivery)} />
                            <Line label={`Refund komplain (${t.refunded_orders} pesanan)`} value={t.refund ? `- ${rupiah(t.refund)}` : rupiah(0)} />
                            <Line label="Pendapatan bersih toko" value={rupiah(t.net)} strong />
                        </tbody>
                    </table>
                    <p className="m-0 text-xs text-muted">
                        Posisi dana: {rupiah(t.available)} sudah di saldo tersedia toko, {rupiah(t.held)} masih tertahan sampai pesanan diambil, H+1, atau komplain selesai. Kode unik pembayaran adalah biaya platform dan tidak termasuk pendapatan toko.
                    </p>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="m-0 text-[15px] font-bold">2. Penjualan per menu</h2>
                    <Table
                        head={['Menu dan varian', 'Harga', 'Porsi', 'Total']}
                        align={['left', 'right', 'right', 'right']}
                        rows={r.variants.map((v) => [v.name, rupiah(v.price), v.qty, rupiah(v.total)])}
                        foot={['Total', '', t.portions, rupiah(t.menu)]}
                    />
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="m-0 text-[15px] font-bold">3. Pengambilan dan pengantaran</h2>
                    <Table
                        head={['Titik ambil / area antar', 'Waktu', 'Pesanan', 'Porsi']}
                        align={['left', 'left', 'right', 'right']}
                        rows={r.places.map((p) => [`${p.kind}: ${p.place}`, p.time, p.orders, p.portions])}
                    />
                    <p className="m-0 text-xs text-muted">
                        Diambil atau terkirim {t.picked_up} pesanan, belum diambil {t.waiting}, tidak diambil {t.not_picked_up}.
                    </p>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="m-0 text-[15px] font-bold">4. Pesanan per hari</h2>
                    <Table head={['Tanggal bayar', 'Pesanan', 'Nilai untuk toko']} align={['left', 'right', 'right']} rows={r.days.map((d) => [d.date, d.orders, rupiah(d.amount)])} />
                </section>

                <section className="flex flex-col gap-3 print:break-before-page">
                    <h2 className="m-0 text-[15px] font-bold">5. Daftar transaksi</h2>
                    <Table
                        small
                        head={['Kode', 'Dibayar', 'Pembeli', 'Pesanan', 'Subtotal', 'Ongkir', 'Refund']}
                        align={['left', 'left', 'left', 'left', 'right', 'right', 'right']}
                        rows={r.orders.map((o) => [o.code, o.paid_at, o.buyer, o.items, rupiah(o.subtotal), rupiah(o.delivery_fee), o.refund ? rupiah(o.refund) : '-'])}
                        foot={['', '', '', `${t.orders} pesanan`, rupiah(t.menu), rupiah(t.delivery), rupiah(t.refund)]}
                    />
                </section>

                <section className="grid grid-cols-2 gap-8 pt-4 text-center" style={{ breakInside: 'avoid' }}>
                    <Sign title="Mengetahui, Ketua" value={signers.ketua} onChange={(v) => setSigners((s) => ({ ...s, ketua: v }))} />
                    <Sign title="Disusun oleh, Bendahara" value={signers.bendahara} onChange={(v) => setSigners((s) => ({ ...s, bendahara: v }))} />
                </section>
            </article>
        </div>
    );
}

function Stat({ label, value, strong }) {
    return (
        <div className={`flex flex-col gap-0.5 rounded-xl px-3 py-2.5 ${strong ? 'bg-forest text-white' : 'bg-[#F3F6F1]'}`}>
            <span className={`text-[11.5px] ${strong ? 'text-[#CFE3CB]' : 'text-muted'}`}>{label}</span>
            <span className="tabular text-[17px] font-bold">{value}</span>
        </div>
    );
}

function Line({ label, value, strong }) {
    return (
        <tr className={strong ? 'border-t-2 border-[#141A16] font-bold' : 'border-b border-[#E3E9E0]'}>
            <td className="py-1.5">{label}</td>
            <td className="tabular py-1.5 text-right">{value}</td>
        </tr>
    );
}

function Table({ head, rows, foot, align, small }) {
    const cls = (i) => (align?.[i] === 'right' ? 'text-right tabular' : 'text-left');
    return (
        <div className="-mx-1 overflow-x-auto px-1 print:overflow-visible">
            <table className={`w-full border-collapse ${small ? 'text-[11.5px]' : ''}`}>
                <thead>
                    <tr className="bg-[#F3F6F1]">
                        {head.map((h, i) => (
                            <th key={h + i} className={`whitespace-nowrap px-2 py-1.5 font-semibold ${cls(i)}`}>
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={head.length} className="px-2 py-3 text-center text-muted">
                                Belum ada data.
                            </td>
                        </tr>
                    )}
                    {rows.map((row, ri) => (
                        <tr key={ri} className="border-b border-[#E3E9E0]" style={{ breakInside: 'avoid' }}>
                            {row.map((cell, i) => (
                                <td key={i} className={`px-2 py-1.5 align-top ${cls(i)}`}>
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
                {foot && (
                    <tfoot>
                        <tr className="border-t-2 border-[#141A16] font-bold">
                            {foot.map((cell, i) => (
                                <td key={i} className={`px-2 py-1.5 ${cls(i)}`}>
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    </tfoot>
                )}
            </table>
        </div>
    );
}

function Sign({ title, value, onChange }) {
    return (
        <div className="flex flex-col items-center gap-14">
            <span>{title}</span>
            <label className="flex w-full flex-col items-center gap-1">
                <input
                    aria-label={`Nama ${title.split(', ')[1]}`}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="Tulis nama"
                    className="w-full max-w-[220px] border-0 border-b border-[#141A16] bg-transparent pb-1 text-center text-[13px] font-semibold outline-none placeholder:text-[#B6C2B6] print:placeholder:text-transparent"
                />
            </label>
        </div>
    );
}
