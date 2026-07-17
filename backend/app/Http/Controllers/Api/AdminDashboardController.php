<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\QardHasan;
use App\Models\WithdrawalRequest;
use App\Models\Contribution;
use App\Models\Vendor;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Basic Counts
        $stats = [
            'total_users' => User::count(),
            'active_loans' => QardHasan::where('status', 'active')->count(),
            'pending_loans' => QardHasan::where('status', 'pending')->count(),
            'pending_withdrawals' => WithdrawalRequest::where('status', 'pending')->count(),
            'pending_vendors' => Vendor::where('is_approved', false)->count(),
            'unread_support' => SupportMessage::whereNull('read_at')->count(),
        ];

        // Recent Activity (Simplified)
        $recent_users = User::latest()
            ->take(5)
            ->get(['id', 'surname', 'name', 'other_names', 'membership_number', 'created_at'])
            ->map(fn($u) => [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'membership_id' => $u->membership_number,
                'created_at' => $u->created_at,
            ]);

        // Liquidity Summary (Approximate)
        $total_deposits = Contribution::where('status', 'success')->sum('amount');
        $total_withdrawals = WithdrawalRequest::where('status', 'completed')->sum('amount');

        return response()->json([
            'stats' => $stats,
            'recent_users' => $recent_users,
            'liquidity' => [
                'deposits' => $total_deposits,
                'withdrawals' => $total_withdrawals,
                'net' => $total_deposits - $total_withdrawals,
            ]
        ]);
    }
}
