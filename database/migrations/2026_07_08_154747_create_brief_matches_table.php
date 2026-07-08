<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brief_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_id')->constrained('briefs')->cascadeOnDelete();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->float('score');           // score final (0..1)
            $table->smallInteger('rank');     // position dans les résultats
            $table->timestampTz('created_at')->nullable();

            $table->index('brief_id', 'idx_matches_brief');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_matches');
    }
};
