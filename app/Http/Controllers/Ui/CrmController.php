<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Records\RecordsController;
use App\Models\Bpa\Application;
use App\Models\Tracking\Unit;
use App\Services\Records\KlaesRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Customer Relationship Management: dashboard, appointments and the appointment calendar (no store yet: preview saves). */
class CrmController extends Controller
{
    public const PURPOSES = ['Building plan submission', 'Site inspection', 'Collect approval / permit', 'Payment enquiry', 'Complaint', 'Progress enquiry'];

    public function dashboard(KlaesRecords $records): View
    {
        [$stats, $error] = $records->attempt(fn () => $records->stats($records->definition('crm-entities')), null);
        $types = collect($stats['breakdowns'][0]['rows'] ?? [])->pluck('n', 'label')->all();

        return view('ui.crm.dashboard', [
            'stats' => $stats,
            'error' => $error,
            'types' => $types,
            'slugs' => array_flip(RecordsController::CRM_TYPES),
            'applicants' => Application::query()->distinct()->count('developer_name'),
            'withPhone' => Application::query()->whereNotNull('developer_phone')->distinct()->count('developer_phone'),
            'recentApplicants' => Application::query()->latest('id')->limit(6)->get(['developer_name', 'developer_phone', 'developer_email', 'application_no', 'created_at']),
        ]);
    }

    public function appointments(): View
    {
        return view('ui.crm.appointments', ['purposes' => self::PURPOSES, 'units' => $this->units()]);
    }

    public function calendar(Request $request): View
    {
        try {
            $month = Carbon::createFromFormat('Y-m', (string) $request->query('month', now()->format('Y-m')))->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        return view('ui.crm.calendar', ['month' => $month, 'purposes' => self::PURPOSES, 'units' => $this->units()]);
    }

    private function units(): array
    {
        try {
            return Unit::query()->where('is_active', true)->orderBy('sort_order')->pluck('name')->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
