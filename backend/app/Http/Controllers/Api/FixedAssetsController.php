<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciation;
use App\Services\FixedAssetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FixedAssetsController extends Controller
{
    /**
     * Create an asset category with default accounts and parameters.
     */
    public function createCategory(Request $request)
    {
        $this->authorize('create', \App\Models\AssetCategory::class);
        if (!Schema::hasTable('asset_categories')) {
            return response()->json([
                'message' => 'Fixed assets module is not enabled yet. Please run migrations after deployment.'
            ], 503);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'method' => 'nullable|in:straight_line,reducing_balance',
            'useful_life_months' => 'nullable|integer|min:1',
            'rate_percent' => 'nullable|numeric|min:0',
            'asset_account_id' => 'required|integer|exists:ledger_accounts,id',
            'accum_dep_account_id' => 'required|integer|exists:ledger_accounts,id',
            'dep_expense_account_id' => 'required|integer|exists:ledger_accounts,id',
        ]);

        $cat = AssetCategory::create($data);
        return response()->json($cat, 201);
    }

    /**
     * Create an asset and optionally pre-generate depreciation schedules.
     */
    public function createAsset(Request $request)
    {
        $this->authorize('create', \App\Models\Asset::class);
        if (!Schema::hasTable('assets')) {
            return response()->json([
                'message' => 'Fixed assets module is not enabled yet. Please run migrations after deployment.'
            ], 503);
        }

        $data = $request->validate([
            'asset_category_id' => 'required|integer|exists:asset_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'acquisition_date' => 'required|date',
            'acquisition_cost' => 'required|numeric|min:0',
            'residual_value' => 'nullable|numeric|min:0',
            'asset_account_id' => 'nullable|integer|exists:ledger_accounts,id',
            'accum_dep_account_id' => 'nullable|integer|exists:ledger_accounts,id',
            'dep_expense_account_id' => 'nullable|integer|exists:ledger_accounts,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'schedule' => 'nullable|boolean',
        ]);

        /** @var FixedAssetService $svc */
        $svc = app(FixedAssetService::class);
        $asset = $svc->createAsset($data, (bool) ($data['schedule'] ?? true));
        return response()->json($asset->load('category'), 201);
    }

    /**
     * Post a single scheduled depreciation by ID.
     */
    public function postDepreciation(int $id)
    {
        if (!Schema::hasTable('asset_depreciations')) {
            return response()->json([
                'message' => 'Fixed assets module is not enabled yet. Please run migrations after deployment.'
            ], 503);
        }

        $dep = AssetDepreciation::with('asset.category')->findOrFail($id);
        $this->authorize('post', $dep);
        /** @var FixedAssetService $svc */
        $svc = app(FixedAssetService::class);
        $journal = $svc->postDepreciation($dep);
        return response()->json([
            'depreciation' => $dep->fresh(),
            'journal' => $journal,
        ]);
    }
}
