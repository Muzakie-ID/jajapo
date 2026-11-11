import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';
import Pagination from '@/Components/Pagination';
import FilterChips from '@/Components/FilterChips';

const STATUS = {
    new: { label: 'Baru', cls: 'bg-amber-bg text-amber-ink' },
    in_progress: { label: 'Ditangani', cls: 'bg-[#E3ECF7] text-[#1F4573]' },
    done: { label: 'Selesai', cls: 'bg-[#DCEFD6] text-[#1F5A2D]' },
};

const KIND_CLS = { bug: 'bg-[#FBE4E1] text-[#8A2A1E]', idea: 'bg-[#E9F1E5] text-forest', other: 'bg-[#ECEFEA] text-[#4A564C]' };

/** Ringkas user agent jadi "Chrome di Android" supaya mudah dibaca sekilas. */
function device(ua) {
    if (!ua) return null;
    const os = /iPhone|iPad/.test(ua) ? 'iPhone/iPad' : /Android/.test(ua) ? 'Android' : /Windows/.test(ua) ? 'Windows' : /Mac OS/.test(ua) ? 'Mac' : /Linux/.test(ua) ? 'Linux' : null;
    const browser = /Edg\//.test(ua) ? 'Edge' : /SamsungBrowser/.test(ua) ? 'Samsung Internet' : /Firefox\//.test(ua) ? 'Firefox' : /Chrome\//.test(ua) ? 'Chrome' : /Safari\//.test(ua) ? 'Safari' : null;
    if (!os && !browser) return 'Perangkat lain';
    return [browser, os].filter(Boolean).join(' di ');
}

export default function Feedback({ items, status, jenis, kinds, counts }) {
    return (
        <PanelLayout variant="admin" active="feedback" title="Masukan pengguna">
            <div className="flex flex-col gap-4 px-4 pt-4 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Masukan pengguna</h1>
                    <p className="m-0 text-sm text-muted">Laporan bug dan saran dari pembeli maupun penjual. Masukan baru tampil paling atas.</p>
                </div>

                <FilterChips
                    path="/admin/masukan"
                    value={status}
                    options={[
                        { value: '', label: 'Semua' },
                        { value: 'new', label: 'Baru', count: counts.new },
                        { value: 'in_progress', label: 'Ditangani', count: counts.in_progress },
                        { value: 'done', label: 'Selesai' },
                    ]}
                />
                <FilterChips
                    path="/admin/masukan"
                    param="jenis"
                    value={jenis}
                    options={[{ value: '', label: 'Semua jenis' }, ...Object.entries(kinds).map(([value, label]) => ({ value, label }))]}
                />

                {items.data.length === 0 && (
                    <PanelCard className="flex flex-col items-center gap-2 p-8 text-center">
                        <Icon name="message" size={26} className="text-leaf" />
                        <span className="text-[15px] font-bold">Belum ada masukan</span>
                        <span className="text-[13px] text-muted">Pengguna bisa mengirim dari halaman Akun atau menu penjual.</span>
                    </PanelCard>
                )}

                <div className="flex flex-col gap-3">
                    {items.data.map((f) => (
                        <Item key={f.id} f={f} kinds={kinds} />
                    ))}
                </div>

                <Pagination page={items} />
            </div>
        </PanelLayout>
    );
}

