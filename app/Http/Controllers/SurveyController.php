<?php

namespace App\Http\Controllers;

use App\Models\SurveyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SurveyController extends Controller
{
    public function create(): View
    {
        return view('survey.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ease_rating' => ['required', 'integer', 'between:1,5'],
            'speed_rating' => ['required', 'integer', 'between:1,5'],
            'clarity_rating' => ['required', 'integer', 'between:1,5'],
            'usefulness_rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        SurveyResponse::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        return redirect()->route('dashboard')->with('success', 'Thanks for your feedback — it helps improve EcoSight.');
    }
}
