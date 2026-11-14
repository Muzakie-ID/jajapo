import { useForm } from '@inertiajs/react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import { rupiah } from '@/lib/format';

export default function Settings({ values, defaults }) {
    const form = useForm({ ...values });

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/pengaturan', { preserveScroll: true });
    };

    return (
        <PanelLayout variant="admin" active="settings" title="Pengaturan">
            <div className="flex flex-col gap-4 px-4 pt-4 lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Pengaturan platform</h1>
                    <p className="m-0 text-sm text-muted">Berlaku untuk semua toko. Perubahan dipakai pesanan dan pencairan berikutnya, yang berjalan tidak ikut berubah.</p>
                </div>

                <form onSubmit={submit} className="flex max-w-[560px] flex-col gap-4">
                    <PanelCard className="flex flex-col gap-4 p-4 lg:p-5">
                        <Field
                            label="Batas waktu bayar"
                            unit="menit"
                            hint={`Tagihan QRIS kedaluwarsa setelah ini. Bawaan ${defaults.payment_ttl_minutes} menit, samakan dengan pengaturan di dashboard gateway.`}
                            error={form.errors.payment_ttl_minutes}
                            value={form.data.payment_ttl_minutes}
                            onChange={(v) => form.setData('payment_ttl_minutes', v)}
                        />
                        <Field
                            label="Minimal pencairan"
                            unit="rupiah"
                            hint={`Penjual baru bisa mengajukan pencairan dari nominal ini. Bawaan ${rupiah(defaults.min_payout)}.`}
                            error={form.errors.min_payout}
                            value={form.data.min_payout}
                            onChange={(v) => form.setData('min_payout', v)}
                            preview={rupiah(Number(form.data.min_payout) || 0)}
                        />
                        <Field
                            label="Maksimal porsi per pesanan"
                            unit="porsi"
                            hint={`Batas jumlah porsi dalam satu pesanan pembeli. Bawaan ${defaults.max_qty_per_order} porsi.`}
                            error={form.errors.max_qty_per_order}
                            value={form.data.max_qty_per_order}
                            onChange={(v) => form.setData('max_qty_per_order', v)}
                        />
                        <Field
                            label="Maksimal toko per akun"
                            unit="toko"
                            hint={`Jumlah toko yang boleh dibuat satu akun, misalnya toko organisasi dan toko pribadi. Toko yang ditolak tidak dihitung. Bawaan ${defaults.max_shops_per_user} toko.`}
                            error={form.errors.max_shops_per_user}
                            value={form.data.max_shops_per_user}
                            onChange={(v) => form.setData('max_shops_per_user', v)}
                        />
                    </PanelCard>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="h-12 self-start rounded-xl bg-forest px-6 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {form.processing ? 'Menyimpan...' : 'Simpan pengaturan'}
                    </button>
                </form>
            </div>
        </PanelLayout>
    );
}

function Field({ label, unit, hint, error, value, onChange, preview }) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-body">{label}</span>
            <span className="flex items-center gap-2.5">
                <input
                    inputMode="numeric"
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value.replace(/\D/g, ''))}
                    className={`tabular h-12 w-40 rounded-xl border bg-white px-3.5 text-sm text-ink outline-none ${error ? 'border-danger' : 'border-panel-line'}`}
                />
                <span className="text-[13px] text-muted">{unit}</span>
                {preview && <span className="tabular text-[13px] font-semibold">{preview}</span>}
            </span>
            {error ? <span className="text-xs font-semibold text-danger">{error}</span> : <span className="text-xs text-muted">{hint}</span>}
        </label>
    );
}
