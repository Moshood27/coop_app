<?php

namespace App\Observers;

use App\Models\ShariahAuditLog as ShariahAudit;
use Illuminate\Database\Eloquent\Model;

class ShariahAuditObserver
{
    public function created(Model $model): void
    {
        $this->logAction($model, 'Created ' . class_basename($model));
    }

    public function updated(Model $model): void
    {
        // Don't log if no changes were made (e.g. just a touch)
        if (empty($model->getChanges())) {
            return;
        }
        $this->logAction($model, 'Updated ' . class_basename($model));
    }

    public function deleted(Model $model): void
    {
        $this->logAction($model, 'Deleted ' . class_basename($model));
    }

    protected function logAction(Model $model, string $action): void
    {
        $user = auth()->user();

        // If not logged in (e.g. CLI or webhook), try to get user from model
        if (!$user) {
            if (isset($model->user)) {
                $user = $model->user;
            } elseif (isset($model->user_id)) {
                $user = \App\Models\User::find($model->user_id);
            }
        }

        ShariahAudit::log(
            $user,
            $action,
            [
                'model' => get_class($model),
                'id' => $model->id,
                'changes' => $model->getChanges(),
                'attributes' => $model->getAttributes(),
                'ip' => request()->ip(),
                'url' => request()->fullUrl(),
            ]
        );
    }
}
