<?php

namespace App\Http\Controllers;

use App\Models\Talent;
use App\Services\OpenRouter\OpenRouterClient;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consommation OpenRouter : crédits restants (source de vérité = l'API OpenRouter)
 * + activité locale (appels, dépense mesurée sur les benchmarks) et modèles en jeu.
 */
class UsageController extends Controller
{
    public function __construct(private readonly OpenRouterClient $client) {}

    public function index(): Response
    {
        $benchmark = DB::table('benchmark_results')->selectRaw(
            'count(*) as runs, coalesce(sum(cost_usd),0) as cost, '.
            'coalesce(sum(prompt_tokens),0) as prompt_tokens, coalesce(sum(completion_tokens),0) as completion_tokens'
        )->first();

        return Inertia::render('Usage', [
            'credits' => $this->client->credits(),
            'models' => [
                'vision' => config('services.openrouter.vision_model'),
                'embedding' => config('services.openrouter.embedding_model'),
                'parsing' => config('services.openrouter.parsing_model'),
            ],
            'activity' => [
                'analyses' => Talent::whereHas('appearance')->count(),
                'embeddings' => Talent::whereHas('profile')->count(),
                'searches' => DB::table('briefs')->count(),
                'benchmark_runs' => (int) ($benchmark->runs ?? 0),
            ],
            'benchmark' => [
                'cost' => round((float) ($benchmark->cost ?? 0), 4),
                'prompt_tokens' => (int) ($benchmark->prompt_tokens ?? 0),
                'completion_tokens' => (int) ($benchmark->completion_tokens ?? 0),
            ],
        ]);
    }
}
