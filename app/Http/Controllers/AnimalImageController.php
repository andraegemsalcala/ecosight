<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnimalImageRequest;
use App\Http\Requests\WikipediaImageRequest;
use App\Models\Animal;
use App\Services\AnimalImageService;
use App\Services\WikipediaImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AnimalImageController extends Controller
{
    public function store(AnimalImageRequest $request, Animal $animal, AnimalImageService $images): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        try {
            $images->storeUploadedFile($request->file('image'), $animal, [
                'alt' => $request->validated('image_alt'),
                'credit' => $request->validated('image_credit') ?: 'Uploaded by '.$user->name,
                'license' => $request->validated('image_license') ?: 'User upload',
                'reviewed' => true,
            ]);
        } catch (RuntimeException $exception) {
            return $this->imageError($request, $exception->getMessage());
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Image updated.', 'image_url' => $animal->fresh()->image_src])
            : back()->with('status', 'Image updated.');
    }

    public function destroy(Request $request, Animal $animal, AnimalImageService $images): RedirectResponse|JsonResponse
    {
        $images->remove($animal);

        return $request->expectsJson()
            ? response()->json(['message' => 'Image removed.'])
            : back()->with('status', 'Image removed.');
    }

    public function candidate(Animal $animal, WikipediaImageService $wikipedia): View
    {
        $candidate = null;
        $lookupError = null;

        try {
            $candidate = $wikipedia->candidateFor($animal);
        } catch (RuntimeException $exception) {
            $lookupError = $exception->getMessage();
        }

        return view('animals.image-candidate', compact('animal', 'candidate', 'lookupError'));
    }

    public function fromWikipedia(WikipediaImageRequest $request, Animal $animal, WikipediaImageService $wikipedia, AnimalImageService $images): RedirectResponse|JsonResponse
    {
        try {
            $candidate = $wikipedia->decodeCandidate($request->validated('candidate_token'), $animal);
            $contents = $wikipedia->download($candidate);

            $images->storeBinary($contents, $animal, [
                'alt' => $request->validated('image_alt') ?: ($candidate['alt'] ?? null),
                'credit' => $candidate['credit'] ?? null,
                'license' => $candidate['license'] ?? null,
                'source_url' => $candidate['source_url'] ?? null,
                'reviewed' => true,
            ]);
        } catch (RuntimeException $exception) {
            return $this->imageError($request, $exception->getMessage());
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Image updated from Wikimedia Commons.', 'image_url' => $animal->fresh()->image_src])
            : redirect()->route('animals.show', $animal)->with('status', 'Image updated from Wikimedia Commons.');
    }

    public function review(Request $request, Animal $animal): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'reviewed' => ['required', 'boolean'],
        ]);

        if (! $animal->hasImage()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Add an image before reviewing it.'], 422)
                : back()->with('error', 'Add an image before reviewing it.');
        }

        $animal->forceFill([
            'image_reviewed_at' => (bool) $data['reviewed'] ? now() : null,
        ])->save();

        $message = (bool) $data['reviewed'] ? 'Image marked as reviewed.' : 'Image sent back for review.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }

    public function reviewIndex(): View
    {
        $animals = Animal::query()
            ->withImage()
            ->whereNull('image_reviewed_at')
            ->orderByDesc('updated_at')
            ->paginate(24);

        return view('animals.image-review', compact('animals'));
    }

    private function imageError(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['image' => $message])->withInput();
    }
}
