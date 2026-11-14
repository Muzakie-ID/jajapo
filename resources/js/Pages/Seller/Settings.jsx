import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import BankSelect from '@/Components/BankSelect';

export default function Settings({ shop, profile, account, pendingAccount, rejectedAccount, banks, children }) {
    return (
        <PanelLayout variant="seller" active="settings" title="Pengaturan toko" shop={shop}>
            <div className="flex flex-col gap-4 px-4 pt-4 lg:gap-5 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Pengaturan toko</h1>
                    <p className="m-0 text-sm text-muted">Profil yang dilihat pembeli dan rekening pencairan. Hanya admin toko yang bisa mengubahnya.</p>
                </div>
                <div className="grid gap-4 lg:grid-cols-2 lg:items-start lg:gap-5">
                    <ProfileCard profile={profile} />
                    <AccountCard profile={profile} account={account} pending={pendingAccount} rejected={rejectedAccount} banks={banks} />
                </div>
                {children}
            </div>
        </PanelLayout>
    );
}

const inputCls = 'h-12 w-full min-w-0 rounded-[14px] border border-line bg-white px-3.5 text-sm outline-none';

function Field({ label, error, hint, children }) {
    return (
        <label className="flex min-w-0 flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-body">{label}</span>
            {children}
            {hint && !error && <span className="text-xs text-muted">{hint}</span>}
            {error && <span className="text-xs font-semibold text-danger">{error}</span>}
        </label>
    );
}

function ProfileCard({ profile }) {
    const { appHost } = usePage().props;
    const form = useForm({ name: profile.name, instagram: profile.instagram, whatsapp: profile.whatsapp, logo: null, remove_logo: false });
    const [preview, setPreview] = useState(null);
    useEffect(() => {
        if (!form.data.logo) {
            setPreview(null);
            return undefined;
        }
        const url = URL.createObjectURL(form.data.logo);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [form.data.logo]);
    const logo = preview || (form.data.remove_logo ? null : profile.logo_url);

    return (
        <PanelCard className="flex flex-col gap-3.5 p-4">
            <h2 className="m-0 text-base font-bold">Profil toko</h2>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/seller/pengaturan', { forceFormData: true, preserveScroll: true, onSuccess: () => form.setData((d) => ({ ...d, logo: null, remove_logo: false })) });
                }}
                className="flex flex-col gap-3.5"
            >
                <div className="flex items-center gap-3.5">
                    <span className="flex h-[72px] w-[72px] shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-forest text-xl font-bold text-white">
                        {logo ? <img src={logo} alt="Logo toko" className="h-full w-full object-cover" /> : form.data.name.slice(0, 2).toUpperCase()}
                    </span>
                    <div className="flex flex-col gap-1.5">
                        <label className="flex h-10 cursor-pointer items-center gap-2 self-start rounded-xl border border-[#DCE3D9] bg-white px-3.5 text-[13px] font-semibold">
                            <Icon name="image" size={17} className="text-leaf" />
                            {logo ? 'Ganti logo' : 'Unggah logo'}
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="sr-only"
                                onChange={(e) => {
                                    const f = e.target.files?.[0];
                                    if (f) form.setData((d) => ({ ...d, logo: f, remove_logo: false }));
                                    e.target.value = '';
                                }}
                            />
                        </label>
                        {logo && (
                            <button type="button" onClick={() => form.setData((d) => ({ ...d, logo: null, remove_logo: true }))} className="self-start text-xs font-semibold text-danger">
                                Hapus logo
                            </button>
                        )}
                        {form.errors.logo && <span className="text-xs font-semibold text-danger">{form.errors.logo}</span>}
                    </div>
                </div>
                <Field label="Nama toko" error={form.errors.name}>
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} maxLength={60} className={inputCls} />
                </Field>
                <Field label="URL toko" hint="URL tidak bisa diganti supaya tautan yang sudah dibagikan tetap jalan.">
                    <input value={`${appHost}/${profile.slug}`} readOnly className={`${inputCls} bg-[#F6F8F5] text-muted`} />
                </Field>
                <Field label="Instagram (opsional)" error={form.errors.instagram}>
                    <input value={form.data.instagram} onChange={(e) => form.setData('instagram', e.target.value)} placeholder="@danusdiesftp" className={inputCls} />
                </Field>
                <Field label="Nomor WhatsApp toko" error={form.errors.whatsapp} hint="Dipakai tombol Hubungi penjual di tiket pembeli.">
                    <input type="tel" value={form.data.whatsapp} onChange={(e) => form.setData('whatsapp', e.target.value)} className={inputCls} />
                </Field>
                <button type="submit" disabled={form.processing} className="h-12 rounded-xl bg-forest text-sm font-bold text-white disabled:opacity-50">
                    Simpan profil
                </button>
            </form>
        </PanelCard>
    );
}

