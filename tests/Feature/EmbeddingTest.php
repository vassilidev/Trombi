<?php

use App\Jobs\AnalyzeTalentJob;
use App\Models\Talent;
use App\Services\Embedding\EmbeddingService;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(TagSeeder::class);
});

it('builds searchable text from description and tag labels', function () {
    $text = app(EmbeddingService::class)->buildSearchableText('Portrait doux.', ['Naturel', 'Editorial']);

    expect($text)->toBe('Portrait doux.. Naturel, Editorial');
});

it('analyzes then embeds a pending talent end to end', function () {
    fakeOpenRouter([
        'genre' => 'femme',
        'cheveux_couleur' => 'brun',
        'vibe' => ['naturel'],
        'description_fr' => 'Allure naturelle et lumineuse.',
    ]);

    $talent = talentWithImage();

    AnalyzeTalentJob::dispatchSync($talent->id);

    $talent->refresh()->load(['appearance', 'profile']);

    expect($talent->appearance->source_label)->toBe('ai')
        ->and($talent->profile)->not->toBeNull()
        ->and($talent->profile->searchable_text)->toContain('Allure naturelle');

    // Le vecteur pgvector est bien écrit.
    $hasVector = DB::selectOne(
        'SELECT description_embedding IS NOT NULL AS has FROM talent_profiles WHERE talent_id = ?',
        [$talent->id],
    );
    expect($hasVector->has)->toBeTrue();
});

it('marks embedded talents as no longer pending', function () {
    fakeOpenRouter(['genre' => 'homme', 'description_fr' => 'Regard franc.']);

    $talent = talentWithImage();
    expect(Talent::pendingAnalysis()->count())->toBe(1);

    AnalyzeTalentJob::dispatchSync($talent->id);

    expect(Talent::pendingAnalysis()->count())->toBe(0);
});
