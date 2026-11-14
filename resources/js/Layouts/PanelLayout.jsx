import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '@/Components/Icon';
import Flash from '@/Components/Flash';

const SELLER_NAV = [
    { key: 'home', label: 'Beranda', icon: 'home', href: '/seller' },
    { key: 'po', label: 'PO dan menu', icon: 'box', href: '/seller/po' },
    { key: 'orders', label: 'Pesanan', icon: 'list', href: '/seller/pesanan' },
    { key: 'scan', label: 'Scan tiket', icon: 'scan', href: '/seller/scan' },
    { key: 'deliveries', label: 'Pengantaran', icon: 'truck', href: '/seller/antar' },
    { key: 'complaints', label: 'Komplain', icon: 'flag', href: '/seller/komplain' },
    { key: 'rekap', label: 'Rekap produksi', icon: 'chart', href: '/seller/rekap' },
    { key: 'balance', label: 'Saldo dan pencairan', icon: 'wallet', href: '/seller/saldo' },
    { key: 'members', label: 'Anggota', icon: 'users', href: '/seller/anggota', adminOnly: true },
    { key: 'settings', label: 'Pengaturan toko', icon: 'sliders', href: '/seller/pengaturan', adminOnly: true },
    { key: 'chat', label: 'Obrolan', icon: 'chat', href: '/obrolan?tab=penjual&dari=%2Fseller' },
    { key: 'notifications', label: 'Notifikasi', icon: 'bell', href: '/notifikasi?dari=%2Fseller' },
    { key: 'feedback', label: 'Kirim masukan', icon: 'message', href: '/masukan?dari=%2Fseller' },
];

const ADMIN_NAV = [
    { key: 'overview', label: 'Ringkasan', icon: 'grid', href: '/admin' },
    { key: 'search', label: 'Cari', icon: 'search', href: '/admin/cari' },
    { key: 'shops', label: 'Toko', icon: 'store', href: '/admin/toko' },
    { key: 'payments', label: 'Pembayaran', icon: 'card', href: '/admin/pembayaran' },
    { key: 'payouts', label: 'Pencairan', icon: 'wallet', href: '/admin/pencairan' },
    { key: 'complaints', label: 'Komplain dan refund', icon: 'flag', href: '/admin/komplain' },
    { key: 'recon', label: 'Rekonsiliasi', icon: 'scale', href: '/admin/rekonsiliasi' },
    { key: 'feedback', label: 'Masukan pengguna', icon: 'message', href: '/admin/masukan' },
    { key: 'notifications', label: 'Notifikasi', icon: 'bell', href: '/notifikasi?dari=%2Fadmin' },
    { key: 'log', label: 'Jejak tindakan', icon: 'list', href: '/admin/log' },
    { key: 'banks', label: 'Bank dan e-wallet', icon: 'bank', href: '/admin/bank' },
    { key: 'settings', label: 'Pengaturan', icon: 'sliders', href: '/admin/pengaturan' },
];

function Badge({ value, dark }) {
    if (!value) return null;
    return (
        <span className={`min-w-[22px] rounded-full px-[7px] py-0.5 text-center text-[11.5px] font-bold ${dark ? 'bg-sprout text-[#12301B]' : 'bg-leaf text-white'}`}>
            {value}
        </span>
    );
}

