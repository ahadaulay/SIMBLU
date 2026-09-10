<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->user()->hasReportingRole(['ADMIN', 'UNIT', 'PIMPINAN'])) {
            return view('Admin.ReportingDashboard.index');
        }

        return redirect()
            ->route('profile.show')
            ->with('warning', 'Akun Anda belum memiliki role SIBLU. Hubungi administrator.');
    }
}
