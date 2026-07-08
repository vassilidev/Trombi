# PRD - Casting IA (POC / MVP)

Moteur de recherche de talents en langage naturel pour agence de mannequins / castings.

Version 1.2 - finalisé (prêt pour build)

---

## 1. Contexte et objectif

Aujourd'hui tu reçois des demandes de casting (prompt, PDF, brief texte) décrivant le profil recherché, et tu dois retrouver à la main la personne idéale dans ta base. L'objectif du POC est de valider qu'on peut :

1. Analyser automatiquement chaque photo de talent pour en extraire un **portrait robot structuré** (attributs typés) + une **description naturelle**.
2. Laisser un utilisateur **taper son besoin en français libre** (comme un message ChatGPT, sans filtre, sans recherche avancée) et **retrouver le meilleur profil** dans la base.

Le POC utilise des visages synthétiques (thispersondoesnotexist.com) pour peupler la base sans problème de droit ni de RGPD. L'archi est pensée pour passer sur de vrais talents ensuite sans refonte.

### Critère de succès du POC

Sur un set de quelques centaines de visages importés, une requête en français comme "je cherche une femme la trentaine, cheveux bruns, air méditerranéen, sourire naturel, plutôt pub que haute couture" doit remonter dans le top 3 un profil que toi tu jugerais pertinent, de façon reproductible.

### Hors scope du POC (assumé)

- **Reverse / portrait robot / image générative** : préparé par le schéma, mais non construit (cf. section 8).
- **Authentification / multi-utilisateurs** : le POC tourne en accès unique / interne. À ajouter au MVP.
- **Outillage RGPD** (consentement, registre) : inutile sur des visages synthétiques, à cadrer avant les vrais talents (cf. section 9).

---

## 2. Concept produit

Deux flux distincts.

### Flux A - Ingestion (offline, tu le lances pour peupler la base)

```
photo -> analyse vision (OpenRouter) -> JSON structuré + description FR
      -> embedding de la description -> insertion en base
```

Précédé d'une phase de **calibration** (section 6) où tu annotes une poignée d'images à la main avant de lâcher l'automatique.

### Flux B - Recherche (online, ce que voit l'utilisateur)

```
requête FR libre
   -> parsing IA en 2 morceaux :
        (a) contraintes dures -> DTO de filtres (vocabulaire contraint à la DB)
        (b) reste du besoin -> texte sémantique
   -> pré-filtre SQL sur les attributs (a)
   -> ranking vectoriel (pgvector) sur (b)
   -> fusion -> top K résultats
```

L'UI est volontairement minimaliste : un champ de saisie type chat, une liste de résultats avec photo + description + score.

---

## 3. Stack et architecture

| Brique | Choix | Pourquoi |
|---|---|---|
| Backend | Laravel | Ta stack de référence, DDD friendly |
| Front | Vue + Inertia | Idem, et le champ de recherche est trivial |
| Base | PostgreSQL 16 + extension `pgvector` | Vecteurs et données relationnelles dans une seule DB, zéro infra en plus |
| IA | OpenRouter (une seule clé) | Vision, embeddings, parsing requête, et image gen plus tard, tout via un endpoint compatible OpenAI |

### OpenRouter, ce qu'on utilise

Base URL : `https://openrouter.ai/api/v1` (compatible OpenAI, on peut brancher le SDK OpenAI ou juste le client HTTP de Laravel).

| Usage | Endpoint | Modèle suggéré (interchangeable) |
|---|---|---|
| Analyse vision | `/chat/completions` (message avec image) | `openai/gpt-4o` (défaut POC, swappable) |
| Embeddings | `/embeddings` | `openai/text-embedding-3-small` (1536 dims) |
| Parsing requête | `/chat/completions` | `openai/gpt-4o-mini` ou `google/gemini-2.5-flash` (rapide et cheap) |
| Image gen (v2, reverse) | `/chat/completions` (modalité image) | `google/gemini-2.5-flash-image` |

Choix du modèle vision : par défaut `gpt-4o` pour la qualité d'extraction, notamment sur le set gold qui sert de référence. La calibration (section 6) permet de comparer, sur tes propres images labellisées, le taux d'accord de `gpt-4o` et de `gemini-2.5-flash` (bien moins cher). Si flash tient sur les attributs qui pilotent le filtre, on bascule le run de masse dessus (un seul paramètre à changer). Le modèle se choisit donc sur preuve, pas au feeling.

Un seul modèle d'embedding pour l'ingestion ET la recherche, sinon les vecteurs ne sont pas comparables. Si tu changes de modèle d'embedding un jour, il faut re-embedder toute la base.

Headers utiles sur chaque appel :
```
Authorization: Bearer <OPENROUTER_API_KEY>
HTTP-Referer: <url de ton app>   (optionnel, pour les stats OpenRouter)
X-Title: Casting IA             (optionnel)
```

---

## 4. Modèle de données (schéma complet)

