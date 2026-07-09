<?php

use App\Support\VectorProjection;

it('computes cosine similarity', function () {
    expect(VectorProjection::cosine([1, 0, 0], [1, 0, 0]))->toBe(1.0)
        ->and(VectorProjection::cosine([1, 0], [0, 1]))->toBe(0.0)
        ->and(round(VectorProjection::cosine([1, 1], [-1, -1]), 4))->toBe(-1.0);
});

it('projects vectors to 2D with variance ratios in range', function () {
    $vectors = [
        [2.0, 0.0, 0.0],
        [-2.0, 0.1, 0.0],
        [0.0, 2.0, 0.0],
        [0.1, -2.0, 0.0],
    ];

    $result = VectorProjection::pca($vectors);

    expect($result['points'])->toHaveCount(4)
        ->and($result['points'][0])->toHaveKeys(['x', 'y'])
        ->and($result['variance'][0])->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0)
        ->and($result['variance'][0] + $result['variance'][1])->toBeLessThanOrEqual(1.0001);
});

it('handles fewer than two vectors gracefully', function () {
    expect(VectorProjection::pca([])['points'])->toBe([])
        ->and(VectorProjection::pca([[1.0, 2.0]])['points'])->toBe([['x' => 0.0, 'y' => 0.0]]);
});
