import { Head, Link, usePage } from '@inertiajs/react';
import Icon from '@/Components/Icon';
import Flash from '@/Components/Flash';

function NavItem({ href, icon, label, active }) {
    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            className={`flex min-h-12 flex-col items-center justify-center gap-1 text-[11.5px] no-underline ${active ? 'font-bold text-leaf' : 'font-semibold text-muted'}`}
        >
            <Icon name={icon} size={22} fill={active ? '#DCEBD7' : 'none'} />
            {label}
        </Link>
    );
}

export default function BuyerLayout({ title, active, children, bottomBar, hideNav = false }) {
    const { ticketHref } = usePage().props;
    return (
        <div className="min-h-screen bg-[#E4EBE1]">
            <Head title={title} />
            <div className="relative mx-auto min-h-screen max-w-[480px] bg-mint text-ink">
                <Flash />
                <main className={`px-5 pt-4 ${hideNav ? 'pb-28' : 'pb-48'}`}>{children}</main>

                <div className="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-[480px]">
                    {bottomBar && <div className={`px-4 ${hideNav ? 'pb-5' : 'pb-4'}`}>{bottomBar}</div>}
                    {!hideNav && (
                        <nav
                            aria-label="Menu utama"
                            className="grid h-[84px] grid-cols-5 items-center border-t border-line bg-white px-2 pb-3"
                        >
                            <NavItem href="/" icon="home" label="Beranda" active={active === 'home'} />
                            <NavItem href="/toko" icon="store" label="Toko" active={active === 'shops'} />
                            <Link
                                href={ticketHref || '/pesanan'}
                                aria-label="Tiket QR pesanan"
                                className="-mt-7 flex h-[60px] w-[60px] items-center justify-center justify-self-center rounded-full border-4 border-mint bg-leaf text-white shadow-[0_10px_20px_-8px_rgba(47,122,62,0.7)]"
                            >
                                <Icon name="qr" size={26} />
                            </Link>
                            <NavItem href="/pesanan" icon="receipt" label="Pesanan" active={active === 'orders'} />
                            <NavItem href="/akun" icon="user" label="Akun" active={active === 'account'} />
                        </nav>
                    )}
                </div>
            </div>
        </div>
    );
}

export function TopBar({ back, title, right }) {
    return (
        <div className="mb-4 flex items-center justify-between gap-3">
            {back ? (
                <Link
                    href={back}
                    aria-label="Kembali"
                    className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-line bg-white text-ink"
                >
                    <Icon name="arrowLeft" size={20} strokeWidth={1.9} />
                </Link>
            ) : (
                <span className="w-11" />
            )}
            {title && <h1 className="m-0 text-[17px] font-bold">{title}</h1>}
            <div className="flex min-w-11 justify-end gap-2.5">{right}</div>
        </div>
    );
}

export function CircleButton({ label, icon, onClick, pressed, children }) {
    return (
        <button
            type="button"
            aria-label={label}
            aria-pressed={pressed}
            onClick={onClick}
            className="flex h-11 w-11 items-center justify-center rounded-full border border-line bg-white text-ink"
        >
            {children || <Icon name={icon} size={20} />}
        </button>
    );
}
