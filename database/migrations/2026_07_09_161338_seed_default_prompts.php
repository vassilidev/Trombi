<?php

use App\Models\Prompt;
use App\Services\Prompt\PromptService;
use Illuminate\Database\Migrations\Migration;

/**
 * Sème les prompts par défaut au déploiement (php artisan migrate), pour ne pas
 * dépendre d'un seeder lancé à la main. Idempotent : n'écrase pas une version éditée.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (PromptService::defaults() as $key => $default) {
            Prompt::firstOrCreate(
                ['key' => $key],
                ['label' => $default['label'], 'content' => $default['content'], 'version' => 1],
            );
        }
    }

    public function down(): void
    {
        // On ne supprime pas les prompts : ils peuvent avoir été édités depuis.
    }
};
