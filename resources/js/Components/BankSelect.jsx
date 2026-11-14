import Icon from '@/Components/Icon';

/** Pilihan bank atau e-wallet. Daftarnya diatur super admin di /admin/bank. */
export default function BankSelect({ banks, value, onChange, className = '' }) {
    return (
        <span className="relative flex">
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                required
                className={`${className} w-full cursor-pointer appearance-none pr-10 ${value ? 'text-ink' : 'text-muted'}`}
            >
                <option value="" disabled>
                    Pilih bank atau e-wallet
                </option>
                {banks.map((name) => (
                    <option key={name} value={name} className="text-ink">
                        {name}
                    </option>
                ))}
            </select>
            <Icon name="chevronDown" size={18} className="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-muted" />
        </span>
    );
}
