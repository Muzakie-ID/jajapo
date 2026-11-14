import { Link } from '@inertiajs/react';
import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import BuyerLayout from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';
import Avatar from '@/Components/Avatar';
import StatusPill from '@/Components/StatusPill';
import { pillLabel } from '@/lib/format';

const POLL_MS = 4000;

function xsrf() {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

const jsonHeaders = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

export default function Show({ conversation: c, messages: initial, pendingOrder, hasOlder: initialHasOlder }) {
    const [messages, setMessages] = useState(initial);
    const [hasOlder, setHasOlder] = useState(initialHasOlder);
    const [body, setBody] = useState('');
    const [photo, setPhoto] = useState(null);
    const [preview, setPreview] = useState(null);
    const [order, setOrder] = useState(pendingOrder);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');
    const lastId = useRef(initial.length ? initial[initial.length - 1].id : 0);
    const stickToBottom = useRef(true);
    const fileInput = useRef(null);

    const scrollToBottom = () => window.scrollTo({ top: document.documentElement.scrollHeight });

    const append = useCallback((incoming) => {
        if (!incoming.length) return;
        setMessages((prev) => {
            const known = new Set(prev.map((m) => m.id));
            const fresh = incoming.filter((m) => !known.has(m.id));
            return fresh.length ? [...prev, ...fresh] : prev;
        });
        lastId.current = Math.max(lastId.current, ...incoming.map((m) => m.id));
    }, []);

    // Mulai dari pesan terbaru, dan ikut turun saat pesan baru datang kalau pengguna sedang di bawah.
    useLayoutEffect(() => {
        if (stickToBottom.current) scrollToBottom();
    }, [messages.length]);

    useEffect(() => {
        const onScroll = () => {
            stickToBottom.current = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 160;
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        let stopped = false;
        const tick = async () => {
            if (document.hidden || stopped) return;
            try {
                const res = await fetch(`/obrolan/${c.id}/pesan?setelah=${lastId.current}`, { credentials: 'same-origin', headers: jsonHeaders });
                if (res.ok) append((await res.json()).messages);
            } catch {
                // jaringan putus sebentar; coba lagi di putaran berikutnya
            }
        };
        const timer = setInterval(tick, POLL_MS);
        document.addEventListener('visibilitychange', tick);
        return () => {
            stopped = true;
            clearInterval(timer);
            document.removeEventListener('visibilitychange', tick);
        };
    }, [c.id, append]);

    useEffect(() => {
        if (!photo) {
            setPreview(null);
            return undefined;
        }
        const url = URL.createObjectURL(photo);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [photo]);

    const loadOlder = async () => {
        const res = await fetch(`/obrolan/${c.id}/pesan?sebelum=${messages[0]?.id ?? 0}`, { credentials: 'same-origin', headers: jsonHeaders });
        if (!res.ok) return;
        const data = await res.json();
        const before = document.documentElement.scrollHeight;
        stickToBottom.current = false;
        setMessages((prev) => [...data.messages, ...prev]);
        setHasOlder(data.hasOlder);
        requestAnimationFrame(() => window.scrollTo({ top: document.documentElement.scrollHeight - before }));
    };

    const send = async (e) => {
        e.preventDefault();
        if (sending || (!body.trim() && !photo && !order)) return;
        setSending(true);
        setError('');
        const form = new FormData();
        if (body.trim()) form.append('body', body.trim());
        if (photo) form.append('photo', photo);
        if (order) form.append('order', order.code);
        try {
            const res = await fetch(`/obrolan/${c.id}/pesan`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { ...jsonHeaders, 'X-XSRF-TOKEN': xsrf() },
                body: form,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                setError(data.errors ? Object.values(data.errors).flat()[0] : data.message || 'Pesan belum terkirim. Coba lagi.');
                return;
            }
            stickToBottom.current = true;
            append([data.message]);
            setBody('');
            setPhoto(null);
            setOrder(null);
        } catch {
            setError('Pesan belum terkirim. Periksa koneksi lalu coba lagi.');
        } finally {
            setSending(false);
        }
    };

    const composer = (
        <form onSubmit={send} className="flex flex-col gap-2 rounded-[22px] border border-line bg-white p-2 shadow-[0_8px_24px_-12px_rgba(24,36,27,0.25)]">
            {(order || preview) && (
                <div className="flex flex-wrap gap-2 px-1 pt-1">
                    {order && (
                        <span className="flex max-w-full items-center gap-2 rounded-xl bg-panel-soft py-1.5 pl-2.5 pr-1.5 text-[12.5px]">
                            <Icon name="receipt" size={15} className="shrink-0 text-leaf" />
                            <span className="truncate">
                                Lampirkan pesanan <b>{order.code}</b>
                            </span>
                            <button type="button" aria-label="Jangan lampirkan pesanan" onClick={() => setOrder(null)} className="flex h-6 w-6 items-center justify-center rounded-full text-muted">
                                <Icon name="x" size={14} />
                            </button>
                        </span>
                    )}
                    {preview && (
                        <span className="relative">
                            <img src={preview} alt="Foto yang akan dikirim" className="h-16 w-16 rounded-xl object-cover" />
                            <button type="button" aria-label="Hapus foto" onClick={() => setPhoto(null)} className="absolute -right-1.5 -top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-ink text-white">
                                <Icon name="x" size={13} />
                            </button>
                        </span>
                    )}
                </div>
            )}
            {error && <span className="px-2 text-xs font-semibold text-danger">{error}</span>}
            <div className="flex items-end gap-1.5">
                <button type="button" aria-label="Kirim foto" onClick={() => fileInput.current?.click()} className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-leaf hover:bg-[#F0F3EE]">
                    <Icon name="image" size={22} />
                </button>
                <input
                    ref={fileInput}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="sr-only"
                    onChange={(e) => {
                        const f = e.target.files?.[0];
                        if (f) setPhoto(f);
                        e.target.value = '';
                    }}
                />
                <textarea
                    rows={1}
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey && window.matchMedia('(pointer: fine)').matches) send(e);
                    }}
                    placeholder="Tulis pesan"
                    aria-label="Tulis pesan"
                    maxLength={2000}
                    className="max-h-32 min-h-11 flex-1 resize-none bg-transparent px-1 py-3 text-sm outline-none [field-sizing:content]"
                />
                <button
                    type="submit"
                    aria-label="Kirim"
                    disabled={sending || (!body.trim() && !photo && !order)}
                    className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-forest text-white disabled:opacity-40"
                >
                    <Icon name="send" size={19} />
                </button>
            </div>
        </form>
    );

    let lastDay = null;

    return (
        <BuyerLayout title={`Obrolan dengan ${c.name}`} active={c.role === 'buyer' ? 'account' : null} bottomBar={composer}>
            <div className="sticky top-0 z-10 -mx-5 -mt-4 mb-3 flex items-center gap-3 border-b border-line bg-mint px-5 pb-3 pt-4">
                <Link href={c.back} aria-label="Kembali" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-line bg-white text-ink">
                    <Icon name="arrowLeft" size={20} strokeWidth={1.9} />
                </Link>
                <Avatar initials={c.initials} logo={c.logo_url} size="h-10 w-10" />
                <span className="flex min-w-0 flex-col">
                    {c.link ? (
                        <Link href={c.link} className="truncate text-[15px] font-bold text-ink no-underline hover:text-ink">
                            {c.name}
                        </Link>
                    ) : (
                        <span className="truncate text-[15px] font-bold">{c.name}</span>
                    )}
                    <span className="truncate text-xs text-muted">{c.subtitle}</span>
                </span>
            </div>

            {hasOlder && (
                <button type="button" onClick={loadOlder} className="mx-auto mb-3 block h-9 rounded-full bg-white px-4 text-[13px] font-semibold text-leaf">
                    Muat pesan sebelumnya
                </button>
            )}

            {messages.length === 0 && (
                <p className="mx-auto my-6 max-w-[280px] text-center text-[13px] text-muted">
                    {c.role === 'buyer'
                        ? 'Tanyakan apa saja soal pesanan atau menunya. Nomor WhatsApp kalian tidak dibagikan.'
                        : 'Mulai obrolan dengan pembeli ini. Nomor WhatsApp tokomu tidak dibagikan.'}
                </p>
            )}

            <ol className="m-0 flex list-none flex-col gap-1.5 p-0">
                {messages.map((m) => {
                    const showDay = m.day !== lastDay;
                    lastDay = m.day;
                    return (
                        <li key={m.id} className="flex flex-col">
                            {showDay && <span className="mx-auto my-2 rounded-full bg-white/80 px-3 py-1 text-[11.5px] font-semibold text-muted">{m.day}</span>}
                            <Bubble m={m} />
                        </li>
                    );
                })}
            </ol>
        </BuyerLayout>
    );
}

function Bubble({ m }) {
    return (
        <div className={`flex max-w-[82%] flex-col gap-1 ${m.mine ? 'items-end self-end' : 'items-start self-start'}`}>
            {/* Anggota toko melihat siapa di antara mereka yang membalas. */}
            {m.sender && <span className="px-1 text-[11.5px] font-semibold text-muted">{m.sender}</span>}
            {m.order && <OrderCard order={m.order} />}
            {m.photo_url && (
                <a href={m.photo_url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-2xl border border-line bg-white">
                    <img src={m.photo_url} alt="Foto di obrolan" loading="lazy" className="block max-h-72 max-w-full object-contain" />
                </a>
            )}
            {m.body && (
                <p className={`m-0 whitespace-pre-line break-words rounded-2xl px-3.5 py-2.5 text-[14.5px] leading-[1.45] ${m.mine ? 'rounded-br-md bg-forest text-white' : 'rounded-bl-md bg-white text-ink'}`}>
                    {m.body}
                </p>
            )}
            <span className="px-1 text-[11px] text-muted">{m.time}</span>
        </div>
    );
}

function OrderCard({ order }) {
    return (
        <Link href={order.url} className="flex w-64 max-w-full flex-col gap-1 rounded-2xl border border-line bg-white px-3.5 py-3 text-ink no-underline hover:text-ink">
            <span className="flex items-center justify-between gap-2">
                <span className="tabular text-[13px] font-bold">{order.code}</span>
                <StatusPill status={order.status} label={pillLabel(order)} />
            </span>
            <span className="text-[13.5px] font-semibold">{order.title}</span>
            <span className="text-[12.5px] text-muted">{order.items}</span>
        </Link>
    );
}
