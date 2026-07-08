<?php

namespace App\Services\Benchmark;

use App\Models\BenchmarkRun;
use App\Models\Talent;
use App\Services\Calibration\AgreementScorer;
use App\Services\Vision\VisionAnalysisService;
use Illuminate\Support\Collection;

/**
 * Compare N modèles vision sur le set gold, avec le MÊME prompt, et score chaque
 * sortie contre le label humain (PRD §6.4). On sépare propreté et justesse.
 */
class BenchmarkService
{
    public function __construct(
        private readonly VisionAnalysisService $vision,
        private readonly AgreementScorer $scorer,
    ) {}

    /**
     * Lance un run sur les talents gold disposant d'une annotation humaine.
     *
     * @param  list<string>  $models
     */
    public function run(array $models, ?int $goldLimit = null, ?string $label = null): BenchmarkRun
    {
        $gold = $this->goldTalents($goldLimit);

        $run = BenchmarkRun::create([
            'label' => $label,
            'prompt_version' => $this->vision->promptVersion(),
            'models' => array_values($models),
            'gold_count' => $gold->count(),
        ]);

        foreach ($gold as $talent) {
            $human = $talent->annotations->firstWhere('source', 'human');

            foreach ($models as $model) {
                $this->evaluate($run, $talent, $human->payload, $model);
            }
        }

        return $run;
    }

    /**
     * Un appel modèle sur une image + scoring vs gold, persisté.
     *
     * @param  array<string, mixed>  $goldPayload
     */
    private function evaluate(BenchmarkRun $run, Talent $talent, array $goldPayload, string $model): void
    {
        try {
            $result = $this->vision->analyze($talent, $model);
        } catch (\Throwable $e) {
            report($e);
            $run->results()->create([
                'talent_id' => $talent->id,
                'model' => $model,
                'payload' => null,
                'is_valid_json' => false,
                'agreement_score' => null,
                'per_field_result' => null,
            ]);

            return;
        }

        $comparison = $this->scorer->compare($goldPayload, $result->payload);

        $run->results()->create([
            'talent_id' => $talent->id,
            'model' => $model,
            'payload' => $result->payload,
            'is_valid_json' => $result->isValidJson,
            'agreement_score' => $comparison['overall'],
            'per_field_result' => $comparison['per_field'],
            'latency_ms' => $result->latencyMs,
            'cost_usd' => $result->costUsd,
            'prompt_tokens' => $result->promptTokens,
            'completion_tokens' => $result->completionTokens,
        ]);
    }

    /**
     * @return Collection<int, Talent>
     */
    private function goldTalents(?int $limit): Collection
    {
        return Talent::where('is_gold', true)
            ->whereHas('annotations', fn ($q) => $q->where('source', 'human'))
            ->with(['photos', 'annotations' => fn ($q) => $q->where('source', 'human')])
            ->orderBy('id')
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->get();
    }
}
