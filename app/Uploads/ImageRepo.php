<?php

namespace BookStack\Uploads;

use BookStack\Entities\Queries\PageQueries;
use BookStack\Exceptions\ImageUploadException;
use BookStack\Permissions\PermissionApplicator;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageRepo
{
    public function __construct(
        protected ImageService $imageService,
        protected PermissionApplicator $permissions,
        protected ImageResizer $imageResizer,
        protected PageQueries $pageQueries,
    ) {
    }

    /**
     * Get an image with the given id.
     */
    public function getById($id): Image
    {
        return Image::query()->findOrFail($id);
    }

    /**
     * Execute a paginated query, returning in a standard format.
     * Also runs the query through the restriction system.
     */
    protected function returnPaginated(Builder $query, int $page = 1, int $pageSize = 24): array
    {
        $images = $query->orderBy('created_at', 'desc')->skip($pageSize * ($page - 1))->take($pageSize + 1)->get();

        return [
            'images'   => $images->take($pageSize),
            'has_more' => count($images) > $pageSize,
        ];
    }

    /**
     * Fetch a list of images in a paginated format, filtered by image type.
     * Can be filtered by uploaded to and also by name.
     */
    public function getPaginatedByType(
        string $type,
        int $page = 0,
        int $pageSize = 24,
        int $uploadedTo = null,
        string $search = null,
        callable $whereClause = null
    ): array {
        $imageQuery = Image::query()->where('type', '=', strtolower($type));

        if ($uploadedTo !== null) {
            $imageQuery = $imageQuery->where('uploaded_to', '=', $uploadedTo);
        }

        if ($search !== null) {
            $imageQuery = $imageQuery->where('name', 'LIKE', '%' . $search . '%');
        }

        // Filter by page access
        $imageQuery = $this->permissions->restrictPageRelationQuery($imageQuery, 'images', 'uploaded_to');

        if ($whereClause !== null) {
            $imageQuery = $imageQuery->where($whereClause);
        }

        return $this->returnPaginated($imageQuery, $page, $pageSize);
    }

    /**
     * Get paginated gallery images within a specific page or book.
     */
    public function getEntityFiltered(
        string $type,
        ?string $filterType,
        int $page,
        int $pageSize,
        int $uploadedTo,
        ?string $search
    ): array {
        $contextPage = $this->pageQueries->findVisibleByIdOrFail($uploadedTo);
        $parentFilter = null;

        if ($filterType === 'book' || $filterType === 'page') {
            $parentFilter = function (Builder $query) use ($filterType, $contextPage) {
                if ($filterType === 'page') {
                    $query->where('uploaded_to', '=', $contextPage->id);
                } elseif ($filterType === 'book') {
                    $validPageIds = $contextPage->book->pages()
                        ->scopes('visible')
                        ->pluck('id')
                        ->toArray();
                    $query->whereIn('uploaded_to', $validPageIds);
                }
            };
        }

        return $this->getPaginatedByType($type, $page, $pageSize, null, $search, $parentFilter);
    }

    /**
     * Save a new image into storage and return the new image.
     *
     * @throws ImageUploadException
     */
    public function saveNew(UploadedFile $uploadFile, string $type, int $uploadedTo = 0, int $resizeWidth = null, int $resizeHeight = null, bool $keepRatio = true): Image
    {
        $image = $this->imageService->saveNewFromUpload($uploadFile, $type, $uploadedTo, $resizeWidth, $resizeHeight, $keepRatio);

        if ($type !== 'system') {
            $this->imageResizer->loadGalleryThumbnailsForImage($image, true);
        }

        return $image;
    }

    /**
     * Save a new image from an existing image data string.
     *
     * @throws ImageUploadException
     */
    public function saveNewFromData(string $imageName, string $imageData, string $type, int $uploadedTo = 0): Image
    {
        $image = $this->imageService->saveNew($imageName, $imageData, $type, $uploadedTo);
        $this->imageResizer->loadGalleryThumbnailsForImage($image, true);

        return $image;
    }

    /**
     * Save a drawing in the database.
     *
     * @throws ImageUploadException
     */
    public function saveDrawing(string $base64Uri, int $uploadedTo): Image
    {
        $name = 'Drawing-' . user()->id . '-' . time() . '.png';

        return $this->imageService->saveNewFromBase64Uri($base64Uri, $name, 'drawio', $uploadedTo);
    }

    /**
     * Update the details of an image via an array of properties.
     *
     * @throws Exception
     */
    public function updateImageDetails(Image $image, $updateDetails): Image
    {
        $image->fill($updateDetails);
        $image->updated_by = user()->id;
        $image->save();
        $this->imageResizer->loadGalleryThumbnailsForImage($image, false);

        return $image;
    }

    /**
     * Update the image file of an existing image in the system.
     * @throws ImageUploadException
     */
    public function updateImageFile(Image $image, UploadedFile $file): void
    {
        if (strtolower($file->getClientOriginalExtension()) !== strtolower(pathinfo($image->path, PATHINFO_EXTENSION))) {
            throw new ImageUploadException(trans('errors.image_upload_replace_type'));
        }

        $image->refresh();
        $image->updated_by = user()->id;
        $image->touch();
        $image->save();

        $this->imageService->replaceExistingFromUpload($image->path, $image->type, $file);
        $this->imageResizer->loadGalleryThumbnailsForImage($image, true);
    }

    /**
     * Destroys an Image object along with its revisions, files and thumbnails.
     *
     * @throws Exception
     */
    public function destroyImage(Image $image = null): void
    {
        if ($image) {
            $this->imageService->destroy($image);
        }
    }

    /**
     * Destroy images that have a specific URL and type combination.
     *
     * @throws Exception
     */
    public function destroyByUrlAndType(string $url, string $imageType): void
    {
        $images = Image::query()
            ->where('url', '=', $url)
            ->where('type', '=', $imageType)
            ->get();

        foreach ($images as $image) {
            $this->destroyImage($image);
        }
    }

    /**
     * Get the raw image data from an Image.
     */
    public function getImageData(Image $image): ?string
    {
        try {
            return $this->imageService->getImageData($image);
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * Get the user visible pages using the given image.
     */
    public function getPagesUsingImage(Image $image): array
    {
        $pages = $this->pageQueries->visibleForList()
            ->where('html', 'like', '%' . $image->url . '%')
            ->get();

        foreach ($pages as $page) {
            $page->setAttribute('url', $page->getUrl());
        }

        return $pages->all();
    }
}
