<?php

use App\Http\Controllers\ActionCompletionController;
use App\Http\Controllers\AnimalController;
use App\Http\Controllers\AnimalImageController;
use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageCreditController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScanFeedbackController;
use App\Http\Controllers\ScanHistoryController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScannerEventController;
use App\Http\Controllers\SpeciesLookupController;
use App\Http\Controllers\SurveyController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'landing'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');

Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store')->middleware('throttle:10,1');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/scanner', [ScannerController::class, 'show'])->name('scanner');
    Route::post('/scanner/scan', [ScannerController::class, 'store'])->name('scanner.store')->middleware('throttle:10,1');
    Route::get('/scanner/labels', [ScannerController::class, 'labels'])->name('scanner.labels');
    Route::get('/scanner/animal/{label}', [ScannerController::class, 'lookup'])->name('scanner.lookup');
    Route::get('/scanner/lookup/{qid}', [SpeciesLookupController::class, 'show'])->name('scanner.species.lookup');
    Route::get('/scanner/benchmark', [BenchmarkController::class, 'index'])->name('scanner.benchmark');

    Route::get('/scans', [ScanHistoryController::class, 'index'])->name('scans.index');
    Route::delete('/scans/{scan}', [ScanHistoryController::class, 'destroy'])->name('scans.destroy');
    Route::get('/scans/export', [ScanHistoryController::class, 'export'])->name('scans.export');
    Route::post('/scans/feedback', [ScanFeedbackController::class, 'store'])->name('scans.feedback')->middleware('throttle:10,1');

    Route::post('/scanner/event', [ScannerEventController::class, 'store'])->name('scanner.event')->middleware('throttle:60,1');

    Route::get('/survey', [SurveyController::class, 'create'])->name('survey.create');
    Route::post('/survey', [SurveyController::class, 'store'])->name('survey.store')->middleware('throttle:10,1');

    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance');
    Route::get('/performance/csv', [PerformanceController::class, 'csv'])->name('performance.csv');

    Route::post('/actions/{action}/complete', [ActionCompletionController::class, 'store'])->name('actions.complete')->middleware('throttle:30,1');
    Route::delete('/actions/{action}/complete', [ActionCompletionController::class, 'destroy'])->name('actions.complete.destroy');

    Route::post('/animals/from-scan', [AnimalController::class, 'storeFromScan'])->name('animals.from-scan')->middleware('throttle:10,1');
    Route::get('/animals/images/review', [AnimalImageController::class, 'reviewIndex'])->name('animals.images.review');
    Route::get('/animals/{animal}/image/candidate', [AnimalImageController::class, 'candidate'])->name('animals.image.candidate')->middleware('throttle:10,1');
    Route::post('/animals/{animal}/image', [AnimalImageController::class, 'store'])->name('animals.image.store')->middleware('throttle:10,1');
    Route::post('/animals/{animal}/image/from-wikipedia', [AnimalImageController::class, 'fromWikipedia'])->name('animals.image.from-wikipedia')->middleware('throttle:5,1');
    Route::patch('/animals/{animal}/image/review', [AnimalImageController::class, 'review'])->name('animals.image.review')->middleware('throttle:30,1');
    Route::delete('/animals/{animal}/image', [AnimalImageController::class, 'destroy'])->name('animals.image.destroy')->middleware('throttle:10,1');
    Route::post('/animals/{animal}/varieties', [AnimalController::class, 'storeVariety'])->name('animals.varieties.store')->middleware('throttle:10,1');
    Route::patch('/animals/{animal}/review', [AnimalController::class, 'review'])->name('animals.review')->middleware('throttle:30,1');

    Route::resource('animals', AnimalController::class)->except(['index', 'show']);
    Route::delete('/animals/{animal}/actions/{action}', [AnimalController::class, 'destroyAction'])->name('animals.actions.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::resource('animals', AnimalController::class)->only(['index', 'show']);
Route::get('/credits', [ImageCreditController::class, 'index'])->name('credits');

Route::view('/reflection', 'reflection')->name('reflection');

require __DIR__.'/auth.php';
