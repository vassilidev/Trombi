<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();          // ex: TAL-0001, lisible
            $table->string('source', 64)->default('tpdne'); // tpdne | real
            $table->boolean('is_gold')->default(false);     // annoté à la main
            $table->boolean('is_active')->default(true);
            $table->char('image_hash', 64)->nullable();     // sha256, dédupe
            $table->timestampsTz();

            $table->unique('image_hash', 'idx_talents_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talents');
    }
};