### 4.1 Vue d'ensemble des relations

```
talents (1) ------ (N) talent_photos
talents (1) ------ (1) talent_appearances     [le portrait robot structuré retenu]
talents (1) ------ (1) talent_profiles        [description FR + embedding]
talents (1) ------ (N) annotations            [labels humains ET IA, pour la calibration]
talents (N) ------ (N) tags   via talent_tag  [vibe, signes distinctifs, catégories]

briefs (1) ------ (N) brief_matches (N) ------ (1) talents

benchmark_runs (1) ------ (N) benchmark_results (N) ------ (1) talents  [compare N modèles vs gold]
```

### 4.2 Principe : deux natures d'attributs

Le portrait robot mélange deux types de traits, et on les stocke différemment parce que l'IA de recherche les traite différemment :

- **Attributs mutuellement exclusifs et à faible cardinalité** (genre, couleur des yeux, forme du visage...) : colonnes **enum** typées. Un talent a une seule valeur. Servent au pré-filtre SQL.
- **Attributs cumulables et ouverts** (vibe, signes distinctifs, catégories casting...) : **tags** many-to-many. Un talent en a plusieurs. Servent au filtrage secondaire et à l'affinage.

Tout ce que l'IA a produit et qu'on ne modélise pas encore reste dans un `raw_analysis` JSONB, donc on ne perd jamais rien et on peut enrichir le schéma après coup.

`talent_appearances` contient les valeurs **retenues** (canoniques) : issues de l'IA en masse, ou du label humain pour le set de calibration (le label humain prime quand il existe).

### 4.3 DDL PostgreSQL

