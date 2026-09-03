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
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('specification_id')->nullable()->change();
            $table->foreignId('user_story_id')->nullable()->after('specification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acceptance_criterion_id')->nullable()->after('user_story_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acceptance_criterion_id');
            $table->dropConstrainedForeignId('user_story_id');
            $table->foreignId('specification_id')->nullable(false)->change();
        });
    }
};
