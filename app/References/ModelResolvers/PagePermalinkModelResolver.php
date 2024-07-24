<?php

namespace BookStack\References\ModelResolvers;

use BookStack\App\Model;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Queries\PageQueries;

class PagePermalinkModelResolver implements CrossLinkModelResolver
{
    public function __construct(
        protected PageQueries $queries
    ) {
    }

    public function resolve(string $link): ?Model
    {
        $pattern = '/^' . preg_quote(url('/link'), '/') . '\/(\d+)/';
        $matches = [];
        $match = preg_match($pattern, $link, $matches);
        if (!$match) {
            return null;
        }

        $id = intval($matches[1]);
        /** @var ?Page $model */
        $model = $this->queries->start()->find($id, ['id']);

        return $model;
    }
}