export function ShopSwitcher({ shop }) {
    const { auth } = usePage().props;
    const shops = auth.user.shops.filter((s) => s.status === 'active');
    return (
        <div className="flex flex-col gap-1">
            <label className="relative flex items-center gap-2.5 rounded-[14px] border border-panel-line bg-white p-2.5">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-[10px] bg-forest text-[13px] font-bold text-white">
                    {shop.logo_url ? <img src={shop.logo_url} alt="" className="h-full w-full object-cover" /> : shop.initials}
                </span>
                <span className="flex min-w-0 flex-1 flex-col gap-px">
                    <span className="truncate text-sm font-bold">{shop.name}</span>
                    <span className="text-xs text-muted">{shops.length > 1 ? 'Ganti toko' : 'Toko aktif'}</span>
                </span>
                <Icon name="updown" size={18} className="text-muted" />
                {shops.length > 1 && (
                    <select
                        aria-label="Ganti toko"
                        value={shop.id}
                        onChange={(e) => router.post('/seller/toko', { shop_id: Number(e.target.value) })}
                        className="absolute inset-0 cursor-pointer opacity-0"
                    >
                        {shops.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </select>
                )}
            </label>
            <Link href="/seller/toko-baru" className="flex min-h-9 items-center gap-1.5 px-2.5 text-[13px] font-semibold text-leaf no-underline hover:text-forest">
                <Icon name="plus" size={16} strokeWidth={2.2} />
                Buat toko lain
            </Link>
        </div>
    );
}

export default function PanelLayout({ variant = 'seller', active, title, shop, badges: pageBadges = {}, children }) {
    const { auth, panelBadges, chatUnread } = usePage().props;
    const badges = { ...(panelBadges || {}), notifications: auth.user?.unread || null, chat: (variant === 'seller' && chatUnread?.seller) || null, ...pageBadges };
    const [moreOpen, setMoreOpen] = useState(false);
    const dark = variant === 'admin';
    const nav = dark ? ADMIN_NAV : SELLER_NAV.filter((item) => !item.adminOnly || shop?.role === 'admin');
    const user = auth.user;

    return (
        <div className="min-h-dvh bg-panel text-[#141A16]">
            <Head title={title} />
            <Flash />
            <div className="lg:flex">
                <aside
                    className={`hidden w-[264px] shrink-0 flex-col gap-[18px] px-3.5 py-5 lg:sticky lg:top-0 lg:flex lg:h-dvh ${
                        dark ? 'bg-night text-[#E6EFE4]' : 'border-r border-panel-line bg-[#FBFCFA]'
                    }`}
                >
                    <div className="flex items-center justify-between gap-2 px-1.5">
                        <Link href={dark ? '/admin' : '/seller'} className={`text-[22px] font-extrabold tracking-[-0.03em] no-underline ${dark ? 'text-white hover:text-white' : 'text-forest hover:text-forest'}`}>
                            jajapo
                        </Link>
                        <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${dark ? 'bg-[#FFE2A8] text-[#5C3A00]' : 'bg-[#E3F1DF] text-[#1F5A2D]'}`}>
                            {dark ? 'Super admin' : 'Panel penjual'}
                        </span>
                    </div>

                    {!dark && shop && <ShopSwitcher shop={shop} />}

                    <nav aria-label={dark ? 'Menu super admin' : 'Menu penjual'} className="flex flex-col gap-0.5">
                        {nav.map((item) => {
                            const on = item.key === active;
                            return (
                                <Link
                                    key={item.key}
                                    href={item.href}
                                    aria-current={on ? 'page' : undefined}
                                    className={`flex min-h-11 items-center gap-3 rounded-xl border px-3 text-sm no-underline ${
                                        dark
                                            ? on
                                                ? 'border-transparent bg-[#24402B] font-bold text-white hover:text-white'
                                                : 'border-transparent font-medium text-[#D2E0D0] hover:text-white'
                                            : on
                                              ? 'border-panel-line bg-white font-bold text-[#141A16] hover:text-[#141A16]'
                                              : 'border-transparent font-medium text-body hover:text-[#141A16]'
                                    }`}
                                >
                                    <Icon name={item.icon} size={20} className={on ? (dark ? 'text-sprout' : 'text-leaf') : dark ? 'text-[#A9BDA8]' : 'text-muted'} />
                                    <span className="flex-1">{item.label}</span>
                                    <Badge value={badges[item.key]} dark={dark} />
                                </Link>
                            );
                        })}
                        <Link href="/" className={`flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium no-underline ${dark ? 'text-[#D2E0D0] hover:text-white' : 'text-body hover:text-[#141A16]'}`}>
                            <Icon name="store" size={20} className={dark ? 'text-[#A9BDA8]' : 'text-muted'} />
                            Halaman pembeli
                        </Link>
                    </nav>

                    <div className={`mt-auto flex flex-col gap-1 rounded-[14px] p-2.5 ${dark ? 'bg-[#1F3524]' : 'border border-panel-line bg-white'}`}>
                        <div className="flex items-center gap-2.5 px-0.5 pb-2 pt-1">
                            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[13px] font-bold ${dark ? 'bg-sprout text-[#12301B]' : 'bg-[#DCEBD7] text-[#1F5A2D]'}`}>
                                {user.initials}
                            </span>
                            <span className="flex min-w-0 flex-col gap-px">
                                <span className={`truncate text-sm font-bold ${dark ? 'text-white' : ''}`}>{user.name}</span>
                                <span className={`text-xs ${dark ? 'text-[#A9BDA8]' : 'text-muted'}`}>{dark ? 'Pemilik platform' : shop?.role === 'admin' ? 'Admin toko' : 'Anggota toko'}</span>
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.post('/logout')}
                            className={`flex min-h-10 items-center gap-2.5 border-t px-1.5 text-[13px] font-semibold ${dark ? 'border-[#2C4733] text-[#D2E0D0]' : 'border-[#EDF1EB] text-body'}`}
                        >
                            <Icon name="logout" size={18} />
                            Keluar
                        </button>
                    </div>
                </aside>

                <main className="min-w-0 flex-1 pb-28 lg:pb-10">{children}</main>
            </div>

            <MobileNav variant={variant} active={active} badges={badges} onMore={() => setMoreOpen(true)} />
            {moreOpen && <MoreSheet dark={dark} shop={shop} badges={badges} onClose={() => setMoreOpen(false)} />}
        </div>
    );
}

