<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class MetricController extends Controller
{
    public function index(): View
    {
        return view('Admin.Metric.index');
    }
}
