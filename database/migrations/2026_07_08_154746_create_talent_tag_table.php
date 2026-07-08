<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_tag', function (Blueprint $table) {
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();

            $table->primary(['talent_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_tag');
    }
};
