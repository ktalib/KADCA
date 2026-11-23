<?php

namespace App\Models\Tracking;

use App\Models\Bpa\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A tracked KADCA file (a BP file) or mail: its tracking ID, where it is, and its movements. */
class FileTracker extends Model
{
    protected $table = 'file_trackers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'tracking_id';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'bpa_application_id');
    }

    public function currentUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'current_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FileMovement::class, 'file_tracker_id');
    }

    public function isOverdue(): bool
    {
        return $this->due_on !== null && $this->due_on->isPast() && ! $this->due_on->isToday();
    }
}
