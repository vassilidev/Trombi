<?php

namespace App\Services\Annotation;

use App\Models\Tag;
use App\Models\Talent;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\DB;

/**
 * Écrit une annotation (humaine ou IA) et reporte les valeurs retenues dans
 * talent_appearances + talent_tag. Règle du PRD §4.2 : le label humain prime.
 */
class TalentAnnotationService
{
    /**
     * @param  array<string, mixed>  $payload  Même forme que la sortie vision.
     * @param  'human'|'ai'|'computed'  $source
     */
    public function record(
        Talent $talent,
        array $payload,
        string $source,
        ?string $annotator = null,
        ?string $model = null,
    ): void {
        DB::transaction(function () use ($talent, $payload, $source, $annotator, $model): void {
            $talent->annotations()->create([
                'source' => $source,
                'annotator' => $annotator,
                'payload' => $payload,
            ]);

            if ($source === 'human') {
                $talent->forceFill(['is_gold' => true])->save();
            }

            // Le label humain prime : une annotation IA n'écrase pas un canon humain.
            if ($source !== 'human' && $this->hasHumanCanon($talent)) {
                return;
            }

            $this->writeAppearance($talent, $payload, $source, $model);
            $this->syncTags($talent, $payload);
        });
    }

    private function hasHumanCanon(Talent $talent): bool
    {
        return $talent->appearance()->where('source_label', 'human')->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeAppearance(Talent $talent, array $payload, string $source, ?string $model): void
    {
        $attributes = [];

        foreach (array_keys(Taxonomy::singleAttributes()) as $field) {
            $attributes[$field] = $this->cleanScalar($payload[$field] ?? null);
        }

        $attributes['age_min'] = $this->cleanInt($payload['age_min'] ?? null);
        $attributes['age_max'] = $this->cleanInt($payload['age_max'] ?? null);
        $attributes['source_label'] = $source === 'human' ? 'human' : 'ai';
        $attributes['raw_analysis'] = $payload;
        $attributes['model_used'] = $model;
        $attributes['analyzed_at'] = now();

        $talent->appearance()->updateOrCreate(['talent_id' => $talent->id], $attributes);
    }

    /**
     * Associe les tags (vibe + signes distinctifs) présents dans le payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function syncTags(Talent $talent, array $payload): void
    {
        $slugs = [];

        foreach (Taxonomy::multiAttributes() as $field => $enum) {
            $values = is_array($payload[$field] ?? null) ? $payload[$field] : [];

            foreach ($values as $value) {
                if (in_array($value, $enum::values(), true)) {
                    $slugs[] = $value;
                }
            }
        }

        $tagIds = Tag::whereIn('slug', $slugs)->pluck('id')->all();
        $talent->tags()->sync($tagIds);
    }

    private function cleanScalar(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function cleanInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
