<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LedgerAttachment;
use App\Models\LedgerJournal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class LedgerAttachmentsController extends Controller
{
    public function index(int $journalId)
    {
        if (!Schema::hasTable('ledger_attachments')) {
            return response()->json([
                'message' => 'Migrations pending: ledger_attachments table is missing.'
            ], 503);
        }

        $journal = LedgerJournal::findOrFail($journalId);
        $this->authorize('view', $journal);
        return $journal->attachments()
            ->select(['id', 'original_name', 'mime_type', 'size_bytes', 'path', 'created_at'])
            ->orderByDesc('id')
            ->get();
    }

    public function store(Request $request, int $journalId)
    {
        if (!Schema::hasTable('ledger_attachments')) {
            return response()->json([
                'message' => 'Migrations pending: ledger_attachments table is missing.'
            ], 503);
        }

        $journal = LedgerJournal::findOrFail($journalId);
        $this->authorize('attach', $journal);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20MB
        ]);

        $file = $data['file'];
        $original = $file->getClientOriginalName();
        $mime = $file->getClientMimeType();
        $size = $file->getSize();
        $dir = 'ledger_attachments/' . $journal->id;
        $filename = uniqid('att_') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
        $path = $file->storeAs($dir, $filename, ['disk' => 'public']);

        $attachment = LedgerAttachment::create([
            'ledger_journal_id' => $journal->id,
            'path' => $path,
            'original_name' => $original,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'uploaded_by' => optional(Auth::user())->id,
        ]);

        return response()->json($attachment, 201);
    }

    public function destroy(int $journalId, int $attachmentId)
    {
        if (!Schema::hasTable('ledger_attachments')) {
            return response()->json([
                'message' => 'Migrations pending: ledger_attachments table is missing.'
            ], 503);
        }

        $journal = LedgerJournal::findOrFail($journalId);
        $attachment = $journal->attachments()->where('id', $attachmentId)->firstOrFail();
        $this->authorize('delete', $attachment);

        // Attempt to delete file from storage
        if ($attachment->path) {
            try {
                Storage::disk('public')->delete($attachment->path);
            } catch (\Throwable $e) {
                // ignore storage deletion errors; continue to delete record
            }
        }

        $attachment->delete();
        return response()->json(['status' => 'ok']);
    }
}
