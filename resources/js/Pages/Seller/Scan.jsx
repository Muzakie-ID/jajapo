import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import QrScanner from 'qr-scanner';
import Icon from '@/Components/Icon';
import { postJson } from '@/lib/http';

const DONE = ['picked_up', 'delivered'];

function storageKey(shopId, name) {
    return `jajapo-scan-${shopId}-${name}`;
}

function load(key, fallback) {
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
}

function save(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // penyimpanan diblokir
    }
}

export default function Scan({ shop, tickets: serverTickets }) {
    const videoRef = useRef(null);
    const scannerRef = useRef(null);
    const busyRef = useRef(false);
    const [tickets, setTickets] = useState(serverTickets);
    const [queue, setQueue] = useState([]);
    const [online, setOnline] = useState(true);
    const [camera, setCamera] = useState('starting');
    const [torch, setTorch] = useState(false);
    const [result, setResult] = useState(null);
    const [query, setQuery] = useState('');

    // Tiket disimpan di HP supaya scan tetap jalan tanpa sinyal.
    useEffect(() => {
        setOnline(navigator.onLine);
        save(storageKey(shop.id, 'tickets'), serverTickets);
        setQueue(load(storageKey(shop.id, 'queue'), []));
        const on = () => setOnline(true);
        const off = () => setOnline(false);
        window.addEventListener('online', on);
        window.addEventListener('offline', off);
        return () => {
            window.removeEventListener('online', on);
            window.removeEventListener('offline', off);
        };
    }, [shop.id, serverTickets]);

    const markLocal = useCallback(
        (token) => {
            setTickets((list) => {
                const next = list.map((t) => (t.token === token ? { ...t, status: 'picked_up' } : t));
                save(storageKey(shop.id, 'tickets'), next);
                return next;
            });
        },
        [shop.id],
    );

    const progressFor = useCallback(
        (ticket, list) => {
            const same = list.filter((t) => t.place === ticket.place);
            return { place: ticket.place, done: same.filter((t) => DONE.includes(t.status)).length, total: same.length };
        },
        [],
    );

    const submit = useCallback(
        async ({ token, code }) => {
            if (busyRef.current) return;
            busyRef.current = true;
            const local = tickets.find((t) => (token && t.token === token) || (code && t.code === code.toUpperCase()));

            try {
                if (!navigator.onLine) throw new Error('offline');
                const { ok, data } = await postJson('/seller/scan', token ? { token } : { code });
                setResult({ ok, message: data.message || 'Tiket tidak bisa diproses.', order: data.order, progress: data.progress });
                if (ok && local) markLocal(local.token);
            } catch {
                // Tanpa sinyal: cek dari daftar tiket di HP, kirim belakangan.
                if (!local) {
                    setResult({ ok: false, message: 'Tiket tidak ada di daftar yang tersimpan di HP.' });
                } else if (DONE.includes(local.status)) {
                    setResult({ ok: false, message: `Pesanan ${local.code} sudah diambil.`, order: local });
                } else {
                    const nextQueue = [...queue, local.token];
                    setQueue(nextQueue);
                    save(storageKey(shop.id, 'queue'), nextQueue);
                    markLocal(local.token);
                    const nextList = tickets.map((t) => (t.token === local.token ? { ...t, status: 'picked_up' } : t));
                    setResult({
                        ok: true,
                        offline: true,
                        message: 'Ditandai diambil (tersimpan di HP)',
                        order: local,
                        progress: progressFor(local, nextList),
                    });
                }
            } finally {
                setTimeout(() => {
                    busyRef.current = false;
                }, 1500);
            }
        },
        [tickets, queue, shop.id, markLocal, progressFor],
    );

    // Kirim antrean scan offline begitu sinyal kembali.
    useEffect(() => {
        if (!online || queue.length === 0) return;
        let cancelled = false;
        (async () => {
            const left = [];
            for (const token of queue) {
                try {
                    const { status } = await postJson('/seller/scan', { token });
                    if (status >= 500) left.push(token);
                } catch {
                    left.push(token);
                }
            }
            if (!cancelled) {
                setQueue(left);
                save(storageKey(shop.id, 'queue'), left);
            }
        })();
        return () => {
            cancelled = true;
        };
    }, [online]); // eslint-disable-line react-hooks/exhaustive-deps

    // Kamera dibuat sekali; hasil scan diteruskan ke submit terbaru lewat ref.
    const submitRef = useRef(submit);
    submitRef.current = submit;

    useEffect(() => {
        if (!videoRef.current) return undefined;
        const scanner = new QrScanner(videoRef.current, (res) => submitRef.current({ token: res.data }), {
            returnDetailedScanResult: true,
            preferredCamera: 'environment',
            maxScansPerSecond: 4,
        });
        scannerRef.current = scanner;
        scanner
            .start()
            .then(() => setCamera('on'))
            .catch(() => setCamera('blocked'));
        return () => {
            scanner.destroy();
            scannerRef.current = null;
        };
    }, []);

    const toggleTorch = async () => {
        const scanner = scannerRef.current;
        if (!scanner) return;
        try {
            if (await scanner.hasFlash()) {
                await scanner.toggleFlash();
                setTorch(scanner.isFlashOn());
            }
        } catch {
            // HP tidak punya senter
        }
    };

    const matches = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (q.length < 2) return [];
        return tickets.filter((t) => t.code.toLowerCase().includes(q) || t.buyer.toLowerCase().includes(q)).slice(0, 5);
    }, [query, tickets]);

    const progress = result?.progress;

    return (
        <div className="min-h-dvh bg-[#101A13] lg:flex">
            <Head title="Scan tiket" />
            <div className="relative h-[52dvh] min-h-[360px] overflow-hidden lg:h-dvh lg:flex-1">
                <video ref={videoRef} muted playsInline className="absolute inset-0 h-full w-full object-cover opacity-80" />
                <div className="absolute inset-x-0 top-0 flex flex-col gap-3.5 p-4">
                    <div className="flex items-center justify-between">
                        <Link href="/seller" aria-label="Kembali ke beranda toko" className="flex h-11 w-11 items-center justify-center rounded-full bg-white/15 text-white hover:text-white">
                            <Icon name="arrowLeft" size={20} strokeWidth={1.9} />
                        </Link>
                        <h1 className="m-0 text-[17px] font-bold text-white">Scan tiket</h1>
                        <button
                            type="button"
                            onClick={toggleTorch}
                            aria-label={torch ? 'Matikan senter' : 'Nyalakan senter'}
                            aria-pressed={torch}
                            className="flex h-11 w-11 items-center justify-center rounded-full bg-white/15 text-white"
                        >
                            <Icon name="flash" size={20} />
                        </button>
                    </div>
                    <span role="status" className="flex items-center gap-1.5 self-center rounded-full bg-white/15 py-1.5 pl-2.5 pr-3 text-[12.5px] font-semibold text-[#E6EFE4]">
                        <Icon name={online ? 'check' : 'offline'} size={15} strokeWidth={2} className="text-sprout" />
                        {online
                            ? `${tickets.length} tiket tersimpan di HP${queue.length ? `, ${queue.length} menunggu dikirim` : ''}`
                            : `Mode offline, ${queue.length} scan menunggu sinyal`}
                    </span>
                </div>
                <div aria-hidden="true" className="absolute left-1/2 top-[55%] h-[220px] w-[220px] -translate-x-1/2 -translate-y-1/2">
                    <span className="absolute left-0 top-0 h-10 w-10 rounded-tl-[14px] border-l-4 border-t-4 border-sprout" />
                    <span className="absolute right-0 top-0 h-10 w-10 rounded-tr-[14px] border-r-4 border-t-4 border-sprout" />
                    <span className="absolute bottom-0 left-0 h-10 w-10 rounded-bl-[14px] border-b-4 border-l-4 border-sprout" />
                    <span className="absolute bottom-0 right-0 h-10 w-10 rounded-br-[14px] border-b-4 border-r-4 border-sprout" />
                </div>
                <p className="absolute inset-x-6 bottom-6 m-0 text-center text-[13.5px] text-[#C8D6C7]">
                    {camera === 'blocked'
                        ? 'Kamera tidak bisa dibuka. Izinkan akses kamera, atau cari tiket secara manual.'
                        : 'Arahkan kamera ke QR di tiket pembeli'}
                </p>
            </div>

            <section aria-label="Hasil scan" className="-mt-6 flex flex-col gap-3.5 rounded-t-[26px] bg-white px-5 pb-8 pt-2.5 lg:mt-0 lg:w-[420px] lg:rounded-none lg:pt-8">
                <span aria-hidden="true" className="mx-auto block h-1 w-10 rounded-full bg-[#D3DDD0] lg:hidden" />

                {result ? (
                    <>
                        <div role="status" className="flex items-center gap-3">
                            <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white ${result.ok ? 'bg-leaf' : 'bg-danger'}`}>
                                <Icon name={result.ok ? 'check' : 'x'} size={22} strokeWidth={2.4} />
                            </span>
                            <span className="flex flex-col gap-px">
                                <span className="text-[17px] font-bold">{result.message}</span>
                                {result.order && <span className="text-[12.5px] text-muted">{result.order.code}</span>}
                            </span>
                        </div>
                        {result.ok && result.order && (
                            <div className="flex flex-col gap-1 rounded-[14px] border border-[#D3E3CE] bg-[#F3F8F1] px-3.5 py-3">
                                <span className="text-[12.5px] text-body">Serahkan ke {result.order.buyer}</span>
                                <span className="text-lg font-bold text-forest">{result.order.items}</span>
                                <span className="text-[12.5px] text-body">{result.order.slot}</span>
                            </div>
                        )}
                        {progress && progress.total > 0 && (
                            <div className="flex flex-col gap-1.5">
                                <div className="flex justify-between text-[13px]">
                                    <span className="text-muted">Sudah diambil di {progress.place}</span>
                                    <span className="tabular font-bold">
                                        {progress.done} dari {progress.total}
                                    </span>
                                </div>
                                <span className="block h-2 overflow-hidden rounded-full bg-[#EDF1EB]">
                                    <span className="block h-full rounded-full bg-leaf" style={{ width: `${Math.round((progress.done / progress.total) * 100)}%` }} />
                                </span>
                            </div>
                        )}
                    </>
                ) : (
                    <div className="flex flex-col gap-1">
                        <span className="text-[17px] font-bold">Siap scan</span>
                        <span className="text-[13px] text-muted">Hasil scan muncul di sini. Pesanan langsung ditandai diambil.</span>
                    </div>
                )}

                <label className="flex flex-col gap-1.5">
                    <span className="text-[12.5px] font-semibold text-body">QR tidak terbaca? Cari manual</span>
                    <span className="flex h-[46px] items-center gap-2 rounded-xl border border-[#DCE3D9] bg-[#FBFCFA] px-3 text-muted">
                        <Icon name="search" size={18} />
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Kode pesanan atau nama pembeli"
                            className="min-w-0 flex-1 border-0 bg-transparent text-sm text-[#141A16] outline-none"
                        />
                    </span>
                </label>
                {matches.length > 0 && (
                    <ul className="m-0 flex list-none flex-col gap-2 p-0">
                        {matches.map((t) => (
                            <li key={t.token} className="flex items-center justify-between gap-3 rounded-xl border border-panel-line px-3 py-2.5">
                                <span className="flex min-w-0 flex-col gap-px">
                                    <span className="text-sm font-bold">
                                        {t.code}, {t.buyer}
                                    </span>
                                    <span className="truncate text-xs text-muted">{t.items}</span>
                                </span>
                                {DONE.includes(t.status) ? (
                                    <span className="text-xs font-semibold text-muted">Sudah diambil</span>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            submit({ token: t.token });
                                            setQuery('');
                                        }}
                                        className="h-10 shrink-0 rounded-[10px] bg-forest px-3 text-[13px] font-bold text-white"
                                    >
                                        Tandai diambil
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <button type="button" onClick={() => setResult(null)} className="h-[52px] rounded-2xl bg-forest text-[15px] font-bold text-white">
                    Scan tiket berikutnya
                </button>
            </section>
        </div>
    );
}
