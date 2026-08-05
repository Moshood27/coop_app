<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\WalletTransaction;
use App\Services\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\ShariahAuditLog as ShariahAudit;
use Illuminate\Support\Str;

class UssdController extends Controller
{
    const SESSION_TTL = 300; // 5 minutes

    public function handleCallback(Request $request)
    {
        // Termii USSD payload: msisdn, sessionId, userInput, serviceCode
        $msisdn = $request->input('msisdn');
        $sessionId = $request->input('sessionId');
        $userInput = $request->input('userInput');

        if (!$msisdn || !$sessionId) {
            return response()->json(['message' => 'Invalid request', 'type' => 'release']);
        }

        $smsService = new SmsService();
        $normalizedPhone = $smsService->normalizeMsisdn($msisdn);

        // Try to find user by normalized phone or as stored in DB
        $user = User::where('phone', $normalizedPhone)
            ->orWhere('phone', '0' . substr($normalizedPhone, 3))
            ->orWhere('phone', $msisdn)
            ->first();

        if (!$user) {
            $appName = config('app.name');
            return $this->release("Welcome to $appName. Your phone number ($msisdn) is not registered. Please visit our office to register.");
        }

        $sessionData = Cache::get("ussd_session_$sessionId");

        // If it's the very first request (initial dial)
        if (!$sessionData) {
            $sessionData = [
                'state' => 'MAIN_MENU',
                'data' => []
            ];
            Cache::put("ussd_session_$sessionId", $sessionData, self::SESSION_TTL);
            return $this->showMainMenu($user);
        }

        return $this->processState($user, $sessionData, $userInput, $sessionId);
    }

    private function processState($user, $sessionData, $userInput, $sessionId)
    {
        $state = $sessionData['state'];

        switch ($state) {
            case 'MAIN_MENU':
                return $this->handleMainMenu($user, $userInput, $sessionId);
            case 'PASSBOOK_MENU':
                return $this->handlePassbookMenu($user, $userInput, $sessionId);
            case 'LOAN_LIST_MENU':
                return $this->handleLoanListMenu($user, $userInput, $sessionId);
            case 'LOAN_AMOUNT_INPUT':
                return $this->handleLoanAmountInput($user, $userInput, $sessionId, $sessionData);
            case 'LOAN_REPAY_CONFIRM':
                return $this->handleLoanRepayConfirm($user, $userInput, $sessionId, $sessionData);
            default:
                return $this->showMainMenu($user);
        }
    }

    private function prompt($message)
    {
        return response()->json([
            'message' => $message,
            'type' => 'prompt'
        ]);
    }

    private function release($message)
    {
        return response()->json([
            'message' => $message,
            'type' => 'release'
        ]);
    }

    private function showMainMenu($user)
    {
        $name = $user->name ?: 'Member';
        $appName = config('app.name');
        $menu = "Welcome $name to $appName\n"
              . "1. Wallet Balance\n"
              . "2. Passbook Balances\n"
              . "3. Repay Loan\n"
              . "4. Fund Wallet\n"
              . "5. Exit";
        return $this->prompt($menu);
    }

    private function handleMainMenu($user, $userInput, $sessionId)
    {
        switch ($userInput) {
            case '1':
                return $this->release("Your Wallet Balance: ₦" . number_format((float)$user->balance, 2));
            case '2':
                return $this->showPassbookMenu($sessionId);
            case '3':
                return $this->showLoanList($user, $sessionId);
            case '4':
                return $this->showFundWallet($user);
            case '5':
                $appName = config('app.name');
                return $this->release("Thank you for using $appName USSD.");
            default:
                return $this->showMainMenu($user);
        }
    }

    private function showPassbookMenu($sessionId)
    {
        Cache::put("ussd_session_$sessionId", ['state' => 'PASSBOOK_MENU', 'data' => []], self::SESSION_TTL);
        $menu = "Passbook Balances:\n"
              . "1. Ordinary Savings\n"
              . "2. Special Savings\n"
              . "3. Shares Capital\n"
              . "4. Others\n"
              . "0. Back";
        return $this->prompt($menu);
    }

    private function handlePassbookMenu($user, $userInput, $sessionId)
    {
        if ($userInput === '0') {
             Cache::put("ussd_session_$sessionId", ['state' => 'MAIN_MENU', 'data' => []], self::SESSION_TTL);
             return $this->showMainMenu($user);
        }

        switch ($userInput) {
            case '1':
                return $this->release("Ordinary Savings: ₦" . number_format((float)$user->ordinary_savings, 2));
            case '2':
                return $this->release("Special Savings: ₦" . number_format((float)$user->special_savings_balance, 2));
            case '3':
                return $this->release("Shares Capital: ₦" . number_format((float)$user->shares_capital, 2));
            case '4':
                $bal = (float)$user->building_balance + (float)$user->development_fund_balance + (float)$user->welfare_balance;
                return $this->release("Other Funds: ₦" . number_format($bal, 2));
            default:
                return $this->showPassbookMenu($sessionId);
        }
    }

