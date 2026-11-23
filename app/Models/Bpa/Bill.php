<?php

namespace App\Models\Bpa;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A KADCA bill (the BPA application fee for now). Kept in kadca_bills until the user approves
 * writing KADCA bills into KLAES `billing` with source = KADCA.
 */
class Bill extends Model
{
    protected $table = 'bills';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'amount' => 'decimal:2', 'amount_paid' => 'decimal:2', 'paid_on' => 'date', 'payment_recorded_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'bpa_application_id');
    }
}
