<?php

namespace App\Http\Controllers;

use App\Models\Talent;
use App\Support\AttributeGlossary;
use App\Support\Taxonomy;
use App\Support\VectorProjection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Le Labo » : page pédagogique qui rend visible la mécanique IA de Trombi —
 * pipeline photo → vecteur, projection 2D du dataset, similarité cosinus.
 */
class LabController extends Controller
{
    /** Plafond d'échantillons pour la projection/paires (coût O(n²·d) en PHP). */
    private const SAMPLE = 80;

    public function index(): Response
    {
        $embeddings = $this->loadEmbeddings();

        return Inertia::render('Lab', [
            'pipeline' => [
                'talents' => Talent::count(),
                'analyzed' => Talent::whereHas('appearance')->count(),
                'searchable' => Talent::whereHas('profile')->count(),
                'gold' => Talent::where('is_gold', true)->count(),
            ],
            'embedding' => [
                'model' => config('services.openrouter.embedding_model'),
                'dims' => config('services.openrouter.embedding_dimensions'),
            ],
            'dataset' => $this->datasetComposition(),
            'projection' => $this->projection($embeddings),
            'pairs' => $this->similarityPairs($embeddings),
            'anatomy' => $this->anatomy($embeddings),
        ]);
    }

    /**
     * Charge les vecteurs (limités) + l'identité minimale de chaque talent.
     *
     * @return list<array{id: int, code: string, name: ?string, genre: ?string, photo: ?string, vec: list<float>}>
     */
    private function loadEmbeddings(): array
    {
        $rows = DB::table('talent_profiles as p')
            ->join('talents as t', 't.id', '=', 'p.talent_id')
            ->leftJoin('talent_appearances as a', 'a.talent_id', '=', 'p.talent_id')
            ->whereNotNull('p.description_embedding')
            ->orderBy('p.talent_id')
            ->limit(self::SAMPLE)
            ->get([
                'p.talent_id',
                't.code',
                't.first_name',
                't.last_name',
                'a.genre',
                DB::raw('p.description_embedding::text as vec'),
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $talents = Talent::with('photos')->whereIn('id', $rows->pluck('talent_id'))->get()->keyBy('id');

        return $rows->map(function ($row) use ($talents): array {
            $name = trim("{$row->first_name} {$row->last_name}");

            return [
                'id' => (int) $row->talent_id,
                'code' => $row->code,
                'name' => $name === '' ? null : $name,
                'genre' => $row->genre,
                'photo' => $talents->get($row->talent_id)?->displayPhotoUrl(),
                'vec' => array_map('floatval', explode(',', trim($row->vec, '[]'))),
            ];
        })->all();
    }

    /**
     * Composition du dataset : répartition des valeurs par attribut structuré.
     *
     * @return list<array{key: string, label: string, total: int, bars: list<array{label: string, count: int}>}>
     */
    private function datasetComposition(): array
    {
        $glossary = collect(AttributeGlossary::forFrontend()['single'])->keyBy('key');
        $out = [];

        foreach (array_keys(Taxonomy::singleAttributes()) as $field) {
            $counts = DB::table('talent_appearances')
                ->whereNotNull($field)
                ->groupBy($field)
                ->select($field.' as value', DB::raw('count(*) as n'))
                ->pluck('n', 'value');

            if ($counts->isEmpty()) {
                continue;
            }

            $options = collect($glossary->get($field)['options'] ?? [])->keyBy('value');
            $bars = $counts
                ->map(fn ($n, $value): array => [
                    'label' => $options->get($value)['label'] ?? $value,
                    'count' => (int) $n,
                ])
                ->sortByDesc('count')
                ->values()
                ->all();

            $out[] = [
                'key' => $field,
                'label' => $glossary->get($field)['label'] ?? $field,
                'total' => (int) $counts->sum(),
                'bars' => $bars,
            ];
        }

        return $out;
    }

    /**
     * Projection PCA 2D des embeddings, normalisée dans [-1, 1] pour l'affichage.
     *
     * @param  list<array<string, mixed>>  $embeddings
     * @return array{points: list<array<string, mixed>>, variance: array{0: float, 1: float}, count: int}
     */
    private function projection(array $embeddings): array
    {
        if (count($embeddings) < 2) {
            return ['points' => [], 'variance' => [0.0, 0.0], 'count' => count($embeddings)];
        }

        $pca = VectorProjection::pca(array_column($embeddings, 'vec'));
        $coords = $pca['points'];

        $maxAbs = 1e-9;
        foreach ($coords as $c) {
            $maxAbs = max($maxAbs, abs($c['x']), abs($c['y']));
        }

        $points = [];
        foreach ($embeddings as $i => $e) {
            $points[] = [
                'id' => $e['id'],
                'code' => $e['code'],
                'name' => $e['name'],
                'genre' => $e['genre'],
                'photo' => $e['photo'],
                'x' => round($coords[$i]['x'] / $maxAbs, 4),
                'y' => round($coords[$i]['y'] / $maxAbs, 4),
            ];
        }

        return ['points' => $points, 'variance' => $pca['variance'], 'count' => count($embeddings)];
    }

    /**
     * Paire la plus proche et la plus éloignée (cosinus) pour illustrer la mesure.
     *
     * @param  list<array<string, mixed>>  $embeddings
     * @return array{closest: ?array<string, mixed>, farthest: ?array<string, mixed>}|null
     */
    private function similarityPairs(array $embeddings): ?array
    {
        $n = count($embeddings);
        if ($n < 2) {
            return null;
        }

        $best = null;
        $worst = null;

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $score = VectorProjection::cosine($embeddings[$i]['vec'], $embeddings[$j]['vec']);

                if ($best === null || $score > $best['score']) {
                    $best = ['score' => $score, 'a' => $i, 'b' => $j];
                }
                if ($worst === null || $score < $worst['score']) {
                    $worst = ['score' => $score, 'a' => $i, 'b' => $j];
                }
            }
        }

        return [
            'closest' => $this->pairCard($embeddings, $best),
            'farthest' => $this->pairCard($embeddings, $worst),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $embeddings
     * @param  array{score: float, a: int, b: int}  $pair
     * @return array<string, mixed>
     */
    private function pairCard(array $embeddings, array $pair): array
    {
        $card = fn (array $e): array => [
            'id' => $e['id'],
            'code' => $e['code'],
            'name' => $e['name'],
            'photo' => $e['photo'],
        ];

        return [
            'score' => round($pair['score'], 4),
            'a' => $card($embeddings[$pair['a']]),
            'b' => $card($embeddings[$pair['b']]),
        ];
    }

    /**
     * Anatomie d'un vecteur : aperçu des premières valeurs + norme, sur un exemple.
     *
     * @param  list<array<string, mixed>>  $embeddings
     * @return array{code: string, values: list<float>, norm: float}|null
     */
    private function anatomy(array $embeddings): ?array
    {
        if ($embeddings === []) {
            return null;
        }

        $sample = $embeddings[0];
        $vec = $sample['vec'];
        $norm = sqrt(array_sum(array_map(fn (float $v): float => $v * $v, $vec)));

        return [
            'code' => $sample['code'],
            'values' => array_map(fn (float $v): float => round($v, 4), array_slice($vec, 0, 64)),
            'norm' => round($norm, 4),
        ];
    }
}
