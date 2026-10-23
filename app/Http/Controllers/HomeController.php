<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HomeController extends Controller
{
    public function landing(): View
    {
        return view('home');
    }

    public function about(): View
    {
        return view('about');
    }
}
