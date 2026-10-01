<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Payment extends Model
{
    use BelongsToWorkspace;
    use HasUuids;

    protected $fillable = [
        'workspace_id', 'user_id', 'plan', 'amount_paise', 'currency', 'status',
        'razorpay_order_id', 'razorpay_payment_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount_paise' => 'integer'];
    }
}