```sql
-- Extension vecteurs
CREATE EXTENSION IF NOT EXISTS vector;

-- ---------------------------------------------------------------------------
-- Le talent (la personne)
-- ---------------------------------------------------------------------------
CREATE TABLE talents (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code         VARCHAR(32) UNIQUE NOT NULL,          -- ex: TAL-0001, lisible
    source       VARCHAR(64) NOT NULL DEFAULT 'tpdne', -- tpdne = thispersondoesnotexist, 'real' plus tard
    is_gold      BOOLEAN NOT NULL DEFAULT FALSE,        -- fait partie du set annoté à la main
    is_active    BOOLEAN NOT NULL DEFAULT TRUE,
    image_hash   CHAR(64),                              -- sha256 de l'image, pour dédupe
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX idx_talents_hash ON talents(image_hash);

-- ---------------------------------------------------------------------------
-- Les photos
-- ---------------------------------------------------------------------------
CREATE TABLE talent_photos (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    talent_id    BIGINT NOT NULL REFERENCES talents(id) ON DELETE CASCADE,
    path         VARCHAR(512) NOT NULL,   -- storage local ou S3
    is_primary   BOOLEAN NOT NULL DEFAULT FALSE,
    width        INT,
    height       INT,
    source       VARCHAR(64) NOT NULL DEFAULT 'tpdne',
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_photos_talent ON talent_photos(talent_id);

-- ---------------------------------------------------------------------------
-- Le portrait robot structuré retenu (attributs enum, 1 ligne par talent)
-- ---------------------------------------------------------------------------
CREATE TABLE talent_appearances (
    talent_id        BIGINT PRIMARY KEY REFERENCES talents(id) ON DELETE CASCADE,

    -- identité perçue
    genre            VARCHAR(16),   -- femme | homme | non_binaire | androgyne
    age_min          SMALLINT,      -- tranche d'âge perçue, borne basse
    age_max          SMALLINT,      -- borne haute
    type_percu       VARCHAR(32),   -- europeen | afro | maghrebin | moyen_oriental
                                    -- asiatique_est | asiatique_sud | latino | metis | autre
    carnation        VARCHAR(16),   -- phototype: I | II | III | IV | V | VI

    -- cheveux
    cheveux_couleur  VARCHAR(16),   -- brun | chatain | blond | roux | noir | gris_blanc | colore | rase
    cheveux_longueur VARCHAR(16),   -- rase | court | mi_long | long | tres_long
    cheveux_texture  VARCHAR(16),   -- raide | ondule | boucle | frise | crepu | na

    -- visage
    yeux_couleur     VARCHAR(16),   -- marron | noisette | vert | bleu | gris | ambre
    forme_visage     VARCHAR(16),   -- ovale | rond | carre | rectangulaire | coeur | diamant | triangulaire
    pilosite         VARCHAR(24),   -- glabre | barbe_naissante | barbe_courte | barbe_fournie | moustache | bouc
    expression       VARCHAR(16),   -- sourire | neutre | serieux | intense | doux | joyeux

    -- corps (souvent null en POC car TPDNE ne donne que des visages)
    morphologie      VARCHAR(16),   -- mince | athletique | moyen | rond | plus_size | inconnu

    source_label     VARCHAR(8) NOT NULL DEFAULT 'ai',  -- 'ai' ou 'human'
    raw_analysis     JSONB,         -- sortie IA brute complète, filet de sécurité
    model_used       VARCHAR(64),   -- modèle vision utilisé
    analyzed_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Index sur les colonnes les plus filtrées
CREATE INDEX idx_app_genre   ON talent_appearances(genre);
CREATE INDEX idx_app_type    ON talent_appearances(type_percu);
CREATE INDEX idx_app_cheveux ON talent_appearances(cheveux_couleur);
CREATE INDEX idx_app_yeux    ON talent_appearances(yeux_couleur);

-- ---------------------------------------------------------------------------
-- Annotations : labels humains ET IA (sert à la calibration et à l'eval)
-- ---------------------------------------------------------------------------
CREATE TABLE annotations (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    talent_id    BIGINT NOT NULL REFERENCES talents(id) ON DELETE CASCADE,
    source       VARCHAR(8) NOT NULL,      -- 'human' | 'ai'
    annotator    VARCHAR(64),              -- 'vassili' ou le nom du modèle IA
    payload      JSONB NOT NULL,           -- le jeu d'attributs complet (même forme que la sortie vision)
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_annotations_talent ON annotations(talent_id);
CREATE INDEX idx_annotations_source ON annotations(source);

-- ---------------------------------------------------------------------------
-- La description IA + le vecteur
-- ---------------------------------------------------------------------------
CREATE TABLE talent_profiles (
    talent_id            BIGINT PRIMARY KEY REFERENCES talents(id) ON DELETE CASCADE,
    description_fr       TEXT NOT NULL,          -- paragraphe canonique en français
    searchable_text      TEXT NOT NULL,          -- description + tags concaténés (ce qu'on embed)
    description_embedding vector(1536),          -- text-embedding-3-small
    model_used           VARCHAR(64),
    embedded_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Index vectoriel HNSW, distance cosinus (à créer une fois les données peuplées)
CREATE INDEX idx_profiles_embedding
    ON talent_profiles
    USING hnsw (description_embedding vector_cosine_ops);

-- ---------------------------------------------------------------------------
-- Tags (vibe, signes distinctifs, catégories casting) - cumulables
-- ---------------------------------------------------------------------------
CREATE TABLE tags (
    id       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug     VARCHAR(64) UNIQUE NOT NULL,   -- ex: taches_de_rousseur, editorial, streetwear
    label    VARCHAR(128) NOT NULL,         -- libellé lisible
    famille  VARCHAR(32) NOT NULL           -- vibe | signe_distinctif | categorie
);

CREATE TABLE talent_tag (
    talent_id BIGINT NOT NULL REFERENCES talents(id) ON DELETE CASCADE,
    tag_id    BIGINT NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (talent_id, tag_id)
);

-- ---------------------------------------------------------------------------
-- Les briefs entrants (le prompt/PDF qu'on te confie) + le matching loggé
-- ---------------------------------------------------------------------------
CREATE TABLE briefs (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    raw_text          TEXT NOT NULL,        -- le besoin brut (saisi ou extrait d'un PDF)
    source_kind       VARCHAR(16) NOT NULL DEFAULT 'chat', -- chat | pdf | prompt
    parsed_filters    JSONB,                -- le DTO de filtres extrait par l'IA
    semantic_text     TEXT,                 -- la partie sémantique de la requête
    query_embedding   vector(1536),
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE brief_matches (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    brief_id   BIGINT NOT NULL REFERENCES briefs(id) ON DELETE CASCADE,
    talent_id  BIGINT NOT NULL REFERENCES talents(id) ON DELETE CASCADE,
    score      REAL NOT NULL,      -- score final (0..1)
    rank       SMALLINT NOT NULL,  -- position dans les résultats
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_matches_brief ON brief_matches(brief_id);

-- ---------------------------------------------------------------------------
-- Benchmark : comparer plusieurs modèles sur le même prompt, vs le gold
-- ---------------------------------------------------------------------------
CREATE TABLE benchmark_runs (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    label          VARCHAR(128),
    prompt_version VARCHAR(32),          -- pour tracer quel prompt a servi
    models         JSONB NOT NULL,       -- liste des modèles testés
    gold_count     SMALLINT NOT NULL,    -- nb d'images du gold set évaluées
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE benchmark_results (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    run_id             BIGINT NOT NULL REFERENCES benchmark_runs(id) ON DELETE CASCADE,
    talent_id          BIGINT NOT NULL REFERENCES talents(id) ON DELETE CASCADE,
    model              VARCHAR(64) NOT NULL,
    payload            JSONB,             -- la sortie du modèle
    is_valid_json      BOOLEAN NOT NULL,  -- PROPRETÉ: JSON bien formé + valeurs dans la taxonomie
    agreement_score    REAL,              -- JUSTESSE globale vs gold (0..1)
    per_field_result   JSONB,             -- { attribut: true|false } vs gold, pour la vue delta
    latency_ms         INT,
    cost_usd           NUMERIC(10,6),
    prompt_tokens      INT,
    completion_tokens  INT,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_bench_results_run   ON benchmark_results(run_id);
CREATE INDEX idx_bench_results_model ON benchmark_results(model);
```

