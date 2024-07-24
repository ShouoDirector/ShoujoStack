<?php

namespace BookStack\Permissions;

use BookStack\Entities\Queries\EntityQueries;
use BookStack\Entities\Tools\PermissionsUpdater;
use BookStack\Http\Controller;
use BookStack\Permissions\Models\EntityPermission;
use BookStack\Users\Models\Role;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    public function __construct(
        protected PermissionsUpdater $permissionsUpdater,
        protected EntityQueries $queries,
    ) {
    }

    /**
     * Show the permissions view for a page.
     */
    public function showForPage(string $bookSlug, string $pageSlug)
    {
        $page = $this->queries->pages->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $this->checkOwnablePermission('restrictions-manage', $page);

        $this->setPageTitle(trans('entities.pages_permissions'));
        return view('pages.permissions', [
            'page' => $page,
            'data' => new PermissionFormData($page),
        ]);
    }

    /**
     * Set the permissions for a page.
     */
    public function updateForPage(Request $request, string $bookSlug, string $pageSlug)
    {
        $page = $this->queries->pages->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $this->checkOwnablePermission('restrictions-manage', $page);

        $this->permissionsUpdater->updateFromPermissionsForm($page, $request);

        $this->showSuccessNotification(trans('entities.pages_permissions_success'));

        return redirect($page->getUrl());
    }

    /**
     * Show the permissions view for a chapter.
     */
    public function showForChapter(string $bookSlug, string $chapterSlug)
    {
        $chapter = $this->queries->chapters->findVisibleBySlugsOrFail($bookSlug, $chapterSlug);
        $this->checkOwnablePermission('restrictions-manage', $chapter);

        $this->setPageTitle(trans('entities.chapters_permissions'));
        return view('chapters.permissions', [
            'chapter' => $chapter,
            'data' => new PermissionFormData($chapter),
        ]);
    }

    /**
     * Set the permissions for a chapter.
     */
    public function updateForChapter(Request $request, string $bookSlug, string $chapterSlug)
    {
        $chapter = $this->queries->chapters->findVisibleBySlugsOrFail($bookSlug, $chapterSlug);
        $this->checkOwnablePermission('restrictions-manage', $chapter);

        $this->permissionsUpdater->updateFromPermissionsForm($chapter, $request);

        $this->showSuccessNotification(trans('entities.chapters_permissions_success'));

        return redirect($chapter->getUrl());
    }

    /**
     * Show the permissions view for a book.
     */
    public function showForBook(string $slug)
    {
        $book = $this->queries->books->findVisibleBySlugOrFail($slug);
        $this->checkOwnablePermission('restrictions-manage', $book);

        $this->setPageTitle(trans('entities.books_permissions'));
        return view('books.permissions', [
            'book' => $book,
            'data' => new PermissionFormData($book),
        ]);
    }

    /**
     * Set the permissions for a book.
     */
    public function updateForBook(Request $request, string $slug)
    {
        $book = $this->queries->books->findVisibleBySlugOrFail($slug);
        $this->checkOwnablePermission('restrictions-manage', $book);

        $this->permissionsUpdater->updateFromPermissionsForm($book, $request);

        $this->showSuccessNotification(trans('entities.books_permissions_updated'));

        return redirect($book->getUrl());
    }

    /**
     * Show the permissions view for a shelf.
     */
    public function showForShelf(string $slug)
    {
        $shelf = $this->queries->shelves->findVisibleBySlugOrFail($slug);
        $this->checkOwnablePermission('restrictions-manage', $shelf);

        $this->setPageTitle(trans('entities.shelves_permissions'));
        return view('shelves.permissions', [
            'shelf' => $shelf,
            'data' => new PermissionFormData($shelf),
        ]);
    }

    /**
     * Set the permissions for a shelf.
     */
    public function updateForShelf(Request $request, string $slug)
    {
        $shelf = $this->queries->shelves->findVisibleBySlugOrFail($slug);
        $this->checkOwnablePermission('restrictions-manage', $shelf);

        $this->permissionsUpdater->updateFromPermissionsForm($shelf, $request);

        $this->showSuccessNotification(trans('entities.shelves_permissions_updated'));

        return redirect($shelf->getUrl());
    }

    /**
     * Copy the permissions of a bookshelf to the child books.
     */
    public function copyShelfPermissionsToBooks(string $slug)
    {
        $shelf = $this->queries->shelves->findVisibleBySlugOrFail($slug);
        $this->checkOwnablePermission('restrictions-manage', $shelf);

        $updateCount = $this->permissionsUpdater->updateBookPermissionsFromShelf($shelf);
        $this->showSuccessNotification(trans('entities.shelves_copy_permission_success', ['count' => $updateCount]));

        return redirect($shelf->getUrl());
    }

    /**
     * Get an empty entity permissions form row for the given role.
     */
    public function formRowForRole(string $entityType, string $roleId)
    {
        $this->checkPermissionOr('restrictions-manage-all', fn() => userCan('restrictions-manage-own'));

        $role = Role::query()->findOrFail($roleId);

        return view('form.entity-permissions-row', [
            'role' => $role,
            'permission' => new EntityPermission(),
            'entityType' => $entityType,
            'inheriting' => false,
        ]);
    }
}
