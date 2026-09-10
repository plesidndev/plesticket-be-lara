<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\PayoutAccount;
use App\Services\AuditLogger;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where an organizer's payouts are sent. Scoped to the caller throughout — an organizer only ever
 * reads or writes their own destination account.
 */
class PayoutAccountController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        $account = PayoutAccount::where('user_id', auth('api')->id())->first();

        return $this->success('Payout account retrieved.', $account === null ? null : [
            'bank_code' => $account->bank_code,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'account_holder' => $account->account_holder,
        ]);
    }

    public function update(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'bank_code' => ['required', 'string', 'max:20'],
            'account_number' => ['required', 'string', 'max:40'],
            'account_holder' => ['required', 'string', 'max:255'],
        ]);

        $bank = Bank::where('code', $data['bank_code'])->where('is_active', true)->first();

        if (! $bank) {
            return $this->error('That bank is not available.', 422, ['bank_code' => ['That bank is not available.']]);
        }

        $account = PayoutAccount::updateOrCreate(
            ['user_id' => auth('api')->id()],
            ['bank_code' => $bank->code, 'bank_name' => $bank->name, 'account_number' => $data['account_number'], 'account_holder' => $data['account_holder']],
        );

        // Recorded because changing where money is sent is exactly the kind of action worth tracing.
        $audit->record('payout_account.updated', 'user', (string) auth('api')->user()?->uid, auth('api')->user()?->name, [
            'bank_name' => $bank->name,
        ]);

        return $this->success('Payout account saved.', [
            'bank_code' => $account->bank_code,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'account_holder' => $account->account_holder,
        ]);
    }
}
