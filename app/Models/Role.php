<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string|null $display_name
 * @property string|null $description
 * @property bool $is_system
 * @property string $guard_name
 */
class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
        'guard_name',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
