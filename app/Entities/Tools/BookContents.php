<?php

namespace BookStack\Entities\Tools;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\BookChild;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Entity;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Queries\EntityQueries;
use Illuminate\Support\Collection;

class BookContents
{
    protected EntityQueries $queries;

    public function __construct(
        protected Book $book,
    ) {
        $this->queries = app()->make(EntityQueries::class);
    }

    /**
     * Get the current priority of the last item at the top-level of the book.
     */
    public function getLastPriority(): int
    {
        $maxPage = $this->book->pages()
            ->where('draft', '=', false)
            ->where('chapter_id', '=', 0)
            ->max('priority');

        $maxChapter = $this->book->chapters()
            ->max('priority');

        return max($maxChapter, $maxPage, 1);
    }

    /**
     * Get the contents as a sorted collection tree.
     */
    public function getTree(bool $showDrafts = false, bool $renderPages = false): Collection
    {
        $pages = $this->getPages($showDrafts, $renderPages);
        $chapters = $this->book->chapters()->scopes('visible')->get();
        $all = collect()->concat($pages)->concat($chapters);
        $chapterMap = $chapters->keyBy('id');
        $lonePages = collect();

        $pages->groupBy('chapter_id')->each(function ($pages, $chapter_id) use ($chapterMap, &$lonePages) {
            $chapter = $chapterMap->get($chapter_id);
            if ($chapter) {
                $chapter->setAttribute('visible_pages', collect($pages)->sortBy($this->bookChildSortFunc()));
            } else {
                $lonePages = $lonePages->concat($pages);
            }
        });

        $chapters->whereNull('visible_pages')->each(function (Chapter $chapter) {
            $chapter->setAttribute('visible_pages', collect([]));
        });

        $all->each(function (Entity $entity) use ($renderPages) {
            $entity->setRelation('book', $this->book);

            if ($renderPages && $entity instanceof Page) {
                $entity->html = (new PageContent($entity))->render();
            }
        });

        return collect($chapters)->concat($lonePages)->sortBy($this->bookChildSortFunc());
    }

    /**
     * Function for providing a sorting score for an entity in relation to the
     * other items within the book.
     */
    protected function bookChildSortFunc(): callable
    {
        return function (Entity $entity) {
            if (isset($entity['draft']) && $entity['draft']) {
                return -100;
            }

            return $entity['priority'] ?? 0;
        };
    }

    /**
     * Get the visible pages within this book.
     */
    protected function getPages(bool $showDrafts = false, bool $getPageContent = false): Collection
    {
        if ($getPageContent) {
            $query = $this->queries->pages->visibleWithContents();
        } else {
            $query = $this->queries->pages->visibleForList();
        }

        if (!$showDrafts) {
            $query->where('draft', '=', false);
        }

        return $query->where('book_id', '=', $this->book->id)->get();
    }

    /**
     * Sort the books content using the given sort map.
     * Returns a list of books that were involved in the operation.
     *
     * @returns Book[]
     */
    public function sortUsingMap(BookSortMap $sortMap): array
    {
        // Load models into map
        $modelMap = $this->loadModelsFromSortMap($sortMap);

        // Sort our changes from our map to be chapters first
        // Since they need to be process to ensure book alignment for child page changes.
        $sortMapItems = $sortMap->all();
        usort($sortMapItems, function (BookSortMapItem $itemA, BookSortMapItem $itemB) {
            $aScore = $itemA->type === 'page' ? 2 : 1;
            $bScore = $itemB->type === 'page' ? 2 : 1;

            return $aScore - $bScore;
        });

        // Perform the sort
        foreach ($sortMapItems as $item) {
            $this->applySortUpdates($item, $modelMap);
        }

        /** @var Book[] $booksInvolved */
        $booksInvolved = array_values(array_filter($modelMap, function (string $key) {
            return str_starts_with($key, 'book:');
        }, ARRAY_FILTER_USE_KEY));

        // Update permissions of books involved
        foreach ($booksInvolved as $book) {
            $book->rebuildPermissions();
        }

        return $booksInvolved;
    }

    /**
     * Using the given sort map item, detect changes for the related model
     * and update it if required. Changes where permissions are lacking will
     * be skipped and not throw an error.
     *
     * @param array<string, Entity> $modelMap
     */
    protected function applySortUpdates(BookSortMapItem $sortMapItem, array $modelMap): void
    {
        /** @var BookChild $model */
        $model = $modelMap[$sortMapItem->type . ':' . $sortMapItem->id] ?? null;
        if (!$model) {
            return;
        }

        $priorityChanged = $model->priority !== $sortMapItem->sort;
        $bookChanged = $model->book_id !== $sortMapItem->parentBookId;
        $chapterChanged = ($model instanceof Page) && $model->chapter_id !== $sortMapItem->parentChapterId;

        // Stop if there's no change
        if (!$priorityChanged && !$bookChanged && !$chapterChanged) {
            return;
        }

        $currentParentKey = 'book:' . $model->book_id;
        if ($model instanceof Page && $model->chapter_id) {
            $currentParentKey = 'chapter:' . $model->chapter_id;
        }

        $currentParent = $modelMap[$currentParentKey] ?? null;
        /** @var Book $newBook */
        $newBook = $modelMap['book:' . $sortMapItem->parentBookId] ?? null;
        /** @var ?Chapter $newChapter */
        $newChapter = $sortMapItem->parentChapterId ? ($modelMap['chapter:' . $sortMapItem->parentChapterId] ?? null) : null;

        if (!$this->isSortChangePermissible($sortMapItem, $model, $currentParent, $newBook, $newChapter)) {
            return;
        }

        // Action the required changes
        if ($bookChanged) {
            $model->changeBook($newBook->id);
        }

        if ($model instanceof Page && $chapterChanged) {
            $model->chapter_id = $newChapter->id ?? 0;
        }

        if ($priorityChanged) {
            $model->priority = $sortMapItem->sort;
        }

        if ($chapterChanged || $priorityChanged) {
            $model->save();
        }
    }

