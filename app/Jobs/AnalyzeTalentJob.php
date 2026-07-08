<?php

namespace App\Jobs;

use App\Models\Talent;
use App\Services\Annotation\TalentAnnotationService;
use App\Services\Embedding\EmbeddingService;
use App\Services\Vision\VisionAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Analyse IA d'un talent (si pas encore analysé) puis embedding de son profil.
 * Import et analyse étant découplés, ce job traite un talent en masse (PRD §5).
 */
class AnalyzeTalentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(public readonly int $talentId) {}

    public function handle(
        VisionAnalysisService $vision,
        TalentAnnotationService $annotations,
        EmbeddingService $embeddings,
    ): void {
        $talent = Talent::with(['photos', 'appearance', 'tags'])->find($this->talentId);

        if ($talent === null) {
            return;
        }

        // Analyse IA seulement si le talent n'a pas encore de valeurs retenues.
        if ($talent->appearance === null) {
            $result = $vision->analyze($talent);
            $annotations->record(
                talent: $talent,
                payload: $result->payload,
                source: 'ai',
                annotator: $result->model,
                model: $result->model,
            );
            $talent->refresh()->load('tags');
        }

        $embeddings->embedTalent($talent);
    }
}
