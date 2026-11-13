import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import BuyerLayout, { TopBar } from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';
import { rupiah } from '@/lib/format';

export default function Complaint({ order, reasons }) {
    const { auth } = usePage().props;
    const form = useForm({
        reason: '',
        description: '',
        amount: String(order.max_amount),
        refund_bank: '',
        refund_number: '',
        refund_name: auth?.user?.name ?? '',
        photo: null,
    });
    const { data, setData, errors } = form;
    const [preview, setPreview] = useState(null);

    useEffect(() => {
        if (!data.photo) {
            setPreview(null);
            return undefined;
        }
        const url = URL.createObjectURL(data.photo);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [data.photo]);

    const submit = (e) => {
        e.preventDefault();
        form.post(`/pesanan/${order.code}/komplain`, { forceFormData: true, preserveScroll: true });
    };

    return (
        <BuyerLayout title={`Komplain ${order.code}`} active="orders">
            <TopBar back={`/pesanan/${order.code}`} title="Ajukan komplain" />

            <form onSubmit={submit} className="flex flex-col gap-4">
                <section className="flex flex-col gap-1 rounded-[20px] border border-line bg-white p-4">
                    <span className="text-xs text-muted">
                        {order.code}, {order.shop_name}
                    </span>
                    <span className="text-[15px] font-bold">{order.title}</span>
                    <span className="text-[13px] text-body">{order.items}</span>
                    <span className="text-xs text-muted">{order.place}</span>
                </section>

                <Section title="Apa masalahnya?" error={errors.reason}>
                    <div role="radiogroup" aria-label="Alasan komplain" className="flex flex-col gap-2">
                        {reasons.map((r) => (
                            <label
                                key={r.key}
                                className={`flex min-h-12 cursor-pointer items-center justify-between gap-3 rounded-[14px] px-3.5 ${
                                    data.reason === r.key ? 'border-2 border-leaf bg-[#F3F8F1]' : 'border border-line bg-white'
                                }`}
                            >
                                <span className="text-sm font-semibold">{r.label}</span>
                                <input type="radio" name="reason" checked={data.reason === r.key} onChange={() => setData('reason', r.key)} className="h-[18px] w-[18px] accent-leaf" />
                            </label>
                        ))}
                    </div>
                </Section>

                <Section title="Ceritakan">
                    <Field label="Apa yang terjadi" error={errors.description}>
                        <textarea
                            rows={4}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Contoh: datang jam 10.30 di titik ambil, penjual tidak ada sampai jam 12.00."
                            maxLength={1000}
                            className="rounded-[14px] border border-line bg-white px-3.5 py-3 text-sm outline-none"
                        />
                    </Field>
                    <div className="flex flex-col gap-1.5">
                        <span className="text-[13px] font-semibold text-[#3E4C41]">Foto bukti (opsional)</span>
                        <label className="relative flex h-36 cursor-pointer flex-col items-center justify-center gap-1.5 overflow-hidden rounded-[14px] border border-dashed border-[#BFD0BA] bg-[#F6F8F5]">
                            {preview ? (
                                <img src={preview} alt="Pratinjau foto bukti" className="absolute inset-0 h-full w-full object-cover" />
                            ) : (
                                <>
                                    <Icon name="image" size={26} className="text-leaf" />
                                    <span className="text-xs font-semibold text-forest">Pilih foto</span>
                                </>
                            )}
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="sr-only"
                                onChange={(e) => {
                                    const f = e.target.files?.[0];
                                    if (f) setData('photo', f);
                                    e.target.value = '';
                                }}
                            />
                        </label>
                        {data.photo && (
                            <button type="button" onClick={() => setData('photo', null)} className="self-end text-xs font-semibold text-danger">
                                Hapus foto
                            </button>
                        )}
                        {errors.photo && <span className="text-xs font-semibold text-[#A3281C]">{errors.photo}</span>}
                    </div>
                </Section>

                <Section title="Refund">
                    <Field label="Nominal yang diminta" error={errors.amount} hint={`Maksimal ${rupiah(order.max_amount)}, sesuai harga pesanan dan ongkir.`}>
                        <span className="flex h-12 items-center gap-1.5 rounded-[14px] border border-line bg-white px-3.5">
                            <span className="text-sm text-muted">Rp</span>
                            <input
                                inputMode="numeric"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value.replace(/\D/g, ''))}
                                className="tabular h-full w-full bg-transparent text-sm outline-none"
                            />
                        </span>
                    </Field>
                    <Field label="Bank atau e-wallet" error={errors.refund_bank}>
                        <input value={data.refund_bank} onChange={(e) => setData('refund_bank', e.target.value)} placeholder="BCA, BNI, GoPay, DANA" className={inputCls} />
                    </Field>
                    <Field label="Nomor rekening atau e-wallet" error={errors.refund_number}>
                        <input inputMode="numeric" value={data.refund_number} onChange={(e) => setData('refund_number', e.target.value.replace(/\D/g, ''))} className={inputCls} />
                    </Field>
                    <Field label="Nama pemilik" error={errors.refund_name}>
                        <input value={data.refund_name} onChange={(e) => setData('refund_name', e.target.value)} className={inputCls} />
                    </Field>
                </Section>

                <p className="m-0 text-xs leading-[1.5] text-muted">
                    Penjual punya waktu 24 jam untuk menanggapi. Kalau penjual menolak atau tidak menjawab, super admin yang memutuskan. Batas komplain {order.deadline}.
                </p>
                <button type="submit" disabled={form.processing} className="h-[52px] rounded-2xl bg-forest text-[15px] font-bold text-white disabled:opacity-70">
                    Kirim komplain
                </button>
            </form>
        </BuyerLayout>
    );
}

const inputCls = 'h-12 rounded-[14px] border border-line bg-white px-3.5 text-sm outline-none';

function Section({ title, error, children }) {
    return (
        <section className="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-4" data-error={error ? 'true' : undefined}>
            <h2 className="m-0 text-base font-bold">{title}</h2>
            {children}
            {error && <span className="text-xs font-semibold text-[#A3281C]">{error}</span>}
        </section>
    );
}

function Field({ label, error, hint, children }) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-[#3E4C41]">{label}</span>
            {children}
            {hint && !error && <span className="text-xs text-muted">{hint}</span>}
            {error && <span className="text-xs font-semibold text-[#A3281C]">{error}</span>}
        </label>
    );
}
