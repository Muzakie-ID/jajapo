import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';

export default function Members({ shop, domain, roles, members, invitations }) {
    const form = useForm({ email: '', role: 'member' });
    const roleLabel = Object.fromEntries(roles.map((r) => [r.key, r.label]));

    return (
        <PanelLayout variant="seller" active="members" title="Anggota toko" shop={shop}>
            <div className="flex flex-col gap-4 px-4 pt-4 lg:gap-5 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Anggota toko</h1>
                    <p className="m-0 text-sm text-muted">
                        Anggota bisa mengelola PO, scan tiket, dan menanggapi komplain. Admin toko juga bisa mengubah pengaturan, rekening, anggota, dan mengajukan pencairan.
                    </p>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start lg:gap-5">
                    <PanelCard className="flex flex-col p-4">
                        <h2 className="m-0 pb-2 text-base font-bold">{members.length} anggota</h2>
                        {members.map((m) => (
                            <div key={m.id} className="flex flex-wrap items-center gap-3 border-t border-[#EDF1EB] py-3">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#DCEBD7] text-sm font-bold text-[#1F5A2D]">{m.initials}</span>
                                <span className="flex min-w-0 flex-1 flex-col gap-px">
                                    <span className="truncate text-sm font-bold">
                                        {m.name}
                                        {m.is_me && <span className="font-medium text-muted"> (kamu)</span>}
                                    </span>
                                    <span className="truncate text-xs text-muted">
                                        {m.email} · sejak {m.joined}
                                    </span>
                                </span>
                                {m.is_creator ? (
                                    <span className="rounded-full bg-[#E9F2E6] px-2.5 py-1 text-xs font-bold text-forest">Pembuat toko</span>
                                ) : (
                                    <div className="flex items-center gap-1.5">
                                        <select
                                            aria-label={`Peran ${m.name}`}
                                            value={m.role}
                                            onChange={(e) => router.post(`/seller/anggota/${m.id}/peran`, { role: e.target.value }, { preserveScroll: true })}
                                            className="h-9 rounded-[10px] border border-line bg-white px-2 text-[13px] font-semibold"
                                        >
                                            {roles.map((r) => (
                                                <option key={r.key} value={r.key}>
                                                    {r.label}
                                                </option>
                                            ))}
                                        </select>
                                        {!m.is_me && (
                                            <button
                                                type="button"
                                                aria-label={`Keluarkan ${m.name}`}
                                                onClick={() => window.confirm(`Keluarkan ${m.name} dari ${shop.name}?`) && router.delete(`/seller/anggota/${m.id}`, { preserveScroll: true })}
                                                className="flex h-9 w-9 items-center justify-center rounded-[10px] text-muted hover:text-danger"
                                            >
                                                <Icon name="trash" size={17} />
                                            </button>
                                        )}
                                    </div>
                                )}
                            </div>
                        ))}
                    </PanelCard>

                    <div className="flex flex-col gap-4">
                        <PanelCard className="flex flex-col gap-3 p-4">
                            <h2 className="m-0 text-base font-bold">Undang anggota</h2>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.post('/seller/anggota/undang', { preserveScroll: true, onSuccess: () => form.reset('email') });
                                }}
                                className="flex flex-col gap-3"
                            >
                                <label className="flex flex-col gap-1.5">
                                    <span className="text-[13px] font-semibold text-body">Email UGM</span>
                                    <input
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) => form.setData('email', e.target.value)}
                                        placeholder={`nama@${domain}`}
                                        className="h-12 rounded-[14px] border border-line bg-white px-3.5 text-sm outline-none"
                                    />
                                    {form.errors.email && <span className="text-xs font-semibold text-danger">{form.errors.email}</span>}
                                </label>
                                <div role="radiogroup" aria-label="Peran" className="grid grid-cols-2 gap-1 rounded-xl bg-[#EEF2EC] p-1">
                                    {roles.map((r) => (
                                        <button
                                            key={r.key}
                                            type="button"
                                            role="radio"
                                            aria-checked={form.data.role === r.key}
                                            onClick={() => form.setData('role', r.key)}
                                            className={`h-10 rounded-[10px] text-[13px] font-semibold ${form.data.role === r.key ? 'bg-white text-forest shadow-[0_2px_8px_rgba(20,26,22,0.08)]' : 'text-muted'}`}
                                        >
                                            {r.label}
                                        </button>
                                    ))}
                                </div>
                                <button type="submit" disabled={form.processing} className="h-11 rounded-xl bg-forest text-sm font-semibold text-white disabled:opacity-60">
                                    Buat tautan undangan
                                </button>
                                <p className="m-0 text-xs text-muted">Tautan berlaku 7 hari dan hanya bisa dipakai oleh email yang diundang.</p>
                            </form>
                        </PanelCard>

                        {invitations.length > 0 && (
                            <PanelCard className="flex flex-col p-4">
                                <h2 className="m-0 pb-2 text-base font-bold">Undangan belum dipakai</h2>
                                {invitations.map((i) => (
                                    <Invite key={i.id} invitation={i} shop={shop} roleLabel={roleLabel[i.role]} />
                                ))}
                            </PanelCard>
                        )}
                    </div>
                </div>
            </div>
        </PanelLayout>
    );
}

function Invite({ invitation: i, shop, roleLabel }) {
    const [copied, setCopied] = useState(false);
    const text = `Kamu diundang jadi ${roleLabel.toLowerCase()} ${shop.name} di Jajapo. Masuk pakai ${i.email}, lalu buka: ${i.url}`;

    return (
        <div className="flex flex-col gap-2 border-t border-[#EDF1EB] py-3">
            <div className="flex items-start justify-between gap-2">
                <span className="flex min-w-0 flex-col gap-px">
                    <span className="truncate text-sm font-bold">{i.email}</span>
                    <span className="text-xs text-muted">
                        {roleLabel}, berlaku sampai {i.expires}
                    </span>
                </span>
                <button
                    type="button"
                    onClick={() => window.confirm(`Batalkan undangan untuk ${i.email}?`) && router.delete(`/seller/anggota/undangan/${i.id}`, { preserveScroll: true })}
                    className="shrink-0 text-xs font-semibold text-danger"
                >
                    Batalkan
                </button>
            </div>
            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={async () => {
                        try {
                            await navigator.clipboard.writeText(i.url);
                            setCopied(true);
                            setTimeout(() => setCopied(false), 2000);
                        } catch {
                            window.prompt('Salin tautan undangan:', i.url);
                        }
                    }}
                    className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-[#DCE3D9] bg-white text-[13px] font-semibold"
                >
                    <Icon name={copied ? 'check' : 'copy'} size={16} />
                    {copied ? 'Tersalin' : 'Salin tautan'}
                </button>
                <a
                    href={`https://wa.me/?text=${encodeURIComponent(text)}`}
                    target="_blank"
                    rel="noreferrer"
                    className="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl bg-forest text-[13px] font-semibold text-white no-underline hover:text-white"
                >
                    <Icon name="message" size={16} />
                    Kirim lewat WhatsApp
                </a>
            </div>
        </div>
    );
}
