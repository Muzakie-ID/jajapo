import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import BuyerLayout, { TopBar } from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';

const HINT = {
    bug: 'Contoh: setelah bayar QRIS, tiket tidak muncul dan halaman berputar terus.',
    idea: 'Contoh: ingin bisa memilih jam ambil sendiri, bukan hanya slot yang disediakan.',
    other: 'Ceritakan apa saja yang ingin kamu sampaikan ke tim Jajapo.',
};

export default function Feedback({ kinds, from }) {
    const form = useForm({ kind: 'bug', message: '', photo: null, from: from || '' });
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
        form.post('/masukan', { forceFormData: true, preserveScroll: true });
    };

    return (
        <BuyerLayout title="Kirim masukan" active={from === '/akun' ? 'account' : null}>
            <TopBar back={from || '/akun'} title="Kirim masukan" />

            <form onSubmit={submit} className="flex flex-col gap-4">
                <p className="m-0 text-[13.5px] leading-[1.55] text-body">
                    Jajapo masih baru. Kalau ada yang error, membingungkan, atau kurang, ceritakan di sini. Masukanmu langsung dibaca tim Jajapo.
                </p>

                <section className="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-4">
                    <h2 className="m-0 text-[15px] font-bold">Jenis masukan</h2>
                    {errors.kind && <span className="text-xs font-semibold text-[#A3281C]">{errors.kind}</span>}
                    <div role="radiogroup" aria-label="Jenis masukan" className="grid grid-cols-3 gap-2">
                        {kinds.map((k) => (
                            <label
                                key={k.key}
                                className={`flex min-h-12 cursor-pointer items-center justify-center rounded-[14px] px-2 text-center text-[13px] font-semibold ${
                                    data.kind === k.key ? 'border-2 border-leaf bg-[#F3F8F1] text-forest' : 'border border-line bg-white text-ink'
                                }`}
                            >
                                <input type="radio" name="kind" checked={data.kind === k.key} onChange={() => setData('kind', k.key)} className="sr-only" />
                                {k.label}
                            </label>
                        ))}
                    </div>
                </section>

                <section className="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-4">
                    <label htmlFor="message" className="text-[15px] font-bold">
                        Ceritakan
                    </label>
                    <textarea
                        id="message"
                        rows={6}
                        value={data.message}
                        onChange={(e) => setData('message', e.target.value)}
                        placeholder={HINT[data.kind]}
                        maxLength={2000}
                        className="rounded-[14px] border border-line bg-white px-3.5 py-3 text-sm outline-none"
                    />
                    <span className="flex justify-between gap-2 text-xs">
                        {errors.message ? <span className="font-semibold text-[#A3281C]">{errors.message}</span> : <span className="text-muted">{data.kind === 'bug' ? 'Sebutkan langkah yang kamu lakukan sebelum error muncul.' : ''}</span>}
                        <span className="tabular shrink-0 text-muted">{data.message.length}/2000</span>
                    </span>

                    <div className="flex flex-col gap-1.5">
                        <span className="text-[13px] font-semibold text-[#3E4C41]">Screenshot (opsional)</span>
                        <label className="relative flex h-36 cursor-pointer flex-col items-center justify-center gap-1.5 overflow-hidden rounded-[14px] border border-dashed border-[#BFD0BA] bg-[#F6F8F5]">
                            {preview ? (
                                <img src={preview} alt="Pratinjau screenshot" className="absolute inset-0 h-full w-full object-contain" />
                            ) : (
                                <>
                                    <Icon name="image" size={26} className="text-leaf" />
                                    <span className="text-xs font-semibold text-forest">Pilih gambar</span>
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
                                Hapus gambar
                            </button>
                        )}
                        {errors.photo && <span className="text-xs font-semibold text-[#A3281C]">{errors.photo}</span>}
                    </div>
                </section>

                <p className="m-0 text-xs leading-[1.5] text-muted">Nama, email, halaman asal, dan jenis perangkatmu ikut terkirim supaya tim bisa menelusuri masalahnya.</p>
                <button type="submit" disabled={form.processing} className="h-[52px] rounded-2xl bg-forest text-[15px] font-bold text-white disabled:opacity-70">
                    {form.processing ? 'Mengirim...' : 'Kirim masukan'}
                </button>
            </form>
        </BuyerLayout>
    );
}
