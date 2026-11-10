import { Link } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import BuyerLayout, { TopBar } from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';
import StatusPill from '@/Components/StatusPill';
import { rupiah } from '@/lib/format';

const DONE = ['picked_up', 'delivered'];

export default function Ticket({ order }) {
    const done = DONE.includes(order.status);
    const paid = ['paid', 'ready', 'out_for_delivery', 'not_picked_up', ...DONE].includes(order.status);
    const onTheWay = order.status === 'out_for_delivery';
    const ready = ['ready', 'out_for_delivery', 'not_picked_up', ...DONE].includes(order.status);
    const steps = order.is_delivery
        ? [
              { label: 'Dibayar', on: paid },
              { label: 'Disiapkan', on: paid, current: paid && !ready },
              { label: 'Siap diantar', on: ready, current: ready && !done && !onTheWay },
              { label: 'Diantar', on: onTheWay || done, current: onTheWay },
              { label: 'Terkirim', on: done },
          ]
        : [
              { label: 'Dibayar', on: paid },
              { label: 'Disiapkan', on: paid, current: paid && !ready },
              { label: 'Siap diambil', on: ready, current: ready && !done },
              { label: 'Diambil', on: done },
          ];

    return (
        <BuyerLayout title={`Tiket ${order.code}`} active="orders">
            <TopBar back="/pesanan" title="Tiket pesanan" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2.5">
                    {order.status === 'ready' ? (
                        <span className="inline-flex items-center gap-[5px] rounded-full bg-[#DCEFD6] py-1.5 pl-[9px] pr-3 text-[13px] font-bold text-[#1F5A2D]">
                            <Icon name="check" size={15} strokeWidth={2.4} />
                            {order.is_delivery ? 'Siap diantar' : 'Siap diambil'}
                        </span>
                    ) : (
                        <StatusPill status={order.status} />
                    )}
                    <span className="text-[13px] text-body">
                        {done
                            ? 'Pesanan sudah kamu terima'
                            : onTheWay
                              ? `${order.courier || 'Kurir'} sedang mengantar sejak ${order.out_label}`
                              : ready
                                ? `${order.is_delivery ? 'Diantar' : 'Bisa diambil'} ${order.ready_label}`
                                : `Penjual sedang menyiapkan pesananmu. ${order.is_delivery ? 'Diantar' : 'Diambil'} ${order.ready_label}`}
                    </span>
                </div>

                <section aria-label="Tiket QR" className="overflow-hidden rounded-3xl border border-line bg-white">
                    <div className="flex flex-col gap-1 bg-forest px-5 pb-5 pt-[18px]">
                        <span className="text-xs text-[#BFD3BF]">{order.shop_name}</span>
                        <span className="text-[19px] font-bold text-white">{order.title}</span>
                        <span className="text-[13px] text-[#D7E5D7]">{order.items}</span>
                    </div>
                    <div className="relative h-0">
                        <span className="absolute -left-[13px] -top-[13px] h-[26px] w-[26px] rounded-full bg-mint" />
                        <span className="absolute -right-[13px] -top-[13px] h-[26px] w-[26px] rounded-full bg-mint" />
                    </div>
                    <div className="flex flex-col items-center gap-2.5 px-5 pb-[18px] pt-5">
                        <div className={done ? 'opacity-30' : ''}>
                            <QRCodeSVG value={order.ticket_token} size={164} fgColor="#18241B" level="M" title={`QR tiket pengambilan ${order.code}`} />
                        </div>
                        <span className="tabular text-[17px] font-extrabold tracking-[0.08em]">{order.code}</span>
                        <span className="text-center text-[12.5px] text-muted">
                            {done
                                ? 'Tiket ini sudah dipakai.'
                                : order.is_delivery
                                  ? 'Tunjukkan QR ini ke kurir panitia saat pesanan sampai.'
                                  : 'Tunjukkan QR ini ke panitia saat mengambil pesanan.'}
                        </span>
                        <div className="my-1 w-full border-t-2 border-dashed border-line" />
                        <dl className="m-0 grid w-full grid-cols-2 gap-x-4 gap-y-3">
                            <Info label={order.is_delivery ? 'Diantar ke' : 'Ambil di'} value={order.place_note ? `${order.place}, ${order.place_note}` : order.place} />
                            <Info label={order.is_delivery ? 'Jadwal antar' : 'Waktu'} value={order.time_label} />
                            <Info label="Total dibayar" value={rupiah(order.total)} tabular />
                            <Info label="Dibayar" value={order.paid_label} />
                        </dl>
                    </div>
                </section>

                <ol aria-label="Status pesanan" className={`m-0 grid list-none gap-2 p-0 ${steps.length === 4 ? 'grid-cols-4' : 'grid-cols-3'}`}>
                    {steps.map((s) => (
                        <li key={s.label} aria-current={s.current ? 'step' : undefined} className="flex flex-col gap-1.5">
                            <span className={`h-1 rounded-full ${s.on ? 'bg-leaf' : 'bg-[#D3DDD0]'}`} />
                            <span className={`text-[12.5px] ${s.on ? 'font-bold' : 'font-semibold text-muted'}`}>{s.label}</span>
                        </li>
                    ))}
                </ol>

                <div className="flex flex-col gap-2.5">
                    {order.whatsapp_href && (
                        <a
                            href={order.whatsapp_href}
                            target="_blank"
                            rel="noreferrer"
                            className="flex h-[50px] items-center justify-center gap-2 rounded-2xl border border-line bg-white text-sm font-bold text-ink no-underline hover:text-ink"
                        >
                            <Icon name="message" size={20} className="text-leaf" />
                            Hubungi penjual lewat WhatsApp
                        </a>
                    )}
                    {order.complaint ? (
                        <ComplaintCard complaint={order.complaint} />
                    ) : (
                        order.can_complain && (
                            <div className="flex flex-col gap-2">
                                <Link
                                    href={`/pesanan/${order.code}/komplain`}
                                    className="flex h-[50px] items-center justify-center gap-2 rounded-2xl border border-[#E5C7C2] bg-white text-sm font-bold text-danger no-underline hover:text-danger"
                                >
                                    <Icon name="flag" size={19} />
                                    Ajukan komplain
                                </Link>
                                <p className="m-0 text-center text-xs text-muted">Pesanan bermasalah? Komplain bisa diajukan sampai {order.complaint_until}.</p>
                            </div>
                        )
                    )}
                </div>
            </div>
        </BuyerLayout>
    );
}

