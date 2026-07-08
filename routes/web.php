<?php

use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\CalibrationController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TalentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SearchController::class, 'index'])->name('search');

Route::get('/import', [ImportController::class, 'index'])->name('import.index');
Route::post('/import/upload', [ImportController::class, 'upload'])->name('import.upload');
Route::post('/import/pull', [ImportController::class, 'pull'])->name('import.pull');

Route::get('/talents', [TalentController::class, 'index'])->name('talents.index');
Route::post('/talents/analyze-pending', [TalentController::class, 'analyzePending'])->name('talents.analyze-pending');
Route::get('/talents/{talent}/qualify', [TalentController::class, 'qualify'])->name('talents.qualify');
Route::post('/talents/{talent}/qualify', [TalentController::class, 'storeQualification'])->name('talents.qualify.store');
Route::post('/talents/{talent}/analyze', [TalentController::class, 'analyze'])->name('talents.analyze');
Route::post('/talents/{talent}/photos', [TalentController::class, 'addPhotos'])->name('talents.photos.add');
Route::delete('/talents/{talent}/photos/{photo}', [TalentController::class, 'destroyPhoto'])->name('talents.photos.destroy');
Route::delete('/talents/{talent}', [TalentController::class, 'destroy'])->name('talents.destroy');

Route::get('/prompts', [PromptController::class, 'index'])->name('prompts.index');
Route::put('/prompts/{prompt}', [PromptController::class, 'update'])->name('prompts.update');

Route::get('/calibration', [CalibrationController::class, 'index'])->name('calibration.index');
Route::post('/calibration/analyze-gold', [CalibrationController::class, 'analyzeGold'])->name('calibration.analyze-gold');

Route::get('/benchmark', [BenchmarkController::class, 'index'])->name('benchmark.index');
Route::post('/benchmark/run', [BenchmarkController::class, 'run'])->name('benchmark.run');
Route::get('/benchmark/{benchmark}', [BenchmarkController::class, 'show'])->name('benchmark.show');
