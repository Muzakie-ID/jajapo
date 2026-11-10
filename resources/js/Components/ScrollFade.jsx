import { useRef, useState, useEffect, useCallback } from 'react';

export default function ScrollFade({ className = '', children, ...props }) {
    const ref = useRef(null);
    const [atEnd, setAtEnd] = useState(false);
    const [atStart, setAtStart] = useState(true);

    const check = useCallback(() => {
        const el = ref.current;
        if (!el) return;
        setAtStart(el.scrollLeft <= 2);
        setAtEnd(el.scrollLeft + el.clientWidth >= el.scrollWidth - 2);
    }, []);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        check();
        el.addEventListener('scroll', check, { passive: true });
        const ro = new ResizeObserver(check);
        ro.observe(el);
        return () => { el.removeEventListener('scroll', check); ro.disconnect(); };
    }, [check]);

    let mask = 'none';
    if (!atStart && !atEnd) mask = 'linear-gradient(to right, transparent, black 32px, black calc(100% - 32px), transparent)';
    else if (!atEnd) mask = 'linear-gradient(to right, black calc(100% - 32px), transparent)';
    else if (!atStart) mask = 'linear-gradient(to right, transparent, black 32px)';

    return (
        <div style={{ maskImage: mask, WebkitMaskImage: mask }}>
            <div ref={ref} {...props} className={`-mr-5 flex overflow-x-auto pr-5 [scrollbar-width:none] ${className}`}>
                {children}
            </div>
        </div>
    );
}
