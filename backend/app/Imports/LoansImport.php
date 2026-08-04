<?php

namespace App\Imports;

use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\User;
use App\Support\DurationHelper;
use App\Imports\Concerns\HandlesExcelDates;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Str;

class LoansImport implements ToModel, WithHeadingRow, WithValidation, WithChunkReading
{
    use HandlesExcelDates;

    protected $migrationDate;

    public function __construct($migrationDate = null)
    {
        $this->migrationDate = $migrationDate ?: now();
    }

    public function chunkSize(): int
    {
        return 100;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        \Illuminate\Support\Facades\Log::info("Processing loan row for membership_no: " . ($row['membership_no'] ?? 'N/A'));
        $user = User::where('membership_number', $row['membership_no'])->first();
        if (!$user) {
            \Illuminate\Support\Facades\Log::warning("User not found for membership_no: " . ($row['membership_no'] ?? 'N/A'));
            return null;
        }

        $totalRepaid = (float) ($row['total_repaid_to_date'] ?? 0);
        $originalAmount = (float) $row['original_loan_amount'];

        // Calculate total installments based on remaining principal and installment amount
        $remaining = (float) $row['remaining_principal'];
        $perInstallment = (float) $row['next_installment_amount'];

        $totalInstallments = (int) ($row['total_installments'] ?? 0);
        if ($totalInstallments <= 0) {
            $installmentsLeft = ($perInstallment > 0) ? ceil($remaining / $perInstallment) : 1;
            $installmentsRepaid = ($perInstallment > 0) ? floor($totalRepaid / $perInstallment) : 0;
            $totalInstallments = $installmentsRepaid + $installmentsLeft;
        }

        $receivedAt = $this->parseExcelDate($row['received_at'], $this->migrationDate);
        $defaultedAt = $this->parseExcelDate($row['defaulted_at']);

        // Check for duplicate loan to avoid double entry from csv file
        $existingLoan = QardHasan::where('user_id', $user->id)
            ->where('principal_amount', $originalAmount)
            ->where('received_at', $receivedAt)
            ->first();

        if ($existingLoan) {
            \Illuminate\Support\Facades\Log::info("Duplicate loan found for user_id: {$user->id}, amount: {$originalAmount}, received_at: {$receivedAt}. Skipping.");
            return null;
        }

        // Enforce max duration rules
        $allowedDuration = DurationHelper::getLoanDuration($originalAmount, $receivedAt);
        if ($totalInstallments > $allowedDuration) {
            $totalInstallments = $allowedDuration;
            $perInstallment = round($originalAmount / $totalInstallments, 2);
        }

        $loan = new QardHasan([
            'user_id' => $user->id,
            'qard_id_string' => 'MIG-' . Str::upper(Str::random(8)),
            'principal_amount' => $originalAmount,
            'paid_amount' => $totalRepaid,
            'total_installments' => $totalInstallments,
            'per_installment' => $perInstallment,
            'interval' => strtolower($row['interval'] ?? 'monthly'),
            'status' => ($totalRepaid >= $originalAmount && $originalAmount > 0) ? 'completed' : (($defaultedAt && $defaultedAt->year > 1970 && $defaultedAt->lte(now())) ? 'defaulted' : 'active'),
            'approved_at' => $this->migrationDate,
            'received_at' => $receivedAt,
            'defaulted_at' => $defaultedAt,
        ]);

        // Explicitly set created_at as it might not be in $fillable
        $loan->created_at = $this->migrationDate;

        \Illuminate\Support\Facades\Log::info("Returning loan model for user_id: {$user->id}, qard_id_string: {$loan->qard_id_string}");

        return $loan;
    }

    public function rules(): array
    {
        return [
            'membership_no' => 'required|exists:users,membership_number',
            'original_loan_amount' => 'required|numeric',
            'remaining_principal' => 'required|numeric',
            'next_installment_amount' => 'required|numeric',
            'received_at' => 'nullable',
            'defaulted_at' => 'nullable',
        ];
    }
}
