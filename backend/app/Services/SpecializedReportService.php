<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Scheme;
use App\Models\WalletTransaction;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\CharityEntry;
use App\Models\IncomeEntry;
use App\Models\ExpenseEntry;
use App\Models\StoreOrder;
use App\Models\ProjectProfit;
use App\Models\User;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\JuniorAccount;
use App\Models\TakafulPoolEntry;
use App\Models\AttendanceRecord;
use App\Models\ShariahAudit;

class SpecializedReportService
{
    public function buildZakatReport(float $goldPrice): array
    {
        $nisabNgn = config('cooperative.zakat.nisab_ngn', 500000);
        $rate = config('cooperative.zakat.rate', 0.025);

        // 1. Cooperative's own Zakat (from assets)
        $tb = $this->buildTrialBalance(null, now()->toDateString(), $goldPrice);
        $cash = $tb['accounts']['Cash & Bank']['debit'] - $tb['accounts']['Cash & Bank']['credit'];
        $murabahah = $tb['accounts']['Murabahah Receivables']['debit'] - $tb['accounts']['Murabahah Receivables']['credit'];
        $goldInv = $tb['accounts']['Gold Inventory']['debit'] - $tb['accounts']['Gold Inventory']['credit'];

        $totalCoopZakatable = $cash + $murabahah + $goldInv;
        $coopZakatDue = $totalCoopZakatable >= $nisabNgn ? $totalCoopZakatable * $rate : 0;

        // 2. Member Zakat Summary (Current due)
        $members = User::whereNotNull('zakat_tracking')->get();
        $memberZakatData = $members->map(function ($user) use ($goldPrice, $nisabNgn, $rate) {
            $baseWealth = $user->zakatBaseWealth($goldPrice);
            return [
                'name' => $user->full_name,
                'membership_number' => $user->membership_number,
                'base_wealth' => $baseWealth,
                'zakat_due' => $baseWealth >= $nisabNgn ? $baseWealth * $rate : 0,
            ];
        });

        // 3. Member Zakat Paid History (Amanah)
        $zakatProject = \App\Models\SadaqahProject::where('name', 'General Zakat Fund')->first();
        $totalPaidZakat = 0.0;
        if ($zakatProject) {
            $totalPaidZakat = (float) \App\Models\SadaqahContribution::where('sadaqah_project_id', $zakatProject->id)
                ->where('status', 'success')
                ->sum('amount');
        }

        return [
            'date' => now()->toDateString(),
            'gold_price' => $goldPrice,
            'nisab_ngn' => $nisabNgn,
            'rate' => $rate * 100 . '%',
            'coop_cash_balance' => round($cash, 2),
            'coop_murabahah_receivables' => round($murabahah, 2),
            'coop_gold_inventory' => round($goldInv, 2),
            'coop_zakatable_total' => round($totalCoopZakatable, 2),
            'coop_zakat_due' => round($coopZakatDue, 2),
            'members_count' => $members->count(),
            'total_member_zakat_due' => round($memberZakatData->sum('zakat_due'), 2),
            'total_collected_zakat' => round($totalPaidZakat, 2),
            'member_details' => $memberZakatData,
        ];
    }