> Note Laravel : les colonnes `vector(1536)` et l'index HNSW ne passent pas par le query builder standard. Tu déclares ces colonnes via `DB::statement(...)` dans tes migrations, ou tu utilises un package pgvector pour Laravel. Le reste des tables se fait en migrations classiques.

> Note perf : crée l'index HNSW **après** avoir inséré le gros du seed, c'est plus rapide à construire.

### 4.4 La taxonomie canonique (le "DTO" partagé)

C'est la pièce centrale de ton intuition sur le matching. Toutes les valeurs autorisées (enums + slugs de tags) vivent dans **une seule source de vérité** côté code, par exemple des enums PHP :

```php
enum Genre: string {
    case Femme = 'femme';
    case Homme = 'homme';
    case NonBinaire = 'non_binaire';
    case Androgyne = 'androgyne';
}
// idem: TypePercu, Carnation, CheveuxCouleur, CheveuxLongueur,
//       CheveuxTexture, YeuxCouleur, FormeVisage, Pilosite, Expression, Morphologie
```

Cette taxonomie sert à trois endroits :
1. À l'annotation manuelle : les selects de l'interface de calibration sont peuplés depuis ces enums (zéro saisie libre).
2. À l'ingestion IA : on impose au modèle vision de ne remplir les attributs qu'avec ces valeurs.
3. À la recherche : on injecte cette même liste dans le prompt de parsing, donc l'IA **ne peut extraire que des filtres qui existent réellement en base**.

C'est ça, ton "IA qui met les tags via le DTO de la DB" : le vocabulaire de la base est contractuel et injecté partout.

---

## 5. Pipeline d'ingestion

### 5.1 Import des visages (multi-source, N libre)

