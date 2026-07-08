<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Description IA + vecteur d'embedding. Colonne vector(1536) et index HNSW
     * posés en SQL brut : hors du query builder standard (cf. PRD note Laravel).
     */
    public function up(): void
    {
        Schema::create('talent_profiles', function (Blueprint $table) {
            $table->foreignId('talent_id')->primary()->constrained('talents')->cascadeOnDelete();
            $table->text('description_fr');
            $table->text('searchable_text');   // description + tags concaténés (embeddé)
            $table->string('model_used', 64)->nullable();
            $table->timestampTz('embedded_at')->nullable();
        });

        DB::statement('ALTER TABLE talent_profiles ADD COLUMN description_embedding vector(1536)');

        // Index HNSW cosinus. En prod on le crée après le seed pour la vitesse ;
        // en POC le volume est faible, on le pose directement.
        DB::statement('CREATE INDEX idx_profiles_embedding ON talent_profiles USING hnsw (description_embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_profiles');
    }
};
