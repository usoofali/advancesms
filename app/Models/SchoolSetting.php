<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use HasFactory;

    protected $table = 'school_settings';

    protected $fillable = [
        'school_name',
        'school_code',
        'motto',
        'address',
        'phone',
        'email',
        'currency_symbol',
        'currency_code',
        'principal_name',
        'logo_path',
        'stamp_path',
    ];

    /**
     * Retrieve the singleton instance of school settings.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'school_name' => 'School Management System',
            'school_code' => 'SMS-APP',
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
        ]);
    }
}
