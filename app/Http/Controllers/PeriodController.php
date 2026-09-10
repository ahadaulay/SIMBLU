<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PeriodController extends Controller
{
    public function index(): View
    {
        return view('Admin.Period.index');
    }
}
