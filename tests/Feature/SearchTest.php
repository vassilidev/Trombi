<?php

use App\Jobs\AnalyzeTalentJob;
use App\Models\Brief;
use App\Models\Talent;
use App\Services\Search\SearchService;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(TagSeeder::class);
});

/**
 * Crée un talent gold qualifié et embeddé (recherchable).
 */
function searchableTalent(array $qualify): Talent
{
    $talent = talentWithImage();
    test()->post("/talents/{$talent->id}/qualify", $qualify);
    AnalyzeTalentJob::dispatchSync($talent->id);

    return $talent->refresh();
}

it('applies the hard filter then vector ranking', function () {
    // Le parser (IA) renverra ce DTO ; les embeddings renverront un vecteur constant.
    fakeOpenRouter(['genre' => 'femme', 'semantic_text' => 'allure douce']);

    $femme = searchableTalent(['genre' => 'femme', 'cheveux_couleur' => 'brun']);
    $homme = searchableTalent(['genre' => 'homme', 'cheveux_couleur' => 'noir']);

    $outcome = app(SearchService::class)->search('une femme brune et douce');

    $ids = array_column($outcome['results'], 'talent_id');

    expect($ids)->toContain($femme->id)
        ->and($ids)->not->toContain($homme->id)
        ->and($outcome['relaxed'])->toBeFalse();
});

it('logs the brief and its matches', function () {
    fakeOpenRouter(['genre' => 'femme', 'semantic_text' => 'douce']);
    searchableTalent(['genre' => 'femme']);

    app(SearchService::class)->search('une femme douce');

    $brief = Brief::latest('id')->first();

    expect($brief)->not->toBeNull()
        ->and($brief->raw_text)->toBe('une femme douce')
        ->and($brief->parsed_filters['attributes']['genre'])->toBe(['femme'])
        ->and($brief->matches()->count())->toBeGreaterThan(0);
});

it('relaxes constraints instead of returning an empty list (souple)', function () {
    // On cherche une femme, mais la base ne contient qu'un homme.
    fakeOpenRouter(['genre' => 'femme', 'durete' => 'souple', 'semantic_text' => 'x']);
    $homme = searchableTalent(['genre' => 'homme']);

    $outcome = app(SearchService::class)->search('une femme');

    expect($outcome['results'])->not->toBeEmpty()
        ->and($outcome['relaxed'])->toBeTrue()
        ->and(array_column($outcome['results'], 'talent_id'))->toContain($homme->id);
});

it('renders search results through the home page', function () {
    fakeOpenRouter(['genre' => 'femme', 'semantic_text' => 'douce']);
    searchableTalent(['genre' => 'femme']);

    $this->get('/?q='.urlencode('une femme douce'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Search')
            ->has('search.results', 1)
        );
});