    private function showLoanList($user, $sessionId)
    {
        $loans = QardHasan::where('user_id', $user->id)
            ->whereIn('status', ['active', 'defaulted'])
            ->get();

        if ($loans->isEmpty()) {
            return $this->release("You have no active loans to repay.");
        }

        if ($loans->count() === 1) {
            $loan = $loans->first();
            Cache::put("ussd_session_$sessionId", [
                'state' => 'LOAN_AMOUNT_INPUT',
                'data' => ['loan_id' => $loan->id]
            ], self::SESSION_TTL);
            return $this->prompt("Loan: " . $loan->qard_id_string . "\nBal: ₦" . number_format($loan->remaining_principal, 2) . "\nEnter amount to repay:");
        }

        $menu = "Select Loan:\n";
        $data = [];
        foreach ($loans as $index => $loan) {
            $i = $index + 1;
            $menu .= "$i. " . $loan->qard_id_string . " (₦" . number_format($loan->remaining_principal, 2) . ")\n";
            $data[$i] = $loan->id;
        }
        $menu .= "0. Back";

        Cache::put("ussd_session_$sessionId", [
            'state' => 'LOAN_LIST_MENU',
            'data' => ['loans' => $data]
        ], self::SESSION_TTL);

        return $this->prompt($menu);
    }

    private function handleLoanListMenu($user, $userInput, $sessionId)
    {
        $sessionData = Cache::get("ussd_session_$sessionId");
        if ($userInput === '0') {
            Cache::put("ussd_session_$sessionId", ['state' => 'MAIN_MENU', 'data' => []], self::SESSION_TTL);
            return $this->showMainMenu($user);
        }

        $loanId = $sessionData['data']['loans'][$userInput] ?? null;
        if (!$loanId) {
            return $this->showLoanList($user, $sessionId);
        }

        $loan = QardHasan::find($loanId);
        Cache::put("ussd_session_$sessionId", [
            'state' => 'LOAN_AMOUNT_INPUT',
            'data' => ['loan_id' => $loan->id]
        ], self::SESSION_TTL);

        return $this->prompt("Loan: " . $loan->qard_id_string . "\nBal: ₦" . number_format($loan->remaining_principal, 2) . "\nEnter amount to repay:");
    }

    private function handleLoanAmountInput($user, $userInput, $sessionId, $sessionData)
    {
        $amount = (float) $userInput;
        if ($amount <= 0) {
            return $this->prompt("Invalid amount. Enter amount to repay:");
        }

        $loanId = $sessionData['data']['loan_id'];
        $loan = QardHasan::find($loanId);

        if ($amount > $loan->remaining_principal) {
            $amount = $loan->remaining_principal;
        }

        if ($amount > (float)$user->balance) {
            return $this->release("Insufficient wallet balance. Your balance is ₦" . number_format((float)$user->balance, 2));
        }

        Cache::put("ussd_session_$sessionId", [
            'state' => 'LOAN_REPAY_CONFIRM',
            'data' => ['loan_id' => $loan->id, 'amount' => $amount]
        ], self::SESSION_TTL);

        return $this->prompt("Repay ₦" . number_format($amount, 2) . " for loan " . $loan->qard_id_string . "?\n1. Yes\n2. No");
    }

    private function handleLoanRepayConfirm($user, $userInput, $sessionId, $sessionData)
    {
        if ($userInput !== '1') {
            Cache::put("ussd_session_$sessionId", ['state' => 'MAIN_MENU', 'data' => []], self::SESSION_TTL);
            return $this->showMainMenu($user);
        }

        $loanId = $sessionData['data']['loan_id'];
        $amount = (float)$sessionData['data']['amount'];

        try {
            return DB::transaction(function () use ($user, $loanId, $amount) {
                $user = User::lockForUpdate()->find($user->id);
                $loan = QardHasan::lockForUpdate()->find($loanId);

                if ($amount > (float)$user->balance) {
                    return $this->release("Insufficient wallet balance.");
                }

                $reference = 'QHREP-USSD-' . now()->format('YmdHis') . '-' . $user->id . '-' . Str::upper(Str::random(5));

                // Create repayment record
                $rep = QardHasanRepayment::create([
                    'qard_hasan_id' => $loan->id,
                    'amount' => $amount,
                    'payment_method' => 'ussd',
                    'reference' => $reference,
                    'status' => 'success',
                    'paid_at' => now(),
                ]);

                // Deduct wallet and record transaction
                $user->decrement('balance', $amount);
                WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'reference' => $reference,
                    'source' => 'loan_repayment',
                    'meta' => [
                        'qard_hasan_id' => $loan->id,
                        'qard_id_string' => $loan->qard_id_string,
                        'ussd' => true
                    ],
                ]);

                // Update aggregates
                $loan->paid_amount = (float) $loan->paid_amount + $amount;
                if ($loan->paid_amount >= $loan->principal_amount) {
                    $loan->status = 'completed';
                }
                $loan->save();

                ShariahAudit::log($user, 'repay_qard_hasan_ussd', [
                    'qard' => $loan->qard_id_string,
                    'amount' => $amount,
                    'reference' => $reference,
                ]);

                $msg = "Loan repayment of ₦" . number_format($amount, 2) . " successful. Remaining: ₦" . number_format($loan->remaining_principal, 2);
                $user->notifyMember('Loan Repayment (USSD)', $msg, [
                    'type' => 'loan_repayment',
                    'loan_id' => $loan->id,
                    'repayment_id' => $rep->id,
                    'amount' => $amount,
                ]);

                return $this->release($msg);
            });
        } catch (\Exception $e) {
            Log::error('USSD Repayment error', ['error' => $e->getMessage()]);
            return $this->release("An error occurred while processing your repayment. Please try again later.");
        }
    }

    private function showFundWallet($user)
    {
        $dvaNum = $user->dva_account_number;
        if (!$dvaNum) {
            return $this->release("You don't have a virtual account yet. Please log in to the app to generate one.");
        }

        $msg = "Fund your wallet by transferring to:\n"
             . "Bank: " . $user->dva_bank_name . "\n"
             . "Acc No: " . $dvaNum . "\n"
             . "Name: " . $user->dva_account_name;

        return $this->release($msg);
    }
}
