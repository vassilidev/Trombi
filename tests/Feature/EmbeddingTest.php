<?php

use App\Jobs\AnalyzeTalentJob;
use App\Models\Talent;
use App\Models\TalentAppearance;
use App\Services\Embedding\EmbeddingService;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(TagSeeder::class);
});

it('builds searchable text from structured attributes, tags and description', function () {
    $appearance = new TalentAppearance([
        'genre' => 'homme',
        'type_percu' => 'europeen',
        'cheveux_couleur' => 'brun',
        'age_min' => 30,
        'age_max' => 35,
    ]);

    $text = app(EmbeddingService::class)->buildSearchableText($appearance, ['Naturel', 'Commercial'], 'Portrait doux.');

    expect($text)
        ->toContain('Homme')
        ->toContain('Brun')
        ->toContain('30 à 35 ans')
        ->toContain('Naturel, Commercial')
        ->toContain('Portrait doux.');
});

it('stays searchable even without a written description', function () {
    $appearance = new TalentAppearance(['genre' => 'femme', 'yeux_couleur' => 'vert']);

    $text = app(EmbeddingService::class)->buildSearchableText($appearance, [], '');

    expect($text)->toContain('Femme')->not->toBe('profil sans description');
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
