<?php

namespace App\Support;

/**
 * Glossaire pédagogique de la taxonomie : pour chaque attribut, une définition
 * claire (expert ou non), une justification quand c'est utile (ex. échelle
 * Fitzpatrick), et un indice par valeur. Sert d'aide contextuelle dans l'UI.
 */
class AttributeGlossary
{
    /**
     * Meta descriptive par attribut single.
     *
     * @return array<string, array{label: string, help: string, detail: string, note: ?string, values: array<string, string>}>
     */
    public static function single(): array
    {
        return [
            'genre' => [
                'label' => 'Genre perçu',
                'help' => 'Le genre que la personne donne à voir sur la photo.',
                'detail' => 'On note ce qu\'un directeur de casting perçoit à l\'image, pas une identité déclarée. En cas de doute, choisis « androgyne » ou « non binaire ».',
                'note' => 'Attribut perçu, subjectif — corrigé à la main.',
                'values' => [
                    'femme' => 'Présentation féminine.',
                    'homme' => 'Présentation masculine.',
                    'non_binaire' => 'Présentation ni clairement féminine ni masculine.',
                    'androgyne' => 'Traits volontairement ambigus, qui jouent sur les deux registres.',
                ],
            ],
            'type_percu' => [
                'label' => 'Type perçu',
                'help' => 'L\'origine visuelle telle qu\'elle est perçue à l\'écran.',
                'detail' => 'Un repère de casting pour matcher un brief (« look méditerranéen », « type scandinave »…), jamais une vérité sur la personne. Toujours subjectif et corrigé à la main.',
                'note' => 'Donnée sensible : perçue, jamais déclarative. Encadrée RGPD sur de vrais talents.',
                'values' => [
                    'europeen' => 'Europe de l\'Ouest / du Nord.',
                    'afro' => 'Afrique subsaharienne, diaspora afro.',
                    'maghrebin' => 'Afrique du Nord (Maroc, Algérie, Tunisie…).',
                    'moyen_oriental' => 'Moyen-Orient (Levant, Golfe, Iran…).',
                    'asiatique_est' => 'Asie de l\'Est (Chine, Corée, Japon…).',
                    'asiatique_sud' => 'Asie du Sud (Inde, Pakistan, Sri Lanka…).',
                    'latino' => 'Amérique latine.',
                    'metis' => 'Métissage visible entre plusieurs types.',
                    'autre' => 'Ne rentre dans aucune case ci-dessus.',
                ],
            ],
            'carnation' => [
                'label' => 'Carnation (phototype)',
                'help' => 'La couleur de peau, du plus clair au plus foncé.',
                'detail' => 'On utilise l\'échelle de Fitzpatrick, notée en chiffres romains I à VI. C\'est le standard de référence en dermatologie et en maquillage : I = peau la plus claire, VI = peau la plus foncée.',
                'note' => 'Échelle de Fitzpatrick — standard dermatologique reconnu.',
                'values' => [
                    'I' => 'Très claire. Brûle toujours au soleil, ne bronze jamais (souvent rousseur).',
                    'II' => 'Claire. Brûle facilement, bronze peu.',
                    'III' => 'Claire à mate. Brûle modérément, bronze progressivement.',
                    'IV' => 'Mate / olive. Brûle peu, bronze bien.',
                    'V' => 'Brun foncé. Brûle rarement.',
                    'VI' => 'Très foncée à noire. Ne brûle quasiment jamais.',
                ],
            ],
            'cheveux_couleur' => [
                'label' => 'Couleur de cheveux',
                'help' => 'La couleur dominante de la chevelure.',
                'detail' => 'La couleur visible telle quelle sur la photo (teinte naturelle ou colorée).',
                'note' => null,
                'values' => [
                    'brun' => 'Châtain foncé à brun.',
                    'chatain' => 'Entre le blond et le brun (marron clair).',
                    'blond' => 'Du blond clair au blond foncé.',
                    'roux' => 'Roux à auburn.',
                    'noir' => 'Noir profond.',
                    'gris_blanc' => 'Poivre & sel, gris ou blanc.',
                    'colore' => 'Teinture non naturelle (rose, bleu, platine marqué…).',
                    'rase' => 'Crâne rasé : couleur indéterminable.',
                ],
            ],
            'cheveux_longueur' => [
                'label' => 'Longueur de cheveux',
                'help' => 'La longueur de la chevelure.',
                'detail' => 'Repère visuel simple, mesuré par rapport au visage et aux épaules.',
                'note' => null,
                'values' => [
                    'rase' => 'Rasé ou quasi rasé.',
                    'court' => 'Au-dessus des oreilles.',
                    'mi_long' => 'Jusqu\'à la nuque / aux épaules.',
                    'long' => 'Sous les épaules.',
                    'tres_long' => 'Milieu du dos et au-delà.',
                ],
            ],
            'cheveux_texture' => [
                'label' => 'Texture de cheveux',
                'help' => 'La forme du cheveu : raide, ondulé, bouclé…',
                'detail' => 'De la fibre parfaitement lisse au crépu serré.',
                'note' => null,
                'values' => [
                    'raide' => 'Lisse, sans ondulation.',
                    'ondule' => 'Vagues souples.',
                    'boucle' => 'Boucles définies.',
                    'frise' => 'Frisottis serrés.',
                    'crepu' => 'Texture crépue, très serrée.',
                    'na' => 'Non applicable (ex. crâne rasé).',
                ],
            ],
            'yeux_couleur' => [
                'label' => 'Couleur des yeux',
                'help' => 'La couleur dominante de l\'iris.',
                'detail' => 'Si l\'iris mélange plusieurs teintes, choisis la dominante (souvent « noisette »).',
                'note' => null,
                'values' => [
                    'marron' => 'Brun uni.',
                    'noisette' => 'Mélange marron / vert / doré.',
                    'vert' => 'Vert.',
                    'bleu' => 'Bleu.',
                    'gris' => 'Gris.',
                    'ambre' => 'Jaune doré, cuivré.',
                ],
            ],
            'forme_visage' => [
                'label' => 'Forme du visage',
                'help' => 'La géométrie générale du visage.',
                'detail' => 'Repère utilisé pour orienter coupes, cadrages et lumière. On regarde le contour front / pommettes / mâchoire.',
                'note' => null,
                'values' => [
                    'ovale' => 'Équilibré, légèrement plus long que large.',
                    'rond' => 'Longueur et largeur proches, contours doux.',
                    'carre' => 'Mâchoire large et marquée.',
                    'rectangulaire' => 'Long, avec une mâchoire droite.',
                    'coeur' => 'Front large, menton fin.',
                    'diamant' => 'Pommettes larges, front et menton plus étroits.',
                    'triangulaire' => 'Mâchoire large, front étroit.',
                ],
            ],
            'pilosite' => [
                'label' => 'Pilosité faciale',
                'help' => 'La barbe / moustache visible.',
                'detail' => 'Ce qu\'on voit sur le visage, du menton glabre à la barbe fournie.',
                'note' => null,
                'values' => [
                    'glabre' => 'Imberbe, aucune pilosité.',
                    'barbe_naissante' => 'Repousse de quelques jours.',
                    'barbe_courte' => 'Barbe taillée, courte.',
                    'barbe_fournie' => 'Barbe longue et dense.',
                    'moustache' => 'Moustache seule.',
                    'bouc' => 'Pilosité concentrée sur le menton.',
                ],
            ],
            'expression' => [
                'label' => 'Expression',
                'help' => 'L\'émotion dominante du visage.',
                'detail' => 'L\'énergie qui se dégage à l\'instant de la photo — utile pour matcher le ton d\'un brief.',
                'note' => null,
                'values' => [
                    'sourire' => 'Sourire léger, bouche fermée ou entrouverte.',
                    'neutre' => 'Visage détendu, sans émotion marquée.',
                    'serieux' => 'Sérieux, concentré.',
                    'intense' => 'Regard marquant, magnétique.',
                    'doux' => 'Doux, avenant.',
                    'joyeux' => 'Rire franc, joie visible.',
                ],
            ],
            'morphologie' => [
                'label' => 'Morphologie',
                'help' => 'La silhouette générale.',
                'detail' => 'Mets « inconnu » quand la silhouette n\'est pas visible sur la photo (ex. portrait cadré serré sur le visage).',
                'note' => null,
                'values' => [
                    'mince' => 'Silhouette fine.',
                    'athletique' => 'Musclée, sportive.',
                    'moyen' => 'Silhouette moyenne.',
                    'rond' => 'Silhouette ronde.',
                    'plus_size' => 'Grande taille (plus-size).',
                    'inconnu' => 'Non visible sur la photo.',
                ],
            ],
        ];
    }

