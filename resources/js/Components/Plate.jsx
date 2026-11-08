// Ilustrasi piring abstrak, dipakai selama penjual belum mengunggah poster.
const PALETTES = [
    { tint: '#F6ECD4', food: '#EDC25A', dot: '#C68A31' },
    { tint: '#FCEFD6', food: '#F4B63F', dot: '#FFE29A' },
    { tint: '#F4E7D2', food: '#D9A35B', dot: '#B9782F' },
    { tint: '#FBE6DD', food: '#F09A6B', dot: '#FFD4A8' },
    { tint: '#EFE6DC', food: '#B88A62', dot: '#E9D6BF' },
];

export function palette(seed = 0) {
    return PALETTES[Math.abs(seed) % PALETTES.length];
}

export default function Plate({ seed = 0, size = 96, rich = false }) {
    const p = palette(seed);
    return (
        <svg viewBox="0 0 100 100" width={size} height={size} aria-hidden="true">
            <circle cx="50" cy="50" r="46" fill="#FFFFFF" stroke="#E8DCC2" strokeWidth="2" />
            {rich && <circle cx="50" cy="50" r="40" fill={p.tint} />}
            <circle cx="50" cy="50" r="34" fill={p.food} />
            <circle cx="40" cy="40" r="5" fill={p.dot} />
            <circle cx="59" cy="37" r="4" fill={p.dot} />
            <circle cx="63" cy="57" r="5" fill={p.dot} />
            <circle cx="44" cy="62" r="4" fill={p.dot} />
            <circle cx="51" cy="50" r="3" fill={p.dot} />
            <circle cx="35" cy="53" r="2.5" fill="#5E9E5E" />
            <circle cx="66" cy="45" r="2.5" fill="#5E9E5E" />
        </svg>
    );
}

export function PlateTile({ seed = 0, image, className = '', plateSize = 96 }) {
    const p = palette(seed);
    if (image) {
        return <img src={image} alt="" className={`object-cover ${className}`} />;
    }
    return (
        <div className={`flex items-center justify-center ${className}`} style={{ background: p.tint }}>
            <Plate seed={seed} size={plateSize} />
        </div>
    );
}
