<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pilihan bank dan e-wallet yang muncul di form rekening penjual. */
class BankController extends Controller
{
    public function index(): Response
    {
        $usage = BankAccount::query()->selectRaw('bank, count(*) as n')->groupBy('bank')->pluck('n', 'bank');

        return Inertia::render('Admin/Banks', [
            'banks' => Bank::query()->orderByRaw('lower(name)')->get()->map(fn (Bank $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'active' => $b->is_active,
                'used' => (int) ($usage[$b->name] ?? 0),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(), $this->messages());
        $bank = Bank::create(['name' => $data['name']]);
        AuditLog::record($request->user(), 'bank.created', $bank, ['name' => $bank->name]);

        return back()->with('success', "{$bank->name} ditambahkan ke pilihan.");
    }

    public function update(Request $request, Bank $bank): RedirectResponse
    {
        $data = $request->validate([
            ...$this->rules($bank),
            'active' => ['required', 'boolean'],
        ], $this->messages());

        $old = $bank->name;
        DB::transaction(function () use ($bank, $data, $old) {
            $bank->update(['name' => $data['name'], 'is_active' => $data['active']]);
            // Membetulkan nama ikut membetulkan rekening yang sudah memakainya.
            if ($old !== $data['name']) {
                BankAccount::query()->where('bank', $old)->update(['bank' => $data['name']]);
            }
        });
        AuditLog::record($request->user(), 'bank.updated', $bank, array_filter([
            'name' => $old !== $bank->name ? [$old, $bank->name] : null,
            'active' => $bank->wasChanged('is_active') ? $bank->is_active : null,
        ], fn ($v) => $v !== null));

        return back()->with('success', "{$bank->name} disimpan.");
    }

    public function destroy(Request $request, Bank $bank): RedirectResponse
    {
        if ($bank->usageCount() > 0) {
            return back()->with('error', "{$bank->name} sudah dipakai rekening, jadi hanya bisa disembunyikan.");
        }
        AuditLog::record($request->user(), 'bank.deleted', $bank, ['name' => $bank->name]);
        $bank->delete();

        return back()->with('success', "{$bank->name} dihapus dari pilihan.");
    }

    private function rules(?Bank $bank = null): array
    {
        return ['name' => ['required', 'string', 'max:60', Rule::unique('banks', 'name')->ignore($bank)]];
    }

    private function messages(): array
    {
        return ['name.required' => 'Isi nama bank atau e-wallet.', 'name.unique' => 'Nama ini sudah ada di pilihan.'];
    }
}
