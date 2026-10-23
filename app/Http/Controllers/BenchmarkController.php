<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class BenchmarkController extends Controller
{
    /**
     * Internal recogniser benchmark. Gated to debug builds because it loads
     * all three models (CLIP, MobileNet, COCO-SSD) in one page and is meant
     * for evaluation runs on a labelled photo set, not for end users.
     */
    public function index(): View
    {
        abort_unless(config('app.debug'), 404);

        return view('scanner.benchmark');
    }
}
