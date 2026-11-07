import { Link } from '@inertiajs/react';
import BuyerLayout from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';

export default function Shops({ shops }) {
    return (
        <BuyerLayout title="Toko" active="shops">
            <h1 className="m-0 mb-4 text-2xl font-bold tracking-[-0.01em]">Toko</h1>
            <div className="flex flex-col gap-2.5">
                {shops.map((s) => (
                    <Link key={s.slug} href={`/${s.slug}`} className="flex items-center gap-3 rounded-2xl border border-line bg-white p-3 text-ink no-underline hover:text-ink">
                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-forest text-sm font-bold text-white">{s.initials}</span>
                        <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span className="text-[15px] font-bold">{s.name}</span>
                            <span className="text-xs text-muted">{s.open_count > 0 ? `${s.open_count} PO sedang buka` : 'Belum ada PO yang buka'}</span>
                        </span>
                        <Icon name="chevronRight" size={18} strokeWidth={2} className="text-muted" />
                    </Link>
                ))}
            </div>
        </BuyerLayout>
    );
}
