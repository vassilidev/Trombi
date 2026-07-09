<?php

use App\Jobs\AnalyzeTalentJob;
use App\Models\Talent;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(TagSeeder::class);
    Queue::fake();
});

it('queues the embedding so the talent becomes searchable', function () {
    $talent = Talent::factory()->create();

    $this->post("/talents/{$talent->id}/qualify", ['genre' => 'femme', 'stay' => true]);

    Queue::assertPushed(AnalyzeTalentJob::class, fn ($job) => $job->talentId === $talent->id);
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

it('stays on the same talent when saving with stay', function () {
    $first = Talent::factory()->create();
    Talent::factory()->create();

    $this->post("/talents/{$first->id}/qualify", ['genre' => 'homme', 'stay' => true])
        ->assertRedirect("/talents/{$first->id}/qualify");

    $first->refresh()->load('annotations');

    expect($first->is_gold)->toBeTrue()
        ->and($first->annotations->firstWhere('source', 'human')->annotator)->toBe('humain');
});

it('persists an AI import immediately and flashes the note', function () {
    $talent = Talent::factory()->create();

    $this->post("/talents/{$talent->id}/qualify", [
        'cheveux_couleur' => 'brun',
        'stay' => true,
        'note' => '« cheveux_couleur » importé de l’IA.',
    ])
        ->assertRedirect("/talents/{$talent->id}/qualify")
        ->assertSessionHas('flash.message', '« cheveux_couleur » importé de l’IA.');

    $talent->refresh()->load('appearance');

    expect($talent->appearance->cheveux_couleur)->toBe('brun');
});
