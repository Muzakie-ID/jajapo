<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Shop;
use App\Services\Ledger;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BalanceController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $account = $shop->bankAccount;
        $balances = $ledger->balances($shop);

        $heldOrders = $shop->orders()->whereNotNull('paid_at')->whereNull('released_at')->count();

        return Inertia::render('Seller/Balance', [
            'shop' => DashboardController::shopPropsFor($shop),
            'available' => $balances['available'],
            'held' => $balances['held'],
            'heldOrders' => $heldOrders,
            'minPayout' => Setting::int('min_payout'),
            'account' => $account ? [
                'label' => $account->label(),
                'name' => $account->account_name,
                'verified' => (bool) $account->verified_at,
            ] : null,
            'payouts' => $shop->payouts()->with('bankAccount')->latest()->limit(20)->get()->map(fn (Payout $p) => [
                'id' => $p->id,
                'date' => tanggal($p->created_at, withTime: true, withYear: true),
                'amount' => $p->amount,
                'account' => $p->bankAccount->label(),
                'status' => $p->status,
                'proof_url' => $p->proofUrl(),
                'note' => $p->note,
            ]),
            'ledger' => $shop->ledgerEntries()
                ->whereIn('bucket', [LedgerEntry::HELD, LedgerEntry::AVAILABLE])
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (LedgerEntry $e) => [
                    'time' => tanggal($e->created_at, withTime: true),
                    'desc' => $e->description,
                    'bucket' => $e->bucket === LedgerEntry::HELD ? 'Tertahan' : 'Tersedia',
                    'amount' => (int) $e->amount,
                ]),
        ]);
    }

    public function store(Request $request, PayoutService $payouts): RedirectResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1']], ['amount.required' => 'Isi nominal pencairan.']);

        $payout = $payouts->request($shop, (int) $data['amount'], $request->user());

        return back()->with('success', 'Pencairan '.rupiah($payout->amount).' diajukan. Diproses paling lambat 1x24 jam hari kerja.');
    }
}
