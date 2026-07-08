<?php

namespace App\Http\Controllers;

use App\Models\Talent;
use App\Services\Annotation\TalentAnnotationService;
use App\Services\Calibration\AgreementScorer;
use App\Services\Vision\VisionAnalysisService;
use App\Support\Taxonomy;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CalibrationController extends Controller
{
    public function __construct(private readonly AgreementScorer $scorer) {}

    /**
     * Tableau de bord : taux d'accord IA vs humain, agrégé par attribut sur le gold.
     */
    public function index(): Response
    {
        $talents = Talent::where('is_gold', true)
            ->with('annotations')
            ->get();

        $fields = [
            ...array_keys(Taxonomy::singleAttributes()),
            'age',
            ...array_keys(Taxonomy::multiAttributes()),
        ];

        $agree = array_fill_keys($fields, 0);
        $pairs = 0;
        $overallSum = 0.0;

        foreach ($talents as $talent) {
            $human = $talent->annotations->where('source', 'human')->sortByDesc('id')->first();
            $ai = $talent->annotations->where('source', 'ai')->sortByDesc('id')->first();

            if ($human === null || $ai === null) {
                continue;
            }

            $pairs++;
            $comparison = $this->scorer->compare($human->payload, $ai->payload);
            $overallSum += $comparison['overall'];

            foreach ($fields as $field) {
                if ($comparison['per_field'][$field] ?? false) {
                    $agree[$field]++;
                }
            }
        }

        $perAttribute = [];
        foreach ($fields as $field) {
            $perAttribute[] = [
                'attribute' => $field,
                'rate' => $pairs > 0 ? round($agree[$field] / $pairs, 4) : null,
                'agree' => $agree[$field],
            ];
        }

        return Inertia::render('Calibration', [
            'summary' => [
                'gold' => $talents->count(),
                'pairs' => $pairs,
                'overall' => $pairs > 0 ? round($overallSum / $pairs, 4) : null,
                'pending' => $talents->count() - $pairs,
            ],
            'perAttribute' => $perAttribute,
        ]);
    }

    /**
     * Analyse IA de tous les talents gold qui n'ont pas encore d'annotation IA.
     */
    public function analyzeGold(
        VisionAnalysisService $vision,
        TalentAnnotationService $annotations,
    ): RedirectResponse {
        $talents = Talent::where('is_gold', true)
            ->whereDoesntHave('annotations', fn ($q) => $q->where('source', 'ai'))
            ->with('photos')
            ->get();

        $done = 0;
        $failed = 0;

        foreach ($talents as $talent) {
            try {
                $result = $vision->analyze($talent);
                $annotations->record(
                    talent: $talent,
                    payload: $result->payload,
                    source: 'ai',
                    annotator: $result->model,
                    model: $result->model,
                );
                $done++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return back()->with('flash', [
            'message' => "Analyse IA du gold : {$done} traité(s), {$failed} échec(s).",
        ]);
    }
}
