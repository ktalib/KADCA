<?php

namespace App\Models\Bpa;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A Building Plan Approval application (KADCA Assessment Stage). Lives in kadca_bpa_applications.
 * The BP file is commissioned later, after the application bill is paid.
 */
class Application extends Model
{
    protected $table = 'bpa_applications';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'documents_submitted' => 'array',
            'drawings_submitted' => 'array',
            'submitted_on' => 'date',
            'commissioned_at' => 'datetime',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(ApplicationFile::class, 'bpa_application_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class, 'bpa_application_id');
    }

    public function bill(): HasOne
    {
        return $this->hasOne(Bill::class, 'bpa_application_id');
    }

    /** The physical BP file's tracking (File Tracking), once commissioned or logged. */
    public function tracker(): HasOne
    {
        return $this->hasOne(\App\Models\Tracking\FileTracker::class, 'bpa_application_id');
    }
}
