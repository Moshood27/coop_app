<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecurringJournal;
use App\Services\RecurringJournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RecurringJournalsController extends Controller
{
    protected function guardMigrations()
    {
        if (!Schema::hasTable('recurring_journals')) {
            abort(503, 'Migrations for recurring journals are not yet applied.');
        }
    }

    public function index()
    {
        $this->guardMigrations();
        $this->authorize('viewAny', \App\Models\RecurringJournal::class);
        return RecurringJournal::orderBy('id', 'desc')->get();
    }

    public function store(Request $request)
    {
        $this->guardMigrations();
        $this->authorize('create', \App\Models\RecurringJournal::class);
        $data = $request->validate([
            'name' => 'required|string',
            'schedule' => 'nullable|string',
            'template' => 'required|array',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $data['created_by'] = optional($request->user())->id;
        return RecurringJournal::create($data);
    }

    public function runNow($id, RecurringJournalService $service)
    {
        $this->guardMigrations();
        $recurring = RecurringJournal::findOrFail($id);
        $this->authorize('runNow', $recurring);
        $journal = $service->runNow($recurring, now());
        return $journal ?: response()->json(['message' => 'No journal created'], 400);
    }
}
