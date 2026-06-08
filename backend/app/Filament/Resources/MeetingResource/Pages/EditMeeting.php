<?php

namespace App\Filament\Resources\MeetingResource\Pages;

use App\Filament\Resources\MeetingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMeeting extends EditRecord
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('attendance_qr')
                ->label('Attendance QR')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->modalContent(fn () => view('filament.resources.meetings.attendance-qr', ['meeting' => $this->record]))
                ->modalSubmitAction(false)
                ->visible(fn (): bool => in_array($this->record->status, ['scheduled', 'ongoing'])),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $meeting = $this->record;

        // If it's scheduled, notify about the update
        if ($meeting->status === 'scheduled') {
            $date = $meeting->date->format('l, jS F Y');
            $time = date('h:i A', strtotime($meeting->start_time));

            $meeting->notifyMembers(
                "📝 Meeting Updated: {$meeting->name}",
                "The meeting schedule has been updated to {$date} at {$time}. Please take note.",
                ['type' => 'meeting_updated']
            );
        }
    }
}
