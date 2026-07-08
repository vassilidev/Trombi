<?php

namespace App\Http\Controllers;

use App\Models\BenchmarkRun;
use App\Models\Talent;
use App\Services\Benchmark\BenchmarkService;
use App\Services\Benchmark\BenchmarkSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BenchmarkController extends Controller
{
    public function index(): Response
    {
        $goldReady = Talent::where('is_gold', true)
            ->whereHas('annotations', fn ($q) => $q->where('source', 'human'))
            ->count();

        return Inertia::render('Benchmark', [
            'runs' => BenchmarkRun::withCount('results')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (BenchmarkRun $run) => [
                    'id' => $run->id,
                    'label' => $run->label,
                    'models' => $run->models,
                    'gold_count' => $run->gold_count,
                    'results_count' => $run->results_count,
                    'created_at' => $run->created_at?->toDateTimeString(),
                ]),
            'availableModels' => config('services.openrouter.benchmark_models'),
            'goldReady' => $goldReady,
        ]);
    }

    public function run(Request $request, BenchmarkService $benchmark): RedirectResponse
    {
        $validated = $request->validate([
            'models' => ['required', 'array', 'min:1'],
            'models.*' => ['string'],
            'label' => ['nullable', 'string', 'max:128'],
            'gold_limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $run = $benchmark->run(
            models: $validated['models'],
            goldLimit: $validated['gold_limit'] ?? null,
            label: $validated['label'] ?? null,
        );

        return redirect()->route('benchmark.show', $run);
    }

    public function show(BenchmarkRun $benchmark): Response
    {
        return Inertia::render('BenchmarkRun', [
            'summary' => BenchmarkSummary::for($benchmark),
        ]);
    }
}
