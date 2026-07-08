<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une sortie de modèle par (run, talent, modèle), scorée vs le gold.
     */
    public function up(): void
    {
        Schema::create('benchmark_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('benchmark_runs')->cascadeOnDelete();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->string('model', 64);
            $table->jsonb('payload')->nullable();          // sortie du modèle
            $table->boolean('is_valid_json');              // PROPRETÉ
            $table->float('agreement_score')->nullable();  // JUSTESSE globale vs gold
            $table->jsonb('per_field_result')->nullable(); // { attribut: bool } vs gold
            $table->integer('latency_ms')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index('run_id', 'idx_bench_results_run');
            $table->index('model', 'idx_bench_results_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_results');
    }
};
