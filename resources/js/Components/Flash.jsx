import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Flash() {
    const { flash, errors } = usePage().props;
    const message = flash?.success || flash?.error || Object.values(errors || {})[0];
    const isError = Boolean(flash?.error || Object.keys(errors || {}).length);
    const [visible, setVisible] = useState(Boolean(message));

    useEffect(() => {
        setVisible(Boolean(message));
        if (!message) return undefined;
        const t = setTimeout(() => setVisible(false), 5000);
        return () => clearTimeout(t);
    }, [message]);

    if (!visible || !message) return null;
    return (
        <div
            role={isError ? 'alert' : 'status'}
            className={`fixed left-1/2 top-4 z-50 w-[min(92vw,440px)] -translate-x-1/2 rounded-2xl px-4 py-3 text-sm font-semibold shadow-lg ${
                isError ? 'bg-[#FCE4E1] text-[#A3281C]' : 'bg-forest text-white'
            }`}
        >
            {message}
        </div>
    );
}
