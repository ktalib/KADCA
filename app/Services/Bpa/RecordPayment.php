<?php

namespace App\Services\Bpa;

use App\Models\Bpa\Bill;
use App\Models\User;
use App\Support\BpaStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirms that a KADCA bill was paid (receipt number, date, amount, channel). Until KADCA bills are
 * written into KLAES `billing` and receipted through REV-M, staff confirm the receipt here.
 * Part payments are not taken: the amount must equal the bill.
 */
class RecordPayment
{
    public const CHANNELS = ['Bank', 'POS', 'Interswitch', 'USSD', 'Other'];

    /** @param array{receipt_no: string, paid_on: string, amount_paid: string|float, payment_channel: string, payment_channel_other?: ?string} $data */
    public function handle(Bill $bill, array $data, User $officer): Bill
    {
        return DB::transaction(function () use ($bill, $data, $officer) {
            $bill = Bill::query()->lockForUpdate()->findOrFail($bill->getKey());

            if ($bill->status === 'Paid') {
                throw ValidationException::withMessages(['receipt_no' => "Bill {$bill->bill_no} is already recorded as paid."]);
            }
            if (round((float) $data['amount_paid'], 2) !== round((float) $bill->amount, 2)) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'The amount paid must equal the bill, ₦'.number_format((float) $bill->amount, 2).'. Part payments are not taken.',
                ]);
            }
            if (Bill::query()->where('id', '!=', $bill->id)->whereRaw('UPPER(receipt_no) = ?', [mb_strtoupper($data['receipt_no'])])->exists()) {
                throw ValidationException::withMessages(['receipt_no' => 'That receipt number is already recorded on another bill.']);
            }

            $bill->update([
                'status' => 'Paid',
                'receipt_no' => $data['receipt_no'],
                'paid_on' => $data['paid_on'],
                'amount_paid' => $data['amount_paid'],
                'payment_channel' => $data['payment_channel'],
                'payment_channel_other' => $data['payment_channel'] === 'Other' ? ($data['payment_channel_other'] ?? null) : null,
                'payment_recorded_by' => $officer->getKey(),
                'payment_recorded_by_name' => $officer->name,
                'payment_recorded_at' => now(),
            ]);

            $bill->application()->update(['status' => 'Paid, awaiting commissioning', 'stage' => BpaStage::ARCHITECTURE]);

            return $bill;
        });
    }
}
