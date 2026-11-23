<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Bpa\Application;
use App\Models\Bpa\Bill;
use App\Services\Dashboard\KadcaDashboard;
use App\Support\BpaStage;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** MD's View > Managing Director's Dashboard: the whole Building Plan Approval pipeline and revenue at a glance. */
class MdController extends Controller
{
    public function dashboard(KadcaDashboard $dashboard): View
    {
        $from = now()->startOfMonth()->subMonths(11);
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $months[$from->copy()->addMonths($i)->format('Y-m')] = ['apps' => 0, 'paid' => 0.0];
        }
        foreach (Application::query()->where('created_at', '>=', $from)->get(['created_at']) as $a) {
            $months[$a->created_at->format('Y-m')]['apps']++;
        }
        foreach (Bill::query()->where('status', 'Paid')->whereNotNull('paid_on')->where('paid_on', '>=', $from->toDateString())->get(['paid_on', 'amount_paid']) as $b) {
            $k = Carbon::parse($b->paid_on)->format('Y-m');
            if (isset($months[$k])) {
                $months[$k]['paid'] += (float) $b->amount_paid;
            }
        }

        $stages = [];
        foreach (BpaStage::LABELS as $stage => $label) {
            $stages[$label] = Application::query()->where('stage', $stage)->count();
        }

        return view('ui.md.dashboard', [
            'd' => $dashboard->build(),
            'months' => $months,
            'stages' => $stages,
            'landUse' => Application::query()->selectRaw('land_use_check as k, COUNT(*) as n')->groupBy('land_use_check')->pluck('n', 'k')->all(),
            'byLga' => Application::query()->selectRaw("COALESCE(lga, 'Not recorded') as k, COUNT(*) as n")->groupBy('lga')->orderByDesc('n')->limit(8)->pluck('n', 'k')->all(),
            'recent' => Application::query()->latest('id')->limit(8)->get(),
        ]);
    }
}
