<?php

use App\Services\Calibration\AgreementScorer;

it('scores identical payloads as full agreement', function () {
    $payload = [
        'genre' => 'femme',
        'cheveux_couleur' => 'brun',
        'age_min' => 28,
        'age_max' => 35,
        'vibe' => ['naturel', 'editorial'],
        'signes_distinctifs' => [],
    ];

    $result = app(AgreementScorer::class)->compare($payload, $payload);

    expect($result['overall'])->toBe(1.0)
        ->and($result['per_field']['genre'])->toBeTrue()
        ->and($result['per_field']['age'])->toBeTrue();
});

it('flags divergent enums and computes jaccard on lists', function () {
    $human = [
        'genre' => 'femme',
        'cheveux_couleur' => 'brun',
        'age_min' => 28,
        'age_max' => 35,
        'vibe' => ['naturel', 'editorial'],
    ];
    $ai = [
        'genre' => 'femme',
        'cheveux_couleur' => 'chatain', // divergent
        'age_min' => 40,
        'age_max' => 48,               // hors tolérance
        'vibe' => ['naturel'],         // jaccard 0.5
    ];

    $result = app(AgreementScorer::class)->compare($human, $ai);

    expect($result['per_field']['genre'])->toBeTrue()
        ->and($result['per_field']['cheveux_couleur'])->toBeFalse()
        ->and($result['per_field']['age'])->toBeFalse()
        ->and($result['scores']['vibe'])->toBe(0.5)
        ->and($result['per_field']['vibe'])->toBeTrue(); // 0.5 >= seuil
});

it('renders the calibration dashboard', function () {
    $this->get('/calibration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Calibration'));
});
