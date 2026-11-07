import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import StatusPill from '@/Components/StatusPill';
import { PlateTile } from '@/Components/Plate';
import { rupiah } from '@/lib/format';
import { closesIn } from '@/lib/time';
import { sharePreorder } from '@/lib/share';

const TABS = [
    { key: 'all', label: 'Semua' },
    { key: 'open', label: 'Berjalan' },
    { key: 'scheduled', label: 'Terjadwal' },
    { key: 'draft', label: 'Draf' },
    { key: 'closed', label: 'Selesai' },
];

export default function Preorders({ shop, preorders }) {
    const [tab, setTab] = useState('all');
    const counts = Object.fromEntries(TABS.map((t) => [t.key, t.key === 'all' ? preorders.length : preorders.filter((p) => p.stage === t.key).length]));
    const shown = tab === 'all' ? preorders : preorders.filter((p) => p.stage === tab);

    return (
        <PanelLayout variant="seller" active="po" title="PO dan menu" shop={shop}>
            <div className="flex flex-col gap-4 px-4 pt-4 lg:gap-5 lg:px-8 lg:pt-7">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">PO dan menu</h1>
                        <p className="m-0 text-sm text-muted">Buat PO, atur menu dan slot ambil, lalu bagikan tautannya.</p>
                    </div>
                    <Link href="/seller/po/baru" className="flex h-11 items-center gap-2 rounded-xl bg-forest px-4 text-sm font-semibold text-white no-underline hover:text-white">
                        <Icon name="plus" size={18} strokeWidth={2.2} />
                        Buat PO
                    </Link>
                </div>

                <div role="tablist" aria-label="Saring PO" className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:px-0">
                    {TABS.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            role="tab"
                            aria-selected={tab === t.key}
                            onClick={() => setTab(t.key)}
                            className={`flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-semibold ${
                                tab === t.key ? 'border-forest bg-forest text-white' : 'border-[#DCE3D9] bg-white text-body'
                            }`}
                        >
                            {t.label}
                            <span className={`tabular text-xs ${tab === t.key ? 'text-[#CFE3CB]' : 'text-muted'}`}>{counts[t.key]}</span>
                        </button>
                    ))}
                </div>

                {preorders.length === 0 ? (
                    <PanelCard className="flex flex-col items-center gap-3 px-6 py-10 text-center">
                        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E9F2E6] text-leaf">
                            <Icon name="box" size={26} />
                        </span>
                        <h2 className="m-0 text-lg font-bold">Belum ada PO</h2>
                        <p className="m-0 max-w-sm text-sm text-muted">Isi menu, harga, kuota, dan slot ambil. Simpan sebagai draf dulu kalau belum siap dibuka.</p>
                        <Link href="/seller/po/baru" className="mt-1 flex h-11 items-center rounded-xl bg-forest px-5 text-sm font-semibold text-white no-underline hover:text-white">
                            Buat PO pertama
                        </Link>
                    </PanelCard>
                ) : shown.length === 0 ? (
                    <p className="m-0 rounded-2xl border border-dashed border-[#D3DDD0] px-4 py-8 text-center text-sm text-muted">Tidak ada PO di kategori ini.</p>
                ) : (
                    <div className="grid gap-3 lg:grid-cols-2 lg:gap-4">
                        {shown.map((po) => (
                            <PreorderCard key={po.id} po={po} shop={shop} />
                        ))}
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}

