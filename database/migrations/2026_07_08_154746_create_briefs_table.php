<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Briefs entrants (prompt/PDF/chat) + parsing loggé.
     */
    public function up(): void
    {
        Schema::create('briefs', function (Blueprint $table) {
            $table->id();
            $table->text('raw_text');
            $table->string('source_kind', 16)->default('chat'); // chat | pdf | prompt
            $table->jsonb('parsed_filters')->nullable();          // DTO de filtres
            $table->text('semantic_text')->nullable();
            $table->timestampTz('created_at')->nullable();
        });

        DB::statement('ALTER TABLE briefs ADD COLUMN query_embedding vector(1536)');
    }

    public function down(): void
    {
        Schema::dropIfExists('briefs');
    }
};
