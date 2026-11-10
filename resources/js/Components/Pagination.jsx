import { Link } from '@inertiajs/react';

/** Navigasi halaman untuk daftar panjang di panel super admin. */
export default function Pagination({ page }) {
    if (page.last_page <= 1) return null;

    const btn = 'flex h-10 min-w-10 items-center justify-center rounded-xl px-3 text-[13px] font-semibold no-underline';

    return (
        <nav aria-label="Halaman" className="flex items-center justify-between gap-3 pt-1">
            <span className="text-[12.5px] text-muted">
                {page.from}–{page.to} dari {page.total}
            </span>
            <span className="flex items-center gap-2">
                {page.prev_page_url ? (
                    <Link href={page.prev_page_url} preserveScroll className={`${btn} border border-panel-line bg-white text-ink`}>
                        Sebelumnya
                    </Link>
                ) : (
                    <span className={`${btn} border border-panel-line text-[#A8B4AA]`}>Sebelumnya</span>
                )}
                <span className="tabular text-[12.5px] text-muted">
                    {page.current_page}/{page.last_page}
                </span>
                {page.next_page_url ? (
                    <Link href={page.next_page_url} preserveScroll className={`${btn} border border-panel-line bg-white text-ink`}>
                        Berikutnya
                    </Link>
                ) : (
                    <span className={`${btn} border border-panel-line text-[#A8B4AA]`}>Berikutnya</span>
                )}
            </span>
        </nav>
    );
}
