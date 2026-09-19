<?php

namespace App\Filament\Resources\LedgerJournalResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\LedgerAttachment;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    public function isHidden(): bool
    {
        // Hide the relation if the table doesn't exist (pre-migration guard)
        return !Schema::hasTable('ledger_attachments');
    }

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label('Upload Attachment')
                    ->disk('public')
                    ->directory(fn() => 'ledger_attachments/' . $this->ownerRecord->id)
                    ->required(),
            ]);
    }

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('original_name')->label('File'),
                TextColumn::make('mime_type')->label('Type')->toggleable(),
                TextColumn::make('size_bytes')->label('Size (bytes)')->toggleable(),
                TextColumn::make('created_at')->dateTime()->label('Uploaded'),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Upload')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->disk('public')
                            ->directory(fn() => 'ledger_attachments/' . $this->ownerRecord->id)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        if (!Schema::hasTable('ledger_attachments')) {
                            $this->notify('danger', 'Migrations pending: ledger_attachments table is missing.');
                            return;
                        }

                        $file = $data['file'] ?? null;
                        if (!$file) {
                            $this->notify('warning', 'Please select a file to upload.');
                            return;
                        }

                        // FileUpload stores and returns the path relative to disk root
                        $path = is_string($file) ? $file : null;
                        $originalName = is_string($file) ? basename($file) : null;
                        $size = $path ? (Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : null) : null;

                        LedgerAttachment::create([
                            'ledger_journal_id' => $this->ownerRecord->id,
                            'path' => $path,
                            'original_name' => $originalName,
                            'mime_type' => null,
                            'size_bytes' => $size,
                            'uploaded_by' => optional(auth()->user())->id,
                        ]);

                        $this->notify('success', 'Attachment uploaded.');
                    }),
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->url(fn($record) => $record->path ? Storage::disk('public')->url($record->path) : '#', true)
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ]);
    }
}
