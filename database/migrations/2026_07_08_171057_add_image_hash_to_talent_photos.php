<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le hash passe au niveau photo : un talent peut avoir plusieurs images,
     * chacune dédupliquée indépendamment.
     */
    public function up(): void
    {
        Schema::table('talent_photos', function (Blueprint $table) {
            $table->char('image_hash', 64)->nullable()->after('path');
            $table->unique('image_hash', 'idx_photos_hash');
        });

        // Reprise : chaque photo existante hérite du hash de son talent.
        DB::statement('UPDATE talent_photos p SET image_hash = t.image_hash FROM talents t WHERE t.id = p.talent_id');
    }

    public function down(): void
    {
        Schema::table('talent_photos', function (Blueprint $table) {
            $table->dropUnique('idx_photos_hash');
            $table->dropColumn('image_hash');
        });
    }
};