    public function buildCharityFundReport(?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfYear();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now();

        $entries = CharityEntry::whereBetween('created_at', [$fromDate, $toDate])->get();

        $inflows = $entries->where('amount', '>', 0);
        $outflows = $entries->where('amount', '<', 0);

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'total_inflow' => round($inflows->sum('amount'), 2),
            'total_outflow' => round(abs($outflows->sum('amount')), 2),
            'net_balance' => round($entries->sum('amount'), 2),
            'details' => $entries->map(fn($e) => [
                'date' => $e->created_at->toDateString(),
                'source' => $e->source,
                'amount' => (float)$e->amount,
                'note' => $e->note,
            ]),
        ];
    }

    public function buildProjectRoiReport(): array
    {
        $projects = \App\Models\Project::with(['investments', 'profits'])->get();

        return $projects->map(function ($p) {
            $invested = $p->investments->sum('amount');
            $grossProfit = $p->profits->sum('gross_profit');
            $mgtFee = $p->profits->sum('management_fee_amount');
            $netDistributable = $p->profits->sum('net_distributable');

            return [
                'project_name' => $p->name,
                'status' => $p->status,
                'capital_invested' => (float)$invested,
                'gross_profit' => (float)$grossProfit,
                'coop_management_fee' => (float)$mgtFee,
                'net_for_investors' => (float)$netDistributable,
                'roi_percent' => $invested > 0 ? round(($grossProfit / $invested) * 100, 2) : 0,
            ];
        })->toArray();
    }

    public function buildProjectDistributionReport(int $projectId): array
    {
        $project = \App\Models\Project::with(['investments.user', 'profits.payouts.user'])->findOrFail($projectId);

        $investments = $project->investments->map(fn($i) => [
            'member' => $i->user?->full_name,
            'amount' => (float)$i->amount,
            'date' => $i->created_at->toDateString(),
        ]);

        $profits = $project->profits->map(fn($p) => [
            'date' => $p->created_at->toDateString(),
            'gross_profit' => (float)$p->gross_profit,
            'management_fee' => (float)$p->management_fee_amount,
            'net_distributable' => (float)$p->net_distributable,
            'payouts' => $p->payouts->map(fn($pay) => [
                'member' => $pay->user?->full_name,
                'amount' => (float)$pay->amount,
                'status' => $pay->status,
            ]),
        ]);

        return [
            'project_name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'total_invested' => (float)$project->investments->sum('amount'),
            'investments' => $investments,
            'profit_history' => $profits,
        ];
    }

    public function buildTakafulPoolReport(): array
    {
        $entries = TakafulPoolEntry::with('user')->orderBy('created_at', 'desc')->get();
        $totalContributions = TakafulPoolEntry::where('direction', 'credit')->sum('amount');
        $totalClaims = TakafulPoolEntry::where('direction', 'debit')->sum('amount');

        return [
            'total_contributions' => (float)$totalContributions,
            'total_claims_paid' => (float)$totalClaims,
            'net_pool_balance' => (float)($totalContributions - $totalClaims),
            'recent_activity' => $entries->take(20)->map(fn($e) => [
                'date' => $e->created_at->toDateString(),
                'member' => $e->user?->full_name ?? 'System',
                'amount' => (float)$e->amount,
                'type' => $e->direction === 'credit' ? 'Contribution' : 'Claim/Payout',
            ]),
        ];
    }

    public function buildGoldSavingsReport(float $goldPrice): array
    {
        $users = User::where('gold_balance', '>', 0)->get();
        $totalWeight = $users->sum('gold_balance');

        return [
            'current_gold_price' => $goldPrice,
            'total_weight_grams' => (float)$totalWeight,
            'total_market_value' => round($totalWeight * $goldPrice, 2),
            'top_holders' => $users->sortByDesc('gold_balance')->take(10)->map(fn($u) => [
                'name' => $u->full_name,
                'weight' => (float)$u->gold_balance,
                'value' => round($u->gold_balance * $goldPrice, 2),
            ])->values(),
        ];
    }

    public function buildVendorSettlementReport(): array
    {
        $vendors = \App\Models\Vendor::with('owner')->get();

        return $vendors->map(function ($v) {
            $totalSales = \App\Models\StoreOrderItem::where('vendor_id', $v->id)->sum('total_amount');
            $vendorEarnings = \App\Models\StoreOrderItem::where('vendor_id', $v->id)->sum('vendor_amount');
            $coopCommission = (float)$totalSales - (float)$vendorEarnings;

            return [
                'vendor_name' => $v->name,
                'owner' => $v->owner?->full_name,
                'total_sales' => (float)$totalSales,
                'vendor_payouts' => (float)$vendorEarnings,
                'coop_commission' => $coopCommission,
            ];
        })->toArray();
    }

    public function buildAttendanceReport(?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfYear();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now();

        $meetings = \App\Models\Meeting::whereBetween('held_at', [$fromDate, $toDate])
            ->with(['attendanceRecords.user'])
            ->get();

        return $meetings->map(function ($m) {
            $present = $m->attendanceRecords->where('status', 'present')->count();
            $absent = $m->attendanceRecords->where('status', 'absent')->count();
            $fines = $m->attendanceRecords->sum('fine_amount');

            return [
                'meeting_title' => $m->title,
                'date' => $m->held_at->toDateString(),
                'present_count' => $present,
                'absent_count' => $absent,
                'total_fines' => (float)$fines,
            ];
        })->toArray();
    }

    public function buildShariaAuditReport(?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfYear();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now();

        $logs = \App\Models\ShariahAuditLog::whereBetween('created_at', [$fromDate, $toDate])->get();

        // Murabahah Summary (Store orders with financing)
        $murabahahOrders = \App\Models\StoreOrder::whereBetween('created_at', [$fromDate, $toDate])
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereJsonContains('meta->financing->type', 'murabaha')
                    ->orWhere('status', 'like', 'murabaha_%');
            })
            ->get();

        $totalMurabahahValue = $murabahahOrders->sum('total_amount');
        $totalMurabahahProfit = $murabahahOrders->sum('total_profit');

        // Project Summary (Mudarabah/Musharakah)
        $projects = \App\Models\Project::whereBetween('created_at', [$fromDate, $toDate])->get();
        $totalProjectCapital = $projects->sum('capital_goal');

        // Takaful Settlement Summary
        $takafulPayouts = \App\Models\TakafulPoolEntry::whereBetween('created_at', [$fromDate, $toDate])
            ->where('direction', 'debit')
            ->get();

        // Zakat Distribution Summary
        $charityDisbursements = \App\Models\CharityEntry::whereBetween('created_at', [$fromDate, $toDate])
            ->where('amount', '<', 0)
            ->where('status', 'processed')
            ->get();

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'total_audits' => $logs->count(),
            'murabahah' => [
                'count' => $murabahahOrders->count(),
                'total_value' => (float)$totalMurabahahValue,
                'total_profit' => (float)$totalMurabahahProfit,
            ],
            'projects' => [
                'count' => $projects->count(),
                'total_capital' => (float)$totalProjectCapital,
            ],
            'takaful' => [
                'count' => $takafulPayouts->count(),
                'total_amount' => (float)$takafulPayouts->sum('amount'),
            ],
            'charity_disbursements' => [
                'count' => $charityDisbursements->count(),
                'total_amount' => abs((float)$charityDisbursements->sum('amount')),
            ],
            'actions_summary' => $logs->groupBy('action')->map(fn($group) => $group->count()),
            'recent_logs' => $logs->take(50)->map(fn($l) => [
                'date' => $l->created_at->toDateTimeString(),
                'action' => $l->action,
                'payload' => $l->payload,
            ]),
        ];
    }

}
