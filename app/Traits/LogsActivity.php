<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model): void {
            if (method_exists($model, 'logModelActivity')) {
                $model->logModelActivity('created');
            }
        });

        static::updated(function (Model $model): void {
            if (method_exists($model, 'logModelActivity')) {
                $model->logModelActivity('updated');
            }
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'logModelActivity')) {
                $model->logModelActivity('deleted');
            }
        });
    }

    /**
     * Determine the log channel/name for this model.
     */
    public function getActivityLogName(): string
    {
        return 'default';
    }

    /**
     * Generate human readable description for this model event.
     */
    public function getActivityDescription(string $event): string
    {
        $modelClass = class_basename($this);
        $nameOrIdentifier = $this->getActivityIdentifier();

        $action = match ($event) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            default => ucfirst($event),
        };

        return "{$action} {$modelClass} '{$nameOrIdentifier}'";
    }

    /**
     * Get an identifying string or name for the model.
     */
    public function getActivityIdentifier(): string
    {
        if (isset($this->name) && is_string($this->name)) {
            return $this->name;
        }

        if (isset($this->admission_number) && is_string($this->admission_number)) {
            return $this->admission_number;
        }

        if (isset($this->code) && is_string($this->code)) {
            return $this->code;
        }

        return (string) $this->getKey();
    }

    /**
     * Attributes that should never be tracked in activity logs.
     *
     * @return array<int, string>
     */
    public function getActivityIgnoredAttributes(): array
    {
        return [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'updated_at',
            'created_at',
        ];
    }

    /**
     * Dispatch the activity log entry for the given event.
     */
    public function logModelActivity(string $event): void
    {
        $ignored = $this->getActivityIgnoredAttributes();
        $properties = [];

        if ($event === 'created') {
            $attributes = Arr::except($this->getAttributes(), $ignored);
            $properties['attributes'] = $attributes;
        } elseif ($event === 'updated') {
            $dirty = Arr::except($this->getDirty(), $ignored);
            if (empty($dirty)) {
                return; // Nothing meaningful changed
            }

            $old = Arr::only($this->getOriginal(), array_keys($dirty));
            $properties['old'] = $old;
            $properties['attributes'] = $dirty;
        } elseif ($event === 'deleted') {
            $properties['old'] = Arr::except($this->getOriginal(), $ignored);
        }

        ActivityLogger::make($this->getActivityLogName())
            ->performedOn($this)
            ->event($event)
            ->withProperties($properties)
            ->log($this->getActivityDescription($event));
    }
}
