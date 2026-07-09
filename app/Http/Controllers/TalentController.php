<?php

namespace App\Http\Controllers;

use App\Http\Requests\QualifyTalentRequest;
use App\Http\Resources\TalentCardResource;
use App\Jobs\AnalyzeTalentJob;
use App\Models\Talent;
use App\Models\TalentPhoto;
use App\Services\Annotation\TalentAnnotationService;
use App\Services\Calibration\AgreementScorer;
use App\Services\Import\ImportService;
use App\Services\Vision\VisionAnalysisService;
use App\Support\AttributeGlossary;
use App\Support\Taxonomy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TalentController extends Controller
{
    public function __construct(
        private readonly TalentAnnotationService $annotations,
        private readonly AgreementScorer $scorer,
    ) {}

    public function index(Request $request): Response
    {
        $filter = $request->string('filter', 'all')->toString();

        $talents = Talent::with(['photos', 'appearance'])
            ->when($filter === 'gold', fn ($q) => $q->where('is_gold', true))
            ->when($filter === 'analyzed', fn ($q) => $q->whereHas('appearance'))
            ->when($filter === 'unqualified', fn ($q) => $q->where('is_gold', false))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Talents', [
            'talents' => TalentCardResource::collection($talents),
            'filter' => $filter,
            'stats' => [
                'total' => Talent::count(),
                'gold' => Talent::where('is_gold', true)->count(),
                'analyzed' => Talent::whereHas('appearance')->count(),
                'pending' => Talent::pendingAnalysis()->count(),
            ],
        ]);
    }

    /**
     * Ajoute une ou plusieurs photos à un talent existant.
     */
    public function addPhotos(Request $request, Talent $talent, ImportService $import): RedirectResponse
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'max:20'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ]);

        $added = 0;
        foreach ($validated['photos'] as $photo) {
            $binary = file_get_contents($photo->getRealPath());
            if ($binary !== false && $import->addPhotoToTalent($talent, $binary) !== null) {
                $added++;
            }
        }

        return back()->with('flash', ['message' => "{$added} photo(s) ajoutée(s) à {$talent->code}."]);
    }

    /**
     * Supprime une photo. Promeut une autre en principale si besoin.
     */
    public function destroyPhoto(Talent $talent, TalentPhoto $photo): RedirectResponse
    {
        abort_unless($photo->talent_id === $talent->id, 404);

        Storage::disk('public')->delete($photo->path);
        $wasPrimary = $photo->is_primary;
        $photo->delete();

        if ($wasPrimary) {
            $next = $talent->photos()->orderBy('id')->first();
            $next?->update(['is_primary' => true]);
        }

        return back()->with('flash', ['message' => 'Photo supprimée.']);
    }

    /**
     * Met à jour l'identité d'un talent (prénom, nom, localisation).
     */
    public function updateIdentity(Request $request, Talent $talent): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:64'],
            'last_name' => ['nullable', 'string', 'max:64'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        $talent->update($validated);

        return back()->with('flash', ['message' => 'Identité enregistrée.']);
    }

    /**
     * Supprime un talent : fichiers + cascade base.
     */
    public function destroy(Talent $talent): RedirectResponse
    {
        foreach ($talent->photos as $photo) {
            Storage::disk('public')->delete($photo->path);
        }

        $code = $talent->code;
        $talent->delete();

        return redirect()
            ->route('talents.index')
            ->with('flash', ['message' => "{$code} supprimé."]);
    }

    /**
     * Met en file l'analyse IA + embedding de tous les talents non profilés.
     */
    public function analyzePending(): RedirectResponse
    {
        $ids = Talent::pendingAnalysis()->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            AnalyzeTalentJob::dispatch($id);
        }

        return back()->with('flash', [
            'message' => "{$ids->count()} talent(s) mis en file d'analyse. Assure-toi que la queue tourne (composer run dev).",
        ]);
    }

    public function qualify(Talent $talent): Response
    {
        $talent->load(['photos', 'appearance', 'tags', 'annotations']);

        return Inertia::render('Qualify', [
            'talent' => [
                'id' => $talent->id,
                'code' => $talent->code,
                'first_name' => $talent->first_name,
                'last_name' => $talent->last_name,
                'location' => $talent->location,
                'photo_url' => $talent->displayPhotoUrl(),
                'is_gold' => $talent->is_gold,
                'photos' => $talent->photos
                    ->sortByDesc('is_primary')
                    ->map(fn ($photo) => ['id' => $photo->id, 'url' => $photo->url()])
                    ->values(),
            ],
            'values' => $this->currentValues($talent),
            'taxonomy' => AttributeGlossary::forFrontend(),
            'diff' => $this->diff($talent),
            'meta' => $this->meta($talent),
            'nextId' => $this->nextToQualify($talent)?->id,
            'visionModels' => config('services.openrouter.benchmark_models'),
            'defaultModel' => config('services.openrouter.vision_model'),
        ]);
    }

    /**
     * Métadonnées détaillées d'un talent (attributs retenus, JSON IA brut,
     * profil recherchable + vecteur, historique d'annotations).
     *
     * @return array<string, mixed>
     */
    private function meta(Talent $talent): array
    {
        $talent->loadMissing(['appearance', 'profile', 'tags', 'annotations']);
        $appearance = $talent->appearance;
        $profile = $talent->profile;

        return [
            'source' => $talent->source,
            'appearance' => $appearance === null ? null : [
                'source_label' => $appearance->source_label,
                'model_used' => $appearance->model_used,
                'analyzed_at' => $appearance->analyzed_at?->toDateTimeString(),
                'raw_analysis' => $appearance->raw_analysis,
            ],
            'tags' => $talent->tags->map(fn ($t) => ['slug' => $t->slug, 'famille' => $t->famille])->values(),
            'profile' => $profile === null ? null : [
                'searchable_text' => $profile->searchable_text,
                'model_used' => $profile->model_used,
                'embedded_at' => $profile->embedded_at?->toDateTimeString(),
                'embedding' => $this->embeddingStats($talent->id),
            ],
            'annotations' => $talent->annotations
                ->sortByDesc('id')
                ->map(fn ($a) => [
                    'source' => $a->source,
                    'annotator' => $a->annotator,
                    'created_at' => $a->created_at?->toDateTimeString(),
                    'payload' => $a->payload,
                ])
                ->values(),
        ];
    }

    /**
     * Stats lisibles sur le vecteur d'embedding (dimensions, norme, aperçu).
     *
     * @return array{dims: int, norm: float, preview: list<float>}|null
     */
    private function embeddingStats(int $talentId): ?array
    {
        $row = DB::selectOne(
            'SELECT description_embedding::text AS vec FROM talent_profiles WHERE talent_id = ? AND description_embedding IS NOT NULL',
            [$talentId],
        );

        if ($row === null || $row->vec === null) {
            return null;
        }

        $values = array_map('floatval', explode(',', trim($row->vec, '[]')));
        $norm = sqrt(array_sum(array_map(fn (float $v): float => $v * $v, $values)));

        return [
            'dims' => count($values),
            'norm' => round($norm, 4),
            'preview' => array_map(fn (float $v): float => round($v, 4), array_slice($values, 0, 8)),
        ];
    }

    /**
     * Lance l'analyse IA sur ce talent et enregistre l'annotation IA.
     */
    public function analyze(
        Request $request,
        Talent $talent,
        VisionAnalysisService $vision,
    ): RedirectResponse {
        $fewShot = $request->boolean('few_shot')
            ? Talent::where('is_gold', true)
                ->where('id', '!=', $talent->id)
                ->whereHas('annotations', fn ($q) => $q->where('source', 'human'))
                ->with(['photos', 'annotations'])
                ->limit(3)
                ->get()
                ->all()
            : [];

        $result = $vision->analyze($talent, $request->input('model'), $fewShot);

        // JSON non conforme = souvent un refus du modèle (ex. mineur sur la photo).
        // On n'enregistre pas d'annotation vide et on montre la vraie réponse.
        if (! $result->isValidJson) {
            $snippet = Str::of($result->rawContent)->squish()->limit(220)->toString();

            return back()->with('flash', [
                'message' => "L'IA n'a pas renvoyé de portrait exploitable ({$result->model}). "
                    .($snippet !== '' ? "Réponse du modèle : « {$snippet} ». " : '')
                    .'Ce modèle refuse souvent d’analyser un mineur — essaie un autre modèle vision (page Benchmark) ou qualifie ce profil à la main.',
            ]);
        }

        $this->annotations->record(
            talent: $talent,
            payload: $result->payload,
            source: 'ai',
            annotator: $result->model,
            model: $result->model,
        );

        return back()->with('flash', ['message' => "Analyse IA OK ({$result->model}, {$result->latencyMs}ms)."]);
    }

    public function storeQualification(
        QualifyTalentRequest $request,
        Talent $talent,
    ): RedirectResponse {
        $this->annotations->record(
            talent: $talent,
            payload: $request->payload(),
            source: 'human',
            annotator: 'humain',
        );

        // Rend le talent cherchable : (ré)génère l'embedding depuis les valeurs
        // humaines fraîches. L'appearance existe déjà → le job saute la vision.
        AnalyzeTalentJob::dispatch($talent->id);

        // « Sauvegarder » ou import IA : on reste sur la fiche.
        if ($request->boolean('stay')) {
            $message = $request->filled('note')
                ? $request->string('note')->toString()
                : "{$talent->code} qualifié.";

            return redirect()
                ->route('talents.qualify', $talent)
                ->with('flash', ['message' => $message]);
        }

        $next = $this->nextToQualify($talent);

        if ($next !== null) {
            return redirect()
                ->route('talents.qualify', $next)
                ->with('flash', ['message' => "{$talent->code} qualifié. Suivant : {$next->code}."]);
        }

        return redirect()
            ->route('talents.index', ['filter' => 'gold'])
            ->with('flash', ['message' => "{$talent->code} qualifié. Plus rien à qualifier."]);
    }

    /**
     * Diff JSON côte à côte : dernière annotation humaine vs dernière IA.
     *
     * @return array<string, mixed>|null
     */
    private function diff(Talent $talent): ?array
    {
        $human = $talent->annotations->where('source', 'human')->sortByDesc('id')->first();
        $ai = $talent->annotations->where('source', 'ai')->sortByDesc('id')->first();

        // On montre le diff dès qu'il y a une analyse IA, même sans validation
        // humaine : l'opérateur importe/refuse au lieu de tout voir pré-rempli.
        if ($ai === null) {
            return null;
        }

        $humanPayload = $human?->payload ?? [];
        $comparison = $this->scorer->compare($humanPayload, $ai->payload);
        $fields = [];

        foreach (array_keys(Taxonomy::singleAttributes()) as $field) {
            $fields[] = [
                'key' => $field,
                'kind' => 'single',
                'human' => $humanPayload[$field] ?? null,
                'ai' => $ai->payload[$field] ?? null,
                'ai_raw' => $ai->payload[$field] ?? null,
                'agree' => $comparison['per_field'][$field],
            ];
        }

        $fields[] = [
            'key' => 'age',
            'kind' => 'age',
            'human' => $this->ageLabel($humanPayload),
            'ai' => $this->ageLabel($ai->payload),
            'ai_raw' => [
                'age_min' => $ai->payload['age_min'] ?? null,
                'age_max' => $ai->payload['age_max'] ?? null,
            ],
            'agree' => $comparison['per_field']['age'],
        ];

        foreach (array_keys(Taxonomy::multiAttributes()) as $field) {
            $fields[] = [
                'key' => $field,
                'kind' => 'multi',
                'human' => implode(', ', $humanPayload[$field] ?? []),
                'ai' => implode(', ', $ai->payload[$field] ?? []),
                'ai_raw' => array_values($ai->payload[$field] ?? []),
                'agree' => $comparison['per_field'][$field],
            ];
        }

        return [
            'model' => $ai->annotator,
            'overall' => $comparison['overall'],
            'has_human' => $human !== null,
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ageLabel(array $payload): ?string
    {
        $min = $payload['age_min'] ?? null;
        $max = $payload['age_max'] ?? null;

        return $min === null && $max === null ? null : "{$min}–{$max}";
    }

    /**
     * Valeurs pré-remplies : UNIQUEMENT la dernière annotation humaine. On ne
     * pré-remplit jamais avec l'IA — ses valeurs passent par le diff (import/refus).
     *
     * @return array<string, mixed>
     */
    private function currentValues(Talent $talent): array
    {
        $human = $talent->annotations()
            ->where('source', 'human')
            ->latest('id')
            ->first();

        return $human?->payload ?? [];
    }

    /**
     * Prochain talent non encore qualifié à la main (ordre d'import).
     */
    private function nextToQualify(Talent $current): ?Talent
    {
        return Talent::where('is_gold', false)
            ->where('id', '!=', $current->id)
            ->orderBy('id')
            ->first();
    }
}
