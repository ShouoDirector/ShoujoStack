<?php

namespace BookStack\References;

use BookStack\App\Model;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\References\ModelResolvers\BookLinkModelResolver;
use BookStack\References\ModelResolvers\BookshelfLinkModelResolver;
use BookStack\References\ModelResolvers\ChapterLinkModelResolver;
use BookStack\References\ModelResolvers\CrossLinkModelResolver;
use BookStack\References\ModelResolvers\PageLinkModelResolver;
use BookStack\References\ModelResolvers\PagePermalinkModelResolver;
use BookStack\Util\HtmlDocument;

class CrossLinkParser
{
    /**
     * @var CrossLinkModelResolver[]
     */
    protected array $modelResolvers;

    public function __construct(array $modelResolvers)
    {
        $this->modelResolvers = $modelResolvers;
    }

    /**
     * Extract any found models within the given HTML content.
     *
     * @return Model[]
     */
    public function extractLinkedModels(string $html): array
    {
        $models = [];

        $links = $this->getLinksFromContent($html);

        foreach ($links as $link) {
            $model = $this->linkToModel($link);
            if (!is_null($model)) {
                $models[get_class($model) . ':' . $model->id] = $model;
            }
        }

        return array_values($models);
    }

    /**
     * Get a list of href values from the given document.
     *
     * @returns string[]
     */
    protected function getLinksFromContent(string $html): array
    {
        $links = [];

        $doc = new HtmlDocument($html);
        $anchors = $doc->queryXPath('//a[@href]');

        /** @var \DOMElement $anchor */
        foreach ($anchors as $anchor) {
            $links[] = $anchor->getAttribute('href');
        }

        return $links;
    }

    /**
     * Attempt to resolve the given link to a model using the instance model resolvers.
     */
    protected function linkToModel(string $link): ?Model
    {
        foreach ($this->modelResolvers as $resolver) {
            $model = $resolver->resolve($link);
            if (!is_null($model)) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Create a new instance with a pre-defined set of model resolvers, specifically for the
     * default set of entities within BookStack.
     */
    public static function createWithEntityResolvers(): self
    {
        $queries = app()->make(EntityQueries::class);

        return new self([
            new PagePermalinkModelResolver($queries->pages),
            new PageLinkModelResolver($queries->pages),
            new ChapterLinkModelResolver($queries->chapters),
            new BookLinkModelResolver($queries->books),
            new BookshelfLinkModelResolver($queries->shelves),
        ]);
    }
}
