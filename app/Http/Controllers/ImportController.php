<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ImportController extends Controller
{
    public function index(): View
    {
        return view('Admin.Import.index');
    }
}
