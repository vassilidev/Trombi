<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_runs', function (Blueprint $table) {
            $table->id();
            $table->string('label', 128)->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->jsonb('models');                  // liste des modèles testés
            $table->smallInteger('gold_count');       // nb d'images gold évaluées
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_runs');
    }
};
