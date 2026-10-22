<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string|null $username
 * @property string|null $name
 * @property string|null $email
 * @property string $password
 * @property string|null $first_name
 * @property string|null $middle_name
 * @property string|null $last_name
 * @property UserRole $role
 * @property Gender $gender
 * @property string|null $phone
 * @property string|null $blood_group
 * @property string|null $address
 * @property string|null $avatar
 * @property bool $is_active
 * @property int|null $legacy_id
 * @property Carbon|null $email_verified_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'first_name',
        'middle_name',
        'last_name',
        'role',
        'gender',
        'phone',
        'blood_group',
        'address',
        'avatar',
        'is_active',
        'legacy_id',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'gender' => Gender::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Role attribute supporting standard UserRole enums and custom Spatie role names.
     *
     * @return Attribute<UserRole|string, UserRole|string>
     */
    protected function role(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => is_string($value) ? (UserRole::tryFrom($value) ?? $value) : $value,
            set: fn ($value) => $value instanceof UserRole ? $value->value : (string) $value,
        );
    }

    /**
     * Mutator to guarantee uppercase name.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? mb_strtoupper(trim(preg_replace('/\s+/', ' ', $value))) : null,
        );
    }

    /**
     * Mutator & accessor to guarantee uppercase first name with fallback to name string.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value !== null && $value !== '') {
                    return $value;
                }
                $rawName = $this->attributes['name'] ?? null;
                if (! empty($rawName)) {
                    $parts = preg_split('/\s+/', trim($rawName));

                    return $parts[0] ?? null;
                }

                return null;
            },
            set: fn (?string $value) => $value !== null ? mb_strtoupper(trim(preg_replace('/\s+/', ' ', $value))) : null,
        );
    }

    /**
     * Mutator to guarantee uppercase middle name.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function middleName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? mb_strtoupper(trim(preg_replace('/\s+/', ' ', $value))) : null,
        );
    }

    /**
     * Mutator & accessor to guarantee uppercase last name with fallback to name string.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function lastName(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value !== null && $value !== '') {
                    return $value;
                }
                $rawName = $this->attributes['name'] ?? null;
                if (! empty($rawName)) {
                    $parts = preg_split('/\s+/', trim($rawName));
                    if (count($parts) > 1) {
                        array_shift($parts);

                        return implode(' ', $parts);
                    }

                    return $parts[0] ?? null;
                }

                return null;
            },
            set: fn (?string $value) => $value !== null ? mb_strtoupper(trim(preg_replace('/\s+/', ' ', $value))) : null,
        );
    }

    /**
     * Computed full name attribute.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $parts = array_filter([$this->first_name, $this->middle_name, $this->last_name]);

                return ! empty($parts) ? implode(' ', $parts) : ($this->name ?? $this->username ?? 'User');
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Role Helper Checks
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPERADMIN || $this->hasRole(UserRole::SUPERADMIN);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [UserRole::SUPERADMIN, UserRole::ADMIN], true)
            || $this->hasAnyRole([UserRole::SUPERADMIN, UserRole::ADMIN]);
    }

    public function isExamOfficer(): bool
    {
        return $this->role === UserRole::EXAM_OFFICER
            || $this->hasRole(UserRole::EXAM_OFFICER)
            || $this->isAdmin();
    }

    public function isTeacher(): bool
    {
        return $this->role === UserRole::TEACHER || $this->hasRole(UserRole::TEACHER);
    }

    public function isStudent(): bool
    {
        return $this->role === UserRole::STUDENT || $this->hasRole(UserRole::STUDENT);
    }

    public function isGuardian(): bool
    {
        return $this->role === UserRole::GUARDIAN || $this->hasRole(UserRole::GUARDIAN);
    }

    /*
    |--------------------------------------------------------------------------
    | Relational Mappings
    |--------------------------------------------------------------------------
    */

    /**
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * @return HasOne<Teacher, $this>
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * @return HasOne<Guardian, $this>
     */
    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function recordedAttendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class, 'recorded_by');
    }
}
