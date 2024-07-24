<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $colorSettings = [
            'app-color',
            'app-color-light',
            'bookshelf-color',
            'book-color',
            'chapter-color',
            'page-color',
            'page-draft-color',
        ];

        $existing = DB::table('settings')
            ->whereIn('setting_key', $colorSettings)
            ->get()->toArray();

        $newData = [];
        foreach ($existing as $setting) {
            $newSetting = (array) $setting;
            $newSetting['setting_key'] .= '-dark';
            $newData[] = $newSetting;

            if ($newSetting['setting_key'] === 'app-color-dark') {
                $newSetting['setting_key'] = 'link-color';
                $newData[] = $newSetting;
                $newSetting['setting_key'] = 'link-color-dark';
                $newData[] = $newSetting;
            }
        }

        DB::table('settings')->insert($newData);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $colorSettings = [
            'app-color-dark',
            'link-color',
            'link-color-dark',
            'app-color-light-dark',
            'bookshelf-color-dark',
            'book-color-dark',
            'chapter-color-dark',
            'page-color-dark',
            'page-draft-color-dark',
        ];

        DB::table('settings')
            ->whereIn('setting_key', $colorSettings)
            ->delete();
    }
};
