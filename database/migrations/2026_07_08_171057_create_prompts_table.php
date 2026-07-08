<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompts éditables en base (analyse vision, parsing de requête).
     * Versionnés pour tracer quel énoncé a servi (benchmark, historique).
     */
    public function up(): void
    {
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();   // vision | parsing
            $table->string('label', 128);
            $table->text('content');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};
