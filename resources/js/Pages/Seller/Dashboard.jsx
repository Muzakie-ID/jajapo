import { Link, router, usePage } from '@inertiajs/react';
import PanelLayout, { PanelCard, ShopSwitcher } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import { sharePreorder } from '@/lib/share';
import Plate from '@/Components/Plate';
import StatusPill from '@/Components/StatusPill';
import { rupiah } from '@/lib/format';
import { closesIn } from '@/lib/time';

const FILTERS = [
    { value: '', label: 'Semua' },
    { value: 'paid', label: 'Menunggu disiapkan' },
    { value: 'ready', label: 'Siap diambil' },
    { value: 'pending_payment', label: 'Menunggu bayar' },
    { value: 'picked_up', label: 'Diambil' },
    { value: 'cancelled', label: 'Dibatalkan' },
];

export default function Dashboard({ shop, active, kpis, perDay, rekap, orders, statusFilter, balance, draftCount }) {
    const { auth } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];
    const maxDay = Math.max(1, ...perDay.map((d) => d.value));
    const maxRekap = Math.max(1, ...rekap.map((r) => r.qty));

    const share = () => active && sharePreorder({ title: active.title, shopName: shop.name, url: active.url, storyUrl: active.story_url });

    const markReady = (target) =>
        router.post('/seller/pesanan/siap', { preorder_id: active.id, ...target }, { preserveScroll: true });

    const kpiTiles = kpis
        ? [
              { label: 'Pesanan lunas', value: kpis.paid_count, sub: `${kpis.paid_today} masuk hari ini`, accent: true },
              { label: 'Pendapatan', value: rupiah(kpis.revenue), sub: 'Masuk saldo tersedia' },
              { label: 'Belum diambil', value: kpis.waiting_pickup, sub: `${kpis.waiting_ready} menunggu disiapkan` },
              { label: 'Kedaluwarsa', value: kpis.expired, sub: 'Kuotanya sudah dikembalikan' },
          ]
        : [];

    const poCard = active && (
<PanelCard className="flex flex-col gap-3.5 px-[18px] py-4">
                                <div className="hidden items-center justify-between lg:flex">
                                    <h2 className="m-0 text-[17px] font-bold">PO aktif</h2>
                                    <Link href="/seller/po" className="text-[13px] font-semibold text-leaf no-underline">
                                        Semua PO
                                    </Link>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-[14px] bg-[#F6ECD4]">
                                        <Plate seed={active.seed} size={44} />
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-1">
                                        <span className="text-[15px] font-bold">{active.title}</span>
                                        <span className="self-start rounded-full bg-amber-bg px-[9px] py-[3px] text-xs font-bold text-amber-ink">
                                            {active.is_open ? `Tutup ${closesIn(active.closes_at)}` : 'Sudah tutup'}
                                        </span>
                                    </span>
                                </div>
                                {kpis && (
                                    <div className="flex flex-col gap-1.5">
                                        <div className="flex justify-between text-[13px]">
                                            <span className="text-muted">Porsi terjual</span>
                                            <span className="tabular font-bold">
                                                {kpis.sold} dari {kpis.quota}
                                            </span>
                                        </div>
                                        <span
                                            role="img"
                                            aria-label={`${Math.round((kpis.sold / Math.max(1, kpis.quota)) * 100)} persen kuota terjual`}
                                            className="block h-2 overflow-hidden rounded-full bg-[#EDF1EB]"
                                        >
                                            <span className="block h-full rounded-full bg-leaf" style={{ width: `${Math.min(100, Math.round((kpis.sold / Math.max(1, kpis.quota)) * 100))}%` }} />
                                        </span>
                                    </div>
                                )}
                                {active.waiting > 0 && (
                                    <div className="flex flex-col gap-2 rounded-xl border border-[#E3DCC4] bg-[#FDF8EC] p-3">
                                        <span className="flex items-center justify-between gap-2">
                                            <span className="text-[13px] font-semibold">{active.waiting} pesanan menunggu disiapkan</span>
                                            <button
                                                type="button"
                                                onClick={() => markReady({ all: true })}
                                                className="flex h-9 items-center gap-1.5 rounded-xl bg-forest px-3 text-xs font-semibold text-white"
                                            >
                                                <Icon name="check" size={15} strokeWidth={2.4} />
                                                Siapkan semua
                                            </button>
                                        </span>
                                        {active.menus.filter((m) => m.waiting > 0).length > 1 && (
                                            <span className="flex flex-wrap gap-1.5">
                                                {active.menus
                                                    .filter((m) => m.waiting > 0)
                                                    .map((m) => (
                                                        <button
                                                            key={m.id}
                                                            type="button"
                                                            onClick={() => markReady({ product_id: m.id })}
                                                            className="flex h-8 items-center rounded-full border border-[#DCE3D9] bg-white px-3 text-xs font-semibold"
                                                        >
                                                            {m.name} ({m.waiting})
                                                        </button>
                                                    ))}
                                            </span>
                                        )}
                                    </div>
                                )}
                                <div className="flex flex-col gap-2.5">
                                    {active.slots.map((s) => (
                                        <div key={s.place + s.time} className="flex items-center justify-between gap-3 rounded-xl bg-[#F6F8F5] px-3 py-2.5">
                                            <span className="flex flex-col gap-px">
                                                <span className="text-[13.5px] font-semibold">{s.place}</span>
                                                <span className="text-xs text-muted">{s.time}</span>
                                            </span>
                                            <span className="flex items-center gap-2.5">
                                                {s.waiting > 0 && (
                                                    <button
                                                        type="button"
                                                        onClick={() => markReady({ slot_id: s.id })}
                                                        className="flex h-9 items-center gap-1.5 rounded-xl bg-forest px-3 text-xs font-semibold text-white"
                                                    >
                                                        <Icon name="check" size={15} strokeWidth={2.4} />
                                                        Siapkan {s.waiting}
                                                    </button>
                                                )}
                                                <span className="tabular text-[13.5px] font-bold">{s.count} pesanan</span>
                                            </span>
                                        </div>
                                    ))}
                                </div>
                                <div className="flex gap-2 lg:hidden">
                                    <button type="button" onClick={share} className="flex h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-[#DCE3D9] bg-white text-sm font-semibold">
                                        <Icon name="share" size={18} />
                                        Bagikan PO
                                    </button>
                                    <Link href={`/seller/po/${active.id}/ubah`} className="flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-forest text-sm font-semibold text-white no-underline hover:text-white">
                                        <Icon name="pencil" size={17} />
                                        Ubah PO
                                    </Link>
                                </div>
                            </PanelCard>
    );

    return (
        <PanelLayout variant="seller" active="home" title="Beranda toko" shop={shop} badges={{ orders: kpis?.waiting_pickup }}>
            <div className="flex flex-col gap-4 px-4 pt-4 lg:gap-5 lg:px-8 lg:pt-7">
                <div className="lg:hidden">
                    <ShopSwitcher shop={shop} />
                </div>

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Halo, {firstName}</h1>
                        <p className="m-0 text-sm text-muted">
                            {active
                                ? active.is_open
                                    ? `PO ${active.title} tutup dalam ${closesIn(active.closes_at)}. Pengambilan ${active.pickup_label}.`
                                    : `PO ${active.title} sudah tutup. Pengambilan ${active.pickup_label}.`
                                : draftCount > 0
                                  ? `Belum ada PO yang terbit. Ada ${draftCount} draf yang menunggu diterbitkan.`
                                  : 'Belum ada PO. Buat PO pertamamu, isi menu dan slot ambil, lalu bagikan.'}
                        </p>
                    </div>
                    {!active && (
                        <Link href={draftCount > 0 ? '/seller/po' : '/seller/po/baru'} className="flex h-11 items-center gap-2 rounded-xl bg-forest px-[18px] text-sm font-semibold text-white no-underline hover:text-white">
                            <Icon name={draftCount > 0 ? 'box' : 'plus'} size={18} strokeWidth={2.1} />
                            {draftCount > 0 ? 'Lihat draf PO' : 'Buat PO'}
                        </Link>
                    )}
                    {active && (
                        <div className="hidden gap-2.5 lg:flex">
                            <Link href={`/seller/po/${active.id}/ubah`} className="flex h-11 items-center gap-2 rounded-xl border border-[#DCE3D9] bg-white px-4 text-sm font-semibold text-ink no-underline">
                                <Icon name="pencil" size={18} />
                                Ubah PO
                            </Link>
                            <button type="button" onClick={share} className="flex h-11 items-center gap-2 rounded-xl border border-[#DCE3D9] bg-white px-4 text-sm font-semibold">
                                <Icon name="share" size={18} />
                                Bagikan PO
                            </button>
                            <Link href={active.url} className="flex h-11 items-center gap-2 rounded-xl bg-forest px-[18px] text-sm font-semibold text-white no-underline hover:text-white">
                                Lihat halaman PO
                            </Link>
                        </div>
                    )}
                </div>

                <div className="hidden flex-wrap items-center gap-3 rounded-[14px] border border-[#D3E3CE] bg-[#E9F2E6] py-3 pl-4 pr-3.5 lg:flex">
                    <Icon name="chart" size={20} strokeWidth={1.9} className="text-leaf" />
                    <span className="flex-[1_1_280px] text-sm text-forest">Saat PO tutup, rekap produksi dan daftar ambil per titik bisa langsung dipakai.</span>
                    <a href="#rekap" className="flex h-9 items-center rounded-[10px] border border-[#D3E3CE] bg-white px-3.5 text-[13px] font-semibold text-forest no-underline">
                        Lihat rekap
                    </a>
                </div>

                <div className="lg:hidden">{poCard}</div>

                <div className="flex flex-col gap-5 lg:flex-row lg:items-start">
                    <div className="flex min-w-0 flex-col gap-5 lg:flex-[2]">
                        {kpis && (
                            <PanelCard className="overflow-hidden">
                                <div className="flex flex-wrap items-center justify-between gap-3 px-[18px] py-4">
                                    <h2 className="m-0 text-[17px] font-bold">Ringkasan PO ini</h2>
                                    <div role="group" aria-label="Rentang waktu" className="flex rounded-xl bg-[#F0F3EE] p-[3px]">
                                        {[
                                            ['hari-ini', 'Hari ini'],
                                            ['semua', 'Semua'],
                                        ].map(([value, label]) => (
                                            <button
                                                key={value}
                                                type="button"
                                                aria-pressed={kpis.range === value}
                                                onClick={() => router.get('/seller', { rentang: value }, { preserveScroll: true, preserveState: true })}
                                                className={`h-[34px] rounded-[9px] px-3.5 text-[13px] ${kpis.range === value ? 'bg-white font-bold shadow-[0_1px_2px_rgba(20,26,22,0.08)]' : 'font-semibold text-muted'}`}
                                            >
                                                {label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 border-t border-[#EDF1EB]">
                                    {kpiTiles.map((k, i) => (
                                        <div key={k.label} className={`flex flex-col gap-1.5 p-4 lg:p-[18px] ${i % 2 === 0 ? 'border-r border-[#EDF1EB]' : ''} ${i < 2 ? 'border-b border-[#EDF1EB]' : ''}`}>
                                            <span className="text-[13px] text-muted">{k.label}</span>
                                            <span className="tabular text-[22px] font-bold tracking-[-0.02em] lg:text-[28px]">{k.value}</span>
                                            <span className={`text-[12.5px] ${k.accent ? 'font-semibold text-leaf' : 'text-muted'}`}>{k.sub}</span>
                                        </div>
                                    ))}
                                </div>
                            </PanelCard>
                        )}

                        {perDay.length > 0 && (
                            <PanelCard className="hidden flex-col gap-4 px-[18px] pb-[18px] pt-4 lg:flex">
                                <div className="flex items-baseline justify-between gap-3">
                                    <h2 className="m-0 text-[17px] font-bold">Pesanan lunas per hari</h2>
                                    <span className="text-[13px] text-muted">5 hari terakhir</span>
                                </div>
                                <div className="grid h-44 grid-cols-5 items-end gap-4 border-b border-panel-line px-1">
                                    {perDay.map((d) => (
                                        <div key={d.label} className="flex h-full flex-col items-center justify-end gap-1.5">
                                            <span className="tabular text-[13px] font-bold">{d.value}</span>
                                            <span
                                                className={`block w-full max-w-14 rounded-[10px_10px_4px_4px] ${d.today ? 'bg-leaf' : 'bg-[#CFE2C9]'}`}
                                                style={{ height: `${Math.max(4, Math.round((d.value / maxDay) * 130))}px` }}
                                            />
                                        </div>
                                    ))}
                                </div>
                                <div className="-mt-2 grid grid-cols-5 gap-4 px-1">
                                    {perDay.map((d) => (
                                        <span key={d.label} className={`text-center text-[12.5px] ${d.today ? 'font-bold' : 'text-muted'}`}>
                                            {d.label}
                                        </span>
                                    ))}
                                </div>
                            </PanelCard>
                        )}

                        <PanelCard id="pesanan" className="overflow-hidden">
                            <div className="flex flex-wrap items-center justify-between gap-3 px-[18px] pb-3 pt-4">
                                <h2 className="m-0 text-[17px] font-bold">Pesanan terbaru</h2>
                                <Link href="/seller/pesanan" className="text-[13px] font-semibold text-leaf no-underline">
                                    Lihat semua pesanan
                                </Link>
                            </div>
                            <div role="group" aria-label="Filter status" className="flex flex-wrap gap-1.5 px-[18px] pb-3.5">
                                {FILTERS.map((f) => {
                                    const on = (statusFilter || '') === f.value;
                                    return (
                                        <Link
                                            key={f.value || 'all'}
                                            href={f.value ? `/seller?status=${f.value}#pesanan` : '/seller#pesanan'}
                                            preserveScroll
                                            aria-current={on ? 'true' : undefined}
                                            className={`flex h-[34px] items-center rounded-full px-3 text-[13px] font-semibold no-underline ${on ? 'bg-forest text-white hover:text-white' : 'border border-[#DCE3D9] bg-white text-[#141A16] hover:text-[#141A16]'}`}
                                        >
                                            {f.label}
                                        </Link>
                                    );
                                })}
                            </div>
                            {orders.length === 0 && <p className="m-0 border-t border-[#EDF1EB] px-[18px] py-4 text-sm text-muted">Belum ada pesanan dengan status ini.</p>}
                            <div className="hidden overflow-x-auto border-t border-[#EDF1EB] lg:block">
                                <div className="min-w-[760px]">
                                    <div className="grid grid-cols-[0.8fr_1.3fr_1.2fr_1.2fr_0.9fr_1fr] gap-3 bg-panel-soft px-[18px] py-2.5 text-[12.5px] font-semibold text-muted">
                                        <span>Kode</span>
                                        <span>Pembeli</span>
                                        <span>Item</span>
                                        <span>Slot ambil</span>
                                        <span className="text-right">Untuk toko</span>
                                        <span>Status</span>
                                    </div>
                                    {orders.map((o) => (
                                        <div key={o.code} className="grid grid-cols-[0.8fr_1.3fr_1.2fr_1.2fr_0.9fr_1fr] items-center gap-3 border-t border-[#EDF1EB] px-[18px] py-3 text-[13.5px]">
                                            <Link href={`/seller/pesanan/${o.code}`} className="tabular font-bold text-ink no-underline hover:text-leaf">
                                                {o.code}
                                            </Link>
                                            <span>{o.buyer}</span>
                                            <span className="text-body">{o.item}</span>
                                            <span className="text-body">{o.slot}</span>
                                            <span className="tabular text-right font-semibold">{rupiah(o.amount)}</span>
                                            <span className="flex items-center gap-2">
                                                <StatusPill status={o.status} />
                                                {o.status === 'paid' && !o.is_delivery && (
                                                    <button
                                                        type="button"
                                                        onClick={() => router.post('/seller/pesanan/siap', { preorder_id: o.preorder_id, code: o.code }, { preserveScroll: true })}
                                                        className="text-xs font-semibold text-leaf"
                                                    >
                                                        Siapkan
                                                    </button>
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="flex flex-col gap-2.5 px-3.5 pb-3.5 lg:hidden">
                                {orders.map((o) => (
                                    <div key={o.code} className="flex flex-col gap-1.5 rounded-2xl border border-panel-line px-3.5 py-3">
                                        <span className="flex items-center justify-between gap-2">
                                            <Link href={`/seller/pesanan/${o.code}`} className="tabular text-sm font-bold text-ink no-underline">
                                                {o.code}
                                            </Link>
                                            <StatusPill status={o.status} />
                                        </span>
                                        <span className="text-[13.5px]">
                                            {o.buyer}, {o.item}
                                        </span>
                                        <span className="flex items-center justify-between gap-2">
                                            <span className="text-[12.5px] text-muted">{o.slot}</span>
                                            <span className="tabular text-sm font-bold">{rupiah(o.amount)}</span>
                                        </span>
                                        {o.status === 'paid' && !o.is_delivery && (
                                            <button
                                                type="button"
                                                onClick={() => router.post('/seller/pesanan/siap', { preorder_id: o.preorder_id, code: o.code }, { preserveScroll: true })}
                                                className="mt-1 flex h-10 items-center justify-center gap-1.5 rounded-xl bg-forest text-[13px] font-semibold text-white"
                                            >
                                                <Icon name="check" size={16} strokeWidth={2.4} />
                                                Tandai siap diambil
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </PanelCard>
                    </div>

                    <div className="flex min-w-0 flex-col gap-5 lg:flex-1">
                        <div className="hidden lg:block">{poCard}</div>

                        <PanelCard id="rekap" className="flex flex-col gap-3 px-[18px] py-4">
                            <div className="flex items-baseline justify-between">
                                <h2 className="m-0 text-[17px] font-bold">Rekap produksi</h2>
                                <Link href="/seller/rekap" className="text-[13px] font-semibold text-leaf no-underline">
                                    {rekap.reduce((a, r) => a + r.qty, 0)} porsi, lihat rincian
                                </Link>
                            </div>
                            {rekap.length === 0 && <p className="m-0 text-sm text-muted">Belum ada pesanan lunas.</p>}
                            {active && rekap.length > 0 && (
                                <div className="flex gap-2 pt-1">
                                    <a href={`/seller/po/${active.id}/ekspor`} className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-[#DCE3D9] bg-white text-[13px] font-semibold text-ink no-underline">
                                        <Icon name="download" size={16} />
                                        Unduh Excel
                                    </a>
                                    <Link href={`/seller/po/${active.id}/lpj`} className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-[#DCE3D9] bg-white text-[13px] font-semibold text-ink no-underline">
                                        <Icon name="receipt" size={16} />
                                        Laporan LPJ
                                    </Link>
                                </div>
                            )}
                            {rekap.map((r) => (
                                <div key={r.name} className="flex flex-col gap-1.5">
                                    <div className="flex justify-between text-[13.5px]">
                                        <span className="font-semibold">{r.name}</span>
                                        <span className="tabular font-bold">{r.qty} porsi</span>
                                    </div>
                                    <span className="block h-1.5 overflow-hidden rounded-full bg-[#EDF1EB]">
                                        <span className="block h-full rounded-full bg-[#8FBF86]" style={{ width: `${Math.round((r.qty / maxRekap) * 100)}%` }} />
                                    </span>
                                </div>
                            ))}
                        </PanelCard>

                        <section className="flex flex-col gap-3.5 rounded-[18px] bg-forest p-[18px] text-white">
                            <h2 className="m-0 text-[17px] font-bold">Saldo toko</h2>
                            <dl className="m-0 grid grid-cols-2 gap-3">
                                <div className="flex flex-col gap-1">
                                    <dt className="text-[12.5px] text-[#BFD3BF]">Tersedia</dt>
                                    <dd className="tabular m-0 text-xl font-bold">{rupiah(balance.available)}</dd>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <dt className="text-[12.5px] text-[#BFD3BF]">Ditahan komplain</dt>
                                    <dd className="tabular m-0 text-xl font-bold">{rupiah(balance.held)}</dd>
                                </div>
                            </dl>
                            <Link href="/seller/saldo" className="flex h-11 items-center justify-center rounded-xl bg-sprout text-sm font-bold text-[#12301B] no-underline hover:text-[#12301B]">
                                Ajukan pencairan
                            </Link>
                        </section>
                    </div>
                </div>
            </div>
        </PanelLayout>
    );
}
