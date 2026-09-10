<?php

namespace App\Http\Controllers;

use App\Models\Gedung;
use Illuminate\View\View;

class GedungController extends Controller
{
    public function index(): View
    {
        return view('Admin.Gedung.index');
    }

    public function show(Gedung $gedung): View
    {
        return view('Admin.Gedung.show', ['building' => $gedung]);
    }
}
