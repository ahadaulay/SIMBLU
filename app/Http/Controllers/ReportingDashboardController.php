<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ReportingDashboardController extends Controller
{
    public function index(): View
    {
        return view('Admin.ReportingDashboard.index');
    }
}
