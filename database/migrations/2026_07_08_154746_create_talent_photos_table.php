<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->string('path', 512);          // storage local ou S3
            $table->boolean('is_primary')->default(false);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('source', 64)->default('tpdne');
            $table->timestampTz('created_at')->nullable();

            $table->index('talent_id', 'idx_photos_talent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_photos');
    }
};
