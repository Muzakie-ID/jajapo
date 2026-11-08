// Keranjang disimpan di HP pembeli, satu keranjang = satu PO.
const KEY = 'jajapo-cart';

export function readCart() {
    try {
        const raw = window.localStorage.getItem(KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

export function writeCart(cart) {
    try {
        if (!cart || cart.count === 0) window.localStorage.removeItem(KEY);
        else window.localStorage.setItem(KEY, JSON.stringify(cart));
    } catch {
        // Penyimpanan diblokir: keranjang tetap jalan di halaman ini saja.
    }
}

export function clearCart() {
    writeCart(null);
}