function Info({ label, value, tabular }) {
    return (
        <div className="flex flex-col gap-0.5">
            <dt className="text-xs text-muted">{label}</dt>
            <dd className={`m-0 text-sm font-bold ${tabular ? 'tabular' : ''}`}>{value}</dd>
        </div>
    );
}

const COMPLAINT_STEP = {
    open: { title: 'Menunggu tanggapan penjual', text: 'Penjual punya waktu 24 jam. Setelah itu super admin ikut memeriksa.', tone: 'amber' },
    disputed: { title: 'Diperiksa super admin', text: 'Penjual tidak menyetujui refund. Super admin akan memutuskan.', tone: 'amber' },
    refunded: { title: 'Refund disetujui', text: null, tone: 'green' },
    rejected: { title: 'Komplain ditolak', text: null, tone: 'grey' },
};

function ComplaintCard({ complaint: c }) {
    const step = COMPLAINT_STEP[c.status];
    const tone = { amber: 'bg-amber-bg text-amber-ink', green: 'bg-[#DCEFD6] text-[#1F5A2D]', grey: 'bg-[#ECEFEA] text-[#4A564C]' }[step.tone];

    return (
        <section aria-label="Komplain" className="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="flex flex-col gap-0.5">
                    <span className="text-xs text-muted">Komplain, {c.created_label}</span>
                    <span className="text-[15px] font-bold">{c.reason}</span>
                </div>
                <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-bold ${tone}`}>{step.title}</span>
            </div>
            <p className="m-0 text-[13px] leading-[1.5] text-body">{c.description}</p>
            <dl className="m-0 grid grid-cols-2 gap-3 text-[13px]">
                <div className="flex flex-col gap-0.5">
                    <dt className="text-xs text-muted">Refund diminta</dt>
                    <dd className="tabular m-0 font-bold">{rupiah(c.amount_requested)}</dd>
                </div>
                {c.amount_approved !== null && (
                    <div className="flex flex-col gap-0.5">
                        <dt className="text-xs text-muted">Refund disetujui</dt>
                        <dd className="tabular m-0 font-bold">{rupiah(c.amount_approved)}</dd>
                    </div>
                )}
            </dl>
            {step.text && <p className="m-0 text-xs text-muted">{step.text}</p>}
            {c.seller_response && c.status !== 'refunded' && (
                <p className="m-0 rounded-xl bg-[#F6F8F5] px-3 py-2.5 text-[13px] text-body">
                    <span className="font-semibold text-ink">Tanggapan penjual: </span>
                    {c.seller_response}
                </p>
            )}
            {c.decision_note && c.status === 'rejected' && (
                <p className="m-0 rounded-xl bg-[#F6F8F5] px-3 py-2.5 text-[13px] text-body">
                    <span className="font-semibold text-ink">Alasan: </span>
                    {c.decision_note}
                </p>
            )}
            {c.status === 'refunded' && (
                <p className="m-0 rounded-xl bg-[#F3F8F1] px-3 py-2.5 text-[13px] text-forest">
                    {c.refund_status === 'done' ? `Refund sudah ditransfer ke ${c.refund_to}.` : `Refund akan ditransfer ke ${c.refund_to}, paling lambat 1x24 jam hari kerja.`}
                </p>
            )}
        </section>
    );
}
