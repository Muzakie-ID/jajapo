import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import BuyerLayout, { TopBar } from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';
import { postJson } from '@/lib/http';

const KIND_ICON = { order: 'receipt', complaint: 'flag', money: 'wallet', shop: 'store', reminder: 'clock', feedback: 'message' };

export default function Notifications({ items, push, back }) {
    const unread = items.filter((n) => !n.read).length;

    return (
        <BuyerLayout title="Notifikasi" active={back === '/' ? 'account' : null}>
            <TopBar
                back={back}
                title="Notifikasi"
                right={
                    unread > 0 && (
                        <button type="button" onClick={() => router.post('/notifikasi/dibaca', {}, { preserveScroll: true })} className="h-11 text-[13px] font-semibold text-leaf">
                            Tandai dibaca
                        </button>
                    )
                }
            />

            <div className="flex flex-col gap-4">
                <PushToggle publicKey={push.public_key} />

                {items.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-[20px] border border-line bg-white px-5 py-10 text-center">
                        <Icon name="bell" size={28} className="text-leaf" />
                        <span className="text-[15px] font-bold">Belum ada notifikasi</span>
                        <span className="text-[13px] text-muted">Kabar pembayaran, jadwal ambil, dan komplain muncul di sini.</span>
                    </div>
                ) : (
                    <ul className="m-0 flex list-none flex-col overflow-hidden rounded-[20px] border border-line bg-white p-0">
                        {items.map((n) => (
                            <li key={n.id} className="border-b border-[#EDF1EB] last:border-b-0">
                                <Link href={`/notifikasi/${n.id}`} className={`flex gap-3 px-4 py-3.5 no-underline ${n.read ? '' : 'bg-[#F3F8F1]'}`}>
                                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${n.read ? 'bg-[#F0F3EE] text-muted' : 'bg-[#DCEBD7] text-forest'}`}>
                                        <Icon name={KIND_ICON[n.kind] || 'bell'} size={19} />
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span className="flex items-start justify-between gap-2">
                                            <span className={`text-sm text-ink ${n.read ? 'font-semibold' : 'font-bold'}`}>{n.title}</span>
                                            {!n.read && <span aria-label="Belum dibaca" className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-leaf" />}
                                        </span>
                                        <span className="text-[13px] leading-[1.45] text-body">{n.body}</span>
                                        <span className="text-xs text-muted">{n.time}</span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </BuyerLayout>
    );
}

function base64ToBytes(value) {
    const padded = (value + '='.repeat((4 - (value.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

/** Mengaktifkan atau mematikan notifikasi push di HP atau browser ini. */
function PushToggle({ publicKey }) {
    const supported = typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && Boolean(publicKey);
    const [state, setState] = useState('checking');

    useEffect(() => {
        if (!supported) {
            setState('unsupported');
            return;
        }
        if (Notification.permission === 'denied') {
            setState('denied');
            return;
        }
        navigator.serviceWorker
            .register('/sw.js')
            .then((reg) => reg.pushManager.getSubscription())
            .then((sub) => setState(sub ? 'on' : 'off'))
            .catch(() => setState('off'));
    }, [supported]);

    const enable = async () => {
        setState('busy');
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                setState(permission === 'denied' ? 'denied' : 'off');
                return;
            }
            const reg = await navigator.serviceWorker.register('/sw.js');
            await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64ToBytes(publicKey) });
            const res = await postJson('/notifikasi/push', sub.toJSON());
            setState(res.ok ? 'on' : 'error');
        } catch {
            setState('error');
        }
    };

    const disable = async () => {
        setState('busy');
        try {
            const reg = await navigator.serviceWorker.getRegistration('/sw.js');
            const sub = await reg?.pushManager.getSubscription();
            if (sub) {
                await postJson('/notifikasi/push', { endpoint: sub.endpoint }, 'DELETE');
                await sub.unsubscribe();
            }
            setState('off');
        } catch {
            setState('error');
        }
    };

    const text = {
        checking: 'Memeriksa...',
        unsupported: 'Browser ini belum mendukung notifikasi. Di iPhone, pasang Jajapo ke layar utama dulu lewat menu Bagikan.',
        denied: 'Notifikasi diblokir. Izinkan dari pengaturan situs di browser, lalu muat ulang halaman ini.',
        off: 'Dapat kabar pembayaran, jadwal ambil, dan komplain walau aplikasi sedang ditutup.',
        on: 'Notifikasi aktif di perangkat ini.',
        busy: 'Sebentar...',
        error: 'Gagal mengatur notifikasi. Coba lagi.',
    }[state];

    return (
        <section className="flex items-center gap-3 rounded-[20px] border border-line bg-white p-4">
            <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full ${state === 'on' ? 'bg-[#DCEBD7] text-forest' : 'bg-[#F0F3EE] text-muted'}`}>
                <Icon name="bell" size={21} />
            </span>
            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="text-sm font-bold">Notifikasi di HP ini</span>
                <span className="text-xs leading-[1.45] text-muted">{text}</span>
            </span>
            {(state === 'off' || state === 'error') && (
                <button type="button" onClick={enable} className="h-10 shrink-0 rounded-xl bg-forest px-3.5 text-[13px] font-semibold text-white">
                    Aktifkan
                </button>
            )}
            {state === 'on' && (
                <button type="button" onClick={disable} className="h-10 shrink-0 rounded-xl border border-[#DCE3D9] bg-white px-3.5 text-[13px] font-semibold">
                    Matikan
                </button>
            )}
        </section>
    );
}
