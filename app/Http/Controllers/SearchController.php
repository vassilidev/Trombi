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
        }

        return Inertia::render('Search', $props);
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
