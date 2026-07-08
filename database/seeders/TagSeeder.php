<?php

namespace Database\Seeders;

use App\Enums\SigneDistinctif;
use App\Enums\TagFamille;
use App\Enums\Vibe;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Peuple la table tags depuis les enums de taxonomie (source de vérité).
     */
    public function run(): void
    {
        foreach (Vibe::cases() as $vibe) {
            Tag::updateOrCreate(
                ['slug' => $vibe->value],
                ['label' => $vibe->label(), 'famille' => TagFamille::Vibe->value],
            );
        }

        foreach (SigneDistinctif::cases() as $signe) {
            Tag::updateOrCreate(
                ['slug' => $signe->value],
                ['label' => $signe->label(), 'famille' => TagFamille::SigneDistinctif->value],
            );
        }
    }
}
