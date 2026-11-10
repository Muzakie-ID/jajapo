import { rupiah } from '@/lib/format';

export const COMPLAINT_STATUS = {
    open: { label: 'Menunggu penjual', cls: 'bg-[#FFF1D6] text-amber-ink' },
    disputed: { label: 'Diperiksa super admin', cls: 'bg-[#FFF1D6] text-amber-ink' },
    refunded: { label: 'Refund disetujui', cls: 'bg-[#DCEFD6] text-[#1F5A2D]' },
    rejected: { label: 'Ditolak', cls: 'bg-[#ECEFEA] text-[#4A564C]' },
};

export function ComplaintPill({ status, overdue }) {
    const s = COMPLAINT_STATUS[status];
    return (
        <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ${overdue ? 'bg-[#FCE4E1] text-[#A3281C]' : s.cls}`}>
            {overdue ? 'Lewat 24 jam' : s.label}
        </span>
    );
}

/** Isi komplain yang sama untuk panel penjual dan super admin. */
export default function ComplaintDetails({ c, showShop = false }) {
    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <span className="text-xs text-muted">
                        {c.code}
                        {showShop ? `, ${c.shop}` : ''} · {c.created_label}
                    </span>
                    <span className="text-[15px] font-bold">{c.reason}</span>
                    <span className="text-[13px] text-body">
                        {c.buyer}, {c.items}
                    </span>
                    <span className="text-xs text-muted">{c.place}</span>
                </div>
                <ComplaintPill status={c.status} overdue={c.overdue} />
            </div>
            <p className="m-0 whitespace-pre-line rounded-xl bg-[#F6F8F5] px-3 py-2.5 text-[13px] leading-[1.5] text-body">{c.description}</p>
            {c.photo_url && (
                <a href={c.photo_url} target="_blank" rel="noreferrer" className="self-start">
                    <img src={c.photo_url} alt={`Foto bukti komplain ${c.code}`} className="h-28 w-28 rounded-xl object-cover" />
                </a>
            )}
            <dl className="m-0 grid grid-cols-2 gap-3 text-[13px]">
                <div className="flex flex-col gap-0.5">
                    <dt className="text-xs text-muted">Refund diminta</dt>
                    <dd className="tabular m-0 font-bold">{rupiah(c.amount_requested)}</dd>
                </div>
                <div className="flex flex-col gap-0.5">
                    <dt className="text-xs text-muted">{c.amount_approved !== null ? 'Refund disetujui' : 'Nilai pesanan'}</dt>
                    <dd className="tabular m-0 font-bold">{rupiah(c.amount_approved ?? c.max_amount)}</dd>
                </div>
                {c.refund_to && (
                    <div className="col-span-2 flex flex-col gap-0.5">
                        <dt className="text-xs text-muted">Rekening refund</dt>
                        <dd className="m-0 font-semibold">{c.refund_to}</dd>
                    </div>
                )}
            </dl>
            {c.seller_response && (
                <p className="m-0 text-[13px] text-body">
                    <span className="font-semibold text-ink">Tanggapan penjual: </span>
                    {c.seller_response}
                </p>
            )}
            {c.decision_note && c.decision_note !== c.seller_response && (
                <p className="m-0 text-[13px] text-body">
                    <span className="font-semibold text-ink">Catatan keputusan: </span>
                    {c.decision_note}
                </p>
            )}
        </div>
    );
}
