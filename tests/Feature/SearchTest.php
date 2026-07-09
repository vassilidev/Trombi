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

it('scores a fully-matching profile at 100% (criteria coverage)', function () {
    fakeOpenRouter(['genre' => 'femme', 'semantic_text' => 'douce']);
    $femme = searchableTalent(['genre' => 'femme', 'cheveux_couleur' => 'brun']);

    $outcome = app(SearchService::class)->search('une femme');
    $top = collect($outcome['results'])->firstWhere('talent_id', $femme->id);

    expect($top['similarite'])->toBe(1.0)          // tous les critères satisfaits
        ->and($top['matched'])->toContain('Genre perçu');
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

it('relaxes other constraints but never the gender filter (souple)', function () {
    // On cherche une femme rousse ; la seule femme est brune, il y a aussi un homme.
    fakeOpenRouter(['genre' => 'femme', 'cheveux_couleur' => 'roux', 'durete' => 'souple', 'semantic_text' => 'x']);
    $femme = searchableTalent(['genre' => 'femme', 'cheveux_couleur' => 'brun']);
    $homme = searchableTalent(['genre' => 'homme']);

    $outcome = app(SearchService::class)->search('une femme rousse');
    $ids = array_column($outcome['results'], 'talent_id');

    expect($ids)->toContain($femme->id)        // la couleur de cheveux est relâchée
        ->and($ids)->not->toContain($homme->id) // mais le genre reste dur
        ->and($outcome['relaxed'])->toBeTrue();
});

it('never returns the other gender even with no match (femme → jamais un homme)', function () {
    fakeOpenRouter(['durete' => 'souple', 'semantic_text' => 'x']); // le LLM rate le genre
    $homme = searchableTalent(['genre' => 'homme']);

    // « fille » n'est pas dans la taxonomie : le filet synonyme doit le mapper.
    $outcome = app(SearchService::class)->search('je cherche une fille');

    expect(array_column($outcome['results'], 'talent_id'))->not->toContain($homme->id);
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
