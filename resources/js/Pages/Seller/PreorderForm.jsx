import { Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import StatusPill from '@/Components/StatusPill';
import { PlateTile } from '@/Components/Plate';
import { rupiah } from '@/lib/format';

const LABEL_IDEAS = ['Halal', 'Pedas', 'Vegetarian', 'Manis', 'Best seller'];

let uid = 0;
const key = () => `k${++uid}`;

function jakartaNow(addHours = 0) {
    const d = new Date(Date.now() + addHours * 3600_000);
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
            .formatToParts(d)
            .map((p) => [p.type, p.value]),
    );
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

function slugify(value) {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/[\s-]+/g, '-')
        .slice(0, 60);
}

const emptyVariant = (name = '') => ({ _key: key(), id: null, name, price_delta: 0, locked: false });
const emptyProduct = () => ({ _key: key(), id: null, name: '', price: '', quota: '', sold: 0, locked: false, photo: null, photo_url: null, remove_photo: false, variants: [emptyVariant('Reguler')] });
const emptySlot = () => ({ _key: key(), id: null, place: '', place_note: '', date: '', start: '10:00', end: '12:00', locked: false });
const emptyZone = () => ({ _key: key(), id: null, name: '', fee: '', locked: false });
const emptyBundle = () => ({ _key: key(), id: null, name: '', price: '', quota: '', sold: 0, locked: false, items: [{ _key: key(), ref: '', qty: 1 }, { _key: key(), ref: '', qty: 1 }] });

/** Isi paket dari server menunjuk "menu ke-i:varian ke-j"; di form diganti kunci baris supaya aman saat menu diubah urutannya. */
function bundlesFromServer(bundles, products) {
    return (bundles ?? []).map((b) => ({
        ...b,
        _key: key(),
        quota: b.quota ?? '',
        items: b.items.map((item) => {
            const [pi, vi] = item.ref.split(':').map(Number);
            const p = products[pi];
            const v = p?.variants[vi];
            return { _key: key(), ref: p && v ? `${p._key}:${v._key}` : '', qty: item.qty };
        }),
    }));
}

