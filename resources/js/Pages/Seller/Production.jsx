import { Link, router } from '@inertiajs/react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';

export default function Production({ shop, preorders, po }) {
    const max = Math.max(1, ...(po?.menus ?? []).map((m) => m.total));
    const markPlaceReady = (p) => {
        if (window.confirm(`Tandai ${p.waiting_orders} pesanan di ${p.place} siap diambil? Pembelinya langsung diberi tahu.`)) {
            router.post('/seller/pesanan/siap', { preorder_id: po.id, slot_id: p.slot_id }, { preserveScroll: true });
        }
    };

    return (
        <PanelLayout variant="seller" active="rekap" title="Rekap produksi" shop={shop}>
            <div className="flex flex-col gap-4 px-4 pt-4 lg:gap-5 lg:px-8 lg:pt-7">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Rekap produksi</h1>
                        <p className="m-0 text-sm text-muted">Berapa porsi tiap menu yang harus dibuat dan dibawa ke mana. Paket dihitung per menu isinya.</p>
                    </div>
                    {preorders.length > 0 && (
                        <select
                            aria-label="Pilih PO"
                            value={po?.id ?? ''}
                            onChange={(e) => router.get('/seller/rekap', { po: e.target.value }, { preserveScroll: true })}
                            className="h-11 w-full cursor-pointer rounded-xl border border-panel-line bg-white px-3 text-sm text-ink outline-none sm:w-auto"
                        >
                            {preorders.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.title}
                                    {p.open ? ' (sedang buka)' : ''}
                                </option>
                            ))}
                        </select>
                    )}
                </div>

                {!po ? (
                    <PanelCard className="flex flex-col items-center gap-2 p-8 text-center">
                        <Icon name="chart" size={26} className="text-leaf" />
                        <span className="text-[15px] font-bold">Belum ada PO</span>
                        <span className="text-[13px] text-muted">Rekap muncul setelah kamu menerbitkan PO dan ada pesanan yang dibayar.</span>
                    </PanelCard>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Stat label="Porsi harus dibuat" value={po.summary.portions} sub={`dari ${po.summary.orders} pesanan lunas`} strong />
                            <Stat label="Belum disiapkan" value={po.summary.waiting_orders} unit="pesanan" warn={po.summary.waiting_orders > 0} />
                            <Stat label="Siap atau sedang diantar" value={po.summary.ready_orders} unit="pesanan" />
                            <Stat label="Sudah diserahkan" value={po.summary.handed_orders} unit="pesanan" />
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[1.1fr_1fr] lg:gap-5">
                            <PanelCard className="overflow-hidden lg:self-start">
                                <div className="flex flex-wrap items-center justify-between gap-2 px-[18px] pb-3 pt-4">
                                    <h2 className="m-0 text-[17px] font-bold">Per menu</h2>
                                    <span className="text-[13px] text-muted">{po.open ? `PO tutup ${po.closes}, angka masih bisa bertambah` : `PO tutup ${po.closes}`}</span>
                                </div>
                                {po.menus.length === 0 && <p className="m-0 border-t border-[#EDF1EB] px-[18px] py-4 text-sm text-muted">Belum ada pesanan yang dibayar.</p>}
                                {po.menus.map((m) => (
                                    <div key={m.name} className="flex flex-col gap-2 border-t border-[#EDF1EB] px-[18px] py-3.5">
                                        <div className="flex items-baseline justify-between gap-3">
                                            <span className="text-[15px] font-semibold">{m.name}</span>
                                            <span className="tabular shrink-0 text-lg font-bold">{m.total} porsi</span>
                                        </div>
                                        <span className="block h-2 overflow-hidden rounded-full bg-[#EDF1EB]">
                                            <span className="block h-full rounded-full bg-[#8FBF86]" style={{ width: `${Math.round((m.total / max) * 100)}%` }} />
                                        </span>
                                        <span className="flex flex-wrap gap-x-4 gap-y-1 text-[12.5px] text-muted">
                                            <span className={m.waiting > 0 ? 'font-semibold text-amber-ink' : ''}>{m.waiting} belum disiapkan</span>
                                            <span>{m.ready} siap</span>
                                            <span>{m.handed} sudah diserahkan</span>
                                        </span>
                                    </div>
                                ))}
                                <div className="flex gap-2 border-t border-[#EDF1EB] px-[18px] py-3.5">
                                    <a href={`/seller/po/${po.id}/ekspor`} className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-panel-line bg-white text-[13px] font-semibold text-ink no-underline hover:text-ink">
                                        <Icon name="download" size={16} />
                                        Unduh Excel
                                    </a>
                                    <Link href={`/seller/po/${po.id}/lpj`} className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-panel-line bg-white text-[13px] font-semibold text-ink no-underline hover:text-ink">
                                        <Icon name="receipt" size={16} />
                                        Laporan LPJ
                                    </Link>
                                </div>
                            </PanelCard>

                            <div className="flex flex-col gap-3">
                                <h2 className="m-0 px-1 text-[17px] font-bold">Per titik ambil dan area antar</h2>
                                {po.places.length === 0 && <PanelCard className="p-4 text-sm text-muted">PO ini belum punya titik ambil atau pesanan antar.</PanelCard>}
                                {po.places.map((p, k) => (
                                    <PanelCard key={k} className="flex flex-col gap-3 p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="flex min-w-0 flex-col gap-0.5">
                                                <span className="flex items-center gap-1.5 text-[15px] font-bold">
                                                    <Icon name={p.kind === 'antar' ? 'truck' : 'pin'} size={17} className="shrink-0 text-leaf" />
                                                    {p.place}
                                                </span>
                                                {p.note && <span className="text-[12.5px] text-muted">{p.note}</span>}
                                                <span className="text-[12.5px] text-muted">{p.time}</span>
                                            </span>
                                            <span className="flex shrink-0 flex-col items-end">
                                                <span className="tabular text-lg font-bold">{p.portions} porsi</span>
                                                <span className="text-xs text-muted">{p.orders} pesanan</span>
                                            </span>
                                        </div>
                                        {p.menus.length > 0 && (
                                            <ul className="m-0 flex list-none flex-col gap-1 rounded-xl bg-panel-soft px-3 py-2.5 text-[13.5px]">
                                                {p.menus.map((m) => (
                                                    <li key={m.name} className="flex justify-between gap-3">
                                                        <span>{m.name}</span>
                                                        <span className="tabular font-semibold">{m.qty}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        {p.kind === 'ambil' && p.waiting_orders > 0 && (
                                            <button type="button" onClick={() => markPlaceReady(p)} className="flex h-10 items-center justify-center gap-1.5 rounded-xl bg-forest text-[13px] font-semibold text-white">
                                                <Icon name="check" size={16} strokeWidth={2.2} />
                                                Tandai {p.waiting_orders} pesanan siap diambil
                                            </button>
                                        )}
                                        {p.kind === 'antar' && p.orders > 0 && (
                                            <Link href="/seller/antar" className="flex h-10 items-center justify-center gap-1.5 rounded-xl border border-panel-line bg-white text-[13px] font-semibold text-ink no-underline hover:text-ink">
                                                <Icon name="truck" size={16} />
                                                Atur pengantaran
                                            </Link>
                                        )}
                                    </PanelCard>
                                ))}
                            </div>
                        </div>
                    </>
                )}
            </div>
        </PanelLayout>
    );
}

function Stat({ label, value, unit, sub, strong, warn }) {
    const body = (
        <>
            <span className={`text-[12.5px] ${strong ? 'text-[#BFD3BF]' : 'text-muted'}`}>{label}</span>
            <span className={`tabular text-2xl font-bold tracking-[-0.02em] ${warn ? 'text-amber-ink' : ''}`}>
                {value}
                {unit && <span className={`ml-1 text-sm font-semibold ${strong ? 'text-[#D7E5D7]' : 'text-muted'}`}>{unit}</span>}
            </span>
            {sub && <span className={`text-xs ${strong ? 'text-[#BFD3BF]' : 'text-muted'}`}>{sub}</span>}
        </>
    );

    return strong ? (
        <section className="flex flex-col gap-1 rounded-[18px] bg-forest p-4 text-white">{body}</section>
    ) : (
        <PanelCard className="flex flex-col gap-1 p-4">{body}</PanelCard>
    );
}
