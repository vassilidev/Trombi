<?php

use App\Enums\Genre;
use App\Models\Talent;
use App\Support\Taxonomy;

it('exposes the canonical taxonomy for prompts and the front-end', function () {
    expect(Genre::values())->toContain('femme', 'homme');

    $single = Taxonomy::singleValues();
    expect($single)->toHaveKeys(['genre', 'type_percu', 'carnation', 'cheveux_couleur'])
        ->and($single['genre'])->toContain('femme');

    expect(Taxonomy::promptVocabulary())
        ->toContain('genre:')
        ->toContain('vibe (liste):');
});

it('persists a talent with its appearance on postgres', function () {
    $talent = Talent::factory()->gold()->create();

    $talent->appearance()->create([
        'genre' => Genre::Femme->value,
        'age_min' => 28,
        'age_max' => 35,
        'source_label' => 'human',
    ]);

    expect($talent->fresh()->is_gold)->toBeTrue()
        ->and($talent->appearance->genre)->toBe('femme');
});

it('renders the search home page through inertia', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Search'));
});
