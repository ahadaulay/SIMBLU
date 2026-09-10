<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class IndicatorController extends Controller
{
    public function index(): View
    {
        return view('Admin.Indicator.index');
    }
}