function AccountCard({ profile, account, pending, rejected, banks }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ bank: '', account_number: '', account_name: profile.owner });

    return (
        <PanelCard className="flex flex-col gap-3.5 p-4">
            <h2 className="m-0 text-base font-bold">Rekening pencairan</h2>
            {account ? (
                <div className="flex items-center gap-3 rounded-2xl bg-[#F6F8F5] p-3">
                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-forest">
                        <Icon name="bank" size={22} />
                    </span>
                    <span className="flex min-w-0 flex-1 flex-col gap-px">
                        <span className="text-sm font-bold">
                            {account.bank} {account.number}
                        </span>
                        <span className="text-xs text-muted">a.n. {account.name}</span>
                    </span>
                    {account.verified && <span className="rounded-full bg-[#DCEFD6] px-2.5 py-1 text-xs font-bold text-[#1F5A2D]">Terverifikasi</span>}
                </div>
            ) : (
                <p className="m-0 text-sm text-muted">Belum ada rekening aktif.</p>
            )}

            {pending && (
                <div className="flex flex-col gap-2 rounded-2xl border border-[#F0DDB0] bg-amber-bg p-3">
                    <span className="text-[13px] font-bold text-amber-ink">Menunggu persetujuan super admin</span>
                    <span className="text-sm font-semibold">
                        {pending.bank} {pending.number} a.n. {pending.name}
                    </span>
                    <span className="text-xs text-body">Diajukan {pending.date}. Pencairan tetap ke rekening lama sampai disetujui.</span>
                    <button
                        type="button"
                        onClick={() => window.confirm('Batalkan pengajuan ganti rekening?') && router.delete('/seller/pengaturan/rekening', { preserveScroll: true })}
                        className="self-start text-xs font-semibold text-danger"
                    >
                        Batalkan pengajuan
                    </button>
                </div>
            )}

            {rejected && (
                <div className="flex flex-col gap-1 rounded-2xl bg-[#FCE4E1] p-3 text-[13px] text-[#7A2016]">
                    <span className="font-bold">
                        Pengajuan {rejected.bank} {rejected.number} ditolak
                    </span>
                    {rejected.note && <span>Alasan: {rejected.note}</span>}
                </div>
            )}

            {!pending &&
                (open ? (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/seller/pengaturan/rekening', { preserveScroll: true, onSuccess: () => setOpen(false) });
                        }}
                        className="flex flex-col gap-3 rounded-2xl border border-line p-3.5"
                    >
                        <Field label="Bank atau e-wallet" error={form.errors.bank}>
                            <BankSelect banks={banks} value={form.data.bank} onChange={(v) => form.setData('bank', v)} className={inputCls} />
                        </Field>
                        <Field label="Nomor rekening" error={form.errors.account_number}>
                            <input inputMode="numeric" value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value.replace(/\D/g, ''))} className={inputCls} />
                        </Field>
                        <Field label="Nama pemilik rekening" error={form.errors.account_name} hint={`Harus atas nama pembuat toko (${profile.owner}).`}>
                            <input value={form.data.account_name} onChange={(e) => form.setData('account_name', e.target.value)} className={inputCls} />
                        </Field>
                        <div className="flex gap-2">
                            <button type="submit" disabled={form.processing} className="h-11 flex-1 rounded-xl bg-forest text-sm font-semibold text-white disabled:opacity-60">
                                Ajukan ganti rekening
                            </button>
                            <button type="button" onClick={() => setOpen(false)} className="h-11 rounded-xl px-4 text-sm font-semibold text-muted">
                                Batal
                            </button>
                        </div>
                    </form>
                ) : (
                    <button type="button" onClick={() => setOpen(true)} className="flex h-11 items-center justify-center gap-2 rounded-xl border border-[#DCE3D9] bg-white text-sm font-semibold">
                        <Icon name="pencil" size={17} />
                        Ganti rekening
                    </button>
                ))}
            <p className="m-0 text-xs text-muted">Rekening wajib atas nama pembuat toko dan diperiksa ulang super admin sebelum dipakai untuk pencairan.</p>
        </PanelCard>
    );
}
