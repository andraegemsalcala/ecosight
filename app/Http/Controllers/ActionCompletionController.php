<?php

namespace App\Http\Controllers;

use App\Models\ConservationAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActionCompletionController extends Controller
{
    public function store(Request $request, ConservationAction $action): RedirectResponse
    {
        $request->user()->actionCompletions()->firstOrCreate(
            ['conservation_action_id' => $action->id],
            ['completed_at' => now()]
        );

        return back()->with('success', 'Conservation action marked as done.');
    }

    public function destroy(Request $request, ConservationAction $action): RedirectResponse
    {
        $request->user()->actionCompletions()
            ->where('conservation_action_id', $action->id)
            ->delete();

        return back()->with('success', 'Conservation action marked as not done.');
    }
}
