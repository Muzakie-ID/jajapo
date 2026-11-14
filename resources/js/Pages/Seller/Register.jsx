import { useForm, usePage } from '@inertiajs/react';
import BuyerLayout, { TopBar } from '@/Layouts/BuyerLayout';
import BankSelect from '@/Components/BankSelect';

export default function Register({ allowed, domain, email, pendingShop, blocked, ownerName, banks, back, another, owned, maxShops }) {
    const title = another ? 'Buat toko lain' : 'Daftar jadi penjual';
    const { appHost } = usePage().props;
    const form = useForm({ name: '', slug: '', instagram: '', whatsapp: '', bank: '', account_number: '', account_name: ownerName });

    const setName = (value) => {
        const slug = value
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .slice(0, 40);
        form.setData((d) => ({ ...d, name: value, slug: d.slug && d.slug !== autoSlug(d.name) ? d.slug : slug }));
    };

    return (
        <BuyerLayout title={title} active="account">
            <TopBar back={back} title={title} />

            {pendingShop ? (
                <div className="flex flex-col gap-2 rounded-[20px] border border-line bg-white p-5">
                    <h2 className="m-0 text-lg font-bold">{pendingShop.name} sedang ditinjau</h2>
                    <p className="m-0 text-sm text-body">
                        Didaftarkan {pendingShop.created}. Super admin memeriksa email UGM dan rekeningmu.{' '}
                        {another ? 'Toko ini muncul di menu pindah toko setelah disetujui.' : 'Panel penjual terbuka setelah disetujui.'}
                    </p>
                </div>
            ) : blocked ? (
                <div className="flex flex-col gap-2 rounded-[20px] border border-line bg-white p-5">
                    <h2 className="m-0 text-lg font-bold">Belum bisa membuat toko baru</h2>
                    <p className="m-0 text-sm text-body">{blocked}</p>
                </div>
            ) : !allowed ? (
                <div className="flex flex-col gap-2 rounded-[20px] border border-line bg-white p-5">
                    <h2 className="m-0 text-lg font-bold">Pakai email @{domain}</h2>
                    <p className="m-0 text-sm text-body">
                        Kamu masuk sebagai {email}. Penjual wajib memakai email UGM supaya pembeli tahu tokonya dikelola mahasiswa. Keluar, lalu masuk lagi dengan email UGM.
                    </p>
                </div>
            ) : (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/seller/daftar');
                    }}
                    className="flex flex-col gap-4"
                >
                    {another && (
                        <p className="m-0 rounded-2xl bg-white/70 px-4 py-3 text-[13px] leading-[1.5] text-body">
                            Toko baru punya saldo, rekening pencairan, anggota, dan URL sendiri, terpisah dari tokomu yang lain. Ini toko ke-{owned + 1} dari maksimal {maxShops}.
                        </p>
                    )}
                    <Section title="Toko">
                        <Field label="Nama toko" error={form.errors.name}>
                            <input value={form.data.name} onChange={(e) => setName(e.target.value)} placeholder="Danus Dies FTP" className={inputCls} required />
                        </Field>
                        <Field label="URL toko" error={form.errors.slug} hint={`${appHost}/${form.data.slug || 'nama-toko'}`}>
                            <input value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value.toLowerCase())} className={inputCls} required />
                        </Field>
                        <Field label="Instagram (opsional)" error={form.errors.instagram}>
                            <input value={form.data.instagram} onChange={(e) => form.setData('instagram', e.target.value)} placeholder="@danusdiesftp" className={inputCls} />
                        </Field>
                        <Field label="Nomor WhatsApp toko" error={form.errors.whatsapp} hint="Dipakai tombol Hubungi penjual">
                            <input type="tel" value={form.data.whatsapp} onChange={(e) => form.setData('whatsapp', e.target.value)} placeholder="08xxxxxxxxxx" className={inputCls} required />
                        </Field>
                    </Section>

                    <Section title="Rekening pencairan">
                        <Field label="Bank atau e-wallet" error={form.errors.bank}>
                            <BankSelect banks={banks} value={form.data.bank} onChange={(v) => form.setData('bank', v)} className={inputCls} />
                        </Field>
                        <Field label="Nomor rekening" error={form.errors.account_number}>
                            <input inputMode="numeric" value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value.replace(/\D/g, ''))} className={inputCls} required />
                        </Field>
                        <Field label="Nama pemilik rekening" error={form.errors.account_name} hint="Harus atas nama kamu sebagai pembuat toko">
                            <input value={form.data.account_name} onChange={(e) => form.setData('account_name', e.target.value)} className={inputCls} required />
                        </Field>
                    </Section>

                    <button type="submit" disabled={form.processing} className="h-[52px] rounded-2xl bg-forest text-[15px] font-bold text-white disabled:opacity-70">
                        Kirim pendaftaran
                    </button>
                </form>
            )}
        </BuyerLayout>
    );
}

const inputCls = 'h-12 rounded-[14px] border border-line bg-white px-3.5 text-sm outline-none';

function autoSlug(name) {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
        .slice(0, 40);
}

function Section({ title, children }) {
    return (
        <section className="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-4">
            <h2 className="m-0 text-base font-bold">{title}</h2>
            {children}
        </section>
    );
}

function Field({ label, error, hint, children }) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-body">{label}</span>
            {children}
            {hint && !error && <span className="text-xs text-muted">{hint}</span>}
            {error && <span className="text-xs font-semibold text-danger">{error}</span>}
        </label>
    );
}
