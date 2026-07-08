<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tags cumulables : vibe, signe_distinctif, categorie.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('label', 128);
            $table->string('famille', 32); // vibe | signe_distinctif | categorie
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
