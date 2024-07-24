<?php

namespace BookStack\Activity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property int    $webhook_id
 * @property string $event
 */
class WebhookTrackedEvent extends Model
{
    use HasFactory;

    protected $fillable = ['event'];
}
