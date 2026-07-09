<?php

use App\Services\Vision\VisionAnalysisService;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(TagSeeder::class);
});

it('reports a model refusal (e.g. minor) without recording an empty annotation', function () {
    Http::fake([
        '*/embeddings' => Http::response(['data' => [['embedding' => array_fill(0, 1536, 0.01)]]]),
        '*/chat/completions' => Http::response([
            'model' => 'openai/gpt-4o',
            'choices' => [['message' => ['content' => "I'm sorry, but I can't describe this person."]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20],
        ]),
    ]);

    $talent = talentWithImage();

    $this->post("/talents/{$talent->id}/analyze")
        ->assertRedirect()
        ->assertSessionHas('flash.message', fn (string $m): bool => str_contains($m, 'mineur') && str_contains($m, 'sorry'));

    expect($talent->annotations()->where('source', 'ai')->count())->toBe(0);
});

it('analyzes a face, normalizes to the taxonomy and reports validity', function () {
    fakeVision([
        'genre' => 'femme',
        'age_min' => 28,
        'age_max' => 35,
        'type_percu' => 'europeen',
        'cheveux_couleur' => 'brun',
        'yeux_couleur' => 'vert',
        'vibe' => ['naturel', 'inexistant'], // 'inexistant' hors taxonomie
        'signes_distinctifs' => ['taches_de_rousseur'],
        'description_fr' => 'Portrait lumineux.',
    ]);

    $result = app(VisionAnalysisService::class)->analyze(talentWithImage());

    expect($result->payload['genre'])->toBe('femme')
        ->and($result->payload['vibe'])->toBe(['naturel']) // valeur hors taxonomie filtrée
        ->and($result->isValidJson)->toBeFalse()           // propreté KO (valeur inconnue)
        ->and($result->costUsd)->toBe(0.0012)
        ->and($result->descriptionFr)->toBe('Portrait lumineux.');
});

it('writes the AI appearance and tags for a non-gold talent', function () {
    fakeVision([
        'genre' => 'homme',
        'cheveux_couleur' => 'noir',
        'vibe' => ['corporate'],
    ]);

    $talent = talentWithImage();

    $this->post("/talents/{$talent->id}/analyze")->assertRedirect();

    $talent->refresh()->load(['appearance', 'tags', 'annotations']);

    expect($talent->appearance->source_label)->toBe('ai')
        ->and($talent->appearance->genre)->toBe('homme')
        ->and($talent->tags->pluck('slug')->all())->toContain('corporate')
        ->and($talent->annotations->where('source', 'ai'))->toHaveCount(1);
});

it('keeps the human canon when the talent is already gold', function () {
    $talent = talentWithImage();

    // Qualification humaine d'abord.
    $this->post("/talents/{$talent->id}/qualify", ['genre' => 'femme', 'cheveux_couleur' => 'blond']);

    // Puis analyse IA qui dit autre chose.
    fakeVision(['genre' => 'homme', 'cheveux_couleur' => 'noir']);
    $this->post("/talents/{$talent->id}/analyze");

    $talent->refresh()->load('appearance');

    expect($talent->appearance->source_label)->toBe('human')
        ->and($talent->appearance->genre)->toBe('femme'); // l'IA n'écrase pas
});
