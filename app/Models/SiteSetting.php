<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

final class SiteSetting extends Model
{
    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'value', 'is_secret'];

    /** Settings shown on the public site and editable in the admin "Site details" screen. */
    public const array PUBLIC_KEYS = ['business_name', 'tagline', 'contact_email', 'contact_phone', 'address', 'footer_text', 'jurisdiction'];

    public const array DEFAULTS = [
        'business_name' => 'Link2Social AI',
        'tagline' => 'Turn any website into approved, on-brand social media posts.',
        'contact_email' => '',
        'contact_phone' => '',
        'address' => '',
        'footer_text' => '',
        'jurisdiction' => 'India',
    ];

    public static function read(string $name): ?string
    {
        $row = self::query()->find($name);

        if (! $row || $row->value === null || $row->value === '') {
            return null;
        }

        return $row->is_secret ? Crypt::decryptString($row->value) : $row->value;
    }

    public static function write(string $name, ?string $value, bool $secret = false): void
    {
        self::query()->updateOrCreate(
            ['name' => $name],
            ['value' => ($secret && $value !== null && $value !== '') ? Crypt::encryptString($value) : $value, 'is_secret' => $secret],
        );
    }

    /** @return array<string, string> */
    public static function publicSettings(): array
    {
        $out = self::DEFAULTS;
        foreach (self::query()->whereIn('name', self::PUBLIC_KEYS)->where('is_secret', false)->get() as $row) {
            if ($row->value !== null && $row->value !== '') {
                $out[$row->name] = $row->value;
            }
        }

        return $out;
    }
}
