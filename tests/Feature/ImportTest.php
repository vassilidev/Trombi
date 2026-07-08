<?php

use App\Models\Talent;
use App\Services\Import\ImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('imports an uploaded image and dedupes by hash', function () {
    $service = app(ImportService::class);

    $binary = UploadedFile::fake()->image('face.jpg', 200, 200)->get();

    $first = $service->storeImage($binary, 'upload');
    $second = $service->storeImage($binary, 'upload'); // même contenu → doublon

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull()
        ->and(Talent::count())->toBe(1);

    Storage::disk('public')->assertExists($first->photos->first()->path);
});

it('accepts a multi-file upload via the controller', function () {
    $response = $this->post('/import/upload', [
        'photos' => [
            UploadedFile::fake()->image('a.jpg', 100, 100),
            UploadedFile::fake()->image('b.jpg', 120, 120),
        ],
    ]);

    $response->assertRedirect();
    expect(Talent::count())->toBe(2);
});

it('pulls faces from the api and counts imports vs skips', function () {
    Http::fakeSequence()
        ->push('IMAGE_BYTES_A', 200, ['Content-Type' => 'image/jpeg'])
        ->push('IMAGE_BYTES_B', 200, ['Content-Type' => 'image/jpeg']);

    $result = app(ImportService::class)->pullFromApi(2);

    expect($result['imported'])->toBe(2)
        ->and($result['failed'])->toBe(0)
        ->and(Talent::count())->toBe(2);
});