    /**
     * Meta descriptive par attribut multi (tags cumulables).
     *
     * @return array<string, array{label: string, help: string, detail: string, note: ?string, values: array<string, string>}>
     */
    public static function multi(): array
    {
        return [
            'signes_distinctifs' => [
                'label' => 'Signes distinctifs',
                'help' => 'Les détails visibles qui singularisent le visage.',
                'detail' => 'Coche tout ce qui est présent. Plusieurs choix possibles.',
                'note' => null,
                'values' => [
                    'taches_de_rousseur' => 'Éphélides sur le visage.',
                    'grain_de_beaute' => 'Grain de beauté marquant.',
                    'lunettes' => 'Porte des lunettes.',
                    'tatouages' => 'Tatouage(s) visible(s).',
                    'piercings' => 'Piercing(s) visible(s).',
                    'fossettes' => 'Fossettes aux joues.',
                    'dents_du_bonheur' => 'Écart entre les incisives.',
                    'cicatrice' => 'Cicatrice visible.',
                ],
            ],
            'vibe' => [
                'label' => 'Vibe',
                'help' => 'Le registre / l\'ambiance que dégage le profil.',
                'detail' => 'Comment tu « caserais » ce profil pour un brief. Plusieurs choix possibles.',
                'note' => null,
                'values' => [
                    'commercial' => 'Grand public, sourire accessible (pub, catalogue).',
                    'editorial' => 'Mode magazine, plus artistique et pointu.',
                    'luxe' => 'Haut de gamme, élégance premium.',
                    'streetwear' => 'Urbain, mode de rue.',
                    'corporate' => 'Business, institutionnel, rassurant.',
                    'naturel' => 'Spontané, peu apprêté, « vrai monde ».',
                    'edgy' => 'Audacieux, tranché, hors norme.',
                    'glamour' => 'Séduction, sophistication.',
                ],
            ],
        ];
    }

    /**
     * Payload complet pour le front : options + aide, dans l'ordre, single et multi.
     *
     * @return array{single: list<array<string, mixed>>, multi: list<array<string, mixed>>}
     */
    public static function forFrontend(): array
    {
        return [
            'single' => self::describe(Taxonomy::singleAttributes(), self::single()),
            'multi' => self::describe(Taxonomy::multiAttributes(), self::multi()),
        ];
    }

    /**
     * @param  array<string, class-string>  $attributes
     * @param  array<string, array<string, mixed>>  $glossary
     * @return list<array<string, mixed>>
     */
    private static function describe(array $attributes, array $glossary): array
    {
        $described = [];

        foreach ($attributes as $key => $enum) {
            $meta = $glossary[$key];

            $described[] = [
                'key' => $key,
                'label' => $meta['label'],
                'help' => $meta['help'],
                'detail' => $meta['detail'],
                'note' => $meta['note'],
                'options' => array_map(
                    static fn (array $opt): array => [
                        'value' => $opt['value'],
                        'label' => $opt['label'],
                        'hint' => $meta['values'][$opt['value']] ?? null,
                    ],
                    $enum::options(),
                ),
            ];
        }

        return $described;
    }
}
