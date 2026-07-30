<?php

namespace App\Filament\Resources\ContributionResource\Pages;

use App\Filament\Resources\ContributionResource;
use App\Models\Contribution;
use App\Models\ShariahAuditLog as ShariahAudit;
use App\Mail\ContributionCreatedNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CreateContribution extends CreateRecord
{
    protected static string $resource = ContributionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $items = $data['items'] ?? [];

        // If no repeater items provided, fall back to single record creation (should not happen on create)
        if (empty($items)) {
            return static::getModel()::create($data);
        }

        $userId = $data['user_id'] ?? null;
        $status = $data['status'] ?? 'pending';
        $paidAt = $data['paid_at'] ?? null;

        if (! $userId) {
            throw ValidationException::withMessages([
                'user_id' => 'Please select a member.',
            ]);
        }

        // Validate distinct schemes and positive amounts
        $schemeIds = [];
        $normalizedItems = [];
        foreach ($items as $index => $item) {
            $schemeId = $item['scheme_id'] ?? null;
            $amount = isset($item['amount']) ? (float) $item['amount'] : null;
            $projectId = $item['project_id'] ?? null;
            $qardHasanId = $item['qard_hasan_id'] ?? null;
            $savingsGroupId = $item['savings_group_id'] ?? null;

            if (! $schemeId) {
                throw ValidationException::withMessages([
                    "items.$index.scheme_id" => 'Please choose a scheme.',
                ]);
            }
            if ($amount === null || $amount <= 0) {
                throw ValidationException::withMessages([
                    "items.$index.amount" => 'Amount must be greater than 0.',
                ]);
            }

            if ($schemeId === 'combined') {
                $sharesScheme = \App\Models\Scheme::where('active', true)->where(fn($q) => $q->where('name', 'Shares')->orWhere('name', 'like', '%share%'))->first();
                $savingsScheme = \App\Models\Scheme::where('active', true)->where(fn($q) => $q->where('name', 'Savings')->orWhere('name', 'like', '%saving%'))->first();

                if (!$sharesScheme || !$savingsScheme) {
                    throw ValidationException::withMessages([
                        "items.$index.scheme_id" => 'Could not find standard "Shares" or "Savings" schemes for splitting.',
                    ]);
                }

                $half = $amount / 2;

                foreach ([$sharesScheme, $savingsScheme] as $s) {
                    if (in_array($s->id, $schemeIds)) {
                        throw ValidationException::withMessages([
                            "items.$index.scheme_id" => "{$s->name} scheme already exists in items. Cannot split.",
                        ]);
                    }
                    $schemeIds[] = $s->id;
                    $row = [
                        'scheme_id' => $s->id,
                        'amount' => $half,
                    ];
                    if (! empty($projectId)) {
                        $row['project_id'] = (int) $projectId;
                    }
                    if (! empty($savingsGroupId)) {
                        $row['savings_group_id'] = (int) $savingsGroupId;
                    }
                    if (! empty($qardHasanId)) {
                        $row['qard_hasan_id'] = (int) $qardHasanId;
                        $row['category'] = 'loan_repayment';
                    } else {
                        if ($s->name === 'Loan Repayment') {
                            $row['category'] = 'loan_repayment';
                        }
                    }
                    $normalizedItems[] = $row;
                }
                continue;
            }

            if (in_array($schemeId, $schemeIds, true)) {
                throw ValidationException::withMessages([
                    "items.$index.scheme_id" => 'Duplicate scheme selected. Each scheme should appear only once.',
                ]);
            }
            $schemeIds[] = $schemeId;
            $row = [
                'scheme_id' => $schemeId,
                'amount' => $amount,
            ];
            if (! empty($projectId)) {
                $row['project_id'] = (int) $projectId;
            }
            if (! empty($savingsGroupId)) {
                $row['savings_group_id'] = (int) $savingsGroupId;
            }
            if (! empty($qardHasanId)) {
                $row['qard_hasan_id'] = (int) $qardHasanId;
                $row['category'] = 'loan_repayment';
            } else {
                // Check if the scheme name is "Loan Repayment" to set the category even if specific loan not linked
                $schemeModel = \App\Models\Scheme::find($schemeId);
                if ($schemeModel && $schemeModel->name === 'Loan Repayment') {
                    $row['category'] = 'loan_repayment';
                }
            }
            $normalizedItems[] = $row;
        }

        $firstRecord = null;
        $createdContributions = [];

        DB::transaction(function () use ($normalizedItems, $userId, $status, $paidAt, &$firstRecord, &$createdContributions) {
            foreach ($normalizedItems as $item) {
                $row = [
                    'user_id' => $userId,
                    'scheme_id' => $item['scheme_id'],
                    'amount' => $item['amount'],
                    'status' => $status,
                    'paid_at' => $paidAt,
                    // Intentionally skip 'reference' to let the model auto-generate unique references
                ];
                if (! empty($item['project_id'])) {
                    $row['project_id'] = (int) $item['project_id'];
                }
                if (! empty($item['savings_group_id'])) {
                    $row['savings_group_id'] = (int) $item['savings_group_id'];
                }
                if (! empty($item['qard_hasan_id'])) {
                    $row['qard_hasan_id'] = (int) $item['qard_hasan_id'];
                }
                if (! empty($item['category'])) {
                    $row['category'] = $item['category'];
                }

                $created = Contribution::create($row);
                $created->load('scheme');
                $createdContributions[] = $created;

                ShariahAudit::log(auth()->user(), 'manual_contribution_created', [
                    'contribution_id' => $created->id,
                    'user_id' => $created->user_id,
                    'scheme_id' => $created->scheme_id,
                    'amount' => $created->amount,
                    'reference' => $created->reference,
                ]);

                if ($firstRecord === null) {
                    $firstRecord = $created;
                }
            }
        });

        if ($status === 'success' && !empty($createdContributions)) {
            $user = \App\Models\User::find($userId);
            if ($user && $user->email) {
                try {
                    Mail::to($user->email)->send(new ContributionCreatedNotification($user, $createdContributions, $user->getTotalBalance()));
                } catch (\Exception $e) {
                    // Log error but don't fail the request
                    report($e);
                }
            }
        }

        // As CreateRecord expects a Model, return the first one created.
        return $firstRecord ?? static::getModel()::create($data);
    }
}
