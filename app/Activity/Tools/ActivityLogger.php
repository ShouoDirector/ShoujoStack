<?php

namespace BookStack\Activity\Tools;

use BookStack\Activity\DispatchWebhookJob;
use BookStack\Activity\Models\Activity;
use BookStack\Activity\Models\Loggable;
use BookStack\Activity\Models\Webhook;
use BookStack\Activity\Notifications\NotificationManager;
use BookStack\Entities\Models\Entity;
use BookStack\Facades\Theme;
use BookStack\Theming\ThemeEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    public function __construct(
        protected NotificationManager $notifications
    ) {
        $this->notifications->loadDefaultHandlers();
    }

    /**
     * Add a generic activity event to the database.
     */
    public function add(string $type, string|Loggable $detail = ''): void
    {
        $detailToStore = ($detail instanceof Loggable) ? $detail->logDescriptor() : $detail;

        $activity = $this->newActivityForUser($type);
        $activity->detail = $detailToStore;

        if ($detail instanceof Entity) {
            $activity->loggable_id = $detail->id;
            $activity->loggable_type = $detail->getMorphClass();
        }

        $activity->save();

        $this->setNotification($type);
        $this->dispatchWebhooks($type, $detail);
        $this->notifications->handle($activity, $detail, user());
        Theme::dispatch(ThemeEvents::ACTIVITY_LOGGED, $type, $detail);
    }

    /**
     * Get a new activity instance for the current user.
     */
    protected function newActivityForUser(string $type): Activity
    {
        return (new Activity())->forceFill([
            'type'     => strtolower($type),
            'user_id'  => user()->id,
            'ip'       => IpFormatter::fromCurrentRequest()->format(),
        ]);
    }

    /**
     * Removes the entity attachment from each of its activities
     * and instead uses the 'extra' field with the entities name.
     * Used when an entity is deleted.
     */
    public function removeEntity(Entity $entity): void
    {
        $entity->activity()->update([
            'detail'         => $entity->name,
            'loggable_id'    => null,
            'loggable_type'  => null,
        ]);
    }

    /**
     * Flashes a notification message to the session if an appropriate message is available.
     */
    protected function setNotification(string $type): void
    {
        $notificationTextKey = 'activities.' . $type . '_notification';
        if (trans()->has($notificationTextKey)) {
            $message = trans($notificationTextKey);
            session()->flash('success', $message);
        }
    }

    protected function dispatchWebhooks(string $type, string|Loggable $detail): void
    {
        $webhooks = Webhook::query()
            ->whereHas('trackedEvents', function (Builder $query) use ($type) {
                $query->where('event', '=', $type)
                    ->orWhere('event', '=', 'all');
            })
            ->where('active', '=', true)
            ->get();

        foreach ($webhooks as $webhook) {
            dispatch(new DispatchWebhookJob($webhook, $type, $detail));
        }
    }

    /**
     * Log out a failed login attempt, Providing the given username
     * as part of the message if the '%u' string is used.
     */
    public function logFailedLogin(string $username): void
    {
        $message = config('logging.failed_login.message');
        if (!$message) {
            return;
        }

        $message = str_replace('%u', $username, $message);
        $channel = config('logging.failed_login.channel');
        Log::channel($channel)->warning($message);
    }
}
