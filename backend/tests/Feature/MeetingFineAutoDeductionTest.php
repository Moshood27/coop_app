<?php

use App\Models\User;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\AdministrativeChargeService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeetingFineAutoDeductionTest extends TestCase
{
    public function test_meeting_fine_is_not_deducted_when_auto_deduction_is_disabled_for_distant_member()
    {
        // 1. Setup a distant user with outstanding fines
        $user = User::factory()->create([
            'is_distant' => true,
            'balance' => 0,
            'outstanding_fines' => 1000,
        ]);

        // 2. Disable Meeting Fee Auto-Deduction
        Setting::set('auto_meeting_fine_deduction_enabled', false);
        Setting::set('auto_fine_deduction_enabled', true); // Leave this ON to show the conflict

        // 3. Simulate wallet funding (Paystack style)
        // We'll mimic what WebhookController does: increment balance and call applyDeductions

        DB::transaction(function () use ($user) {
            $user->increment('balance', 5000);

            WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'credit',
                'amount' => 5000,
                'reference' => 'TEST_PAYSTACK_REF',
                'source' => 'paystack_dva',
            ]);

            $chargeService = app(AdministrativeChargeService::class);
            $chargeService->applyDeductionsFromWallet($user, 5000, true, 'TEST_PAYSTACK_REF');
        });

        $user->refresh();

        // 4. Verify if fine was deducted
        // If the bug exists, outstanding_fines will be less than 1000
        $this->assertEquals(1000, (float)$user->outstanding_fines, "Fine should NOT have been deducted because meeting fee auto-deduction is disabled");

        $fineTxs = WalletTransaction::where('user_id', $user->id)
            ->whereIn('source', ['attendance_fine_collection', 'attendance_fine'])
            ->count();

        $this->assertEquals(0, $fineTxs, "No fine collection transaction should exist");
    }
}
