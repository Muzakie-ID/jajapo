import { Link, usePage } from '@inertiajs/react';
import Icon from '@/Components/Icon';

export default function NotificationBell({ from, className = '' }) {
    const { auth } = usePage().props;
    const unread = auth.user?.unread ?? 0;
    const href = auth.user ? `/notifikasi${from ? `?dari=${encodeURIComponent(from)}` : ''}` : '/login';

    return (
        <Link
            href={href}
            aria-label={unread ? `Notifikasi, ${unread} belum dibaca` : 'Notifikasi'}
            className={`relative flex h-11 w-11 items-center justify-center rounded-full border border-line bg-white text-ink ${className}`}
        >
            <Icon name="bell" size={20} />
            {unread > 0 && (
                <span className="absolute -right-0.5 -top-0.5 min-w-[18px] rounded-full bg-danger px-[5px] py-px text-center text-[10.5px] font-bold text-white">
                    {unread > 9 ? '9+' : unread}
                </span>
            )}
        </Link>
    );
}
