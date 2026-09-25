<?php

namespace App\Filament\Resources\UserResource\Traits;

use App\Models\Branch;
use App\Models\User;
use App\Models\Contribution;
use App\Models\Scheme;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\MemberApplication;
use App\Services\ChatService;
use App\Filament\Resources\ChatRoomResource;
use App\Mail\NewMemberWelcome;
use App\Mail\MemberApplicationRejected;
use App\Mail\WalletCredited;
use App\Jobs\SendBulkCommunication;
use App\Services\AttendanceService;
use App\Services\PushService;
use App\Services\SmsService;
use App\Services\TakafulService;
use App\Support\SecurityUtils;
use App\Services\AdministrativeChargeService;
use App\Notifications\WellnessCheckNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait UserResourceActions
{
    // Custom action methods will be moved here
}
