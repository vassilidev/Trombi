<?php

namespace Database\Seeders;

use App\Models\Prompt;
use App\Services\Prompt\PromptService;
use Illuminate\Database\Seeder;

class PromptSeeder extends Seeder
{
    /**
     * Insère les prompts par défaut (sans écraser une version déjà éditée).
     */
    public function run(): void
    {
        foreach (PromptService::defaults() as $key => $default) {
            Prompt::firstOrCreate(
                ['key' => $key],
                ['label' => $default['label'], 'content' => $default['content'], 'version' => 1],
            );
        }
    }
}