function Item({ f, kinds }) {
    const form = useForm({ status: f.status, note: f.note || '' });
    const [noteOpen, setNoteOpen] = useState(false);
    const st = STATUS[f.status];

    const setStatus = (next) => {
        form.transform((d) => ({ ...d, status: next }));
        form.post(`/admin/masukan/${f.id}/status`, { preserveScroll: true, onSuccess: () => setNoteOpen(false) });
    };

    return (
        <PanelCard className="flex flex-col gap-3 p-4 lg:p-5">
            <div className="flex flex-wrap items-center gap-2">
                <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${KIND_CLS[f.kind] || KIND_CLS.other}`}>{kinds[f.kind] || f.kind}</span>
                <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${st.cls}`}>{st.label}</span>
                <span className="ml-auto text-xs text-muted">{f.date}</span>
            </div>

            <p className="m-0 whitespace-pre-line text-[14.5px] leading-[1.55] text-ink">{f.message}</p>

            {f.photo_url && (
                <a href={f.photo_url} target="_blank" rel="noreferrer" className="block w-fit overflow-hidden rounded-xl border border-panel-line">
                    <img src={f.photo_url} alt="Screenshot dari pengguna" className="block max-h-56 max-w-full object-contain" />
                </a>
            )}

            <dl className="m-0 grid gap-x-5 gap-y-2 rounded-xl bg-[#F6F8F5] px-3.5 py-3 text-[13px] sm:grid-cols-2">
                <Meta label="Pengirim">
                    <span className="font-semibold">{f.user.name}</span>
                    <span className="block text-muted">{f.user.email}</span>
                    {f.user.phone && <span className="tabular block text-muted">{f.user.phone}</span>}
                </Meta>
                <Meta label="Toko">
                    {f.user.shops.length === 0
                        ? <span className="text-muted">Pembeli, belum punya toko</span>
                        : f.user.shops.map((s, i) => (
                              <span key={s.slug}>
                                  {i > 0 && ', '}
                                  <Link href={`/admin/toko/${s.slug}`} className="font-semibold no-underline">
                                      {s.name}
                                  </Link>
                              </span>
                          ))}
                </Meta>
                <Meta label="Dikirim dari halaman">
                    {f.page ? <code className="text-[12.5px]">{f.page}</code> : <span className="text-muted">Tidak tercatat</span>}
                </Meta>
                <Meta label="Perangkat">
                    <span title={f.device || ''}>{device(f.device) || <span className="text-muted">Tidak tercatat</span>}</span>
                </Meta>
            </dl>

            {f.note && !noteOpen && (
                <p className="m-0 rounded-xl border border-panel-line px-3.5 py-2.5 text-[13px] text-body">
                    <span className="font-semibold text-ink">Catatan tim: </span>
                    {f.note}
                    {f.handler && <span className="block pt-1 text-xs text-muted">{f.handler}, {f.handled}</span>}
                </p>
            )}

            {noteOpen && (
                <label className="flex flex-col gap-1.5">
                    <span className="text-[13px] font-semibold text-[#3E4C41]">Catatan internal (opsional)</span>
                    <textarea
                        rows={3}
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                        maxLength={1000}
                        placeholder="Contoh: sudah diperbaiki di versi 12 Nov, penyebabnya cache QR."
                        className="rounded-[12px] border border-panel-line bg-white px-3 py-2.5 text-sm outline-none"
                    />
                    {form.errors.note && <span className="text-xs font-semibold text-danger">{form.errors.note}</span>}
                </label>
            )}

            <div className="flex flex-wrap gap-2">
                {f.status !== 'in_progress' && f.status !== 'done' && (
                    <button type="button" disabled={form.processing} onClick={() => setStatus('in_progress')} className="h-10 rounded-xl border border-panel-line bg-white px-3.5 text-[13px] font-semibold text-ink">
                        Tandai ditangani
                    </button>
                )}
                {f.status !== 'done' && (
                    <button type="button" disabled={form.processing} onClick={() => setStatus('done')} className="h-10 rounded-xl bg-forest px-3.5 text-[13px] font-semibold text-white">
                        Tandai selesai
                    </button>
                )}
                {f.status === 'done' && (
                    <button type="button" disabled={form.processing} onClick={() => setStatus('new')} className="h-10 rounded-xl border border-panel-line bg-white px-3.5 text-[13px] font-semibold text-ink">
                        Buka lagi
                    </button>
                )}
                <button type="button" onClick={() => setNoteOpen((v) => !v)} className="h-10 px-2 text-[13px] font-semibold text-leaf">
                    {noteOpen ? 'Tutup catatan' : f.note ? 'Ubah catatan' : 'Tambah catatan'}
                </button>
                {noteOpen && (
                    <button type="button" disabled={form.processing} onClick={() => setStatus(f.status)} className="h-10 rounded-xl border border-panel-line bg-white px-3.5 text-[13px] font-semibold text-ink">
                        Simpan catatan
                    </button>
                )}
            </div>
        </PanelCard>
    );
}

function Meta({ label, children }) {
    return (
        <div className="flex min-w-0 flex-col gap-0.5">
            <dt className="text-xs text-muted">{label}</dt>
            <dd className="m-0 min-w-0 break-words">{children}</dd>
        </div>
    );
}
