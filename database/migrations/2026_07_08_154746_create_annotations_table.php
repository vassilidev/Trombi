<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Labels humains ET IA (calibration + eval). source = human | ai | computed.
     */
    public function up(): void
    {
        Schema::create('annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->string('source', 8);              // human | ai | computed
            $table->string('annotator', 64)->nullable(); // 'vassili' ou nom du modèle
            $table->jsonb('payload');                 // jeu d'attributs complet
            $table->timestampTz('created_at')->nullable();

            $table->index('talent_id', 'idx_annotations_talent');
            $table->index('source', 'idx_annotations_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotations');
    }
};
