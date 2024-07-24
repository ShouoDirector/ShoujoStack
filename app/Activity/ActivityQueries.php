<?php

namespace BookStack\Activity;

use BookStack\Activity\Models\Activity;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Entity;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Tools\MixedEntityListLoader;
use BookStack\Permissions\PermissionApplicator;
use BookStack\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ActivityQueries
{
    public function __construct(
        protected PermissionApplicator $permissions,
        protected MixedEntityListLoader $listLoader,
    ) {
    }

    /**
     * Gets the latest activity.
     */
    public function latest(int $count = 20, int $page = 0): array
    {
        $activityList = $this->permissions
            ->restrictEntityRelationQuery(Activity::query(), 'activities', 'loggable_id', 'loggable_type')
            ->orderBy('created_at', 'desc')
            ->with(['user'])
            ->skip($count * $page)
            ->take($count)
            ->get();

        $this->listLoader->loadIntoRelations($activityList->all(), 'loggable', false);

        return $this->filterSimilar($activityList);
    }

    /**
     * Gets the latest activity for an entity, Filtering out similar
     * items to prevent a message activity list.
     */
    public function entityActivity(Entity $entity, int $count = 20, int $page = 1): array
    {
        /** @var array<string, int[]> $queryIds */
        $queryIds = [$entity->getMorphClass() => [$entity->id]];

        if ($entity instanceof Book) {
            $queryIds[(new Chapter())->getMorphClass()] = $entity->chapters()->scopes('visible')->pluck('id');
        }
        if ($entity instanceof Book || $entity instanceof Chapter) {
            $queryIds[(new Page())->getMorphClass()] = $entity->pages()->scopes('visible')->pluck('id');
        }

        $query = Activity::query();
        $query->where(function (Builder $query) use ($queryIds) {
            foreach ($queryIds as $morphClass => $idArr) {
                $query->orWhere(function (Builder $innerQuery) use ($morphClass, $idArr) {
                    $innerQuery->where('loggable_type', '=', $morphClass)
                        ->whereIn('loggable_id', $idArr);
                });
            }
        });

        $activity = $query->orderBy('created_at', 'desc')
            ->with(['loggable' => function (Relation $query) {
                $query->withTrashed();
            }, 'user.avatar'])
            ->skip($count * ($page - 1))
            ->take($count)
            ->get();

        return $this->filterSimilar($activity);
    }

    /**
     * Get the latest activity for a user, Filtering out similar items.
     */
    public function userActivity(User $user, int $count = 20, int $page = 0): array
    {
        $activityList = $this->permissions
            ->restrictEntityRelationQuery(Activity::query(), 'activities', 'loggable_id', 'loggable_type')
            ->orderBy('created_at', 'desc')
            ->where('user_id', '=', $user->id)
            ->skip($count * $page)
            ->take($count)
            ->get();

        return $this->filterSimilar($activityList);
    }

    /**
     * Filters out similar activity.
     *
     * @param Activity[] $activities
     */
    protected function filterSimilar(iterable $activities): array
    {
        $newActivity = [];
        $previousItem = null;

        foreach ($activities as $activityItem) {
            if (!$previousItem || !$activityItem->isSimilarTo($previousItem)) {
                $newActivity[] = $activityItem;
            }

            $previousItem = $activityItem;
        }

        return $newActivity;
    }
}
