<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PayoutAccount
 */
class PayoutAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $accountNumber = is_string($this->account_number) ? $this->account_number : null;
        $last4 = $accountNumber ? substr(preg_replace('/\D+/', '', $accountNumber) ?? '', -4) : null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'account_name' => $this->account_name,
            'account_number_last4' => $last4 ?: null,
            'phone_number' => $this->phone_number,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at,
        ];
    }
}

