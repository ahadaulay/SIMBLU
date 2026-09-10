<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('Admin.Category.index');
    }
}
