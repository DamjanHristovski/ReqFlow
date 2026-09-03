<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('specification_versions', function (Blueprint $table) {
            $table->foreignId('specification_id')->nullable()->change();
            $table->foreignId('user_story_id')->nullable()->after('specification_id')->constrained()->cascadeOnDelete();

            $table->unique(['user_story_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('specification_versions', function (Blueprint $table) {
            $table->dropUnique(['user_story_id', 'version_number']);
            $table->dropConstrainedForeignId('user_story_id');
            $table->foreignId('specification_id')->nullable(false)->change();
        });
    }
};
