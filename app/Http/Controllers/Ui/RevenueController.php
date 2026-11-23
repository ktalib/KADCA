<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Bpa\Bill;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * KLAES REV-M for KADCA, copied in layout from KLAES (Revenue Dashboard, Bill Master, bill types, Bill Legacy, Awaiting Receipt,
 * e-Receipt / Print Receipt, Transaction Token Control). KADCA's own bills live in kadca_bills (user 2026-11-22: bills stay in
 * kadca_bills for now), so the lists read those. Betterment, Contravention, Legacy and TTC have no store yet: preview saves.
 */
class RevenueController extends Controller
{
    /** Bill types on the KADCA REV-M menu (KLAES bill_master_types shape: code => name, group, live?). */
    public const TYPES = [
        'initial' => ['BPA Initial Bill (Application Fee)', 'Automated', true, 'route' => 'bpa.bills.index'],
        'betterment' => ['Betterment Bill', 'Automated', false, 'route' => 'revenue.betterment'],
        'contravention' => ['Contravention Bill', 'Automated', false, 'route' => 'revenue.contravention'],
        'legacy' => ['Bill Legacy', 'Legacy', false, 'route' => 'revenue.legacy'],
    ];

    public const CHANNELS = ['Bank', 'POS', 'Interswitch', 'USSD'];

    public function dashboard(): View
    {
        $from = now()->startOfMonth()->subMonths(11);
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $months[$from->copy()->addMonths($i)->format('Y-m')] = ['billed' => 0.0, 'paid' => 0.0];
        }
        foreach (Bill::query()->where('created_at', '>=', $from)->get(['created_at', 'amount']) as $b) {
            $months[$b->created_at->format('Y-m')]['billed'] += (float) $b->amount;
        }
        foreach (Bill::query()->where('status', 'Paid')->where('paid_on', '>=', $from->toDateString())->get(['paid_on', 'amount_paid']) as $b) {
            $k = Carbon::parse($b->paid_on)->format('Y-m');
            if (isset($months[$k])) {
                $months[$k]['paid'] += (float) $b->amount_paid;
            }
        }

        return view('ui.revenue.dashboard', [
            'billed' => (float) Bill::query()->sum('amount'),
            'paid' => (float) Bill::query()->where('status', 'Paid')->sum('amount_paid'),
            'outstanding' => (float) Bill::query()->where('status', 'Unpaid')->sum('amount'),
            'counts' => Bill::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            'channels' => Bill::query()->where('status', 'Paid')->selectRaw("COALESCE(payment_channel, 'Not recorded') as k, SUM(amount_paid) as n")->groupBy('payment_channel')->pluck('n', 'k')->all(),
            'months' => $months,
            'latest' => Bill::query()->with('application')->where('status', 'Paid')->latest('payment_recorded_at')->limit(6)->get(),
        ]);
    }

    public function billMaster(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['Paid', 'Unpaid'], true) ? $request->query('status') : null;
        $bills = Bill::query()->with('application')
            ->when($status, fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('bill_no', 'like', "%{$q}%")->orWhere('receipt_no', 'like', "%{$q}%")
                ->orWhereHas('application', fn ($a) => $a->where('application_no', 'like', "%{$q}%")->orWhere('developer_name', 'like', "%{$q}%")->orWhere('file_number', 'like', "%{$q}%"))))
            ->latest('id')->paginate(20)->withQueryString();

        return view('ui.revenue.bill-master', [
            'bills' => $bills, 'q' => $q, 'status' => $status,
            'typeCounts' => ['initial' => Bill::query()->count()],
        ]);
    }

    public function betterment(): View
    {
        return view('ui.revenue.betterment');
    }

    public function contravention(): View
    {
        return view('ui.revenue.contravention');
    }

    public function legacy(): View
    {
        return view('ui.revenue.legacy');
    }

    public function awaiting(): View
    {
        return view('ui.revenue.awaiting', [
            'bills' => Bill::query()->with('application')->where('status', 'Paid')->latest('payment_recorded_at')->paginate(20),
        ]);
    }

    public function receipts(Request $request, string $mode): View
    {
        $q = trim((string) $request->query('q', ''));
        $bills = Bill::query()->with('application')->where('status', 'Paid')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('bill_no', 'like', "%{$q}%")->orWhere('receipt_no', 'like', "%{$q}%")
                ->orWhereHas('application', fn ($a) => $a->where('developer_name', 'like', "%{$q}%")->orWhere('application_no', 'like', "%{$q}%"))))
            ->latest('payment_recorded_at')->paginate(20)->withQueryString();

        return view('ui.revenue.receipts', ['bills' => $bills, 'mode' => $mode, 'q' => $q]);
    }

    public function receipt(Bill $bill): View
    {
        abort_unless($bill->status === 'Paid', 404);

        return view('ui.revenue.receipt', ['bill' => $bill->load('application'), 'copy' => request()->query('copy', 'Original')]);
    }

    public function tokens(): View
    {
        return view('ui.revenue.tokens');
    }
}
