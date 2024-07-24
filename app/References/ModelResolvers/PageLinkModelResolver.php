<?php

namespace BookStack\References\ModelResolvers;

use BookStack\App\Model;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Queries\PageQueries;

class PageLinkModelResolver implements CrossLinkModelResolver
{
    public function __construct(
        protected PageQueries $queries
    ) {
    }

    public function resolve(string $link): ?Model
    {
        $pattern = '/^' . preg_quote(url('/books'), '/') . '\/([\w-]+)' . '\/page\/' . '([\w-]+)' . '([#?\/]|$)/';
        $matches = [];
        $match = preg_match($pattern, $link, $matches);
        if (!$match) {
            return null;
        }

        $bookSlug = $matches[1];
        $pageSlug = $matches[2];

        /** @var ?Page $model */
        $model = $this->queries->usingSlugs($bookSlug, $pageSlug)->first(['id']);

        return $model;
    }
}
