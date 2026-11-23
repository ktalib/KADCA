<?php

namespace App\Http\Requests\Portal;

use App\Http\Requests\Bpa\StoreApplicationRequest;
use App\Support\Portal\PortalSession;
use Illuminate\Validation\Rule;

/**
 * The staff application form's checks (StoreApplicationRequest), for an applicant applying on the portal:
 * the plan can't be "scanned at the counter", the date is today, and the applicant ticks the declaration
 * in place of the paper form's signature.
 */
class PortalApplicationRequest extends StoreApplicationRequest
{
    /** How an applicant can send the plan online (the staff list without "Scanned at the counter"). */
    public const RECEIVED_AS = [
        'Soft copy (PDF) from the applicant',
        'Scanned booklet from the applicant',
        'CAD drawing (DXF/DWG)',
    ];

    public function authorize(): bool
    {
        return PortalSession::applicant($this) !== null;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['plan_received_as'] = ['required', Rule::in(self::RECEIVED_AS)];
        unset($rules['submitted_on']);
        $rules['declaration'] = ['accepted'];

        return $rules;
    }

    public function messages(): array
    {
        return parent::messages() + [
            'klaes_file_indexing_id.required' => 'Find and pick your land file first.',
            'declaration.accepted' => 'Tick the declaration to submit.',
        ];
    }
}
