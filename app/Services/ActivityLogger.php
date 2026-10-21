<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    protected string $logName = 'default';

    protected ?Model $subject = null;

    protected ?Model $causer = null;

    protected ?string $event = null;

    /**
     * @var array<string, mixed>
     */
    protected array $properties = [];

    public function __construct(?string $logName = null)
    {
        if ($logName !== null) {
            $this->logName = $logName;
        }

        // Default causer to authenticated user
        if (Auth::check()) {
            $this->causer = Auth::user();
        }
    }

    public static function make(?string $logName = null): self
    {
        return new self($logName);
    }

    public function useLog(string $logName): self
    {
        $this->logName = $logName;

        return $this;
    }

    public function performedOn(?Model $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function causedBy(?Model $causer): self
    {
        $this->causer = $causer;

        return $this;
    }

    public function event(string $event): self
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function withProperties(array $properties): self
    {
        $this->properties = array_merge($this->properties, $properties);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): self
    {
        $this->properties['context'] = array_merge($this->properties['context'] ?? [], $context);

        return $this;
    }

    public function log(string $description): ActivityLog
    {
        $ip = Request::ip();
        $userAgent = Request::userAgent();
        $url = Request::fullUrl();
        $method = Request::method();

        return ActivityLog::create([
            'log_name' => $this->logName,
            'description' => $description,
            'subject_type' => $this->subject ? $this->subject->getMorphClass() : null,
            'subject_id' => $this->subject ? (int) $this->subject->getKey() : null,
            'causer_type' => $this->causer ? $this->causer->getMorphClass() : null,
            'causer_id' => $this->causer ? (int) $this->causer->getKey() : null,
            'event' => $this->event,
            'properties' => ! empty($this->properties) ? $this->properties : null,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'url' => $url,
            'method' => $method,
        ]);
    }
}
