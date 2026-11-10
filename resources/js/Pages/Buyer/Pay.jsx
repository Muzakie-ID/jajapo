import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import BuyerLayout, { TopBar } from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';
import { rupiah } from '@/lib/format';
import { countdown, timeLabel } from '@/lib/time';
import { clearCart } from '@/lib/cart';

export default function Pay({ order, canSimulate }) {
    const [now, setNow] = useState(Date.now());
    const [status, setStatus] = useState(order.status);
    const expired = status === 'expired' || (order.expires_at && new Date(order.expires_at).getTime() <= now);

    useEffect(() => clearCart(), []);

    useEffect(() => {
        const tick = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(tick);
    }, []);

    // Status berubah sendiri: cek ke server tiap 3 detik.
    useEffect(() => {
        if (status !== 'pending_payment') return undefined;
        const poll = setInterval(async () => {
            try {
                const res = await fetch(`/api/orders/${order.code}/status`, { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                if (data.status !== status) {
                    setStatus(data.status);
                    if (data.status !== 'pending_payment') router.visit(data.status === 'expired' || data.status === 'cancelled' ? '/pesanan' : `/pesanan/${order.code}`);
                }
            } catch {
                // sinyal putus sebentar: coba lagi di putaran berikutnya
            }
        }, 3000);
        return () => clearInterval(poll);
    }, [status, order.code]);

    const cancel = () => {
        if (window.confirm(`Batalkan pesanan ${order.code}? Kuota dikembalikan ke PO.`)) {
            router.post(`/bayar/${order.code}/batal`);
        }
    };

    return (
        <BuyerLayout title="Bayar dengan QRIS" hideNav>
            <TopBar
                back="/"
                title="Bayar dengan QRIS"
                right={
                    <button
                        type="button"
                        onClick={() => saveQr(order.code)}
                        aria-label="Simpan gambar QR"
                        className="flex h-11 w-11 items-center justify-center rounded-full border border-line bg-white text-ink"
                    >
                        <Icon name="download" size={20} />
                    </button>
                }
            />

            <div className="flex flex-col gap-3.5">
                {expired ? (
                    <div role="alert" className="rounded-[18px] border border-[#E5C7C2] bg-[#FCE4E1] px-4 py-3 text-sm font-semibold text-danger">
                        Waktu bayar habis. Pesanan ini kedaluwarsa dan kuotanya dikembalikan. Buat pesanan baru dari halaman PO.
                    </div>
                ) : (
                    <div role="status" className="flex items-center justify-between gap-3 rounded-[18px] border border-[#F1D9A9] bg-amber-bg px-4 py-3">
                        <div className="flex items-center gap-2.5">
                            <Icon name="clock" size={22} strokeWidth={1.9} className="text-amber-ink" />
                            <div className="flex flex-col gap-px">
                                <span className="text-sm font-bold text-amber-ink">Menunggu pembayaran</span>
                                <span className="text-xs text-amber-ink">QR berlaku sampai {timeLabel(order.expires_at)}</span>
                            </div>
                        </div>
                        <span className="tabular text-[22px] font-extrabold text-amber-ink">{countdown(order.expires_at, now)}</span>
                    </div>
                )}

                <section aria-label="Kode QRIS" className="flex flex-col items-center gap-3.5 rounded-3xl border border-line bg-white p-5">
                    <div className="flex flex-col items-center gap-0.5">
                        <span className="text-sm font-bold">{order.shop_name}</span>
                        <span className="text-xs text-muted">Pesanan {order.code}</span>
                    </div>
                    <div className={`rounded-[18px] border border-line bg-white p-3 ${expired ? 'opacity-30' : ''}`}>
                        {order.qr_string ? (
                            <QRCodeSVG value={order.qr_string} size={188} fgColor="#18241B" level="M" title="QR pembayaran" />
                        ) : (
                            <div className="flex h-[188px] w-[188px] items-center justify-center text-center text-sm text-muted">QR belum tersedia</div>
                        )}
                    </div>
                    <div className="flex flex-col items-center gap-0.5 text-center">
                        <span className="text-xs text-muted">Total bayar</span>
                        <span className="tabular text-[30px] font-extrabold tracking-[-0.01em]">{rupiah(order.total)}</span>
                        <span className="text-xs text-muted">Nominal sudah terisi otomatis saat QR di-scan.</span>
                    </div>
                    {order.checkout_url && !expired && (
                        <a href={order.checkout_url} target="_blank" rel="noreferrer" className="text-[13px] font-semibold text-leaf">
                            QR tidak terbaca? Buka halaman bayar QRIS
                        </a>
                    )}
                </section>

                <section aria-label="Rincian pembayaran" className="flex flex-col gap-2.5 rounded-[20px] border border-line bg-white px-4 py-3.5 text-[13px]">
                    <Row label={`Subtotal, ${order.item_count} item`} value={rupiah(order.subtotal)} />
                    <Row label={`Ongkir, ${order.place}`} value={rupiah(order.delivery_fee)} />
                    <Row label="Biaya layanan (kode unik)" value={rupiah(order.unique_code)} />
                    <div className="h-px bg-[#E6EDE3]" />
                    <div className="flex justify-between text-[15px] font-bold">
                        <span>Total</span>
                        <span className="tabular">{rupiah(order.total)}</span>
                    </div>
                </section>

                <p className="m-0 flex gap-2.5 text-[12.5px] leading-normal text-body">
                    <Icon name="info" size={18} strokeWidth={1.9} className="mt-px text-leaf" />
                    Status berubah jadi Lunas sendiri setelah pembayaran masuk. Tidak perlu kirim bukti transfer.
                </p>

                {canSimulate && !expired && (
                    <button
                        type="button"
                        onClick={() => router.post(`/bayar/${order.code}/simulasi`)}
                        className="h-11 rounded-[14px] border border-dashed border-leaf bg-[#F3F8F1] text-sm font-bold text-leaf"
                    >
                        Simulasikan pembayaran masuk (mode lokal)
                    </button>
                )}

                <div className="flex gap-2.5">
                    {!expired && (
                        <button type="button" onClick={cancel} className="h-[50px] flex-1 rounded-2xl border border-[#E5C7C2] bg-white text-sm font-bold text-danger">
                            Batalkan pesanan
                        </button>
                    )}
                    <Link href="/pesanan" className="flex h-[50px] flex-[1.3] items-center justify-center rounded-2xl bg-forest text-sm font-bold text-white no-underline hover:text-white">
                        Lihat pesanan saya
                    </Link>
                </div>
            </div>
        </BuyerLayout>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-body">{label}</span>
            <span className="tabular">{value}</span>
        </div>
    );
}

// Menyimpan QR yang sedang tampil sebagai file SVG.
function saveQr(code) {
    const el = document.querySelector('[aria-label="Kode QRIS"] svg');
    if (!el) return;
    const blob = new Blob([new XMLSerializer().serializeToString(el)], { type: 'image/svg+xml' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `qris-${code}.svg`;
    a.click();
    URL.revokeObjectURL(url);
}
