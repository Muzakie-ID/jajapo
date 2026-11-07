const DAYS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export function dayLabel(iso) {
    const d = new Date(iso);
    return `${DAYS[d.getDay()]}, ${d.getDate()} ${MONTHS[d.getMonth()]}`;
}

export function timeLabel(iso) {
    const d = new Date(iso);
    return `${String(d.getHours()).padStart(2, '0')}.${String(d.getMinutes()).padStart(2, '0')}`;
}

// "2 jam 40 mnt", "45 mnt", "besok", atau tanggal kalau masih lama.
export function closesIn(iso, now = Date.now()) {
    const ms = new Date(iso).getTime() - now;
    if (ms <= 0) return 'sudah tutup';
    const mins = Math.floor(ms / 60000);
    if (mins < 60) return `${Math.max(mins, 1)} mnt`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours} jam ${mins % 60} mnt`;
    const target = new Date(iso);
    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);
    if (target.toDateString() === tomorrow.toDateString()) return 'besok';
    return dayLabel(iso);
}

export function countdown(iso, now = Date.now()) {
    const s = Math.max(0, Math.floor((new Date(iso).getTime() - now) / 1000));
    return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
}
