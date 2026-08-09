<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\QardHasan;
use App\Models\WithdrawalRequest;
use App\Models\Contribution;
use App\Models\Vendor;
use App\Models\SupportMessage;
use App\Services\VtuBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $request, VtuBalanceService $vtuService)
    {
        $admin = $request->user();
        $branchId = $admin->branch_id;

        // Basic Counts
        $stats = [
            'total_users' => User::when($branchId, fn($q) => $q->where('branch_id', $branchId))->count(),
            'active_loans' => QardHasan::where('status', 'active')
                ->when($branchId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('branch_id', $branchId)))
                ->count(),
            'pending_loans' => QardHasan::where('status', 'pending')
                ->when($branchId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('branch_id', $branchId)))
                ->count(),
            'pending_withdrawals' => WithdrawalRequest::where('status', 'pending')
                ->when($branchId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('branch_id', $branchId)))
                ->count(),
            'pending_vendors' => Vendor::where('is_approved', false)->count(), // Vendors are global usually, but could be filtered if branch-bound
            'unread_support' => SupportMessage::whereNull('read_at')
                ->when($branchId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('branch_id', $branchId)))
                ->count(),
            'defaulters_count' => User::where('is_defaulter', true)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->count(),
        ];

        // VTU Balances
        $vtu_balances = $vtuService->getBalances();

        // Recent Activity (Simplified)
        $recent_users = User::when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->take(5)
            ->get(['id', 'surname', 'name', 'other_names', 'membership_number', 'created_at'])
            ->map(fn($u) => [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'membership_id' => $u->membership_number,
                'created_at' => $u->created_at,
            ]);

        // Liquidity Summary (Approximate)
        $total_deposits = Contribution::where('status', 'success')
            ->when($branchId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('branch_id', $branchId)))
            ->sum('amount');
        $total_withdrawals = WithdrawalRequest::where('status', 'completed')
            ->when($branchId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('branch_id', $branchId)))
            ->sum('amount');

        return response()->json([
            'user' => $admin->load('roles'),
            'stats' => $stats,
            'vtu_balances' => $vtu_balances,
            'recent_users' => $recent_users,
            'liquidity' => [
                'deposits' => $total_deposits,
                'withdrawals' => $total_withdrawals,
                'net' => $total_deposits - $total_withdrawals,
            ]
        ]);
    }
}
