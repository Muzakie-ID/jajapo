import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import BuyerLayout, { CircleButton, TopBar } from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';
import Plate from '@/Components/Plate';
import { rupiah } from '@/lib/format';
import { closesIn } from '@/lib/time';
import { readCart, writeCart } from '@/lib/cart';

export default function Preorder({ preorder, pendingOrder, savedPhone }) {
    const { auth } = usePage().props;
    const variants = useMemo(
        () =>
            preorder.products.flatMap((p) =>
                p.variants.map((v) => ({
                    ...v,
                    name: p.is_bundle ? p.name : v.name,
                    productName: p.name,
                    multi: !p.is_bundle && preorder.products.filter((x) => !x.is_bundle).length > 1,
                    isBundle: p.is_bundle,
                    contents: p.contents,
                    normalPrice: p.normal_price,
                })),
            ),
        [preorder],
    );

    const initialQty = useMemo(() => {
        const cart = readCartFor(preorder.id);
        return Object.fromEntries(variants.map((v) => [v.id, cart?.qty?.[v.id] || 0]));
    }, [preorder.id, variants]);

    const [qty, setQty] = useState(initialQty);
    const canPickup = preorder.slots.length > 0;
    const canDeliver = preorder.delivery.enabled;
    const [mode, setMode] = useState(canPickup ? 'pickup' : 'delivery');
    const [slot, setSlot] = useState(preorder.slots[0]?.id ?? null);
    const [zone, setZone] = useState(preorder.delivery.zones[0]?.id ?? null);
    const [fav, setFav] = useState(false);
    const form = useForm({ phone: savedPhone || '', address_note: '', note: '' });

    const isDelivery = mode === 'delivery';
    const count = variants.reduce((a, v) => a + qty[v.id], 0);
    const subtotal = variants.reduce((a, v) => a + qty[v.id] * v.price, 0);
    const zoneObj = preorder.delivery.zones.find((z) => z.id === zone);
    const total = subtotal + (isDelivery && count > 0 ? (zoneObj?.fee ?? 0) : 0);
    const slotObj = preorder.slots.find((s) => s.id === slot);
    const summary = [
        ...variants.filter((v) => qty[v.id] > 0).map((v) => `${qty[v.id]} ${v.name}`),
        count > 0 ? (isDelivery ? `Antar ke ${zoneObj?.name ?? '-'}` : slotObj?.place) : null,
    ]
        .filter(Boolean)
        .join(', ');

    useEffect(() => {
        writeCart(
            count > 0
                ? { preorderId: preorder.id, url: preorder.url, title: preorder.title, shopName: preorder.shop.name, count, total, qty }
                : null,
        );
    }, [count, total]); // eslint-disable-line react-hooks/exhaustive-deps

    const bump = (id, d) => setQty((q) => ({ ...q, [id]: Math.max(0, Math.min(10, q[id] + d)) }));

    const submit = (e) => {
        e.preventDefault();
        if (!auth.user) {
            router.visit(`/login?kembali=${encodeURIComponent(preorder.url)}`);
            return;
        }
        form.transform((data) => ({
            ...data,
            preorder_id: preorder.id,
            fulfillment: isDelivery ? 'delivery' : 'pickup',
            slot_id: isDelivery ? null : slot,
            zone_id: isDelivery ? zone : null,
            items: variants.filter((v) => qty[v.id] > 0).map((v) => ({ variant_id: v.id, qty: qty[v.id] })),
        }));
        form.post('/checkout');
    };

    const share = async () => {
        const url = window.location.href;
        const text = `${preorder.title} dari ${preorder.shop.name}. Pesan di Jajapo: ${url}`;
        if (navigator.share) {
            try {
                await navigator.share({ title: preorder.title, text, url });
            } catch {
                // dibatalkan pengguna
            }
        } else {
            window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
        }
    };

    let bottomBar;
    if (pendingOrder) {
        bottomBar = (
            <Link
                href={`/bayar/${pendingOrder.code}`}
                className="flex h-14 items-center justify-center rounded-[20px] bg-forest text-[15px] font-bold text-white no-underline"
            >
                Kamu punya pesanan yang belum dibayar. Lanjut bayar
            </Link>
        );
    } else if (!preorder.is_open) {
        bottomBar = (
            <div className="rounded-[20px] border border-dashed border-[#B9C9B5] bg-white p-[18px] text-center text-sm font-semibold text-muted">
                PO ini sudah tutup
            </div>
        );
    } else if (count > 0) {
        bottomBar = (
            <button
                type="submit"
                form="checkout"
                disabled={form.processing}
                className="flex w-full items-center justify-between gap-3 rounded-[20px] bg-forest py-2.5 pl-[18px] pr-2.5 text-left shadow-[0_14px_28px_-12px_rgba(31,58,38,0.6)] disabled:opacity-70"
            >
                <span className="flex min-w-0 flex-col gap-0.5">
                    <span className="text-[15px] font-bold text-white">{form.processing ? 'Membuat pesanan' : `${count} item dipilih`}</span>
                    <span className="truncate text-xs text-[#BFD3BF]">{summary}</span>
                </span>
                <span className="tabular flex shrink-0 items-center gap-1 rounded-[14px] bg-sprout py-[11px] pl-4 pr-3 text-[15px] font-bold text-[#12301B]">
                    {rupiah(total)}
                    <Icon name="chevronRight" size={18} strokeWidth={2.2} />
                </span>
            </button>
        );
    } else {
        bottomBar = (
            <div className="rounded-[20px] border border-dashed border-[#B9C9B5] bg-white p-[18px] text-center text-sm font-semibold text-muted">
                Tambah minimal satu porsi untuk lanjut bayar
            </div>
        );
    }

    return (
        <BuyerLayout title={preorder.title} hideNav bottomBar={bottomBar}>
            <TopBar
                back="/"
                right={
                    <>
                        <CircleButton label="Simpan ke favorit" pressed={fav} onClick={() => setFav(!fav)}>
                            <Icon name="heart" size={20} strokeWidth={1.9} fill={fav ? '#D9483B' : 'none'} className={fav ? 'text-[#D9483B]' : ''} />
                        </CircleButton>
                        <CircleButton label="Bagikan PO" icon="share" onClick={share} />
                    </>
                }
            />

            <section className="relative min-h-[214px] overflow-hidden rounded-3xl border border-line bg-white p-[18px]">
                {preorder.poster_url ? (
                    <img src={preorder.poster_url} alt="" className="absolute -right-6 top-6 h-44 w-44 rounded-full object-cover" />
                ) : (
                    <span className="absolute -right-11 top-[22px]">
                        <Plate seed={preorder.seed} size={196} rich />
                    </span>
                )}
                <div className="relative flex max-w-[196px] flex-col gap-3">
                    <span className="inline-flex items-center gap-[5px] self-start rounded-full bg-amber-bg py-[5px] pl-2 pr-2.5 text-xs font-bold text-amber-ink">
                        <Icon name="clock" size={14} strokeWidth={2.2} />
                        {preorder.is_open ? `Tutup ${closesIn(preorder.closes_at)}` : 'Sudah tutup'}
                    </span>
                    <div className="flex flex-col gap-1.5">
                        <h1 className="m-0 text-2xl font-extrabold leading-[1.15] tracking-[-0.01em]">{preorder.title}</h1>
                        <Link href={`/${preorder.shop.slug}`} className="flex items-center gap-[5px] text-[13px] font-semibold text-ink no-underline">
                            {preorder.shop.name}
                            {preorder.shop.verified && <Icon name="shield" size={16} strokeWidth={1.9} fill="#DCEBD7" className="text-leaf" label="Email UGM terverifikasi" />}
                        </Link>
                    </div>
                    <dl className="m-0 grid grid-cols-2 gap-2.5">
                        <div className="flex flex-col gap-0.5">
                            <dt className="text-xs text-muted">Sisa kuota</dt>
                            <dd className="m-0 text-[15px] font-bold">
                                {preorder.remaining} dari {preorder.quota}
                            </dd>
                        </div>
                        <div className="flex flex-col gap-0.5">
                            <dt className="text-xs text-muted">{canPickup ? 'Ambil' : 'Diantar'}</dt>
                            <dd className="m-0 text-[15px] font-bold">{preorder.pickup_date_label}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <form id="checkout" onSubmit={submit} className="mt-[18px] flex flex-col gap-[18px]">
                <section className="flex flex-col gap-2.5">
                    <div className="flex items-baseline justify-between">
                        <h2 className="m-0 text-base font-bold">Pilih varian</h2>
                        <span className="text-xs text-muted">Boleh lebih dari satu</span>
                    </div>
                    <div className="overflow-hidden rounded-[20px] border border-line bg-white">
                        {variants.map((v, i) => (
                            <div key={v.id} className={`flex items-center justify-between gap-3 py-2 pl-4 pr-2 ${i ? 'border-t border-[#E6EDE3]' : ''}`}>
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <span className="flex items-center gap-1.5 text-[15px] font-semibold">
                                        {v.isBundle && <span className="rounded-full bg-amber-bg px-2 py-0.5 text-[11px] font-bold text-amber-ink">Paket</span>}
                                        {v.multi ? `${v.productName}, ${v.name}` : v.name}
                                    </span>
                                    {v.isBundle && <span className="text-xs text-body">{v.contents}</span>}
                                    <span className="tabular text-[13px] text-muted">
                                        {rupiah(v.price)}
                                        {v.isBundle && v.normalPrice > v.price && (
                                            <>
                                                {' '}
                                                <span className="line-through">{rupiah(v.normalPrice)}</span>{' '}
                                                <span className="font-semibold text-leaf">hemat {rupiah(v.normalPrice - v.price)}</span>
                                            </>
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center gap-0.5">
                                    <button
                                        type="button"
                                        aria-label={`Kurangi ${v.name}`}
                                        onClick={() => bump(v.id, -1)}
                                        disabled={qty[v.id] === 0}
                                        className="flex h-11 w-11 items-center justify-center"
                                    >
                                        <span className={`flex h-8 w-8 items-center justify-center rounded-full border border-line ${qty[v.id] === 0 ? 'text-[#A8B4AA]' : 'text-ink'}`}>
                                            <Icon name="minus" size={16} strokeWidth={2.2} />
                                        </span>
                                    </button>
                                    <span aria-live="polite" className="tabular min-w-5 text-center text-[15px] font-bold">
                                        {qty[v.id]}
                                    </span>
                                    <button
                                        type="button"
                                        aria-label={`Tambah ${v.name}`}
                                        onClick={() => bump(v.id, 1)}
                                        disabled={!preorder.is_open}
                                        className="flex h-11 w-11 items-center justify-center"
                                    >
                                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-leaf text-white">
                                            <Icon name="plus" size={16} strokeWidth={2.2} />
                                        </span>
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="flex flex-col gap-2.5">
                    <h2 className="m-0 text-base font-bold">Cara terima pesanan</h2>
                    {canPickup && canDeliver && (
                        <div role="radiogroup" aria-label="Cara terima pesanan" className="grid grid-cols-2 gap-1 rounded-2xl bg-[#E6EEE3] p-1">
                            {[
                                { key: 'pickup', label: 'Ambil sendiri', icon: 'pin' },
                                { key: 'delivery', label: 'Diantar', icon: 'truck' },
                            ].map((m) => (
                                <button
                                    key={m.key}
                                    type="button"
                                    role="radio"
                                    aria-checked={mode === m.key}
                                    onClick={() => setMode(m.key)}
                                    className={`flex h-11 items-center justify-center gap-2 rounded-xl text-sm font-semibold ${
                                        mode === m.key ? 'bg-white text-forest shadow-[0_2px_8px_rgba(20,26,22,0.08)]' : 'text-muted'
                                    }`}
                                >
                                    <Icon name={m.icon} size={18} />
                                    {m.label}
                                </button>
                            ))}
                        </div>
                    )}

                    {!isDelivery ? (
                        <div role="radiogroup" aria-label="Titik dan waktu ambil" className="-mr-5 flex gap-2.5 overflow-x-auto pr-5 [scrollbar-width:none]">
                            {preorder.slots.map((s) => (
                                <SlotCard
                                    key={s.id}
                                    on={slot === s.id}
                                    onPick={() => setSlot(s.id)}
                                    title={s.place}
                                    note={s.place_note}
                                    text={`${s.date_label}, ${s.time_label}`}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2.5">
                            {preorder.delivery.schedule && (
                                <p className="m-0 flex items-center gap-2 rounded-[14px] bg-white px-3.5 py-2.5 text-[13px] text-body">
                                    <Icon name="clock" size={17} className="text-leaf" />
                                    Diantar {preorder.delivery.schedule}
                                </p>
                            )}
                            <div role="radiogroup" aria-label="Area antar" className="grid grid-cols-2 gap-2.5">
                                {preorder.delivery.zones.map((z) => (
                                    <SlotCard key={z.id} wide on={zone === z.id} onPick={() => setZone(z.id)} title={z.name} text={z.fee > 0 ? `Ongkir ${rupiah(z.fee)}` : 'Gratis ongkir'} />
                                ))}
                            </div>
                            {form.errors.zone_id && <span className="text-xs font-semibold text-danger">{form.errors.zone_id}</span>}
                        </div>
                    )}
                </section>

                {(isDelivery || !savedPhone) && (
                    <section className="flex flex-col gap-2.5">
                        <h2 className="m-0 text-base font-bold">Data pemesan</h2>
                        {!savedPhone && (
                            <Field label="Nomor WhatsApp" error={form.errors.phone}>
                                <input
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    placeholder="08xxxxxxxxxx"
                                    value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                    className="h-12 rounded-[14px] border border-line bg-white px-3.5 text-sm outline-none"
                                />
                            </Field>
                        )}
                        {isDelivery && (
                            <Field label="Alamat antar" error={form.errors.address_note}>
                                <textarea
                                    rows={2}
                                    placeholder={`Detail di ${zoneObj?.name ?? 'area'}: gedung, lantai, ruang, atau patokan`}
                                    value={form.data.address_note}
                                    onChange={(e) => form.setData('address_note', e.target.value)}
                                    className="rounded-[14px] border border-line bg-white px-3.5 py-3 text-sm outline-none"
                                />
                            </Field>
                        )}
                    </section>
                )}
                {form.errors.items && <p className="m-0 text-sm font-semibold text-[#A3281C]">{form.errors.items}</p>}

                <section className="flex flex-col gap-2.5">
                    <h2 className="m-0 text-base font-bold">Info menu</h2>
                    {preorder.labels.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {preorder.labels.map((l) =>
                                l === 'Halal' ? (
                                    <span key={l} className="inline-flex items-center gap-[5px] rounded-full bg-[#E3F1DF] py-1.5 pl-[9px] pr-3 text-xs font-semibold text-[#1F5A2D]">
                                        <Icon name="check" size={14} strokeWidth={2.2} />
                                        Halal
                                    </span>
                                ) : (
                                    <span key={l} className="rounded-full border border-line bg-white px-3 py-1.5 text-xs font-semibold text-[#3E4C41]">
                                        {l}
                                    </span>
                                ),
                            )}
                        </div>
                    )}
                    {preorder.description && <p className="m-0 text-[13px] leading-[1.55] text-[#3E4C41]">{preorder.description}</p>}
                </section>
            </form>
        </BuyerLayout>
    );
}

function readCartFor(preorderId) {
    const cart = readCart();
    return cart && cart.preorderId === preorderId ? cart : null;
}

function SlotCard({ on, onPick, title, note, text, wide = false }) {
    return (
        <label
            className={`flex ${wide ? 'min-w-0' : 'w-[156px]'} shrink-0 cursor-pointer flex-col gap-1.5 rounded-[18px] px-3.5 py-3 ${
                on ? 'border-2 border-leaf bg-[#F3F8F1]' : 'border border-line bg-white'
            }`}
        >
            <span className="flex items-center justify-between gap-2">
                <span className="text-sm font-bold">{title}</span>
                <input type="radio" name={`pilih-${title}`} checked={on} onChange={onPick} className="m-0 h-[18px] w-[18px] accent-leaf" />
            </span>
            {note && <span className="text-xs leading-[1.4] text-body">{note}</span>}
            <span className="text-xs leading-[1.4] text-muted">{text}</span>
        </label>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-[#3E4C41]">{label}</span>
            {children}
            {error && <span className="text-xs font-semibold text-[#A3281C]">{error}</span>}
        </label>
    );
}