export default function PreorderForm({ shop, preorder, suggestions }) {
    const editing = Boolean(preorder?.id);
    const stage = preorder?.stage ?? 'draft';
    const isDraft = stage === 'draft';

    const [initialProducts] = useState(() =>
        preorder?.products?.length
            ? preorder.products.map((p) => ({ ...p, _key: key(), photo: null, remove_photo: false, variants: p.variants.map((v) => ({ ...v, _key: key() })) }))
            : [emptyProduct()],
    );
    const form = useForm({
        title: preorder?.title ?? '',
        slug: preorder?.slug ?? '',
        description: preorder?.description ?? '',
        labels: preorder?.labels ?? [],
        opens_at: preorder?.opens_at || jakartaNow(),
        closes_at: preorder?.closes_at || '',
        quota: preorder?.quota ?? '',
        delivery_enabled: preorder?.delivery_enabled ?? false,
        delivery_date: preorder?.delivery_date ?? '',
        delivery_start: preorder?.delivery_start ?? '10:00',
        delivery_end: preorder?.delivery_end ?? '14:00',
        zones: preorder?.zones?.length ? preorder.zones.map((z) => ({ ...z, _key: key() })) : [emptyZone()],
        products: initialProducts,
        bundles: bundlesFromServer(preorder?.bundles, initialProducts),
        slots: preorder?.slots?.length ? preorder.slots.map((s) => ({ ...s, _key: key() })) : [emptySlot()],
        poster: null,
        story: null,
        remove_poster: false,
        remove_story: false,
    });
    const { data, setData, errors } = form;
    const [slugTouched, setSlugTouched] = useState(editing || Boolean(preorder?.slug));

    const setTitle = (value) => {
        setData((d) => ({ ...d, title: value, slug: slugTouched ? d.slug : slugify(value) }));
    };

    const updateProduct = (i, patch) => setData('products', data.products.map((p, idx) => (idx === i ? { ...p, ...patch } : p)));
    const updateVariant = (i, j, patch) =>
        updateProduct(i, { variants: data.products[i].variants.map((v, idx) => (idx === j ? { ...v, ...patch } : v)) });
    const updateSlot = (i, patch) => setData('slots', data.slots.map((s, idx) => (idx === i ? { ...s, ...patch } : s)));
    const updateBundle = (i, patch) => setData('bundles', data.bundles.map((b, idx) => (idx === i ? { ...b, ...patch } : b)));
    const menuOptions = data.products.flatMap((p) =>
        p.variants.map((v) => ({
            ref: `${p._key}:${v._key}`,
            label: `${p.name || 'Menu tanpa nama'}${p.variants.length > 1 || v.name !== 'Reguler' ? `, ${v.name || 'varian'}` : ''}`,
            price: Number(p.price || 0) + Number(v.price_delta || 0),
        })),
    );
    const updateZone = (i, patch) => setData('zones', data.zones.map((z, idx) => (idx === i ? { ...z, ...patch } : z)));
    const zonesLocked = data.zones.some((z) => z.locked);

    const summary = useMemo(() => {
        const prices = data.products.flatMap((p) => p.variants.map((v) => Number(p.price || 0) + Number(v.price_delta || 0))).filter((n) => n > 0);
        const menuQuota = data.products.reduce((sum, p) => sum + Number(p.quota || 0), 0);
        return {
            from: prices.length ? Math.min(...prices) : 0,
            quota: data.quota ? Math.min(Number(data.quota), menuQuota) : menuQuota,
        };
    }, [data.products, data.quota]);

    const submit = (publish) => {
        form.transform((d) => ({
            ...d,
            publish,
            ...(editing ? { _method: 'put' } : {}),
            labels: d.labels.filter(Boolean),
            products: d.products.map(({ _key, sold, locked, photo_url, ...p }) => ({ ...p, variants: p.variants.map(({ _key: k, locked: l, ...v }) => v) })),
            bundles: d.bundles.map(({ _key, sold, locked, ...b }) => ({
                ...b,
                items: b.items.map((item) => {
                    const [pk, vk] = item.ref.split(':');
                    const pi = d.products.findIndex((p) => p._key === pk);
                    const vi = pi < 0 ? -1 : d.products[pi].variants.findIndex((v) => v._key === vk);
                    return { ref: pi < 0 || vi < 0 ? '' : `${pi}:${vi}`, qty: item.qty };
                }),
            })),
            slots: d.slots.map(({ _key, locked, ...s }) => s),
            zones: d.delivery_enabled ? d.zones.map(({ _key, locked, ...z }) => ({ ...z, fee: z.fee === '' ? 0 : z.fee })) : [],
        }));
        form.post(editing ? `/seller/po/${preorder.id}` : '/seller/po', {
            forceFormData: true,
            queryStringArrayFormat: 'indices',
            preserveScroll: true,
            onError: () => {
                requestAnimationFrame(() => document.querySelector('[data-error="true"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
            },
        });
    };

    const title = editing ? `Ubah ${preorder.title}` : 'Buat PO';
    const firstError = Object.keys(errors).length > 0;

    const actions = (
        <div className="flex flex-col gap-2.5">
            {firstError && (
                <p className="m-0 rounded-xl bg-[#FCE4E1] px-3 py-2.5 text-[13px] font-semibold text-[#A3281C]">Ada isian yang perlu diperbaiki. Cek tanda merah di form.</p>
            )}
            {isDraft ? (
                <>
                    <button type="button" disabled={form.processing} onClick={() => submit(true)} className="h-12 rounded-xl bg-forest text-[15px] font-bold text-white disabled:opacity-60">
                        Terbitkan PO
                    </button>
                    <button type="button" disabled={form.processing} onClick={() => submit(false)} className="h-12 rounded-xl border border-[#DCE3D9] bg-white text-[15px] font-semibold disabled:opacity-60">
                        Simpan draf
                    </button>
                    <p className="m-0 text-xs text-muted">Draf tidak terlihat pembeli. PO yang terbit langsung tampil di beranda Jajapo sesuai waktu buka.</p>
                </>
            ) : (
                <>
                    <button type="button" disabled={form.processing} onClick={() => submit(false)} className="h-12 rounded-xl bg-forest text-[15px] font-bold text-white disabled:opacity-60">
                        Simpan perubahan
                    </button>
                    <p className="m-0 text-xs text-muted">Perubahan harga hanya berlaku untuk pesanan baru. Pesanan yang sudah masuk tetap memakai harga lama.</p>
                </>
            )}
        </div>
    );

    return (
        <PanelLayout variant="seller" active="po" title={title} shop={shop}>
            <form
                onSubmit={(e) => e.preventDefault()}
                className="flex flex-col gap-4 px-4 pb-6 pt-4 lg:gap-5 lg:px-8 lg:pt-7"
            >
                <div className="flex items-center gap-3">
                    <Link href="/seller/po" aria-label="Kembali ke daftar PO" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-panel-line bg-white text-[#141A16]">
                        <Icon name="arrowLeft" size={20} strokeWidth={1.9} />
                    </Link>
                    <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                        <h1 className="m-0 truncate text-[19px] font-bold tracking-[-0.01em] lg:text-[26px]">{editing ? preorder.title : 'Buat PO'}</h1>
                        <span className="text-[13px] text-muted">{editing ? 'Ubah isi PO' : `PO baru untuk ${shop.name}`}</span>
                    </div>
                    {editing && <StatusPill status={`po_${stage}`} />}
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start lg:gap-5">
                    <div className="flex flex-col gap-4">
                        <Section title="Info PO">
                            <Field label="Judul PO" error={errors.title}>
                                <input value={data.title} onChange={(e) => setTitle(e.target.value)} placeholder="Mac n Cheese Panggang" className={inputCls} maxLength={80} />
                            </Field>
                            <Field label="Alamat PO" error={errors.slug} hint={`jajapo.app/${shop.slug}/${data.slug || 'nama-po'}`}>
                                <input
                                    value={data.slug}
                                    onChange={(e) => {
                                        setSlugTouched(true);
                                        setData('slug', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''));
                                    }}
                                    className={inputCls}
                                    maxLength={60}
                                />
                            </Field>
                            <Field label="Deskripsi (opsional)" error={errors.description}>
                                <textarea
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                    placeholder="Isi, porsi, dan info penting untuk pembeli."
                                    className="rounded-[14px] border border-line bg-white px-3.5 py-3 text-sm outline-none"
                                />
                            </Field>
                            <LabelPicker labels={data.labels} onChange={(labels) => setData('labels', labels)} error={errors.labels} />
                        </Section>

                        <Section title="Gambar" description="Poster tampil di kartu PO. Gambar story ikut terkirim saat kamu menekan Bagikan, siap dipasang di story.">
                            <div className="grid grid-cols-2 gap-3">
                                <ImageInput
                                    label="Poster"
                                    hint="Persegi, maks. 4 MB"
                                    ratio="aspect-square"
                                    current={data.remove_poster ? null : preorder?.poster_url}
                                    file={data.poster}
                                    error={errors.poster}
                                    onPick={(file) => setData((d) => ({ ...d, poster: file, remove_poster: false }))}
                                    onRemove={() => setData((d) => ({ ...d, poster: null, remove_poster: Boolean(preorder?.poster_url) }))}
                                />
                                <ImageInput
                                    label="Gambar story"
                                    hint="9:16, maks. 6 MB"
                                    ratio="aspect-[9/16]"
                                    current={data.remove_story ? null : preorder?.story_url}
                                    file={data.story}
                                    error={errors.story}
                                    onPick={(file) => setData((d) => ({ ...d, story: file, remove_story: false }))}
                                    onRemove={() => setData((d) => ({ ...d, story: null, remove_story: Boolean(preorder?.story_url) }))}
                                />
                            </div>
                        </Section>

                        <Section title="Waktu PO">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Buka" error={errors.opens_at}>
                                    <input type="datetime-local" value={data.opens_at} onChange={(e) => setData('opens_at', e.target.value)} className={inputCls} />
                                </Field>
                                <Field label="Tutup" error={errors.closes_at}>
                                    <input type="datetime-local" value={data.closes_at} min={data.opens_at} onChange={(e) => setData('closes_at', e.target.value)} className={inputCls} />
                                </Field>
                            </div>
                            <span className="text-xs text-muted">Waktu dalam WIB. Pesanan yang belum dibayar saat PO tutup masih bisa dibayar sampai batasnya.</span>
                        </Section>

                        <Section title="Menu" error={typeof errors.products === 'string' ? errors.products : null}>
                            {data.products.map((p, i) => (
                                <ProductEditor
                                    key={p._key}
                                    product={p}
                                    index={i}
                                    errors={errors}
                                    canRemove={data.products.length > 1 && !p.locked}
                                    onChange={(patch) => updateProduct(i, patch)}
                                    onVariant={(j, patch) => updateVariant(i, j, patch)}
                                    onRemove={() => setData('products', data.products.filter((_, idx) => idx !== i))}
                                />
                            ))}
                            <button
                                type="button"
                                onClick={() => setData('products', [...data.products, emptyProduct()])}
                                disabled={data.products.length >= 20}
                                className="flex h-11 items-center justify-center gap-2 rounded-xl border border-dashed border-[#BFD0BA] bg-[#F6F8F5] text-sm font-semibold text-forest disabled:opacity-50"
                            >
                                <Icon name="plus" size={18} strokeWidth={2.2} />
                                Tambah menu
                            </button>
                            <Field label="Batas total porsi PO (opsional)" error={errors.quota} hint="Kosongkan kalau cukup memakai kuota tiap menu. Isi kalau dapur hanya sanggup sekian porsi untuk semua menu.">
                                <input
                                    inputMode="numeric"
                                    value={data.quota ?? ''}
                                    onChange={(e) => setData('quota', e.target.value.replace(/\D/g, ''))}
                                    placeholder="Contoh: 100"
                                    className={inputCls}
                                />
                            </Field>
                        </Section>

                        <Section
                            title="Paket (opsional)"
                            description="Gabungkan beberapa menu dengan harga paket. Kuota paket ikut memotong kuota menu isinya."
                            error={typeof errors.bundles === 'string' ? errors.bundles : null}
                        >
                            {data.bundles.map((b, i) => (
                                <BundleEditor
                                    key={b._key}
                                    bundle={b}
                                    index={i}
                                    options={menuOptions}
                                    errors={errors}
                                    onChange={(patch) => updateBundle(i, patch)}
                                    onRemove={() => setData('bundles', data.bundles.filter((_, idx) => idx !== i))}
                                />
                            ))}
                            <button
                                type="button"
                                onClick={() => setData('bundles', [...data.bundles, emptyBundle()])}
                                disabled={data.bundles.length >= 10}
                                className="flex h-11 items-center justify-center gap-2 rounded-xl border border-dashed border-[#BFD0BA] bg-[#F6F8F5] text-sm font-semibold text-forest disabled:opacity-50"
                            >
                                <Icon name="plus" size={18} strokeWidth={2.2} />
                                Tambah paket
                            </button>
                        </Section>

                        <Section
                            title="Titik ambil"
                            description="Tulis tempat ambil khusus untuk PO ini. Satu PO boleh punya beberapa tempat atau beberapa jam."
                            error={typeof errors.slots === 'string' ? errors.slots : null}
                        >
                            <datalist id="saran-tempat">
                                {suggestions.places.map((p) => (
                                    <option key={p} value={p} />
                                ))}
                            </datalist>
                            {data.slots.length === 0 && (
                                <p className="m-0 rounded-xl bg-[#F6F8F5] px-3 py-2.5 text-[13px] text-muted">Tidak ada titik ambil. Pembeli hanya bisa memilih diantar.</p>
                            )}
                            {data.slots.map((s, i) => (
                                <SlotEditor
                                    key={s._key}
                                    slot={s}
                                    index={i}
                                    errors={errors}
                                    onChange={(patch) => updateSlot(i, patch)}
                                    onRemove={() => setData('slots', data.slots.filter((_, idx) => idx !== i))}
                                />
                            ))}
                            <button
                                type="button"
                                onClick={() => {
                                    const last = data.slots[data.slots.length - 1];
                                    setData('slots', [...data.slots, { ...emptySlot(), ...(last ? { place: last.place, place_note: last.place_note, date: last.date } : {}) }]);
                                }}
                                className="flex h-11 items-center justify-center gap-2 rounded-xl border border-dashed border-[#BFD0BA] bg-[#F6F8F5] text-sm font-semibold text-forest"
                            >
                                <Icon name="plus" size={18} strokeWidth={2.2} />
                                Tambah titik atau jam ambil
                            </button>
                        </Section>

                        <Section title="Pengantaran" error={typeof errors.zones === 'string' ? errors.zones : null}>
                            <label className="flex cursor-pointer items-center gap-3">
                                <Icon name="truck" size={20} className="text-leaf" />
                                <span className="flex flex-1 flex-col gap-px">
                                    <span className="text-sm font-semibold">Terima pesanan antar</span>
                                    <span className="text-xs text-muted">Tentukan area yang kamu layani dan ongkir tiap area.</span>
                                </span>
                                <input
                                    type="checkbox"
                                    checked={data.delivery_enabled}
                                    disabled={zonesLocked && data.delivery_enabled}
                                    onChange={(e) => setData('delivery_enabled', e.target.checked)}
                                    className="h-5 w-5 accent-[#1F4B2C]"
                                />
                            </label>
                            {data.delivery_enabled && (
                                <>
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-[1.4fr_1fr_1fr]">
                                        <Field label="Tanggal antar" error={errors.delivery_date} className="col-span-2 sm:col-span-1">
                                            <input type="date" value={data.delivery_date} onChange={(e) => setData('delivery_date', e.target.value)} className={`${inputCls} px-2.5`} />
                                        </Field>
                                        <Field label="Mulai" error={errors.delivery_start}>
                                            <input type="time" value={data.delivery_start} onChange={(e) => setData('delivery_start', e.target.value)} className={`${inputCls} px-2.5`} />
                                        </Field>
                                        <Field label="Selesai" error={errors.delivery_end}>
                                            <input type="time" value={data.delivery_end} onChange={(e) => setData('delivery_end', e.target.value)} className={`${inputCls} px-2.5`} />
                                        </Field>
                                    </div>

                                    <datalist id="saran-area">
                                        {suggestions.zones.map((z) => (
                                            <option key={z} value={z} />
                                        ))}
                                    </datalist>
                                    <div className="flex flex-col gap-2">
                                        <span className="text-[13px] font-semibold text-body">Area antar dan ongkir</span>
                                        {data.zones.map((z, i) => (
                                            <div key={z._key} className="flex flex-col gap-1">
                                                <div className="flex items-center gap-2">
                                                    <input
                                                        aria-label={`Nama area ${i + 1}`}
                                                        list="saran-area"
                                                        value={z.name}
                                                        onChange={(e) => updateZone(i, { name: e.target.value })}
                                                        placeholder="Contoh: FEB, atau Kos sekitar Pogung"
                                                        maxLength={80}
                                                        className={`${inputCls} flex-[1.5]`}
                                                    />
                                                    <span className="flex-1">
                                                        <MoneyInput value={z.fee} placeholder="0" onChange={(v) => updateZone(i, { fee: v })} />
                                                    </span>
                                                    <button
                                                        type="button"
                                                        aria-label={`Hapus area ${z.name || i + 1}`}
                                                        disabled={data.zones.length === 1 || z.locked}
                                                        onClick={() => setData('zones', data.zones.filter((_, idx) => idx !== i))}
                                                        className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-muted hover:text-danger disabled:opacity-30"
                                                    >
                                                        <Icon name="x" size={18} />
                                                    </button>
                                                </div>
                                                {(errors[`zones.${i}.name`] || errors[`zones.${i}.fee`]) && (
                                                    <span className="text-xs font-semibold text-danger" data-error="true">
                                                        {errors[`zones.${i}.name`] || errors[`zones.${i}.fee`]}
                                                    </span>
                                                )}
                                                {z.locked && <span className="text-xs text-muted">Sudah dipesan, tidak bisa dihapus</span>}
                                            </div>
                                        ))}
                                        <button
                                            type="button"
                                            disabled={data.zones.length >= 20}
                                            onClick={() => setData('zones', [...data.zones, emptyZone()])}
                                            className="flex h-10 items-center gap-1.5 self-start rounded-xl px-1 text-[13px] font-semibold text-forest disabled:opacity-50"
                                        >
                                            <Icon name="plus" size={16} strokeWidth={2.2} />
                                            Tambah area
                                        </button>
                                        <span className="text-xs text-muted">Isi ongkir 0 untuk gratis ongkir. Pembeli memilih area, lalu menulis detail alamatnya.</span>
                                    </div>
                                </>
                            )}
                        </Section>

                        <div className="lg:hidden">
                            <PanelCard className="p-4">{actions}</PanelCard>
                        </div>
                    </div>

                    <aside className="hidden flex-col gap-4 lg:sticky lg:top-6 lg:flex">
                        <PanelCard className="flex flex-col gap-3.5 p-4">
                            <h2 className="m-0 text-[15px] font-bold">Tampilan di pembeli</h2>
                            <div className="flex items-center gap-3 rounded-2xl bg-[#F6F8F5] p-3">
                                <PlateTile seed={preorder?.id ?? 7} image={data.remove_poster ? null : preorder?.poster_url} className="h-16 w-16 shrink-0 rounded-[14px]" plateSize={56} />
                                <span className="flex min-w-0 flex-col gap-0.5">
                                    <span className="truncate text-sm font-bold">{data.title || 'Judul PO'}</span>
                                    <span className="text-xs text-muted">{shop.name}</span>
                                    <span className="text-[13px] font-bold text-forest">{summary.from ? `Mulai ${rupiah(summary.from)}` : 'Harga belum diisi'}</span>
                                </span>
                            </div>
                            <dl className="m-0 flex flex-col gap-2 text-[13px]">
                                <Row label="Menu" value={`${data.products.length} menu, ${data.products.reduce((n, p) => n + p.variants.length, 0)} varian`} />
                                <Row label="Kuota" value={summary.quota ? `${summary.quota} porsi` : '-'} />
                                <Row label="Titik ambil" value={data.slots.length ? `${new Set(data.slots.map((x) => x.place).filter(Boolean)).size || data.slots.length} tempat, ${data.slots.length} jadwal` : '-'} />
                                <Row label="Antar" value={data.delivery_enabled ? `${data.zones.length} area` : 'Tidak'} />
                            </dl>
                        </PanelCard>
                        <PanelCard className="p-4">{actions}</PanelCard>
                    </aside>
                </div>
            </form>
        </PanelLayout>
    );
}

const inputCls = 'h-12 w-full min-w-0 rounded-[14px] border border-line bg-white px-3.5 text-sm outline-none';

function Row({ label, value }) {
    return (
        <div className="flex justify-between gap-3">
            <dt className="text-muted">{label}</dt>
            <dd className="m-0 text-right font-semibold">{value}</dd>
        </div>
    );
}

function Section({ title, description, error, children }) {
    return (
        <PanelCard className="flex flex-col gap-3.5 p-4" data-error={error ? 'true' : undefined}>
            <div className="flex flex-col gap-1">
                <h2 className="m-0 text-base font-bold">{title}</h2>
                {description && <p className="m-0 text-[13px] text-muted">{description}</p>}
            </div>
            {error && <p className="m-0 rounded-xl bg-[#FCE4E1] px-3 py-2 text-[13px] font-semibold text-[#A3281C]">{error}</p>}
            {children}
        </PanelCard>
    );
}

function Field({ label, error, hint, children, className = '' }) {
    return (
        <label className={`flex min-w-0 flex-col gap-1.5 ${className}`} data-error={error ? 'true' : undefined}>
            <span className="text-[13px] font-semibold text-body">{label}</span>
            {children}
            {hint && !error && <span className="text-xs text-muted">{hint}</span>}
            {error && <span className="text-xs font-semibold text-danger">{error}</span>}
        </label>
    );
}

function MoneyInput({ value, onChange, placeholder, allowNegative = false }) {
    return (
        <span className="flex h-12 min-w-0 items-center gap-1.5 rounded-[14px] border border-line bg-white px-3.5">
            <span className="text-sm text-muted">Rp</span>
            <input
                inputMode="numeric"
                value={value ?? ''}
                placeholder={placeholder}
                onChange={(e) => {
                    const raw = e.target.value;
                    const negative = allowNegative && raw.trim().startsWith('-');
                    const digits = raw.replace(/\D/g, '');
                    onChange(digits === '' ? (negative ? '-' : '') : negative ? `-${digits}` : digits);
                }}
                className="tabular h-full w-full min-w-0 bg-transparent text-sm outline-none"
            />
        </span>
    );
}

function LabelPicker({ labels, onChange, error }) {
    const [text, setText] = useState('');
    const add = (value) => {
        const v = value.trim();
        if (!v || labels.includes(v) || labels.length >= 4) return;
        onChange([...labels, v]);
        setText('');
    };

    return (
        <div className="flex flex-col gap-1.5" data-error={error ? 'true' : undefined}>
            <span className="text-[13px] font-semibold text-body">Label (opsional, maks. 4)</span>
            <div className="flex flex-wrap gap-2">
                {labels.map((l) => (
                    <button
                        key={l}
                        type="button"
                        onClick={() => onChange(labels.filter((x) => x !== l))}
                        aria-label={`Hapus label ${l}`}
                        className="flex h-8 items-center gap-1 rounded-full bg-forest pl-3 pr-2 text-[13px] font-semibold text-white"
                    >
                        {l}
                        <Icon name="x" size={14} strokeWidth={2.2} />
                    </button>
                ))}
                {LABEL_IDEAS.filter((l) => !labels.includes(l))
                    .slice(0, labels.length >= 4 ? 0 : 5)
                    .map((l) => (
                        <button key={l} type="button" onClick={() => add(l)} className="flex h-8 items-center gap-1 rounded-full border border-[#DCE3D9] bg-white px-3 text-[13px] font-semibold text-body">
                            <Icon name="plus" size={14} strokeWidth={2.2} />
                            {l}
                        </button>
                    ))}
            </div>
            {labels.length < 4 && (
                <input
                    value={text}
                    onChange={(e) => setText(e.target.value.slice(0, 24))}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ',') {
                            e.preventDefault();
                            add(text);
                        }
                    }}
                    onBlur={() => add(text)}
                    placeholder="Label lain, tekan Enter"
                    className={inputCls}
                />
            )}
            {error && <span className="text-xs font-semibold text-danger">{error}</span>}
        </div>
    );
}

