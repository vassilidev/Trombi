<?php

use App\Services\Search\SearchFilters;

function emptyFilters(): SearchFilters
{
    return SearchFilters::fromLlm([], 'x');
}

it('forces an exact taxonomy word into a hard filter', function () {
    $filters = emptyFilters()->withKeywordFallback('je cherche une femme brune');

    expect($filters->attributes['genre'] ?? null)->toBe(['femme']);
});

it('maps a natural-language synonym to the gender filter', function () {
    expect(emptyFilters()->withKeywordFallback('je cherche une fille')->attributes['genre'] ?? null)->toBe(['femme'])
        ->and(emptyFilters()->withKeywordFallback('un mec baraqué')->attributes['genre'] ?? null)->toBe(['homme']);
});

it('does not confuse femme and homme', function () {
    $filters = emptyFilters()->withKeywordFallback('une femme');

    expect($filters->attributes['genre'] ?? null)->toBe(['femme']);
});

it('is accent and case insensitive', function () {
    expect(emptyFilters()->withKeywordFallback('une FEMME féminine')->attributes['genre'] ?? null)->toBe(['femme']);
});

it('keeps LLM-extracted attributes and adds keyword ones', function () {
    $filters = SearchFilters::fromLlm(['cheveux_couleur' => 'brun'], 'x')
        ->withKeywordFallback('une femme');

    expect($filters->attributes['genre'] ?? null)->toBe(['femme'])
        ->and($filters->attributes['cheveux_couleur'] ?? null)->toBe(['brun']);
});
