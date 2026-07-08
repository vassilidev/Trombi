<?php

use App\Models\BenchmarkRun;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(TagSeeder::class);
});

it('runs a benchmark across models and gold images, then summarizes', function () {
    // Deux talents gold qualifiés à la main.
    $a = talentWithImage();
    $b = talentWithImage();
    $this->post("/talents/{$a->id}/qualify", ['genre' => 'femme', 'cheveux_couleur' => 'brun']);
    $this->post("/talents/{$b->id}/qualify", ['genre' => 'homme', 'cheveux_couleur' => 'noir']);

    // L'IA répond toujours "femme/brun" → juste pour A, faux pour B.
    fakeVision(['genre' => 'femme', 'cheveux_couleur' => 'brun']);

    $response = $this->post('/benchmark/run', [
        'models' => ['openai/gpt-4o', 'google/gemini-2.5-flash'],
    ]);

    $run = BenchmarkRun::latest('id')->first();
    $response->assertRedirect("/benchmark/{$run->id}");

    expect($run->gold_count)->toBe(2)
        ->and($run->results()->count())->toBe(4); // 2 images × 2 modèles
});

it('renders a benchmark run summary', function () {
    $a = talentWithImage();
    $this->post("/talents/{$a->id}/qualify", ['genre' => 'femme']);

    fakeVision(['genre' => 'femme']);
    $this->post('/benchmark/run', ['models' => ['openai/gpt-4o']]);

    $run = BenchmarkRun::latest('id')->first();

    $this->get("/benchmark/{$run->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('BenchmarkRun')
            ->where('summary.perModel.0.model', 'openai/gpt-4o')
            ->where('summary.bestValue', 'openai/gpt-4o')
        );
});