function ImageInput({ label, hint, ratio, current, file, error, onPick, onRemove }) {
    const [preview, setPreview] = useState(null);
    useEffect(() => {
        if (!file) {
            setPreview(null);
            return undefined;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);
    const src = preview || current;

    return (
        <div className="flex min-w-0 flex-col gap-1.5" data-error={error ? 'true' : undefined}>
            <span className="text-[13px] font-semibold text-body">{label}</span>
            <label className={`relative flex ${ratio} max-h-72 cursor-pointer flex-col items-center justify-center gap-1.5 overflow-hidden rounded-[14px] border border-dashed border-[#BFD0BA] bg-[#F6F8F5] text-center`}>
                {src ? (
                    <img src={src} alt={`Pratinjau ${label.toLowerCase()}`} className="absolute inset-0 h-full w-full object-cover" />
                ) : (
                    <>
                        <Icon name="image" size={26} className="text-leaf" />
                        <span className="px-2 text-xs font-semibold text-forest">Pilih gambar</span>
                    </>
                )}
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="sr-only"
                    onChange={(e) => {
                        const f = e.target.files?.[0];
                        if (f) onPick(f);
                        e.target.value = '';
                    }}
                />
            </label>
            <div className="flex items-center justify-between gap-2">
                <span className="text-xs text-muted">{hint}</span>
                {src && (
                    <button type="button" onClick={onRemove} className="text-xs font-semibold text-danger">
                        Hapus
                    </button>
                )}
            </div>
            {error && <span className="text-xs font-semibold text-danger">{error}</span>}
        </div>
    );
}

function ProductEditor({ product, index, errors, canRemove, onChange, onVariant, onRemove }) {
    const e = (field) => errors[`products.${index}.${field}`];
    const variantsError = typeof errors[`products.${index}.variants`] === 'string' ? errors[`products.${index}.variants`] : null;

    return (
        <div className="flex flex-col gap-3 rounded-2xl border border-line p-3.5" data-error={variantsError ? 'true' : undefined}>
            <div className="flex items-center justify-between gap-2">
                <span className="text-[13px] font-bold text-muted">Menu {index + 1}</span>
                {product.locked ? (
                    <span className="text-xs font-semibold text-muted">Sudah dipesan {product.sold} porsi</span>
                ) : (
                    canRemove && (
                        <button type="button" onClick={onRemove} className="flex h-8 items-center gap-1 text-xs font-semibold text-danger">
                            <Icon name="trash" size={15} />
                            Hapus menu
                        </button>
                    )
                )}
            </div>
            <div className="flex gap-3">
                <div className="w-[88px] shrink-0">
                    <ImageInput
                        label="Foto"
                        hint="Maks. 4 MB"
                        ratio="aspect-square"
                        current={product.remove_photo ? null : product.photo_url}
                        file={product.photo}
                        error={e('photo')}
                        onPick={(file) => onChange({ photo: file, remove_photo: false })}
                        onRemove={() => onChange({ photo: null, remove_photo: Boolean(product.photo_url) })}
                    />
                </div>
                <div className="flex min-w-0 flex-1 flex-col gap-3">
                    <Field label="Nama menu" error={e('name')}>
                        <input value={product.name} onChange={(ev) => onChange({ name: ev.target.value })} placeholder="Mac n Cheese Panggang" maxLength={60} className={inputCls} />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Harga dasar" error={e('price')}>
                            <MoneyInput value={product.price} onChange={(v) => onChange({ price: v })} placeholder="12000" />
                        </Field>
                        <Field label="Kuota porsi" error={e('quota')} hint={product.sold > 0 ? `Minimal ${product.sold}` : null}>
                            <input inputMode="numeric" value={product.quota ?? ''} onChange={(ev) => onChange({ quota: ev.target.value.replace(/\D/g, '') })} placeholder="60" className={`tabular ${inputCls}`} />
                        </Field>
                    </div>
                </div>
            </div>

            <div className="flex flex-col gap-2">
                <span className="text-[13px] font-semibold text-body">Varian</span>
                {variantsError && <span className="text-xs font-semibold text-danger">{variantsError}</span>}
                {product.variants.map((v, j) => {
                    const price = Number(product.price || 0) + Number(v.price_delta || 0);
                    return (
                        <div key={v._key} className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <input
                                    aria-label={`Nama varian ${j + 1}`}
                                    value={v.name}
                                    onChange={(ev) => onVariant(j, { name: ev.target.value })}
                                    placeholder="Original"
                                    maxLength={40}
                                    className={`${inputCls} flex-[1.3]`}
                                />
                                <span className="flex-1">
                                    <MoneyInput value={v.price_delta === 0 ? '' : v.price_delta} placeholder="+0" allowNegative onChange={(val) => onVariant(j, { price_delta: val === '' ? 0 : val })} />
                                </span>
                                <button
                                    type="button"
                                    aria-label={`Hapus varian ${v.name || j + 1}`}
                                    disabled={product.variants.length === 1 || v.locked}
                                    onClick={() => onChange({ variants: product.variants.filter((_, idx) => idx !== j) })}
                                    className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-muted hover:text-danger disabled:opacity-30"
                                >
                                    <Icon name="x" size={18} />
                                </button>
                            </div>
                            <span className="text-xs text-muted">
                                {e(`variants.${j}.name`) || e(`variants.${j}.price_delta`) ? (
                                    <span className="font-semibold text-danger">{e(`variants.${j}.name`) || e(`variants.${j}.price_delta`)}</span>
                                ) : (
                                    <>
                                        Harga jual <span className="tabular font-semibold text-ink">{price > 0 ? rupiah(price) : '-'}</span>
                                        {v.locked && ' · sudah dipesan, tidak bisa dihapus'}
                                    </>
                                )}
                            </span>
                        </div>
                    );
                })}
                <button
                    type="button"
                    disabled={product.variants.length >= 10}
                    onClick={() => onChange({ variants: [...product.variants, emptyVariant()] })}
                    className="flex h-10 items-center gap-1.5 self-start rounded-xl px-1 text-[13px] font-semibold text-forest disabled:opacity-50"
                >
                    <Icon name="plus" size={16} strokeWidth={2.2} />
                    Tambah varian
                </button>
            </div>
        </div>
    );
}

function SlotEditor({ slot, index, errors, onChange, onRemove }) {
    const e = (field) => errors[`slots.${index}.${field}`];

    return (
        <div className="flex flex-col gap-3 rounded-2xl border border-line p-3.5">
            <div className="flex items-center justify-between gap-2">
                <span className="text-[13px] font-bold text-muted">Titik {index + 1}</span>
                {slot.locked ? (
                    <span className="text-xs font-semibold text-muted">Sudah dipilih pembeli</span>
                ) : (
                    <button type="button" onClick={onRemove} className="flex h-8 items-center gap-1 text-xs font-semibold text-danger">
                        <Icon name="trash" size={15} />
                        Hapus
                    </button>
                )}
            </div>
            <Field label="Tempat" error={e('place')}>
                <input
                    list="saran-tempat"
                    value={slot.place}
                    onChange={(ev) => onChange({ place: ev.target.value })}
                    placeholder="Contoh: Lobi KPTU FT"
                    maxLength={80}
                    className={inputCls}
                />
            </Field>
            <Field label="Patokan (opsional)" error={e('place_note')}>
                <input
                    value={slot.place_note ?? ''}
                    onChange={(ev) => onChange({ place_note: ev.target.value })}
                    placeholder="Contoh: meja panitia depan musala"
                    maxLength={160}
                    className={inputCls}
                />
            </Field>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-[1.4fr_1fr_1fr]">
                <Field label="Tanggal" error={e('date')} className="col-span-2 sm:col-span-1">
                    <input type="date" value={slot.date} onChange={(ev) => onChange({ date: ev.target.value })} className={`${inputCls} px-2.5`} />
                </Field>
                <Field label="Mulai" error={e('start')}>
                    <input type="time" value={slot.start} onChange={(ev) => onChange({ start: ev.target.value })} className={`${inputCls} px-2.5`} />
                </Field>
                <Field label="Selesai" error={e('end')}>
                    <input type="time" value={slot.end} onChange={(ev) => onChange({ end: ev.target.value })} className={`${inputCls} px-2.5`} />
                </Field>
            </div>
        </div>
    );
}

function BundleEditor({ bundle, index, options, errors, onChange, onRemove }) {
    const e = (field) => errors[`bundles.${index}.${field}`];
    const normal = bundle.items.reduce((sum, item) => sum + (options.find((o) => o.ref === item.ref)?.price ?? 0) * Number(item.qty || 0), 0);
    const itemsError = typeof errors[`bundles.${index}.items`] === 'string' ? errors[`bundles.${index}.items`] : null;
    const setItem = (j, patch) => onChange({ items: bundle.items.map((it, idx) => (idx === j ? { ...it, ...patch } : it)) });

    return (
        <div className="flex flex-col gap-3 rounded-2xl border border-line p-3.5" data-error={itemsError ? 'true' : undefined}>
            <div className="flex items-center justify-between gap-2">
                <span className="text-[13px] font-bold text-muted">Paket {index + 1}</span>
                {bundle.locked ? (
                    <span className="text-xs font-semibold text-muted">Sudah dipesan {bundle.sold} paket, isi dikunci</span>
                ) : (
                    <button type="button" onClick={onRemove} className="flex h-8 items-center gap-1 text-xs font-semibold text-danger">
                        <Icon name="trash" size={15} />
                        Hapus paket
                    </button>
                )}
            </div>
            <Field label="Nama paket" error={e('name')}>
                <input value={bundle.name} onChange={(ev) => onChange({ name: ev.target.value })} placeholder="Paket Hemat Berdua" maxLength={60} className={inputCls} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
                <Field label="Harga paket" error={e('price')}>
                    <MoneyInput value={bundle.price} onChange={(v) => onChange({ price: v })} placeholder="25000" />
                </Field>
                <Field label="Kuota paket (opsional)" error={e('quota')}>
                    <input inputMode="numeric" value={bundle.quota ?? ''} onChange={(ev) => onChange({ quota: ev.target.value.replace(/\D/g, '') })} placeholder="Ikut menu" className={`tabular ${inputCls}`} />
                </Field>
            </div>
            <div className="flex flex-col gap-2">
                <span className="text-[13px] font-semibold text-body">Isi paket</span>
                {itemsError && <span className="text-xs font-semibold text-danger">{itemsError}</span>}
                {bundle.items.map((item, j) => (
                    <div key={item._key} className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <div className="min-w-0 flex-1">
                                <select
                                    aria-label={`Isi paket ${j + 1}`}
                                    value={item.ref}
                                    disabled={bundle.locked}
                                    onChange={(ev) => setItem(j, { ref: ev.target.value })}
                                    className={`${inputCls} disabled:bg-[#F6F8F5]`}
                                >
                                    <option value="">Pilih menu</option>
                                    {options.map((o) => (
                                        <option key={o.ref} value={o.ref}>
                                            {o.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="w-[68px] shrink-0">
                                <input
                                    aria-label={`Jumlah isi paket ${j + 1}`}
                                    inputMode="numeric"
                                    value={item.qty}
                                    disabled={bundle.locked}
                                    onChange={(ev) => setItem(j, { qty: ev.target.value.replace(/\D/g, '').slice(0, 2) })}
                                    className={`tabular ${inputCls} px-2 text-center disabled:bg-[#F6F8F5]`}
                                />
                            </div>
                            <button
                                type="button"
                                aria-label={`Hapus isi paket ${j + 1}`}
                                disabled={bundle.locked || bundle.items.length === 1}
                                onClick={() => onChange({ items: bundle.items.filter((_, idx) => idx !== j) })}
                                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-muted hover:text-danger disabled:opacity-30"
                            >
                                <Icon name="x" size={18} />
                            </button>
                        </div>
                        {(e(`items.${j}.ref`) || e(`items.${j}.qty`)) && <span className="text-xs font-semibold text-danger">{e(`items.${j}.ref`) || e(`items.${j}.qty`)}</span>}
                    </div>
                ))}
                {!bundle.locked && (
                    <button
                        type="button"
                        disabled={bundle.items.length >= 10}
                        onClick={() => onChange({ items: [...bundle.items, { _key: key(), ref: '', qty: 1 }] })}
                        className="flex h-10 items-center gap-1.5 self-start rounded-xl px-1 text-[13px] font-semibold text-forest disabled:opacity-50"
                    >
                        <Icon name="plus" size={16} strokeWidth={2.2} />
                        Tambah isi
                    </button>
                )}
                <span className="text-xs text-muted">
                    Harga satuan {normal ? rupiah(normal) : '-'}
                    {normal > Number(bundle.price || 0) && Number(bundle.price) > 0 && <span className="font-semibold text-leaf"> · pembeli hemat {rupiah(normal - Number(bundle.price))}</span>}
                </span>
            </div>
        </div>
    );
}
