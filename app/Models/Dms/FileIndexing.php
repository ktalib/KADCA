<?php

namespace App\Models\Dms;

use App\Models\Bpa\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A KADCA shadow file: KADCA's own indexing record of a KLAES land file, copied from KLAES's
 * dbo.file_indexings when the file is matched (File Indexing Assistant / Shadow File Commissioning).
 */
class FileIndexing extends Model
{
    protected $table = 'file_indexings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date_matched' => 'date', 'is_deleted' => 'boolean'];
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->where('is_deleted', false);
    }

    public function scannings(): HasMany
    {
        return $this->hasMany(Scanning::class, 'file_indexing_id')->where('is_deleted', false)->orderBy('display_order')->orderBy('id');
    }

    public function pageTypings(): HasMany
    {
        return $this->hasMany(PageTyping::class, 'file_indexing_id')->orderBy('page_number');
    }

    /** BPA applications made on the same KLAES land file. */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'klaes_file_indexing_id', 'klaes_file_indexing_id');
    }
}