function MobileNav({ variant, active, badges, onMore }) {
    const dark = variant === 'admin';
    const items = dark
        ? [
              { key: 'overview', label: 'Ringkasan', icon: 'grid', href: '/admin' },
              { key: 'payouts', label: 'Pencairan', icon: 'wallet', href: '/admin/pencairan' },
              { key: 'payments', label: 'Pembayaran', icon: 'card', href: '/admin/pembayaran' },
              { key: 'shops', label: 'Toko', icon: 'store', href: '/admin/toko' },
          ]
        : [
              { key: 'home', label: 'Beranda', icon: 'home', href: '/seller' },
              { key: 'po', label: 'PO', icon: 'box', href: '/seller/po' },
              { key: 'scan', center: true, label: 'Scan tiket pengambilan', icon: 'scan', href: '/seller/scan' },
              { key: 'balance', label: 'Saldo', icon: 'wallet', href: '/seller/saldo' },
          ];

    return (
        <nav
            aria-label={dark ? 'Menu super admin' : 'Menu penjual'}
            className={`fixed inset-x-0 bottom-0 z-30 grid h-[84px] grid-cols-5 items-center px-1.5 pb-3 lg:hidden ${dark ? 'bg-night' : 'border-t border-panel-line bg-white'}`}
        >
            {items.map((item) =>
                item.center ? (
                    <Link
                        key={item.key}
                        href={item.href}
                        aria-label={item.label}
                        className="-mt-7 flex h-[60px] w-[60px] items-center justify-center justify-self-center rounded-full border-4 border-panel bg-forest text-white shadow-[0_10px_20px_-8px_rgba(31,58,38,0.7)] hover:text-white"
                    >
                        <Icon name={item.icon} size={26} />
                    </Link>
                ) : (
                    <Link
                        key={item.key}
                        href={item.href}
                        aria-current={item.key === active ? 'page' : undefined}
                        className={`flex min-h-12 flex-col items-center justify-center gap-1 text-[11px] no-underline ${
                            item.key === active ? (dark ? 'font-bold text-sprout hover:text-sprout' : 'font-bold text-forest hover:text-forest') : dark ? 'font-semibold text-[#A9BDA8]' : 'font-semibold text-muted'
                        }`}
                    >
                        <span className="relative flex">
                            <Icon name={item.icon} size={22} fill={item.key === active && !dark ? '#DCEBD7' : 'none'} />
                            {badges[item.key] ? (
                                <span className={`absolute -right-3 -top-1.5 min-w-[18px] rounded-full px-[5px] py-px text-center text-[10.5px] font-bold ${dark ? 'bg-[#FFE2A8] text-[#5C3A00]' : 'bg-leaf text-white'}`}>
                                    {badges[item.key]}
                                </span>
                            ) : null}
                        </span>
                        {item.label}
                    </Link>
                ),
            )}
            <button
                type="button"
                onClick={onMore}
                className={`flex min-h-12 flex-col items-center justify-center gap-1 text-[11px] font-semibold ${dark ? 'text-[#A9BDA8]' : 'text-muted'}`}
            >
                <span className="relative flex">
                    <Icon name="menu" size={22} />
                    {badges.complaints || badges.notifications || badges.feedback || badges.chat ? (
                        <span className={`absolute -right-3 -top-1.5 min-w-[18px] rounded-full px-[5px] py-px text-center text-[10.5px] font-bold ${dark ? 'bg-[#FFE2A8] text-[#5C3A00]' : 'bg-danger text-white'}`}>
                            {(badges.complaints || 0) + (badges.notifications || 0) + (badges.feedback || 0) + (badges.chat || 0)}
                        </span>
                    ) : null}
                </span>
                Lainnya
            </button>
        </nav>
    );
}

