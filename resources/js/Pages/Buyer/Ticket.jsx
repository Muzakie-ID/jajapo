import { QRCodeSVG } from 'qrcode.react';
import BuyerLayout, { TopBar } from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';
import StatusPill from '@/Components/StatusPill';
import { rupiah } from '@/lib/format';

const DONE = ['picked_up', 'delivered'];

export default function Ticket({ order }) {
    const done = DONE.includes(order.status);
    const paid = ['paid', 'preparing', 'ready', 'out_for_delivery', 'not_picked_up', ...DONE].includes(order.status);
    const steps = [
        { label: 'Dibayar', on: paid },
        { label: order.is_delivery ? 'Siap diantar' : 'Siap diambil', on: paid, current: paid && !done },
        { label: order.is_delivery ? 'Terkirim' : 'Diambil', on: done },
    ];

    return (
        <BuyerLayout title={`Tiket ${order.code}`} active="orders">
            <TopBar back="/pesanan" title="Tiket pesanan" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2.5">
                    {order.status === 'paid' ? (
                        <span className="inline-flex items-center gap-[5px] rounded-full bg-[#DCEFD6] py-1.5 pl-[9px] pr-3 text-[13px] font-bold text-[#1F5A2D]">
                            <Icon name="check" size={15} strokeWidth={2.4} />
                            Lunas
                        </span>
                    ) : (
                        <StatusPill status={order.status} />
                    )}
                    <span className="text-[13px] text-body">
                        {done ? 'Pesanan sudah kamu terima' : `${order.is_delivery ? 'Diantar' : 'Siap diambil'} ${order.ready_label}`}
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

                <ol aria-label="Status pesanan" className="m-0 grid list-none grid-cols-3 gap-2 p-0">
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
                            rel="noopener"
                            className="flex h-[50px] items-center justify-center gap-2 rounded-2xl border border-line bg-white text-sm font-bold text-ink no-underline hover:text-ink"
                        >
                            <Icon name="message" size={20} className="text-leaf" />
                            Hubungi penjual lewat WhatsApp
                        </a>
                    )}
                    {!done && order.complaint_until && (
                        <p className="m-0 text-xs text-muted">Ada masalah dengan pesanan? Hubungi penjual atau ajukan komplain sampai {order.complaint_until}.</p>
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
