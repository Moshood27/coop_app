<?php

namespace App\Filament\Resources\ExpenseEntryResource\Pages;

use App\Filament\Resources\ExpenseEntryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateExpenseEntry extends CreateRecord
{
    protected static string $resource = ExpenseEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        // Ensure roles exist before querying to avoid RoleDoesNotExist exception
        $targetRoles = ['Chairman', 'Treasurer', 'super_admin'];
        $existingRoles = \Spatie\Permission\Models\Role::whereIn('name', $targetRoles)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        if (empty($existingRoles)) {
            return;
        }

        $branchId = $record->creator?->branch_id;
        $admins = \App\Models\User::role($existingRoles)
            ->when($branchId, function($q) use ($branchId) {
                $q->where(function($sub) use ($branchId) {
                    $sub->where('branch_id', $branchId)
                        ->orWhere(function($sq) {
                            $sq->whereNull('branch_id')
                               ->whereHas('roles', fn($r) => $r->where('name', 'super_admin'));
                        });
                });
            }, function($q) {
                $q->whereNull('branch_id');
            })->get();

        $notification = new \App\Notifications\GeneralNotification(
            title: 'New Expense Awaiting Approval',
            message: "An expense for '{$record->title}' of ₦" . number_format($record->amount, 2) . " has been created and requires approval.",
            data: [
                'type' => 'expense_approval',
                'expense_id' => $record->id,
                'route' => 'admin/expense-entries/' . $record->id,
            ]
        );

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }
}
