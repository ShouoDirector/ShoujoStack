<?php

namespace Database\Seeders;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Permissions\JointPermissionBuilder;
use BookStack\Search\SearchIndex;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LargeContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create an editor user
        $editorUser = User::factory()->create();
        $editorRole = Role::getRole('editor');
        $editorUser->attachRole($editorRole);

        /** @var Book $largeBook */
        $largeBook = Book::factory()->create(['name' => 'Large book' . Str::random(10), 'created_by' => $editorUser->id, 'updated_by' => $editorUser->id]);
        $chapters = Chapter::factory()->count(50)->make(['created_by' => $editorUser->id, 'updated_by' => $editorUser->id]);
        $largeBook->chapters()->saveMany($chapters);

        $allPages = [];

        foreach ($chapters as $chapter) {
            $pages = Page::factory()->count(100)->make(['created_by' => $editorUser->id, 'updated_by' => $editorUser->id, 'chapter_id' => $chapter->id]);
            $largeBook->pages()->saveMany($pages);
            array_push($allPages, ...$pages->all());
        }

        $all = array_merge([$largeBook], $allPages, array_values($chapters->all()));

        app()->make(JointPermissionBuilder::class)->rebuildForEntity($largeBook);
        app()->make(SearchIndex::class)->indexEntities($all);
    }
}
