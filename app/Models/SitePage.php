<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SitePage extends Model
{
    use HasUuids;

    /** slug => default title. These are the only pages the public site serves. */
    public const array SLUGS = [
        'home' => 'Home',
        'about-us' => 'About Us',
        'services' => 'Services',
        'contact-us' => 'Contact Us',
        'privacy-policy' => 'Privacy Policy',
        'refund-policy' => 'Refund Policy',
        'terms-and-conditions' => 'Terms & Conditions',
        'disclaimer' => 'Disclaimer',
    ];

    protected $fillable = ['slug', 'title', 'body', 'meta_description', 'is_published', 'updated_by'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
