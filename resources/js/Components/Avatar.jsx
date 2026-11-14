/** Logo toko kalau ada, selain itu inisial. */
export default function Avatar({ initials, logo, size = 'h-12 w-12' }) {
    return (
        <span className={`flex ${size} shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#DCEBD7] text-sm font-bold text-forest`}>
            {logo ? <img src={logo} alt="" className="h-full w-full object-cover" /> : initials}
        </span>
    );
}
