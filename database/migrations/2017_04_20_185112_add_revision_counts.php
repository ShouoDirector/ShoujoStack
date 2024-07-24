<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->integer('revision_count');
        });
        Schema::table('page_revisions', function (Blueprint $table) {
            $table->integer('revision_number');
            $table->index('revision_number');
        });

        // Update revision count
        $pTable = DB::getTablePrefix() . 'pages';
        $rTable = DB::getTablePrefix() . 'page_revisions';
        DB::statement("UPDATE {$pTable} SET {$pTable}.revision_count=(SELECT count(*) FROM {$rTable} WHERE {$rTable}.page_id={$pTable}.id)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('revision_count');
        });
        Schema::table('page_revisions', function (Blueprint $table) {
            $table->dropColumn('revision_number');
        });
    }
};
