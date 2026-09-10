<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        return view('Admin.Unit.index');
    }
}
