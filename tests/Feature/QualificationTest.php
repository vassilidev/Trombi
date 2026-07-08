<?php

use App\Models\Talent;
use Database\Seeders\TagSeeder;

beforeEach(function () {
    $this->seed(TagSeeder::class);
});

it('records a human annotation, marks gold, writes appearance and tags', function () {
    $talent = Talent::factory()->create();

    $response = $this->post("/talents/{$talent->id}/qualify", [
        'genre' => 'femme',
        'age_min' => 28,
        'age_max' => 35,
        'type_percu' => 'europeen',
        'cheveux_couleur' => 'brun',
        'yeux_couleur' => 'vert',
        'vibe' => ['naturel', 'editorial'],
        'signes_distinctifs' => ['taches_de_rousseur'],
        'description_fr' => 'Allure naturelle et lumineuse.',
    ]);

    $response->assertRedirect();

    $talent->refresh()->load(['appearance', 'tags', 'annotations']);

    expect($talent->is_gold)->toBeTrue()
        ->and($talent->appearance->source_label)->toBe('human')
        ->and($talent->appearance->genre)->toBe('femme')
        ->and($talent->appearance->age_min)->toBe(28)
        ->and($talent->tags->pluck('slug')->sort()->values()->all())
        ->toEqual(['editorial', 'naturel', 'taches_de_rousseur'])
        ->and($talent->annotations->where('source', 'human'))->toHaveCount(1);
});

it('rejects values outside the taxonomy', function () {
    $talent = Talent::factory()->create();

    $this->post("/talents/{$talent->id}/qualify", [
        'genre' => 'martien',
        'cheveux_couleur' => 'arc_en_ciel',
    ])->assertSessionHasErrors(['genre', 'cheveux_couleur']);
});

it('sends the operator to the next talent to qualify', function () {
    $first = Talent::factory()->create();
    $second = Talent::factory()->create();

    $this->post("/talents/{$first->id}/qualify", ['genre' => 'homme'])
        ->assertRedirect("/talents/{$second->id}/qualify");
});
