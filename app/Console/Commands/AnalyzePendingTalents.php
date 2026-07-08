<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeTalentJob;
use App\Models\Talent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('talents:analyze-pending {--sync : Traiter immédiatement au lieu de mettre en file}')]
#[Description('Analyse IA + embedding des talents pas encore profilés (analyse de masse)')]
class AnalyzePendingTalents extends Command
{
    public function handle(): int
    {
        $ids = Talent::pendingAnalysis()->orderBy('id')->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Rien à analyser : tous les talents sont profilés.');

            return self::SUCCESS;
        }

        $this->info("{$ids->count()} talent(s) à analyser.");

        foreach ($ids as $id) {
            $this->option('sync')
                ? AnalyzeTalentJob::dispatchSync($id)
                : AnalyzeTalentJob::dispatch($id);
        }

        $this->info($this->option('sync') ? 'Terminé.' : 'Jobs mis en file.');

        return self::SUCCESS;
    }
}
