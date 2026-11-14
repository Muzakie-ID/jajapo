import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout, { PanelCard } from '@/Layouts/PanelLayout';
import Icon from '@/Components/Icon';

export default function Banks({ banks }) {
    const add = useForm({ name: '' });
    const shown = banks.filter((b) => b.active).length;

    return (
        <PanelLayout variant="admin" active="banks" title="Bank dan e-wallet">
            <div className="flex flex-col gap-4 px-4 pt-4 lg:max-w-[760px] lg:px-8 lg:pt-7">
                <div className="flex flex-col gap-1">
                    <h1 className="m-0 text-2xl font-bold tracking-[-0.02em] lg:text-[28px]">Bank dan e-wallet</h1>
                    <p className="m-0 text-sm text-muted">
                        Pilihan yang muncul saat penjual mendaftar toko atau mengganti rekening. {shown} dari {banks.length} pilihan sedang tampil.
                    </p>
                </div>

                <PanelCard className="p-4">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            add.post('/admin/bank', { preserveScroll: true, onSuccess: () => add.reset() });
                        }}
                        className="flex flex-col gap-2"
                    >
                        <label htmlFor="bank-baru" className="text-[13px] font-semibold text-body">
                            Tambah pilihan
                        </label>
                        <div className="flex gap-2">
                            <input
                                id="bank-baru"
                                value={add.data.name}
                                onChange={(e) => add.setData('name', e.target.value)}
                                placeholder="Contoh: Bank Mega"
                                maxLength={60}
                                className="h-11 min-w-0 flex-1 rounded-xl border border-panel-line bg-white px-3.5 text-sm outline-none"
                            />
                            <button type="submit" disabled={add.processing || !add.data.name.trim()} className="h-11 rounded-xl bg-forest px-4 text-sm font-semibold text-white disabled:opacity-60">
                                Tambah
                            </button>
                        </div>
                        {add.errors.name && <span className="text-xs font-semibold text-danger">{add.errors.name}</span>}
                    </form>
                </PanelCard>

                <PanelCard className="overflow-hidden">
                    {banks.length === 0 && <p className="m-0 p-5 text-center text-sm text-muted">Belum ada pilihan. Penjual tidak bisa mendaftar sampai ada minimal satu.</p>}
                    {banks.map((b) => (
                        <Row key={b.id} bank={b} />
                    ))}
                </PanelCard>

                <p className="m-0 text-xs leading-[1.5] text-muted">
                    Pilihan yang sudah dipakai rekening tidak bisa dihapus, hanya disembunyikan. Mengganti nama ikut memperbarui rekening yang memakai nama lama.
                </p>
            </div>
        </PanelLayout>
    );
}

function Row({ bank }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ name: bank.name, active: bank.active });

    const save = (data) => {
        form.transform(() => data);
        form.put(`/admin/bank/${bank.id}`, { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    return (
        <div className="flex flex-wrap items-center gap-3 border-t border-[#EDF1EB] px-4 py-3 first:border-t-0">
            {editing ? (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        save({ name: form.data.name, active: bank.active });
                    }}
                    className="flex min-w-0 flex-1 flex-col gap-1"
                >
                    <div className="flex gap-2">
                        <input
                            autoFocus
                            aria-label={`Nama baru untuk ${bank.name}`}
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            maxLength={60}
                            className="h-10 min-w-0 flex-1 rounded-xl border border-panel-line bg-white px-3 text-sm outline-none"
                        />
                        <button type="submit" disabled={form.processing} className="h-10 rounded-xl bg-forest px-3.5 text-[13px] font-semibold text-white">
                            Simpan
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                form.reset();
                                form.clearErrors();
                                setEditing(false);
                            }}
                            className="h-10 px-2 text-[13px] font-semibold text-muted"
                        >
                            Batal
                        </button>
                    </div>
                    {form.errors.name && <span className="text-xs font-semibold text-danger">{form.errors.name}</span>}
                    {bank.used > 0 && <span className="text-xs text-muted">{bank.used} rekening ikut memakai nama baru.</span>}
                </form>
            ) : (
                <>
                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span className={`text-[15px] font-semibold ${bank.active ? 'text-ink' : 'text-muted line-through'}`}>{bank.name}</span>
                        <span className="text-xs text-muted">
                            {bank.active ? 'Tampil' : 'Disembunyikan'} · {bank.used > 0 ? `dipakai ${bank.used} rekening` : 'belum dipakai'}
                        </span>
                    </span>
                    <span className="flex items-center gap-1">
                        <button type="button" onClick={() => setEditing(true)} aria-label={`Ganti nama ${bank.name}`} className="flex h-10 w-10 items-center justify-center rounded-xl text-muted hover:bg-[#F0F3EE] hover:text-ink">
                            <Icon name="pencil" size={18} />
                        </button>
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={() => save({ name: bank.name, active: !bank.active })}
                            className="h-10 rounded-xl border border-panel-line bg-white px-3 text-[13px] font-semibold text-ink"
                        >
                            {bank.active ? 'Sembunyikan' : 'Tampilkan'}
                        </button>
                        {bank.used > 0 ? (
                            // Ruang kosong selebar tombol hapus supaya kolom tombol tetap sejajar.
                            <span className="w-10" aria-hidden="true" />
                        ) : (
                            <button
                                type="button"
                                aria-label={`Hapus ${bank.name}`}
                                onClick={() => {
                                    if (confirm(`Hapus ${bank.name} dari pilihan?`)) router.delete(`/admin/bank/${bank.id}`, { preserveScroll: true });
                                }}
                                className="flex h-10 w-10 items-center justify-center rounded-xl text-danger hover:bg-[#FBE4E1]"
                            >
                                <Icon name="trash" size={18} />
                            </button>
                        )}
                    </span>
                </>
            )}
        </div>
    );
}