Le nombre est libre : 200 n'est qu'un exemple. On doit pouvoir importer un lot à la demande, depuis deux sources au choix, et le qualifier tout de suite si on veut (sinon l'analyse se fera plus tard, en masse). Import et analyse sont découplés.

**Source A - Upload depuis l'ordi.** Drag and drop d'un ou plusieurs fichiers (ou d'un dossier) dans une zone d'import. Utile pour importer tes propres photos, pas seulement des visages synthétiques.

**Source B - API de visages.** Un bouton "récupérer N visages" qui boucle sur `GET https://thispersondoesnotexist.com/`, avec N saisi dans un champ (pas figé). Chaque appel renvoie un nouveau visage.

Pour toute image importée, quelle que soit la source :
1. Calcul du **sha256** de l'image. Si le hash existe déjà (`talents.image_hash`), on skip (dédupe).
2. Sauvegarde de l'image (storage local ou S3) + insertion `talents` (avec le hash) et `talent_photos`.
3. Option **"qualifier maintenant"** : on enchaîne directement sur l'écran de qualification (section 6). Sinon l'image reste en attente d'analyse.

Contraintes propres à la source API :
- **Délai + jitter entre les hits** (1 à 2s). Le site régénère l'image à intervalle régulier ; sans délai tu récupères plusieurs fois le même visage.
- Retry avec backoff en cas d'erreur réseau.

> La sandbox de dev où ce document a été produit ne peut pas joindre thispersondoesnotexist (domaine non autorisé). Le pull API tourne donc chez toi en local ; l'upload local, lui, marche partout.

### 5.2 Analyse d'un visage (par talent)

Sur une image donnée, le modèle vision (OpenRouter) fait deux choses en un appel : extraire les attributs (portrait robot) et rédiger la description FR.

1. Appel `/chat/completions` avec l'image + le prompt d'analyse ci-dessous. Récupérer le JSON.
2. Valider le JSON contre la taxonomie (toute valeur inconnue -> null + log).
3. Écrire une ligne dans `annotations` (source = 'ai').
4. Écrire les valeurs retenues dans `talent_appearances` (source_label = 'ai') + les tags dans `talent_tag`.
5. Construire `searchable_text` = `description_fr` + libellés des tags, l'embedder via `/embeddings`, écrire `talent_profiles`.

### 5.3 Prompt système d'analyse vision

À adapter, mais l'idée :

```
Tu es un directeur de casting. On te donne une photo de portrait.
Décris la personne de façon factuelle et professionnelle pour un catalogue de mannequins.

Retourne UNIQUEMENT un objet JSON valide, sans texte autour, avec exactement cette forme :

{
  "genre": "<une valeur parmi: femme, homme, non_binaire, androgyne>",
  "age_min": <entier, âge perçu borne basse>,
  "age_max": <entier, âge perçu borne haute>,
  "type_percu": "<europeen|afro|maghrebin|moyen_oriental|asiatique_est|asiatique_sud|latino|metis|autre>",
  "carnation": "<I|II|III|IV|V|VI>",
  "cheveux_couleur": "<brun|chatain|blond|roux|noir|gris_blanc|colore|rase>",
  "cheveux_longueur": "<rase|court|mi_long|long|tres_long>",
  "cheveux_texture": "<raide|ondule|boucle|frise|crepu|na>",
  "yeux_couleur": "<marron|noisette|vert|bleu|gris|ambre>",
  "forme_visage": "<ovale|rond|carre|rectangulaire|coeur|diamant|triangulaire>",
  "pilosite": "<glabre|barbe_naissante|barbe_courte|barbe_fournie|moustache|bouc>",
  "expression": "<sourire|neutre|serieux|intense|doux|joyeux>",
  "morphologie": "inconnu",
  "signes_distinctifs": [<parmi: taches_de_rousseur, grain_de_beaute, lunettes, tatouages, piercings, fossettes, dents_du_bonheur, cicatrice>],
  "vibe": [<parmi: commercial, editorial, luxe, streetwear, corporate, naturel, edgy, glamour>],
  "description_fr": "<un paragraphe fluide en français, 3 à 5 phrases, décrivant l'allure générale, le style, l'énergie, comme une fiche de book>"
}

Si un attribut n'est pas déterminable, mets null (ou [] pour les listes).
N'invente jamais. Base-toi uniquement sur ce qui est visible.
```

Le `type_percu`, la `carnation` et l'`age` sont explicitement **perçus** (jugement visuel), pas déclaratifs.

Robustesse : parse toujours défensivement (strip d'éventuels backticks markdown, `json_decode`, et si ça casse tu logges le talent et tu continues).

---

## 6. Calibration : qualification manuelle, diff IA vs humain, amélioration continue

C'est la brique que tu tiens à avoir, et elle est au centre de la démarche. Rappel : on ne fine-tune aucun modèle. On qualifie à la main, on compare la sortie de l'IA à la tienne, et on améliore le prompt et la taxonomie en boucle. Le levier, c'est ça, pas des poids de modèle.

Le cycle complet :

```
import image -> qualification manuelle (toi) -> analyse IA (même image)
   -> diff JSON manuel vs IA -> taux d'accord par attribut
   -> ajustement prompt / taxonomie / few-shot -> l'accord monte
   -> quand c'est bon : analyse auto du reste
```

### 6.1 L'écran de qualification manuelle (ultra simple)

Objectif : que tu renseignes à la main, très vite, tout ce que tu vois sur une image.

Layout :
- **Gauche** : l'image en grand.
- **Droite** : un formulaire listant TOUTES les taxonomies, un champ par attribut.
  - Attributs à valeur unique (genre, cheveux_couleur, yeux, forme_visage...) : un combobox **avec barre de recherche** (tu tapes "rou", il propose "roux", tu valides). Zéro saisie libre, uniquement des valeurs de la taxonomie.
  - Attributs multi-valeurs (signes_distinctifs, vibe) : multi-select, même recherche.
  - Âge : deux petits champs min / max.
- **Barre de recherche globale** en haut : tu tapes n'importe quelle valeur de n'importe quelle taxonomie (ex: "fossettes", "editorial", "bleu") et elle se pose directement sur le bon attribut. C'est le mode le plus rapide pour annoter au clavier sans chercher le champ.
- Raccourcis clavier + bouton "image suivante" pour enchaîner un lot de 15-20 sans lâcher le clavier.

À la sauvegarde : le talent passe `is_gold = true`, l'annotation est écrite dans `annotations` (source = 'human', annotator = toi), et reportée comme valeurs retenues dans `talent_appearances` (source_label = 'human'). Ta saisie fait référence.

### 6.2 L'analyse IA + la diff JSON (manuel vs IA)

Une fois une image qualifiée à la main, on lance l'analyse IA sur cette même image, et on affiche une **diff JSON côte à côte** :

- Colonne gauche : ton JSON (manuel). Colonne droite : le JSON de l'IA.
- Par champ : **vert = identique**, **rouge = divergent**, avec les deux valeurs affichées.
- Un bandeau récap : X champs sur N en accord.

C'est exactement ta "diff du json entre manuel et IA". Tu vois d'un coup d'œil où l'IA se plante (ex: elle dit "chatain" quand tu dis "brun", ou "35-45" quand tu dis "28-35").

Agrégé sur le set gold, ça donne un **taux d'accord par attribut** (petit tableau de bord) : le % d'images où IA = toi, champ par champ. C'est ta boussole pour savoir quels attributs sont fiables.

Mesure par type de champ :
- **Enums** : match exact.
- **Âge** : accord si les fourchettes se chevauchent (ou écart des bornes sous un seuil, ex: 5 ans).
- **Listes (tags, vibe)** : indice de Jaccard entre les deux listes.

### 6.3 La boucle d'amélioration continue

À partir des divergences, trois leviers, du plus simple au plus fort, tous sans fine-tuning :

1. **Affiner le prompt et la taxonomie.** Si l'IA confond systématiquement deux valeurs, soit tu précises la définition dans le prompt ("entre blond foncé et brun clair, classe en chatain"), soit tu fusionnes les deux valeurs si la distinction n'est pas exploitable. Tu relances, l'accord monte.

2. **Few-shot depuis tes corrections (le vrai "apprend de moi").** On injecte dans le prompt d'analyse une poignée de tes exemples corrigés (image + le bon JSON, le tien, tirés directement de la table `annotations` où source = 'human'). Le modèle s'aligne sur TON jugement sans qu'on touche à ses poids. Plus tu qualifies d'images, meilleur est le pool d'exemples de référence, plus l'analyse auto colle à ta façon de voir. C'est ça, l'amélioration continue, concrètement.

3. **Suivi dans le temps.** Le taux d'accord est recalculable à tout moment depuis les `annotations`. Quand tu ajoutes de nouvelles images qualifiées, ou quand tu changes de modèle, tu vois tout de suite si l'accord monte ou baisse.

Le cycle est réouvrable en permanence : à tout moment tu qualifies de nouvelles images, tu regardes la diff, tu ajustes, tu relances. Rien n'est figé.

Attendu réaliste : les attributs subjectifs (âge, type perçu, carnation) plafonneront plus bas, c'est normal. Le but n'est pas 100%, c'est de **savoir quels attributs sont assez fiables pour rester en filtre dur**, et lesquels valent mieux comme simple signal sémantique.

### 6.4 Page benchmark : comparer plusieurs modèles sur ton gold

La généralisation de la diff à N modèles. Même prompt, plusieurs modèles, sur ton set qualifié à la main, pour trancher sur preuve.

**Ce que tu lances** : tu choisis un gold set (tes images labellisées), une short-list de modèles à comparer (`gpt-4o`, `gemini-2.5-flash`, plus n'importe quel modèle vision d'OpenRouter), et la version du prompt. On envoie le **même prompt** à chaque combinaison (image × modèle).

**Ce qu'on capture par appel** :
- le JSON de sortie,
- la **latence** (ms),
- le **coût** (récupéré via OpenRouter : les stats de génération renvoient le coût réel de l'appel ; à défaut, tokens × tarif du modèle),
- la **validité** du JSON (bien formé + toutes les valeurs dans la taxonomie).

Puis on **score chaque sortie contre ton JSON manuel** (le gold).

**Ce que la page affiche** :
1. **Tableau récap**, une ligne par modèle : taux de justesse global vs gold, justesse par attribut clé, latence moyenne (p50 / p95), coût total et coût par image, % de JSON valides.
2. **Suggestion "meilleur rapport justesse / coût"** pour t'aider à trancher (le plus juste n'est pas toujours le plus cher).
3. **Vue delta par image** : ton JSON à gauche, chaque modèle à côté, champ par champ, **vert = juste, rouge = faux** (avec ta valeur ET celle du modèle affichées). C'est exactement ce que tu veux : dans le delta, ce qui est propre et vrai vs ce qui est faux et pas bon.
4. **Vue delta par attribut** : sur quel champ chaque modèle se plante le plus. Si tous ratent la carnation, c'est le signal pour ajouter le calcul déterministe (ITA, cf. annexe signaux déterministes) ou reformuler le prompt.

**Deux dimensions à ne pas confondre**, et la page les sépare :
- **Propreté** (le "bien propre") : le JSON est-il valide, complet, avec uniquement des valeurs autorisées ? Un modèle qui invente une valeur hors taxonomie ou casse le JSON est pénalisé, même si le reste "a l'air" bon.
- **Justesse** (le "vrai / faux") : la valeur colle-t-elle à ton label manuel ?

**Réutilisable en permanence** : tu relances le benchmark après chaque changement de prompt ou de taxonomie pour vérifier que tu progresses, et à chaque évolution de ta short-list de modèles. C'est ton outil de décision continu, pas un one-shot. Les runs sont historisés (`benchmark_runs` / `benchmark_results`), donc tu peux comparer deux versions de prompt dans le temps.



### 7.1 Entrée du brief (chat ou PDF)

Le besoin arrive soit tapé dans le champ de recherche, soit sous forme de PDF/prompt qu'on te confie. Pour un PDF, on extrait le texte (pdftotext, une lib PHP, ou en envoyant le PDF à un modèle vision-capable) et on le stocke dans `briefs.raw_text` avec `source_kind = 'pdf'`. À partir de là, le traitement est identique.

### 7.2 Étape 1 - Parsing de la requête -> DTO de filtres

On envoie la requête FR + la taxonomie complète au LLM, qui renvoie ce DTO :

```json
{
  "genre": "femme",
  "age_min": 28,
  "age_max": 38,
  "type_percu": ["europeen", "latino"],
  "carnation": null,
  "cheveux_couleur": ["brun", "chatain"],
  "cheveux_longueur": null,
  "cheveux_texture": null,
  "yeux_couleur": null,
  "forme_visage": null,
  "pilosite": null,
  "tags_requis": ["naturel"],
  "tags_exclus": ["luxe"],
  "durete": "souple",
  "semantic_text": "air méditerranéen, sourire naturel, plutôt pub que haute couture"
}
```

Points clés :
- Les tableaux gèrent l'ambiguïté ("méditerranéen" peut couvrir `europeen` + `latino`).
- `semantic_text` = tout ce qui ne rentre pas dans un attribut structuré. C'est ce qu'on embed.
- `durete` (`stricte` ou `souple`) : indique si les filtres sont bloquants ou juste préférés. Par défaut `souple` pour éviter les résultats vides.

### 7.3 Étape 2 - Pré-filtre SQL

On applique les contraintes dures sur `talent_appearances` et `talent_tag` :

```sql
SELECT p.talent_id, p.description_embedding
FROM talent_profiles p
JOIN talent_appearances a ON a.talent_id = p.talent_id
WHERE a.genre = 'femme'
  AND a.age_max >= 28 AND a.age_min <= 38
  AND a.type_percu = ANY(ARRAY['europeen','latino'])
  AND a.cheveux_couleur = ANY(ARRAY['brun','chatain'])
  AND p.talent_id IN (SELECT talent_id FROM talent_tag tt
                      JOIN tags t ON t.id = tt.tag_id
                      WHERE t.slug = 'naturel')
  AND p.talent_id NOT IN (SELECT talent_id FROM talent_tag tt
                          JOIN tags t ON t.id = tt.tag_id
                          WHERE t.slug = 'luxe');
```

### 7.4 Étape 3 - Ranking vectoriel

On embed `semantic_text` et on classe les candidats pré-filtrés par distance cosinus :

```sql
SELECT talent_id,
       1 - (description_embedding <=> :query_vector) AS similarite
FROM talent_profiles
WHERE talent_id = ANY(:candidats)
ORDER BY description_embedding <=> :query_vector
LIMIT 10;
```

(`<=>` = distance cosinus en pgvector, `1 - distance` = similarité en 0..1.)

### 7.5 Étape 4 - Fusion, scoring et fallback

- **Score final** : pour le POC, la similarité vectorielle des candidats pré-filtrés suffit. Affinage possible : bonus si un tag préféré est présent.
- **Fallback anti-résultat-vide** (crucial) : si `durete = souple` et que le pré-filtre renvoie moins de K résultats, on relâche les contraintes une à une (d'abord les moins importantes), ou on bascule en sémantique pur sur toute la requête. L'utilisateur a toujours des propositions.
- **Log** : on écrit le brief dans `briefs` et les résultats dans `brief_matches`. Historique + mesure de qualité dans le temps.

---

## 8. Le "reverse" / portrait robot (HORS SCOPE POC, préparé)

Non construit pour le POC. Le schéma le supporte déjà, trois options le jour venu, sans migration :

- **Fiche texte** : reconstruire une description lisible depuis `talent_appearances` (templating, pas d'IA).
- **Image regénérée** : envoyer les attributs à un modèle image (`gemini-2.5-flash-image` via OpenRouter) pour un visage représentatif d'un "type" recherché.
- **Visu attributs** : rendu UI (radar, tags) depuis les colonnes.

On stocke attributs structurés + description canonique + `raw_analysis`, donc toute la matière est là.

---

## 9. RGPD et données sensibles (version réelle, pas le POC)

En POC, visages synthétiques = aucun sujet. Dès qu'on passe sur de vrais mannequins :

- Photo + `type_percu` + `carnation` peuvent constituer des données personnelles, potentiellement sensibles voire biométriques selon l'usage.
- À prévoir alors : base légale (consentement dans le contrat de mannequinat), registre de traitement, minimisation, durée de conservation, et assumer que ces attributs sont "perçus".

Rien de bloquant maintenant, mais autant concevoir en connaissance de cause.

---

## 10. Plan de build par phases

| Phase | Contenu | Objectif |
|---|---|---|
| 0 | Setup : Postgres + pgvector, clé OpenRouter, migrations, enums PHP | Socle |
| 1 | Import multi-source (upload local + pull API, N libre), dédupe hash, storage | Peupler les images |
| 2 | Écran de qualification manuelle (toutes taxonomies + barre de recherche) + annotation d'un set gold | Figer les critères, créer la vérité terrain |
| 3 | Analyse IA du set gold + diff JSON manuel vs IA + taux d'accord + boucle prompt/taxonomie/few-shot | Calibrer et améliorer en continu |
| 3b | Page benchmark multi-modèles (délai / coût / justesse vs gold, vue delta) | Choisir le modèle sur preuve |
| 4 | Analyse IA automatique du reste + embeddings | Base complète |
| 5 | Moteur hybride complet : parsing requête -> DTO + pré-filtre SQL + ranking vectoriel + fallback + logging | Matching de bout en bout |
| 6 | UI de recherche : champ chat + liste de résultats (photo / description / score) | Front utilisable |
| 7 (plus tard) | Reverse / portrait robot | Feature bonus, hors POC |

Le découpage 2-3-4 est le cœur de la calibration : on qualifie à la main, on mesure la diff IA vs toi, on ajuste, PUIS on lâche l'automatique. Le matching (phase 5) est construit directement en hybride, sans étape sémantique-pur intermédiaire.

---

## 11. Risques et points d'attention

- **Cohérence de l'analyse vision** : d'où la phase de calibration. Toujours garder `raw_analysis` et les `annotations`.
- **Contraintes dures qui vident les résultats** : mode `souple` + fallback. Jamais de liste vide sur une requête de bonne foi.
- **Subjectivité** : âge, type perçu, carnation sont des jugements. Mesuré en calibration, encadré RGPD en prod.
- **Cohérence des embeddings** : même modèle partout. Changement = re-embed complet.
- **Doublons TPDNE** : le site peut resservir la même image. D'où le délai + la dédupe par hash.
- **Coût** : embeddings très bon marché, vision un peu plus. Batcher, cacher les embeddings (déterministes).
- **Limite des données TPDNE** : que des visages en cadrage portrait. Attributs corps à null / "inconnu" en POC.
- **Français** : `text-embedding-3-small` gère correctement le FR. Sinon, tester un modèle d'embedding multilingue (toujours via OpenRouter).

---

## 12. Décisions arrêtées (PRD finalisé)

- **Import** : les deux sources dès le départ, pull API de visages + upload local depuis l'ordi. N libre.
- **Modèle vision (analyse)** : `gpt-4o` par défaut pour le set gold. Le choix définitif (et l'éventuelle bascule du run de masse sur un modèle moins cher) se fait via la **page benchmark** (6.4), sur preuve : justesse vs gold, coût et latence comparés.
- **Benchmark multi-modèles** : dans le scope POC. Historisé, réutilisable après chaque changement de prompt.
- **Matching** : hybride complet dès le départ (filtres durs via DTO + ranking vectoriel), pas d'étape sémantique-pur intermédiaire.
- **Set gold de départ** : 20 images qualifiées à la main.
- **Storage POC** : local (`storage/app`), S3/OVH plus tard.
- **Embeddings** : `text-embedding-3-small` (1536 dims), même modèle à l'ingestion et à la recherche.
- **Parsing de requête** : modèle rapide et cheap (`gemini-2.5-flash`).
- **Résultats affichés** : top 5.
- **Auth** : aucune en POC (accès interne), à ajouter au MVP.
- **Front** : Vue + Inertia.

Seul point laissé volontairement ouvert, parce qu'il se décide avec les données : **quels attributs restent en filtre dur vs simple signal sémantique**. C'est la calibration (section 6) qui tranche, taux d'accord à l'appui.
---

## 13. Annexe - signaux déterministes optionnels (CV)

Principe : **hybride par attribut, pas un pipeline CV complet à maintenir**. On n'active un calcul déterministe que si le benchmark (6.4) montre que l'IA est faible sur un attribut précis. Sinon, l'IA fait le job sans prise de tête.

Là où le calcul peut battre l'IA (constance et coût nul) :
- **Carnation** : c'est une couleur, donc un calcul. Échantillonnage de patchs de peau (front, joue), passage en espace Lab, calcul de l'**ITA** (Individual Typology Angle, métrique dermato standard) mappé sur les phototypes I à VI. Plus stable que l'IA, qui hésite entre III et IV d'un run à l'autre.
- **Couleur des yeux / des cheveux** : teinte dominante sur la zone (iris, cheveux) par clustering.

Le piège commun : la couleur ne veut rien dire sans **normalisation de la lumière**. Une correction de balance des blancs (gray-world ou équivalent) est indispensable avant tout calcul, sinon deux éclairages donnent deux résultats pour la même personne.

Gagnant sans condition, quel que soit le débat sur les attributs :
- **Détection + alignement de visage** en preprocessing (MediaPipe Face Mesh ou dlib, gratuit et rapide) : valider qu'il y a bien un seul visage, recadrer proprement avant l'appel IA, rejeter les images inexploitables en amont. Améliore l'input du LLM.

À proscrire :
- **Classifieur ML automatique d'origine / ethnie** (type FairFace). Biais connus, précision douteuse, et c'est l'attribut sensible côté RGPD. Le `type_percu` reste IA-perçu corrigé à la main (human-in-the-loop). Pas de fausse rigueur mathématique sur le seul champ où elle serait peu fiable et juridiquement risquée.

Stockage : les valeurs calculées peuvent cohabiter avec les versions IA et humaine via `annotations.source = 'computed'`, sans toucher au schéma.
