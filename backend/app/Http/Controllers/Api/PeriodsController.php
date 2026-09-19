<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Services\PeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PeriodsController extends Controller
{
    protected function guardMigrations()
    {
        if (!Schema::hasTable('fiscal_periods')) {
            abort(503, 'Migrations for fiscal periods are not yet applied.');
        }
    }

    public function index()
    {
        $this->guardMigrations();
        $this->authorize('viewAny', \App\Models\FiscalPeriod::class);
        return FiscalPeriod::orderBy('starts_on', 'desc')->get();
    }

    public function store(Request $request)
    {
        $this->guardMigrations();
        $this->authorize('create', \App\Models\FiscalPeriod::class);
        $data = $request->validate([
            'name' => 'required|string',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
        ]);
        return FiscalPeriod::create($data);
    }

    public function close($id, Request $request, PeriodService $service)
    {
        $this->guardMigrations();
        $periodModel = FiscalPeriod::findOrFail((int) $id);
        $this->authorize('close', $periodModel);
        $userId = optional($request->user())->id;
        $period = $service->close((int) $id, $userId);
        return $period ?: response()->json(['message' => 'Not found'], 404);
    }

    public function open($id, PeriodService $service)
    {
        $this->guardMigrations();
        $periodModel = FiscalPeriod::findOrFail((int) $id);
        $this->authorize('open', $periodModel);
        $period = $service->open((int) $id);
        return $period ?: response()->json(['message' => 'Not found'], 404);
    }
}
