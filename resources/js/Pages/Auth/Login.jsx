import { Head, router } from '@inertiajs/react';
import Icon from '@/Components/Icon';
import Flash from '@/Components/Flash';

export default function Login({ googleEnabled, demoUsers }) {
    return (
        <div className="min-h-screen bg-[#E4EBE1]">
            <Head title="Masuk" />
            <Flash />
            <main className="mx-auto flex min-h-screen max-w-[480px] flex-col justify-center gap-6 bg-mint px-6 py-10">
                <div className="flex flex-col gap-2">
                    <span className="text-[28px] font-extrabold tracking-[-0.03em] text-forest">jajapo</span>
                    <h1 className="m-0 text-2xl font-bold leading-tight">Masuk untuk pesan dan bayar</h1>
                    <p className="m-0 text-sm text-muted">Melihat PO tidak perlu login. Penjual memakai email @mail.ugm.ac.id.</p>
                </div>

                {googleEnabled ? (
                    <a
                        href="/auth/google/redirect"
                        className="flex h-[52px] items-center justify-center gap-2.5 rounded-2xl border border-line bg-white text-[15px] font-bold text-ink no-underline hover:text-ink"
                    >
                        <Icon name="google" size={20} strokeWidth={0} fill="#4285F4" />
                        Masuk dengan Google
                    </a>
                ) : (
                    <p className="m-0 rounded-2xl border border-dashed border-[#B9C9B5] bg-white p-4 text-sm text-muted">
                        Login Google belum diatur. Isi GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET di file .env.
                    </p>
                )}

                {demoUsers.length > 0 && (
                    <section className="flex flex-col gap-2.5">
                        <h2 className="m-0 text-sm font-bold text-body">Masuk cepat (hanya mode lokal)</h2>
                        <div className="overflow-hidden rounded-[18px] border border-line bg-white">
                            {demoUsers.map((u, i) => (
                                <button
                                    key={u.id}
                                    type="button"
                                    onClick={() => router.post(`/dev/login/${u.id}`)}
                                    className={`flex min-h-12 w-full items-center justify-between gap-3 px-4 text-left ${i ? 'border-t border-[#E6EDE3]' : ''}`}
                                >
                                    <span className="text-sm font-semibold">{u.name}</span>
                                    <span className="text-xs text-muted">{u.role}</span>
                                </button>
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </div>
    );
}
