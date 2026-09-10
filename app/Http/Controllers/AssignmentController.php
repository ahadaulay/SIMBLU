<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(): View
    {
        return view('Admin.Assignment.index');
    }
}