function PreorderCard({ po, shop }) {
    const pct = Math.min(100, Math.round((po.sold / Math.max(1, po.quota)) * 100));
    const live = po.stage === 'open' || po.stage === 'scheduled';

    const close = () => {
        if (window.confirm(`Tutup PO ${po.title} sekarang? Pembeli tidak bisa memesan lagi.`)) {
            router.post(`/seller/po/${po.id}/tutup`, {}, { preserveScroll: true });
        }
    };
    const remove = () => {
        if (window.confirm(`Hapus PO ${po.title}? Tindakan ini tidak bisa dibatalkan.`)) {
            router.delete(`/seller/po/${po.id}`, {
                preserveScroll: true,
                onError: (errors) => errors.preorder && window.alert(errors.preorder),
            });
        }
    };

    return (
        <PanelCard className="flex flex-col gap-3.5 p-4">
            <div className="flex items-start gap-3">
                <PlateTile seed={po.seed} image={po.poster_url} className="h-14 w-14 shrink-0 rounded-[14px]" plateSize={48} />
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <div className="flex items-start justify-between gap-2">
                        <h2 className="m-0 truncate text-[15.5px] font-bold">{po.title}</h2>
                        <StatusPill status={`po_${po.stage}`} />
                    </div>
                    <p className="m-0 truncate text-[13px] text-muted">{po.menu || 'Belum ada menu'}</p>
                    {po.places && (
                        <p className="m-0 flex items-center gap-1 truncate text-xs text-body">
                            <Icon name="pin" size={13} className="shrink-0 text-leaf" />
                            <span className="truncate">{po.places}</span>
                        </p>
                    )}
                </div>
            </div>

            <dl className="m-0 grid grid-cols-2 gap-x-3 gap-y-2 text-[13px]">
                <div className="flex flex-col gap-px">
                    <dt className="text-muted">{po.stage === 'scheduled' ? 'Buka' : 'Tutup'}</dt>
                    <dd className="m-0 font-semibold">
                        {po.stage === 'scheduled' ? po.opens_label : po.closes_label}
                        {po.stage === 'open' && <span className="block text-xs font-medium text-amber-ink">{closesIn(po.closes_at)} lagi</span>}
                    </dd>
                </div>
                <div className="flex flex-col gap-px">
                    <dt className="text-muted">Ambil atau antar</dt>
                    <dd className="m-0 font-semibold">{po.pickup_label}</dd>
                </div>
                {po.stage !== 'draft' && (
                    <>
                        <div className="flex flex-col gap-px">
                            <dt className="text-muted">Pesanan lunas</dt>
                            <dd className="tabular m-0 font-semibold">{po.paid_orders}</dd>
                        </div>
                        <div className="flex flex-col gap-px">
                            <dt className="text-muted">Penjualan</dt>
                            <dd className="tabular m-0 font-semibold">{rupiah(po.revenue)}</dd>
                        </div>
                    </>
                )}
            </dl>

            <div className="flex flex-col gap-1.5">
                <div className="flex justify-between text-[13px]">
                    <span className="text-muted">Porsi terpesan</span>
                    <span className="tabular font-bold">
                        {po.sold} dari {po.quota}
                    </span>
                </div>
                <span role="img" aria-label={`${pct} persen kuota terpesan`} className="block h-2 overflow-hidden rounded-full bg-[#EDF1EB]">
                    <span className="block h-full rounded-full bg-leaf" style={{ width: `${pct}%` }} />
                </span>
            </div>

            <div className="flex flex-wrap gap-2">
                <Link
                    href={`/seller/po/${po.id}/ubah`}
                    className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl bg-forest px-3 text-[13.5px] font-semibold text-white no-underline hover:text-white"
                >
                    <Icon name="pencil" size={16} />
                    {po.stage === 'draft' ? 'Lanjutkan' : 'Ubah'}
                </Link>
                {live && (
                    <button
                        type="button"
                        onClick={() => sharePreorder({ title: po.title, shopName: shop.name, url: po.url, storyUrl: po.story_url })}
                        className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-[#DCE3D9] bg-white px-3 text-[13.5px] font-semibold"
                    >
                        <Icon name="share" size={16} />
                        Bagikan
                    </button>
                )}
                <Menu po={po} live={live} onClose={close} onRemove={remove} />
            </div>
        </PanelCard>
    );
}

function Menu({ po, live, onClose, onRemove }) {
    const [open, setOpen] = useState(false);
    const item = 'flex min-h-11 w-full items-center gap-2.5 px-3.5 text-left text-[13.5px] font-semibold text-ink no-underline hover:bg-[#F6F8F5]';

    return (
        <div className="relative">
            <button
                type="button"
                aria-label={`Pilihan lain untuk ${po.title}`}
                aria-expanded={open}
                onClick={() => setOpen((o) => !o)}
                className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#DCE3D9] bg-white"
            >
                <Icon name="menu" size={18} />
            </button>
            {open && (
                <>
                    <button type="button" aria-hidden="true" tabIndex={-1} className="fixed inset-0 z-10 cursor-default" onClick={() => setOpen(false)} />
                    <div className="absolute bottom-12 right-0 z-20 w-56 overflow-hidden rounded-2xl border border-panel-line bg-white py-1.5 shadow-[0_12px_32px_rgba(20,26,22,0.14)]">
                        {po.stage !== 'draft' && (
                            <Link href={po.url} className={item}>
                                <Icon name="eye" size={18} className="text-leaf" />
                                Lihat halaman PO
                            </Link>
                        )}
                        {po.stage !== 'draft' && (
                            <>
                                <Link href={`/seller/po/${po.id}/lpj`} className={item}>
                                    <Icon name="receipt" size={18} className="text-leaf" />
                                    Laporan LPJ
                                </Link>
                                <a href={`/seller/po/${po.id}/ekspor`} className={item}>
                                    <Icon name="download" size={18} className="text-leaf" />
                                    Unduh Excel pesanan
                                </a>
                            </>
                        )}
                        <Link href={`/seller/po/baru?salin=${po.id}`} className={item}>
                            <Icon name="copy" size={18} className="text-leaf" />
                            Salin jadi PO baru
                        </Link>
                        {po.story_url && (
                            <a href={po.story_url} download className={item}>
                                <Icon name="download" size={18} className="text-leaf" />
                                Unduh gambar story
                            </a>
                        )}
                        {live && (
                            <button type="button" onClick={onClose} className={item}>
                                <Icon name="stop" size={18} className="text-amber-ink" />
                                Tutup PO sekarang
                            </button>
                        )}
                        {po.orders_count === 0 && (
                            <button type="button" onClick={onRemove} className={`${item} text-danger`}>
                                <Icon name="trash" size={18} />
                                Hapus PO
                            </button>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
