import { Link, router } from '@inertiajs/react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import StatusPill from '@/Components/StatusPill';
import { pillLabel, rupiah } from '@/lib/format';

const COMPLAINT_LABEL = {
    open: 'Menunggu tanggapanmu',
    disputed: 'Diperiksa super admin',
    refunded: 'Refund disetujui',
    rejected: 'Ditolak',
};

export default function Order({ shop, order: o }) {
    const markReady = () => router.post('/seller/pesanan/siap', { preorder_id: o.preorder_id, code: o.code }, { preserveScroll: true });
    const handOver = () => {
        const what = o.is_delivery ? 'terkirim' : 'sudah diambil';
        if (window.confirm(`Tandai ${o.code} ${what} tanpa scan QR? Pastikan pesanan sudah diterima ${o.buyer}.`)) {
            router.post(`/seller/pesanan/${o.code}/diserahkan`, {}, { preserveScroll: true });
        }
    };

    return (
        <PanelLayout variant="seller" active="orders" title={`Pesanan ${o.code}`} shop={shop}>
            <div className="flex flex-col gap-4 px-4 pt-4 lg:max-w-[980px] lg:gap-5 lg:px-8 lg:pt-7">
                <div className="flex items-start gap-3">
                    <Link href="/seller/pesanan" aria-label="Kembali ke daftar pesanan" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-panel-line bg-white text-ink">
                        <Icon name="arrowLeft" size={20} strokeWidth={1.9} />
                    </Link>
                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="tabular m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">{o.code}</h1>
                            <StatusPill status={o.status} label={pillLabel(o)} />
                        </div>
                        <p className="m-0 text-sm text-muted">
                            {o.preorder}
                            {o.paid ? `, dibayar ${o.paid}` : ''}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    {o.can_mark_ready && (
                        <button type="button" onClick={markReady} className="flex h-11 items-center gap-2 rounded-xl bg-forest px-4 text-sm font-semibold text-white">
                            <Icon name="check" size={18} strokeWidth={2.2} />
                            Tandai siap diambil
                        </button>
                    )}
                    {o.is_delivery && ['paid', 'ready'].includes(o.status) && (
                        <Link href="/seller/antar" className="flex h-11 items-center gap-2 rounded-xl bg-forest px-4 text-sm font-semibold text-white no-underline hover:text-white">
                            <Icon name="truck" size={18} />
                            Atur pengantaran
                        </Link>
                    )}
                    {o.can_hand_over && (
                        <button type="button" onClick={handOver} className="flex h-11 items-center gap-2 rounded-xl border border-panel-line bg-white px-4 text-sm font-semibold text-ink">
                            <Icon name="scan" size={18} />
                            {o.is_delivery ? 'Tandai terkirim' : 'Tandai sudah diambil'}
                        </button>
                    )}
                    <Link
                        href={`/seller/pesanan/${o.code}/obrolan`}
                        method="post"
                        as="button"
                        className="flex h-11 items-center gap-2 rounded-xl border border-panel-line bg-white px-4 text-sm font-semibold text-ink"
                    >
                        <Icon name="chat" size={18} className="text-leaf" />
                        Chat pembeli
                    </Link>
                    {o.whatsapp_href && (
                        <a href={o.whatsapp_href} target="_blank" rel="noreferrer" className="flex h-11 items-center gap-2 rounded-xl border border-panel-line bg-white px-4 text-sm font-semibold text-ink no-underline hover:text-ink">
                            <Icon name="message" size={18} className="text-leaf" />
                            Hubungi pembeli
                        </a>
                    )}
                </div>

                {o.complaint && (
                    <Link
                        href="/seller/komplain"
                        className={`flex items-center justify-between gap-3 rounded-2xl px-4 py-3 text-sm no-underline ${o.complaint.open ? 'bg-amber-bg text-amber-ink hover:text-amber-ink' : 'bg-panel-soft text-body hover:text-body'}`}
                    >
                        <span className="flex min-w-0 flex-col gap-0.5">
                            <span className="font-bold">Komplain: {o.complaint.reason}</span>
                            <span className="text-[13px]">
                                {COMPLAINT_LABEL[o.complaint.status] || o.complaint.status}. Refund diminta {rupiah(o.complaint.amount_requested)}
                                {o.complaint.amount_approved !== null ? `, disetujui ${rupiah(o.complaint.amount_approved)}` : ''}.
                            </span>
                        </span>
                        <Icon name="chevronRight" size={18} />
                    </Link>
                )}

                <div className="grid gap-4 lg:grid-cols-[1.3fr_1fr] lg:gap-5">
                    <div className="flex flex-col gap-4 lg:gap-5">
                        <PanelCard className="overflow-hidden">
                            <h2 className="m-0 px-[18px] pb-2 pt-4 text-[17px] font-bold">Isi pesanan</h2>
                            {o.items.map((i, k) => (
                                <div key={k} className="flex items-start justify-between gap-3 border-t border-[#EDF1EB] px-[18px] py-3 text-sm">
                                    <span className="flex min-w-0 flex-col gap-0.5">
                                        <span className="font-semibold">
                                            {i.qty} {i.name}
                                        </span>
                                        {i.contents && <span className="text-[12.5px] text-muted">Isi paket: {i.contents}</span>}
                                        <span className="tabular text-[12.5px] text-muted">
                                            {i.qty} x {rupiah(i.unit_price)}
                                        </span>
                                    </span>
                                    <span className="tabular shrink-0 font-semibold">{rupiah(i.total)}</span>
                                </div>
                            ))}
                            <dl className="m-0 flex flex-col gap-1.5 border-t border-[#EDF1EB] bg-panel-soft px-[18px] py-3 text-sm">
                                <Money label="Subtotal" value={o.subtotal} />
                                {o.is_delivery && <Money label="Ongkir" value={o.delivery_fee} />}
                                <div className="flex items-center justify-between gap-3 border-t border-panel-line pt-1.5">
                                    <dt className="font-bold">Masuk ke saldo toko</dt>
                                    <dd className="tabular m-0 text-base font-bold">{rupiah(o.amount)}</dd>
                                </div>
                            </dl>
                        </PanelCard>

                        <PanelCard className="flex flex-col gap-3 p-[18px]">
                            <h2 className="m-0 text-[17px] font-bold">{o.is_delivery ? 'Pengantaran' : 'Pengambilan'}</h2>
                            <dl className="m-0 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                <Info label={o.is_delivery ? 'Antar ke' : 'Ambil di'} value={o.is_delivery ? o.place.replace(/^Antar ke /, '') : o.place} sub={o.place_note} />
                                <Info label="Jadwal" value={o.schedule || 'Belum diatur'} />
                                {o.address_note && <Info label="Alamat" value={o.address_note} wide />}
                                {o.note && <Info label="Catatan pembeli" value={o.note} wide />}
                            </dl>
                        </PanelCard>
                    </div>

                    <div className="flex flex-col gap-4 lg:gap-5">
                        <PanelCard className="flex flex-col gap-3 p-[18px]">
                            <h2 className="m-0 text-[17px] font-bold">Pembeli</h2>
                            <dl className="m-0 flex flex-col gap-3 text-sm">
                                <Info label="Nama" value={o.buyer} />
                                <Info label="WhatsApp" value={o.phone || 'Belum diisi'} />
                                <Info label="Email" value={o.email} />
                            </dl>
                        </PanelCard>

                        <PanelCard className="flex flex-col gap-3 p-[18px]">
                            <h2 className="m-0 text-[17px] font-bold">Riwayat</h2>
                            <ol className="m-0 flex list-none flex-col p-0">
                                {o.timeline.map((t, k) => (
                                    <li key={k} className="relative flex gap-3 pb-4 last:pb-0">
                                        {k < o.timeline.length - 1 && <span aria-hidden="true" className="absolute left-[5px] top-4 h-full w-px bg-panel-line" />}
                                        <span aria-hidden="true" className={`relative mt-1.5 h-[11px] w-[11px] shrink-0 rounded-full ${k === o.timeline.length - 1 ? 'bg-leaf' : 'bg-sprout'}`} />
                                        <span className="flex flex-col gap-0.5 text-sm">
                                            <span className="font-semibold">{t.label}</span>
                                            {(t.time || t.by) && (
                                                <span className="text-[12.5px] text-muted">
                                                    {t.time}
                                                    {t.by ? `${t.time ? ', oleh ' : 'Oleh '}${t.by}` : ''}
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </PanelCard>
                    </div>
                </div>
            </div>
        </PanelLayout>
    );
}

function Money({ label, value }) {
    return (
        <div className="flex items-center justify-between gap-3 text-body">
            <dt>{label}</dt>
            <dd className="tabular m-0">{rupiah(value)}</dd>
        </div>
    );
}

function Info({ label, value, sub, wide }) {
    return (
        <div className={`flex min-w-0 flex-col gap-0.5 ${wide ? 'sm:col-span-2' : ''}`}>
            <dt className="text-xs text-muted">{label}</dt>
            <dd className="m-0 break-words font-semibold">{value}</dd>
            {sub && <dd className="m-0 text-[12.5px] text-muted">{sub}</dd>}
        </div>
    );
}
