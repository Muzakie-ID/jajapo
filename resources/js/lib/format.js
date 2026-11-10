export function rupiah(value) {
    const n = Number(value || 0);
    const sign = n < 0 ? '-' : '';
    return `${sign}Rp${Math.abs(n).toLocaleString('id-ID')}`;
}

export const STATUS_STYLE = {
    pending_payment: { label: 'Menunggu bayar', cls: 'bg-[#FFF1D6] text-amber-ink' },
    paid: { label: 'Menunggu disiapkan', cls: 'bg-[#FFF1D6] text-amber-ink' },
    ready: { label: 'Siap diambil', cls: 'bg-[#DCEFD6] text-[#1F5A2D]' },
    out_for_delivery: { label: 'Sedang diantar', cls: 'bg-[#E3EBF5] text-[#23426B]' },
    picked_up: { label: 'Diambil', cls: 'bg-[#E3EBF5] text-[#23426B]' },
    delivered: { label: 'Terkirim', cls: 'bg-[#E3EBF5] text-[#23426B]' },
    not_picked_up: { label: 'Tidak diambil', cls: 'bg-[#ECEFEA] text-[#4A564C]' },
    cancelled: { label: 'Dibatalkan', cls: 'bg-[#FCE4E1] text-[#A3281C]' },
    refunded: { label: 'Di-refund', cls: 'bg-[#ECEFEA] text-[#4A564C]' },
    expired: { label: 'Kedaluwarsa', cls: 'bg-[#ECEFEA] text-[#4A564C]' },
    requested: { label: 'Diproses', cls: 'bg-[#FFF1D6] text-amber-ink' },
    done: { label: 'Selesai', cls: 'bg-[#DCEFD6] text-[#1F5A2D]' },
    rejected: { label: 'Ditolak', cls: 'bg-[#FCE4E1] text-[#A3281C]' },
    po_open: { label: 'Berjalan', cls: 'bg-[#DCEFD6] text-[#1F5A2D]' },
    po_scheduled: { label: 'Terjadwal', cls: 'bg-[#E3EBF5] text-[#23426B]' },
    po_draft: { label: 'Draf', cls: 'bg-[#FFF1D6] text-amber-ink' },
    po_closed: { label: 'Selesai', cls: 'bg-[#ECEFEA] text-[#4A564C]' },
    po_cancelled: { label: 'Dibatalkan', cls: 'bg-[#FCE4E1] text-[#A3281C]' },
};
