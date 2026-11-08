import { router, useForm } from '@inertiajs/react';
import BuyerLayout, { TopBar } from '@/Layouts/BuyerLayout';

const PROBLEMS = {
    missing: ['Undangan tidak ditemukan', 'Tautan ini salah atau undangannya sudah dibatalkan. Minta tautan baru ke admin toko.'],
    used: ['Undangan sudah dipakai', 'Tautan ini sudah dipakai untuk bergabung. Buka panel penjual untuk mulai.'],
    expired: ['Undangan kedaluwarsa', 'Tautan undangan hanya berlaku 7 hari. Minta tautan baru ke admin toko.'],
    inactive: ['Toko sedang tidak aktif', 'Toko ini belum disetujui atau sedang dinonaktifkan super admin.'],
};

export default function Invitation({ token, problem, invitation, currentEmail }) {
    const form = useForm({});

    return (
        <BuyerLayout title="Undangan toko" active="account">
            <TopBar back="/" title="Undangan toko" />
            <section className="flex flex-col items-center gap-4 rounded-[24px] border border-line bg-white px-5 py-7 text-center">
                {invitation && (
                    <span className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-forest text-xl font-bold text-white">
                        {invitation.logo_url ? <img src={invitation.logo_url} alt="" className="h-full w-full object-cover" /> : invitation.initials}
                    </span>
                )}
                {problem === null ? (
                    <>
                        <div className="flex flex-col gap-1.5">
                            <h1 className="m-0 text-xl font-bold">Gabung ke {invitation.shop}</h1>
                            <p className="m-0 text-sm text-body">
                                {invitation.inviter ? `${invitation.inviter} mengundangmu` : 'Kamu diundang'} sebagai <strong>{invitation.role.toLowerCase()}</strong>.
                            </p>
                            <p className="m-0 text-xs text-muted">Berlaku sampai {invitation.expires}.</p>
                        </div>
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={() => form.post(`/undangan/${token}`)}
                            className="h-[52px] w-full rounded-2xl bg-forest text-[15px] font-bold text-white disabled:opacity-60"
                        >
                            Terima undangan
                        </button>
                    </>
                ) : problem === 'wrong_user' ? (
                    <div className="flex flex-col gap-3">
                        <h1 className="m-0 text-xl font-bold">Undangan untuk akun lain</h1>
                        <p className="m-0 text-sm text-body">
                            Undangan {invitation.shop} ini untuk <strong>{invitation.email}</strong>, sedangkan kamu masuk sebagai <strong>{currentEmail}</strong>.
                        </p>
                        <button type="button" onClick={() => router.post('/logout')} className="h-12 rounded-2xl border border-line bg-white text-sm font-bold">
                            Keluar lalu masuk dengan {invitation.email}
                        </button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        <h1 className="m-0 text-xl font-bold">{PROBLEMS[problem][0]}</h1>
                        <p className="m-0 text-sm text-body">{PROBLEMS[problem][1]}</p>
                    </div>
                )}
            </section>
        </BuyerLayout>
    );
}
