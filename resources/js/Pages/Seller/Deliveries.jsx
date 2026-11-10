import { router } from '@inertiajs/react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import StatusPill from '@/Components/StatusPill';
import { rupiah } from '@/lib/format';

const READY = ['paid', 'ready'];

export default function Deliveries({ shop, preorders, selected, zones }) {
    const start = (payload, message) => {
        if (window.confirm(message)) router.post('/seller/antar/mulai', { preorder_id: selected.id, ...payload }, { preserveScroll: true });
    };
    const delivered = (o) => {
        if (window.confirm(`Tandai ${o.code} terkirim tanpa scan QR? Pastikan pesanan sudah diterima ${o.buyer}.`)) {
            router.post(`/seller/antar/${o.code}/terkirim`, {}, { preserveScroll: true });
        }
    };
    const total = zones.reduce((n, z) => n + z.orders.length, 0);
    const done = zones.reduce((n, z) => n + z.orders.filter((o) => o.status === 'delivered').length, 0);

    return (
        <PanelLayout variant="seller" active="deliveries" title="Pengantaran" shop={shop}>
            <div className="flex flex-col gap-4 px-4 pt-4 lg:gap-5 lg:px-8 lg:pt-7">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Pengantaran</h1>
                        <p className="m-0 text-sm text-muted">
                            {selected ? `${selected.schedule ? `Jadwal antar ${selected.schedule}. ` : ''}Scan QR pembeli saat pesanan sampai.` : 'Belum ada PO dengan pengantaran.'}
                        </p>
                    </div>
                    {preorders.length > 1 && (
                        <select
                            aria-label="Pilih PO"
                            value={selected?.id ?? ''}
                            onChange={(e) => router.get('/seller/antar', { po: e.target.value }, { preserveState: false })}
                            className="h-11 rounded-xl border border-[#DCE3D9] bg-white px-3 text-sm font-semibold"
                        >
                            {preorders.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.title}
                                </option>
                            ))}
                        </select>
                    )}
                </div>

                {selected && (
                    <div className="flex items-center gap-3 rounded-[18px] bg-forest px-4 py-3.5 text-white">
                        <Icon name="truck" size={24} />
                        <span className="flex flex-1 flex-col">
                            <span className="text-[13px] text-[#CFE3CB]">Terkirim</span>
                            <span className="tabular text-xl font-bold">
                                {done} dari {total} pesanan
                            </span>
                        </span>
                        <a href="/seller/scan" className="flex h-11 items-center gap-2 rounded-xl bg-sprout px-4 text-sm font-bold text-[#12301B] no-underline">
                            <Icon name="scan" size={18} />
                            Scan
                        </a>
                    </div>
                )}

                {selected && zones.length === 0 && <p className="m-0 text-sm text-muted">PO ini belum punya area antar.</p>}

                <div className="grid gap-4 lg:grid-cols-2">
                    {zones.map((z) => {
                        const ready = z.orders.filter((o) => READY.includes(o.status));
                        return (
                            <PanelCard key={z.id} className="flex flex-col p-4">
                                <div className="flex items-center justify-between gap-3 pb-3">
                                    <div className="flex flex-col">
                                        <h2 className="m-0 text-base font-bold">{z.name}</h2>
                                        <span className="text-xs text-muted">
                                            {z.orders.length} pesanan, ongkir {rupiah(z.fee)}
                                        </span>
                                    </div>
                                    {ready.length > 0 && (
                                        <button
                                            type="button"
                                            onClick={() => start({ zone_id: z.id }, `Mulai antar ${ready.length} pesanan ke ${z.name}? Pembeli akan diberi tahu.`)}
                                            className="flex h-10 items-center gap-1.5 rounded-xl bg-forest px-3.5 text-[13px] font-semibold text-white"
                                        >
                                            <Icon name="truck" size={16} />
                                            Mulai antar ({ready.length})
                                        </button>
                                    )}
                                </div>
                                {z.orders.length === 0 && <p className="m-0 border-t border-[#EDF1EB] pt-3 text-sm text-muted">Belum ada pesanan ke area ini.</p>}
                                {z.orders.map((o) => (
                                    <div key={o.code} className="flex flex-col gap-2 border-t border-[#EDF1EB] py-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="flex min-w-0 flex-col gap-px">
                                                <span className="text-sm font-bold">
                                                    {o.buyer} <span className="font-medium text-muted">· {o.code}</span>
                                                </span>
                                                <span className="text-[13px] text-body">{o.items}</span>
                                            </span>
                                            <StatusPill status={o.status} />
                                        </div>
                                        <p className="m-0 flex items-start gap-1.5 rounded-xl bg-[#F6F8F5] px-3 py-2 text-[13px]">
                                            <Icon name="pin" size={16} className="mt-px shrink-0 text-leaf" />
                                            <span>
                                                {o.address || 'Alamat belum diisi'}
                                                {o.note && <span className="block text-xs text-muted">Catatan: {o.note}</span>}
                                            </span>
                                        </p>
                                        {o.status === 'out_for_delivery' && o.courier && (
                                            <span className="text-xs text-muted">
                                                Diantar {o.courier} sejak {o.out_at}
                                            </span>
                                        )}
                                        {o.status !== 'delivered' && (
                                            <div className="flex gap-2">
                                                {o.whatsapp_href && (
                                                    <a
                                                        href={o.whatsapp_href}
                                                        target="_blank"
                                                        rel="noopener"
                                                        className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-[#DCE3D9] bg-white text-[13px] font-semibold text-ink no-underline"
                                                    >
                                                        <Icon name="message" size={16} className="text-leaf" />
                                                        WhatsApp
                                                    </a>
                                                )}
                                                {READY.includes(o.status) ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => start({ code: o.code }, `Mulai antar ${o.code} ke ${o.buyer}?`)}
                                                        className="h-10 flex-1 rounded-xl bg-forest text-[13px] font-semibold text-white"
                                                    >
                                                        Mulai antar
                                                    </button>
                                                ) : (
                                                    <button type="button" onClick={() => delivered(o)} className="h-10 flex-1 rounded-xl border border-[#DCE3D9] bg-white text-[13px] font-semibold">
                                                        Tandai terkirim
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </PanelCard>
                        );
                    })}
                </div>
            </div>
        </PanelLayout>
    );
}
