<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Legacy Systems. KLAES's menu item points at /legacy-systems, which has no route in KLAES, so this is a fresh design:
 * a launcher for the older records KADCA staff still need (legacy file numbers, legacy bills, scanned archives),
 * each opening the KADCA screen that reads them.
 */
class LegacyController extends Controller
{
    public function index(): View
    {
        return view('ui.legacy.index');
    }
}
