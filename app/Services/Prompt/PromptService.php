<?php

namespace App\Services\Prompt;

use App\Enums\SigneDistinctif;
use App\Enums\Vibe;
use App\Models\Prompt;
use App\Support\Taxonomy;

/**
 * Source des prompts IA. Le texte est éditable en base ; la taxonomie reste
 * injectée à la volée via des placeholders, donc le vocabulaire est toujours à jour.
 */
class PromptService
{
    public const VISION = 'vision';

    public const PARSING = 'parsing';

    /**
     * Prompt d'analyse vision résolu (placeholders remplacés).
     */
    public function vision(): string
    {
        return $this->resolve($this->content(self::VISION));
    }

    public function visionVersion(): int
    {
        return Prompt::where('key', self::VISION)->value('version') ?? 1;
    }

    /**
     * Prompt de parsing de requête résolu.
     */
    public function parsing(): string
    {
        return $this->resolve($this->content(self::PARSING));
    }

    /**
     * Contenu brut (base si présent, sinon défaut).
     */
    public function content(string $key): string
    {
        return Prompt::where('key', $key)->value('content') ?? self::defaults()[$key]['content'];
    }

    /**
     * Remplace les placeholders par la taxonomie courante.
     */
    private function resolve(string $template): string
    {
        return str_replace(
            array_keys($this->placeholderValues()),
            array_values($this->placeholderValues()),
            $template,
        );
    }

    /**
     * Valeurs courantes injectées à la place de chaque placeholder.
     *
     * @return array<string, string>
     */
    public function placeholderValues(): array
    {
        return [
            '{{VOCABULAIRE}}' => Taxonomy::promptVocabulary(),
            '{{TAGS}}' => implode(' | ', [...Vibe::values(), ...SigneDistinctif::values()]),
        ];
    }

    /**
     * Prompts par défaut (servent de seed ET de filet si la base est vide).
     *
     * @return array<string, array{label: string, content: string}>
     */
    public static function defaults(): array
    {
        return [
            self::VISION => [
                'label' => 'Analyse vision (portrait-robot + description)',
                'content' => <<<'PROMPT'
                Tu es un directeur de casting. On te donne une photo de portrait.
                Décris la personne de façon factuelle et professionnelle pour un catalogue de mannequins.

                Retourne UNIQUEMENT un objet JSON valide, sans texte autour, avec ces clés :
                genre, age_min, age_max, type_percu, carnation, cheveux_couleur, cheveux_longueur,
                cheveux_texture, yeux_couleur, forme_visage, pilosite, expression, morphologie,
                signes_distinctifs (liste), vibe (liste), description_fr.

                Les valeurs AUTORISÉES, tu ne dois JAMAIS en sortir :
                {{VOCABULAIRE}}

                Règles :
                - age_min / age_max : entiers, âge perçu (fourchette).
                - type_percu, carnation, age sont PERÇUS (jugement visuel), pas déclaratifs.
                - description_fr : un paragraphe fluide en français, 3 à 5 phrases, comme une fiche de book.
                - Si un attribut n'est pas déterminable, mets null (ou [] pour les listes).
                - N'invente jamais. Base-toi uniquement sur ce qui est visible.
                PROMPT,
            ],
            self::PARSING => [
                'label' => 'Parsing de requête (recherche → filtres)',
                'content' => <<<'PROMPT'
                Tu convertis une demande de casting en français en un objet JSON de filtres.

                Retourne UNIQUEMENT ce JSON :
                {
                  "genre": null,
                  "age_min": null, "age_max": null,
                  "type_percu": [],
                  "carnation": null,
                  "cheveux_couleur": [],
                  "cheveux_longueur": null,
                  "cheveux_texture": null,
                  "yeux_couleur": null,
                  "forme_visage": null,
                  "pilosite": null,
                  "morphologie": null,
                  "tags_requis": [],
                  "tags_exclus": [],
                  "durete": "souple",
                  "semantic_text": ""
                }

                Valeurs AUTORISÉES pour les attributs (n'en invente aucune) :
                {{VOCABULAIRE}}

                Tags autorisés (tags_requis / tags_exclus) : {{TAGS}}

                Règles :
                - Utilise des listes quand la demande est ambiguë ou couvre plusieurs valeurs.
                - Mets dans semantic_text tout ce qui ne rentre pas dans un attribut structuré
                  (ambiance, style, énergie, contexte d'usage). C'est ce qu'on embed.
                - durete = "souple" par défaut, "stricte" seulement si la demande est catégorique.
                - Ne mets QUE des valeurs de la liste autorisée, sinon null / [].
                PROMPT,
            ],
        ];
    }
}
