<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ContactMessage extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'email', 'phone', 'message', 'ip_address', 'is_read'];

    protected function casts(): array
    {
        return ['is_read' => 'boolean'];
    }
}
