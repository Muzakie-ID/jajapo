import { Link, usePage } from '@inertiajs/react';
import Icon from '@/Components/Icon';

/** Tombol Obrolan bulat, berjejer dengan lonceng notifikasi. */
export default function ChatButton({ from, className = '' }) {
    const { auth, chatUnread } = usePage().props;
    const unread = (chatUnread?.buyer ?? 0) + (chatUnread?.seller ?? 0);
    const href = auth.user ? `/obrolan${from ? `?dari=${encodeURIComponent(from)}` : ''}` : '/login';

    return (
        <Link
            href={href}
            aria-label={unread ? `Obrolan, ${unread} belum dibaca` : 'Obrolan'}
            className={`relative flex h-11 w-11 items-center justify-center rounded-full border border-line bg-white text-ink ${className}`}
        >
            <Icon name="chat" size={20} />
            {unread > 0 && (
                <span className="absolute -right-0.5 -top-0.5 min-w-[18px] rounded-full bg-danger px-[5px] py-px text-center text-[10.5px] font-bold text-white">
                    {unread > 9 ? '9+' : unread}
                </span>
            )}
        </Link>
    );
}
