<?php

use App\Models\Prompt;
use App\Services\Prompt\PromptService;
use Database\Seeders\PromptSeeder;

beforeEach(function () {
    $this->seed(PromptSeeder::class);
});

it('resolves the vision prompt with the taxonomy injected', function () {
    $resolved = app(PromptService::class)->vision();

    expect($resolved)->toContain('genre:')          // vocabulaire injecté
        ->not->toContain('{{VOCABULAIRE}}');         // placeholder remplacé
});

it('falls back to defaults when no prompt row exists', function () {
    Prompt::query()->delete();

    expect(app(PromptService::class)->parsing())
        ->toContain('durete')
        ->not->toContain('{{TAGS}}');
});

it('updates a prompt and bumps its version', function () {
    $prompt = Prompt::where('key', 'vision')->first();

    $this->put("/prompts/{$prompt->id}", [
        'content' => 'Nouveau prompt de test avec {{VOCABULAIRE}} injecté dedans.',
    ])->assertRedirect();

    $prompt->refresh();

    expect($prompt->version)->toBe(2)
        ->and($prompt->content)->toContain('Nouveau prompt de test');

    // Le service ressort bien la nouvelle version résolue.
    expect(app(PromptService::class)->vision())->toContain('Nouveau prompt de test');
});

it('renders the prompts page', function () {
    $this->get('/prompts')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Prompts')->has('prompts', 2));
});
