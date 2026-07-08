<?php

use App\Models\Talent;
use App\Models\TalentPhoto;
use App\Services\Import\ImportService;
use Database\Seeders\TagSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(TagSeeder::class);
});

it('adds several photos to one talent and dedupes them', function () {
    $import = app(ImportService::class);
    $talent = talentWithImage();

    $import->addPhotoToTalent($talent, 'SECOND_PHOTO_BYTES');
    $import->addPhotoToTalent($talent, 'SECOND_PHOTO_BYTES'); // doublon

    expect($talent->photos()->count())->toBe(2);
});

it('adds photos through the controller', function () {
    $talent = talentWithImage();

    $this->post("/talents/{$talent->id}/photos", [
        'photos' => [UploadedFile::fake()->image('extra.jpg', 100, 100)],
    ])->assertRedirect();

    expect($talent->photos()->count())->toBe(2);
});

it('deletes a photo and promotes another as primary', function () {
    $talent = talentWithImage();
    app(ImportService::class)->addPhotoToTalent($talent, 'SECOND_PHOTO_BYTES');

    $primary = $talent->photos()->where('is_primary', true)->first();

    $this->delete("/talents/{$talent->id}/photos/{$primary->id}")->assertRedirect();

    expect(TalentPhoto::find($primary->id))->toBeNull()
        ->and($talent->photos()->where('is_primary', true)->count())->toBe(1);
});

it('deletes the last remaining photo of a talent', function () {
    $talent = talentWithImage();
    $only = $talent->photos()->first();

    $this->delete("/talents/{$talent->id}/photos/{$only->id}")->assertRedirect();

    expect($talent->photos()->count())->toBe(0);
});

it('updates a talent identity', function () {
    $talent = talentWithImage();

    $this->patch("/talents/{$talent->id}/identity", [
        'first_name' => 'Camille',
        'last_name' => 'Renard',
        'location' => 'Lyon, France',
    ])->assertRedirect();

    expect($talent->fresh())
        ->first_name->toBe('Camille')
        ->last_name->toBe('Renard')
        ->location->toBe('Lyon, France');
});

it('deletes a talent and cascades its data', function () {
    $talent = talentWithImage();
    $this->post("/talents/{$talent->id}/qualify", ['genre' => 'femme']);

    $this->delete("/talents/{$talent->id}")->assertRedirect('/talents');

    expect(Talent::find($talent->id))->toBeNull()
        ->and(TalentPhoto::where('talent_id', $talent->id)->count())->toBe(0);
});
