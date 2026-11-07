import { STATUS_STYLE } from '@/lib/format';

export default function StatusPill({ status, className = '' }) {
    const s = STATUS_STYLE[status] || { label: status, cls: 'bg-[#ECEFEA] text-[#4A564C]' };
    return (
        <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ${s.cls} ${className}`}>
            {s.label}
        </span>
    );
}
