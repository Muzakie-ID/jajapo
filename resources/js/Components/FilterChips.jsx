import { router } from '@inertiajs/react';

/** Baris chip filter status yang mengganti query string halaman. */
export default function FilterChips({ path, param = 'status', value, options }) {
    return (
        <div className="-mr-4 flex gap-2 overflow-x-auto pr-4 [scrollbar-width:none]">
            {options.map((o) => {
                const on = (value || '') === o.value;
                return (
                    <button
                        key={o.value || 'all'}
                        type="button"
                        aria-pressed={on}
                        onClick={() => router.get(path, o.value ? { [param]: o.value } : {}, { preserveScroll: true })}
                        className={`flex h-10 shrink-0 items-center gap-1.5 rounded-full px-3.5 text-[13px] font-semibold ${
                            on ? 'bg-forest text-white' : 'border border-panel-line bg-white text-ink'
                        }`}
                    >
                        {o.label}
                        {o.count > 0 && (
                            <span className={`tabular rounded-full px-1.5 text-[11px] ${on ? 'bg-white/20' : 'bg-[#EDF1EB]'}`}>{o.count}</span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
