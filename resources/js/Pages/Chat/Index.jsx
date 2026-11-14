import { Link, router } from '@inertiajs/react';
import BuyerLayout, { TopBar } from '@/Layouts/BuyerLayout';
import Icon from '@/Components/Icon';
import Avatar from '@/Components/Avatar';

export default function Index({ tab, unreadOnly, shopFilter, shops, back, tabUnread, conversations }) {
    const isSeller = shops.length > 0;
    const go = (changes) => {
        const next = { tab, belum: unreadOnly ? 1 : undefined, toko: shopFilter || undefined, dari: back, ...changes };
        router.get('/obrolan', Object.fromEntries(Object.entries(next).filter(([, v]) => v !== undefined && v !== null && v !== '')), { preserveScroll: true, preserveState: true });
    };

    return (
        <BuyerLayout title="Obrolan" active="account">
            <TopBar back={back} title="Obrolan" />

            {isSeller && (
                <div role="tablist" aria-label="Obrolan sebagai" className="mb-3 grid grid-cols-2 rounded-2xl bg-white p-1">
                    {[
                        ['pembeli', 'Pembeli'],
                        ['penjual', 'Penjual'],
                    ].map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            aria-selected={tab === value}
                            onClick={() => go({ tab: value, toko: undefined, belum: undefined })}
                            className={`relative h-10 rounded-xl text-sm font-bold ${tab === value ? 'bg-forest text-white' : 'text-muted'}`}
                        >
                            {label}
                            {tabUnread[value] > 0 && <span aria-label={`${tabUnread[value]} belum dibaca`} className="absolute right-3 top-2 h-2 w-2 rounded-full bg-danger" />}
                        </button>
                    ))}
                </div>
            )}

            <div className="mb-3 flex flex-wrap items-center gap-2">
                {[
                    [false, 'Semua'],
                    [true, 'Belum dibaca'],
                ].map(([value, label]) => (
                    <button
                        key={label}
                        type="button"
                        aria-pressed={unreadOnly === value}
                        onClick={() => go({ belum: value ? 1 : undefined })}
                        className={`h-9 rounded-full px-3.5 text-[13px] font-semibold ${unreadOnly === value ? 'bg-[#DCEBD7] text-forest' : 'bg-white text-body'}`}
                    >
                        {label}
                    </button>
                ))}
                {tab === 'penjual' && shops.length > 1 && (
                    <select
                        aria-label="Pilih toko"
                        value={shopFilter || ''}
                        onChange={(e) => go({ toko: e.target.value || undefined })}
                        className="ml-auto h-9 max-w-[170px] cursor-pointer rounded-full border border-line bg-white px-3 text-[13px] text-ink outline-none"
                    >
                        <option value="">Semua toko</option>
                        {shops.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </select>
                )}
            </div>

            {conversations.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-[20px] border border-line bg-white px-5 py-10 text-center">
                    <Icon name="chat" size={28} className="text-leaf" />
                    <span className="text-[15px] font-bold">{unreadOnly ? 'Semua sudah dibaca' : 'Belum ada obrolan'}</span>
                    <span className="text-[13px] text-muted">
                        {tab === 'penjual'
                            ? 'Pesan dari pembeli tokomu muncul di sini.'
                            : 'Tekan Chat penjual di halaman toko atau tiket pesanan untuk bertanya ke penjual.'}
                    </span>
                </div>
            ) : (
                <ul className="m-0 flex list-none flex-col overflow-hidden rounded-[20px] border border-line bg-white p-0">
                    {conversations.map((c) => (
                        <li key={c.id} className="border-b border-[#EDF1EB] last:border-b-0">
                            <Link href={`/obrolan/${c.id}`} className="flex items-center gap-3 px-4 py-3.5 text-ink no-underline hover:text-ink">
                                <Avatar initials={c.initials} logo={c.logo_url} />
                                <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="flex items-baseline justify-between gap-2">
                                        <span className={`truncate text-[15px] ${c.unread ? 'font-bold' : 'font-semibold'}`}>{c.name}</span>
                                        <span className={`shrink-0 text-xs ${c.unread ? 'font-semibold text-leaf' : 'text-muted'}`}>{c.time}</span>
                                    </span>
                                    {c.context && <span className="truncate text-xs text-muted">{c.context}</span>}
                                    <span className="flex items-center justify-between gap-2">
                                        <span className={`truncate text-[13.5px] ${c.unread ? 'text-ink' : 'text-muted'}`}>{c.preview}</span>
                                        {c.unread > 0 && (
                                            <span className="tabular min-w-[20px] shrink-0 rounded-full bg-danger px-1.5 py-px text-center text-[11px] font-bold text-white">{c.unread}</span>
                                        )}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </BuyerLayout>
    );
}