    /**
     * Check if the current user has permissions to apply the given sorting change.
     * Is quite complex since items can gain a different parent change. Acts as a:
     * - Update of old parent element (Change of content/order).
     * - Update of sorted/moved element.
     * - Deletion of element (Relative to parent upon move).
     * - Creation of element within parent (Upon move to new parent).
     */
    protected function isSortChangePermissible(BookSortMapItem $sortMapItem, BookChild $model, ?Entity $currentParent, ?Entity $newBook, ?Entity $newChapter): bool
    {
        // Stop if we can't see the current parent or new book.
        if (!$currentParent || !$newBook) {
            return false;
        }

        $hasNewParent = $newBook->id !== $model->book_id || ($model instanceof Page && $model->chapter_id !== ($sortMapItem->parentChapterId ?? 0));
        if ($model instanceof Chapter) {
            $hasPermission = userCan('book-update', $currentParent)
                && userCan('book-update', $newBook)
                && userCan('chapter-update', $model)
                && (!$hasNewParent || userCan('chapter-create', $newBook))
                && (!$hasNewParent || userCan('chapter-delete', $model));

            if (!$hasPermission) {
                return false;
            }
        }

        if ($model instanceof Page) {
            $parentPermission = ($currentParent instanceof Chapter) ? 'chapter-update' : 'book-update';
            $hasCurrentParentPermission = userCan($parentPermission, $currentParent);

            // This needs to check if there was an intended chapter location in the original sort map
            // rather than inferring from the $newChapter since that variable may be null
            // due to other reasons (Visibility).
            $newParent = $sortMapItem->parentChapterId ? $newChapter : $newBook;
            if (!$newParent) {
                return false;
            }

            $hasPageEditPermission = userCan('page-update', $model);
            $newParentInRightLocation = ($newParent instanceof Book || ($newParent instanceof Chapter && $newParent->book_id === $newBook->id));
            $newParentPermission = ($newParent instanceof Chapter) ? 'chapter-update' : 'book-update';
            $hasNewParentPermission = userCan($newParentPermission, $newParent);

            $hasDeletePermissionIfMoving = (!$hasNewParent || userCan('page-delete', $model));
            $hasCreatePermissionIfMoving = (!$hasNewParent || userCan('page-create', $newParent));

            $hasPermission = $hasCurrentParentPermission
                && $newParentInRightLocation
                && $hasNewParentPermission
                && $hasPageEditPermission
                && $hasDeletePermissionIfMoving
                && $hasCreatePermissionIfMoving;

            if (!$hasPermission) {
                return false;
            }
        }

        return true;
    }

    /**
     * Load models from the database into the given sort map.
     *
     * @return array<string, Entity>
     */
    protected function loadModelsFromSortMap(BookSortMap $sortMap): array
    {
        $modelMap = [];
        $ids = [
            'chapter' => [],
            'page'    => [],
            'book'    => [],
        ];

        foreach ($sortMap->all() as $sortMapItem) {
            $ids[$sortMapItem->type][] = $sortMapItem->id;
            $ids['book'][] = $sortMapItem->parentBookId;
            if ($sortMapItem->parentChapterId) {
                $ids['chapter'][] = $sortMapItem->parentChapterId;
            }
        }

        $pages = $this->queries->pages->visibleForList()->whereIn('id', array_unique($ids['page']))->get();
        /** @var Page $page */
        foreach ($pages as $page) {
            $modelMap['page:' . $page->id] = $page;
            $ids['book'][] = $page->book_id;
            if ($page->chapter_id) {
                $ids['chapter'][] = $page->chapter_id;
            }
        }

        $chapters = $this->queries->chapters->visibleForList()->whereIn('id', array_unique($ids['chapter']))->get();
        /** @var Chapter $chapter */
        foreach ($chapters as $chapter) {
            $modelMap['chapter:' . $chapter->id] = $chapter;
            $ids['book'][] = $chapter->book_id;
        }

        $books = $this->queries->books->visibleForList()->whereIn('id', array_unique($ids['book']))->get();
        /** @var Book $book */
        foreach ($books as $book) {
            $modelMap['book:' . $book->id] = $book;
        }

        return $modelMap;
    }
}
