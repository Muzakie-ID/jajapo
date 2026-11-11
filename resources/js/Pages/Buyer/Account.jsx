import { Link, router, useForm, usePage } from '@inertiajs/react';
import BuyerLayout from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';

export default function Account({ phone, canRegisterShop }) {
    const { auth } = usePage().props;
    const user = auth.user;
    const form = useForm({ phone: phone || '' });
    const activeShops = user.shops.filter((s) => s.status === 'active');

    return (
        <BuyerLayout title="Akun" active="account">
            <section className="flex items-center gap-3.5 rounded-3xl border border-line bg-white p-4">
                <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#DCEBD7] text-lg font-bold text-[#1F5A2D]">{user.initials}</span>
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h1 className="m-0 text-xl font-bold">{user.name}</h1>
                    <span className="truncate text-[13px] text-muted">{user.email}</span>
                </div>
            </section>

            <form
                className="mt-4 flex flex-col gap-2 rounded-[20px] border border-line bg-white p-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/akun', { preserveScroll: true });
                }}
            >
                <label htmlFor="phone" className="text-[13px] font-semibold text-body">
                    Nomor WhatsApp
                </label>
                <div className="flex gap-2">
                    <input
                        id="phone"
                        type="tel"
                        autoComplete="tel"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        placeholder="08xxxxxxxxxx"
                        className="h-12 min-w-0 flex-1 rounded-[14px] border border-line px-3.5 text-sm outline-none"
                    />
                    <button type="submit" disabled={form.processing} className="h-12 rounded-[14px] bg-leaf px-4 text-sm font-bold text-white">
                        Simpan
                    </button>
                </div>
                {form.errors.phone && <span className="text-xs font-semibold text-danger">{form.errors.phone}</span>}
                <span className="text-xs text-muted">Dipakai penjual untuk menghubungi kamu soal pesanan.</span>
            </form>

            <nav aria-label="Menu akun" className="mt-4 overflow-hidden rounded-[20px] border border-line bg-white">
                {activeShops.length > 0 && <MenuLink href="/seller" icon="store" label="Buka panel penjual" />}
                {user.is_super_admin && <MenuLink href="/admin" icon="grid" label="Buka panel super admin" />}
                {activeShops.length === 0 && canRegisterShop && <MenuLink href="/seller/daftar" icon="store" label="Daftar jadi penjual" />}
                <MenuLink href="/pesanan" icon="receipt" label="Pesanan saya" />
                <MenuLink href="/notifikasi" icon="bell" label={user.unread ? `Notifikasi (${user.unread} baru)` : 'Notifikasi'} />
                <MenuLink href="/masukan?dari=%2Fakun" icon="message" label="Kirim masukan atau lapor bug" />
                <button
                    type="button"
                    onClick={() => router.post('/logout')}
                    className="flex min-h-14 w-full items-center gap-3 border-t border-[#E6EDE3] px-4 text-left text-sm font-semibold text-danger"
                >
                    <Icon name="logout" size={20} />
                    Keluar
                </button>
            </nav>
        </BuyerLayout>
    );
}

function MenuLink({ href, icon, label }) {
    return (
        <Link href={href} className="flex min-h-14 items-center gap-3 border-t border-[#E6EDE3] px-4 text-sm font-semibold text-ink no-underline first:border-t-0 hover:text-ink">
            <Icon name={icon} size={20} className="text-leaf" />
            <span className="flex-1">{label}</span>
            <Icon name="chevronRight" size={18} strokeWidth={2} className="text-muted" />
        </Link>
    );
}
