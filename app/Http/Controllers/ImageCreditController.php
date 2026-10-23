<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use Illuminate\View\View;

class ImageCreditController extends Controller
{
    public function index(): View
    {
        $animals = Animal::query()
            ->withImage()
            ->orderBy('common_name')
            ->paginate(36);

        return view('animals.credits', compact('animals'));
    }
}
