<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $school_name
 * @property string $school_code
 * @property string|null $motto
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string $currency_symbol
 * @property string $currency_code
 * @property string|null $principal_name
 * @property string|null $logo_path
 * @property string|null $stamp_path
 * @property string|null $about_text
 * @property string|null $mission
 * @property string|null $vision
 * @property array<string>|null $core_values
 * @property string|null $principal_message
 * @property string|null $principal_photo_path
 * @property string $office_hours
 * @property string|null $map_embed_url
 * @property array<string, string>|null $social_links
 * @property string|null $prospectus_path
 * @property string|null $letterhead_base64
 * @property int $letterhead_top_margin
 * @property int $letterhead_bottom_margin
 * @property int $letterhead_left_margin
 * @property int $letterhead_right_margin
 * @property int $ca_count
 * @property float $default_max_ca1
 * @property float $default_max_ca2
 * @property float $default_max_ca3
 * @property float $default_max_exam
 * @property string $ca1_label
 * @property string $ca2_label
 * @property string $ca3_label
 * @property-read string|null $logo_url
 * @property-read string|null $stamp_url
 * @property-read string|null $principal_photo_url
 * @property-read string|null $prospectus_url
 */
class SchoolSetting extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'settings';
    }

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
        'ca_count',
        'default_max_ca1',
        'default_max_ca2',
        'default_max_ca3',
        'default_max_exam',
        'ca1_label',
        'ca2_label',
        'ca3_label',
        'logo_path',
        'stamp_path',
        'about_text',
        'mission',
        'vision',
        'core_values',
        'principal_message',
        'principal_photo_path',
        'office_hours',
        'map_embed_url',
        'social_links',
        'prospectus_path',
        'letterhead_base64',
        'letterhead_top_margin',
        'letterhead_bottom_margin',
        'letterhead_left_margin',
        'letterhead_right_margin',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'name',
        'code',
        'logo_url',
        'stamp_url',
        'principal_photo_url',
        'prospectus_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ca_count' => 'integer',
            'letterhead_top_margin' => 'integer',
            'letterhead_bottom_margin' => 'integer',
            'letterhead_left_margin' => 'integer',
            'letterhead_right_margin' => 'integer',
            'default_max_ca1' => 'decimal:2',
            'default_max_ca2' => 'decimal:2',
            'default_max_ca3' => 'decimal:2',
            'default_max_exam' => 'decimal:2',
            'core_values' => 'array',
            'social_links' => 'array',
        ];
    }

    /**
     * Universal alias for school_name.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->school_name ?? '',
        );
    }

    /**
     * Universal alias for school_code.
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->school_code ?? '',
        );
    }

    /**
     * Publicly accessible URL for institutional logo.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
        );
    }

    /**
     * Publicly accessible URL for official principal stamp/signature.
     */
    protected function stampUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->stamp_path ? Storage::disk('public')->url($this->stamp_path) : null,
        );
    }

    /**
     * Publicly accessible URL for principal photo.
     */
    protected function principalPhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->principal_photo_path ? Storage::disk('public')->url($this->principal_photo_path) : null,
        );
    }

    /**
     * Publicly accessible URL for downloaded prospectus.
     */
    protected function prospectusUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->prospectus_path ? Storage::disk('public')->url($this->prospectus_path) : null,
        );
    }

    /**
     * Retrieve the singleton instance of school settings.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'school_name' => 'YUM Academy',
            'school_code' => 'YUM-SA',
            'currency_symbol' => '₦',
            'currency_code' => 'NGN',
        ]);
    }
}
