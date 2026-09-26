<?php
namespace App\Filament\Resources;
use App\Filament\Resources\QardHasanResource\Pages;
use App\Filament\Resources\QardHasanResource\RelationManagers;
use App\Models\QardHasan;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
class QardHasanResource extends Resource {
    use QardHasanResource\Traits\HasQardHasanForm;
    use QardHasanResource\Traits\HasQardHasanTable;
    use QardHasanResource\Traits\HasQardHasanInfolist;
    use QardHasanResource\Traits\HasQardHasanActions;
    protected static ?string $model = QardHasan::class;
    protected static ?string $navigationIcon = "heroicon-o-banknotes";
    protected static ?string $navigationGroup = "Loan Management";
    protected static ?string $navigationLabel = "Manage Loans";
    public static function getRelations(): array {
        return [
            RelationManagers\RepaymentsRelationManager::class,
            \App\Filament\RelationManagers\ActivitiesRelationManager::class,
        ];
    }
    public static function getPages(): array {
        return [
            "index" => Pages\ListQardHasans::route("/"),
            "create" => Pages\CreateQardHasan::route("/create"),
            "edit" => Pages\EditQardHasan::route("/{record}/edit"),
        ];
    }
    public static function canViewAny(): bool { return auth()->user()->can("view_loans"); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return auth()->user()->can("edit_loans"); }
    public static function canDelete($record): bool { return auth()->user()->can("delete_loans"); }
    public static function getEloquentQuery(): Builder { return parent::getEloquentQuery()->withoutGlobalScopes([]); }
    public static function getBaseFilteredQuery(): Builder { return static::getModel()::query(); }
}