function MoreSheet({ dark, shop, badges, onClose }) {
    return (
        <div className="fixed inset-0 z-40 flex items-end bg-black/40 lg:hidden" onClick={onClose}>
            <div role="dialog" aria-modal="true" aria-label="Menu lainnya" className="w-full rounded-t-[26px] bg-white px-5 pb-8 pt-3" onClick={(e) => e.stopPropagation()}>
                <span aria-hidden="true" className="mx-auto mb-4 block h-1 w-10 rounded-full bg-[#D3DDD0]" />
                <div className="flex flex-col gap-3">
                    {!dark && shop && <ShopSwitcher shop={shop} />}
                    {!dark && (
                        <Link href="/obrolan?tab=penjual&dari=%2Fseller" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="chat" size={20} className="text-leaf" />
                            Obrolan
                            <Badge value={badges.chat} />
                        </Link>
                    )}
                    <Link href={`/notifikasi?dari=${dark ? '%2Fadmin' : '%2Fseller'}`} onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                        <Icon name="bell" size={20} className="text-leaf" />
                        Notifikasi
                        <Badge value={badges.notifications} />
                    </Link>
                    {dark && (
                        <Link href="/admin/komplain" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="flag" size={20} className="text-leaf" />
                            Komplain dan refund
                            <Badge value={badges.complaints} />
                        </Link>
                    )}
                    {dark && (
                        <Link href="/admin/rekonsiliasi" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="scale" size={20} className="text-leaf" />
                            Rekonsiliasi
                        </Link>
                    )}
                    {dark && (
                        <Link href="/admin/bank" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="bank" size={20} className="text-leaf" />
                            Bank dan e-wallet
                        </Link>
                    )}
                    {dark && (
                        <Link href="/admin/masukan" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="message" size={20} className="text-leaf" />
                            Masukan pengguna
                            <Badge value={badges.feedback} />
                        </Link>
                    )}
                    {!dark && (
                        <Link href="/masukan?dari=%2Fseller" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="message" size={20} className="text-leaf" />
                            Kirim masukan
                        </Link>
                    )}
                    {!dark && (
                        <Link href="/seller/komplain" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="flag" size={20} className="text-leaf" />
                            Komplain
                            <Badge value={badges.complaints} />
                        </Link>
                    )}
                    {!dark && (
                        <Link href="/seller/antar" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="truck" size={20} className="text-leaf" />
                            Pengantaran
                        </Link>
                    )}
                    {!dark && (
                        <Link href="/seller/pesanan" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="list" size={20} className="text-leaf" />
                            Pesanan
                        </Link>
                    )}
                    {!dark && (
                        <Link href="/seller/rekap" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="chart" size={20} className="text-leaf" />
                            Rekap produksi
                        </Link>
                    )}
                    {!dark && shop?.role === 'admin' && (
                        <Link href="/seller/anggota" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="users" size={20} className="text-leaf" />
                            Anggota
                        </Link>
                    )}
                    {!dark && shop?.role === 'admin' && (
                        <Link href="/seller/pengaturan" onClick={onClose} className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                            <Icon name="sliders" size={20} className="text-leaf" />
                            Pengaturan toko
                        </Link>
                    )}
                    <Link href="/" className="flex min-h-12 items-center gap-3 text-sm font-semibold text-ink no-underline">
                        <Icon name="store" size={20} className="text-leaf" />
                        Halaman pembeli
                    </Link>
                    <button type="button" onClick={() => router.post('/logout')} className="flex min-h-12 items-center gap-3 text-left text-sm font-semibold text-danger">
                        <Icon name="logout" size={20} />
                        Keluar
                    </button>
                </div>
            </div>
        </div>
    );
}

export function PanelCard({ className = '', children, ...rest }) {
    return (
        <section className={`rounded-[18px] border border-panel-line bg-white ${className}`} {...rest}>
            {children}
        </section>
    );
}
