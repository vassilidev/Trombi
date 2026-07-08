<?php

namespace App\Services\Benchmark;

use App\Models\BenchmarkResult;
use App\Models\BenchmarkRun;
use App\Models\Talent;
use App\Support\Taxonomy;
use Illuminate\Support\Collection;

/**
 * Agrège un run de benchmark en tableaux prêts pour l'UI : récap par modèle,
 * meilleur rapport justesse/coût, delta par attribut et par image.
 */
class BenchmarkSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function for(BenchmarkRun $run): array
    {
        $run->loadMissing('results');
        $results = $run->results;
        $models = $run->models;

        $fields = [
            ...array_keys(Taxonomy::singleAttributes()),
            'age',
            ...array_keys(Taxonomy::multiAttributes()),
        ];

        return [
            'run' => [
                'id' => $run->id,
                'label' => $run->label,
                'prompt_version' => $run->prompt_version,
                'models' => $models,
                'gold_count' => $run->gold_count,
                'created_at' => $run->created_at?->toDateTimeString(),
            ],
            'perModel' => self::perModel($results, $models, $fields),
            'bestValue' => self::bestValue($results, $models),
            'deltaByAttribute' => self::deltaByAttribute($results, $models, $fields),
            'deltaByImage' => self::deltaByImage($run, $results, $models, $fields),
        ];
    }

    /**
     * @param  Collection<int, BenchmarkResult>  $results
     * @param  list<string>  $models
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    private static function perModel(Collection $results, array $models, array $fields): array
    {
        $rows = [];

        foreach ($models as $model) {
            $rows[] = self::modelRow($results->where('model', $model), $model, $fields);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, BenchmarkResult>  $rows
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private static function modelRow(Collection $rows, string $model, array $fields): array
    {
        $count = $rows->count();
        $latencies = $rows->pluck('latency_ms')->filter()->values()->all();
        $costTotal = (float) $rows->sum('cost_usd');

        $perAttribute = [];
        foreach ($fields as $field) {
            $agree = $rows->filter(fn ($r) => ($r->per_field_result[$field] ?? false) === true)->count();
            $perAttribute[$field] = $count > 0 ? round($agree / $count, 4) : null;
        }

        return [
            'model' => $model,
            'images' => $count,
            'justesse' => $count > 0 ? round((float) $rows->avg('agreement_score'), 4) : null,
            'valid_rate' => $count > 0 ? round($rows->where('is_valid_json', true)->count() / $count, 4) : null,
            'latency_p50' => self::percentile($latencies, 0.5),
            'latency_p95' => self::percentile($latencies, 0.95),
            'cost_total' => round($costTotal, 6),
            'cost_per_image' => $count > 0 ? round($costTotal / $count, 6) : null,
            'per_attribute' => $perAttribute,
        ];
    }

    /**
     * Meilleur rapport justesse / coût (le plus juste n'est pas toujours le plus cher).
     *
     * @param  Collection<int, BenchmarkResult>  $results
     * @param  list<string>  $models
     */
    private static function bestValue(Collection $results, array $models): ?string
    {
        $best = null;
        $bestRatio = -1.0;

        foreach ($models as $model) {
            $rows = $results->where('model', $model);
            if ($rows->isEmpty()) {
                continue;
            }

            $justesse = (float) $rows->avg('agreement_score');
            $cost = (float) $rows->sum('cost_usd');
            // Coût nul (ou inconnu) → on privilégie la justesse pure.
            $ratio = $cost > 0 ? $justesse / $cost : $justesse * 1_000_000;

            if ($ratio > $bestRatio) {
                $bestRatio = $ratio;
                $best = $model;
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int, BenchmarkResult>  $results
     * @param  list<string>  $models
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    private static function deltaByAttribute(Collection $results, array $models, array $fields): array
    {
        $rows = [];

        foreach ($fields as $field) {
            $byModel = [];
            foreach ($models as $model) {
                $modelRows = $results->where('model', $model);
                $count = $modelRows->count();
                $agree = $modelRows->filter(fn ($r) => ($r->per_field_result[$field] ?? false) === true)->count();
                $byModel[$model] = $count > 0 ? round($agree / $count, 4) : null;
            }
            $rows[] = ['attribute' => $field, 'models' => $byModel];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, BenchmarkResult>  $results
     * @param  list<string>  $models
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    private static function deltaByImage(BenchmarkRun $run, Collection $results, array $models, array $fields): array
    {
        $talentIds = $results->pluck('talent_id')->unique()->values();
        $talents = Talent::whereIn('id', $talentIds)
            ->with(['photos', 'annotations' => fn ($q) => $q->where('source', 'human')])
            ->get()
            ->keyBy('id');

        $images = [];

        foreach ($talentIds as $talentId) {
            $talent = $talents->get($talentId);
            if ($talent === null) {
                continue;
            }

            $human = $talent->annotations->firstWhere('source', 'human');
            $modelPayloads = [];

            foreach ($models as $model) {
                $result = $results->firstWhere(fn ($r) => $r->talent_id === $talentId && $r->model === $model);
                $modelPayloads[$model] = [
                    'payload' => $result?->payload ?? [],
                    'per_field' => $result?->per_field_result ?? [],
                    'valid' => $result?->is_valid_json ?? false,
                ];
            }

            $images[] = [
                'talent' => [
                    'id' => $talent->id,
                    'code' => $talent->code,
                    'photo_url' => $talent->displayPhotoUrl(),
                ],
                'fields' => self::imageFields($fields, $human?->payload ?? [], $modelPayloads, $models),
            ];
        }

        return $images;
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $human
     * @param  array<string, array{payload: array<string, mixed>, per_field: array<string, bool>, valid: bool}>  $modelPayloads
     * @param  list<string>  $models
     * @return list<array<string, mixed>>
     */
    private static function imageFields(array $fields, array $human, array $modelPayloads, array $models): array
    {
        $rows = [];

        foreach ($fields as $field) {
            $byModel = [];
            foreach ($models as $model) {
                $byModel[$model] = [
                    'value' => self::displayValue($field, $modelPayloads[$model]['payload']),
                    'agree' => $modelPayloads[$model]['per_field'][$field] ?? false,
                ];
            }

            $rows[] = [
                'attribute' => $field,
                'human' => self::displayValue($field, $human),
                'models' => $byModel,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function displayValue(string $field, array $payload): ?string
    {
        if ($field === 'age') {
            $min = $payload['age_min'] ?? null;
            $max = $payload['age_max'] ?? null;

            return $min === null && $max === null ? null : "{$min}–{$max}";
        }

        $value = $payload[$field] ?? null;

        return is_array($value) ? implode(', ', $value) : $value;
    }

    /**
     * @param  list<int>  $values
     */
    private static function percentile(array $values, float $p): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $index = (int) ceil($p * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return $values[$index];
    }
}
