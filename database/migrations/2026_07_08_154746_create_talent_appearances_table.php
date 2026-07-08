<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le portrait robot structuré retenu (canonique) : 1 ligne par talent.
     * Valeurs issues de l'IA en masse OU du label humain (qui prime).
     */
    public function up(): void
    {
        Schema::create('talent_appearances', function (Blueprint $table) {
            $table->foreignId('talent_id')->primary()->constrained('talents')->cascadeOnDelete();

            // identité perçue
            $table->string('genre', 16)->nullable();
            $table->smallInteger('age_min')->nullable();
            $table->smallInteger('age_max')->nullable();
            $table->string('type_percu', 32)->nullable();
            $table->string('carnation', 16)->nullable();

            // cheveux
            $table->string('cheveux_couleur', 16)->nullable();
            $table->string('cheveux_longueur', 16)->nullable();
            $table->string('cheveux_texture', 16)->nullable();

            // visage
            $table->string('yeux_couleur', 16)->nullable();
            $table->string('forme_visage', 16)->nullable();
            $table->string('pilosite', 24)->nullable();
            $table->string('expression', 16)->nullable();

            // corps (souvent null en POC : TPDNE = visages)
            $table->string('morphologie', 16)->nullable();

            $table->string('source_label', 8)->default('ai'); // ai | human
            $table->jsonb('raw_analysis')->nullable();          // sortie IA brute
            $table->string('model_used', 64)->nullable();
            $table->timestampTz('analyzed_at')->nullable();

            $table->index('genre', 'idx_app_genre');
            $table->index('type_percu', 'idx_app_type');
            $table->index('cheveux_couleur', 'idx_app_cheveux');
            $table->index('yeux_couleur', 'idx_app_yeux');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_appearances');
    }
};
