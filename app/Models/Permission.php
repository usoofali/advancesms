<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string|null $display_name
 * @property string $group
 * @property string|null $description
 * @property string $guard_name
 */
class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'display_name',
        'group',
        'description',
        'guard_name',
    ];
}
