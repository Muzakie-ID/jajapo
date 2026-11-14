import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import Pagination from '@/Components/Pagination';
import FilterChips from '@/Components/FilterChips';
import { ShopStatus } from '@/Pages/Admin/Search';

export default function Shops({ shops, status, q, counts }) {
    const [term, setTerm] = useState(q || '');

    return (
        <PanelLayout variant="admin" active="shops" title="Toko">
            <div className="flex flex-col gap-4 px-4 pt-4 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Toko</h1>
                    <p className="m-0 text-sm text-muted">Semua toko yang pernah mendaftar. Yang menunggu persetujuan tampil di atas.</p>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/admin/toko', { ...(term ? { q: term } : {}), ...(status ? { status } : {}) }, { preserveScroll: true });
                    }}
                    className="flex gap-2.5"
                >
                    <label className="flex h-11 flex-1 items-center gap-2.5 rounded-[14px] border border-panel-line bg-white px-3.5 text-muted">
                        <Icon name="search" size={18} />
                        <input
                            type="search"
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Nama atau URL toko"
                            aria-label="Cari toko"
                            className="min-w-0 flex-1 border-0 bg-transparent text-sm text-ink outline-none"
                        />
                    </label>
                    <button type="submit" className="h-11 rounded-[14px] bg-forest px-4 text-sm font-semibold text-white">
                        Cari
                    </button>
                </form>

                <FilterChips
                    path="/admin/toko"
                    value={status}
                    options={[
                        { value: '', label: 'Semua' },
                        { value: 'pending', label: 'Menunggu', count: counts.pending },
                        { value: 'active', label: 'Aktif', count: counts.active },
                        { value: 'suspended', label: 'Dibekukan', count: counts.suspended },
                        { value: 'rejected', label: 'Ditolak', count: counts.rejected },
                    ]}
                />

                {shops.data.length === 0 && <PanelCard className="p-5 text-center text-sm text-muted">Tidak ada toko yang cocok.</PanelCard>}

                <div className="flex flex-col gap-2.5">
                    {shops.data.map((s) => (
                        <Link key={s.slug} href={`/admin/toko/${s.slug}`} className="no-underline">
                            <PanelCard className="flex items-center gap-3 p-4">
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-forest text-sm font-bold text-white">{s.initials}</span>
                                <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="truncate text-[15px] font-bold text-ink">{s.name}</span>
                                    <span className="truncate text-xs text-muted">
                                        {s.owner} · {s.email}
                                    </span>
                                    <span className="text-xs text-muted">
                                        {s.preorders} PO · {s.orders} pesanan · daftar {s.date}
                                    </span>
                                </span>
                                <ShopStatus status={s.status} />
                            </PanelCard>
                        </Link>
                    ))}
                </div>

                <Pagination page={shops} />
            </div>
        </PanelLayout>
    );
}
