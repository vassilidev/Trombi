<?php

namespace App\Http\Controllers;

use App\Models\Talent;
use App\Services\Search\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));

        $props = [
            'stats' => [
                'talents' => Talent::count(),
                'gold' => Talent::where('is_gold', true)->count(),
                'searchable' => Talent::whereHas('profile')->count(),
            ],
            'query' => $query,
            'search' => null,
        ];

        if ($query !== '') {
            $outcome = $this->search->search($query, 'chat', 5);

            $props['search'] = [
                'filters' => $outcome['filters'],
                'relaxed' => $outcome['relaxed'],
                'results' => $this->hydrate($outcome['results']),
            ];
        } else {
            $props['demo'] = $this->demo();
        }

        return Inertia::render('Search', $props);
    }

    /**
     * Données illustratives pour la landing : un exemple concret du pipeline
     * requête → filtres structurés → profils classés, + une fiche portrait-robot.
     *
     * @return array<string, mixed>
     */
    private function demo(): array
    {
        $query = 'une femme, la trentaine, cheveux bruns, air méditerranéen, sourire naturel, plutôt pub que haute couture';

        // Filtres illustratifs (ce que l'IA extrait typiquement de cette requête).
        $filters = [
            'genre' => 'femme',
            'age_min' => 28,
            'age_max' => 38,
            'type_percu' => ['europeen', 'latino'],
            'cheveux_couleur' => ['brun', 'chatain'],
            'tags_requis' => ['naturel', 'commercial'],
            'semantic_text' => 'air méditerranéen, sourire naturel, plutôt pub que haute couture',
        ];

        // Résultats : de vrais visages de la base, avec des scores d'illustration.
        $talents = Talent::with(['photos', 'profile'])
            ->whereHas('photos')
            ->latest('id')
            ->limit(3)
            ->get();

        $scores = [0.92, 0.87, 0.83];
        $results = $talents->values()->map(fn (Talent $t, int $i): array => [
            'code' => $t->code,
            'photo_url' => $t->displayPhotoUrl(),
            'score' => $scores[$i] ?? 0.8,
            'description' => $t->profile?->description_fr,
        ])->all();

        // Fiche portrait-robot : un talent réellement analysé et RICHE (avec description),
        // sinon un exemple curé pour que la vitrine reste parlante.
        $rich = Talent::with(['photos', 'appearance'])
            ->whereHas('appearance')
            ->get()
            ->first(fn (Talent $t): bool => filled($t->appearance?->raw_analysis['description_fr'] ?? null));

        $example = [
            'genre' => 'femme',
            'age_min' => 29,
            'age_max' => 36,
            'type_percu' => 'europeen',
            'carnation' => 'III',
            'cheveux_couleur' => 'brun',
            'cheveux_longueur' => 'mi_long',
            'yeux_couleur' => 'vert',
            'expression' => 'sourire',
            'vibe' => ['naturel', 'commercial'],
            'signes_distinctifs' => ['taches_de_rousseur'],
            'description_fr' => 'Allure naturelle et lumineuse, sourire franc et regard chaleureux. Un profil très publicitaire, à l\'aise en registre grand public.',
        ];

        $portrait = [
            'photo_url' => ($rich ?? $talents->first())?->displayPhotoUrl(),
            'json' => $rich?->appearance?->raw_analysis ?? $example,
            'is_example' => $rich === null,
        ];

        return [
            'query' => $query,
            'filters' => $filters,
            'results' => $results,
            'portrait' => $portrait,
        ];
    }

    /**
     * Hydrate les résultats (talent + description + score), en préservant l'ordre.
     *
     * @param  list<array{talent_id: int, similarite: float}>  $results
     * @return list<array<string, mixed>>
     */
    private function hydrate(array $results): array
    {
        $ids = array_column($results, 'talent_id');

        if ($ids === []) {
            return [];
        }

        $talents = Talent::with(['photos', 'profile', 'appearance'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $cards = [];

        foreach ($results as $result) {
            $talent = $talents->get($result['talent_id']);

            if ($talent === null) {
                continue;
            }

            $cards[] = [
                'id' => $talent->id,
                'code' => $talent->code,
                'photo_url' => $talent->displayPhotoUrl(),
                'is_gold' => $talent->is_gold,
                'is_analyzed' => $talent->appearance !== null,
                'description' => $talent->profile?->description_fr,
                'score' => $result['similarite'],
            ];
        }

        return $cards;
    }
}
