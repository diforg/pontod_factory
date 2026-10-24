<?php

namespace App\Models;

use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilamentMovement extends Model
{
    use HasFactory;

    protected $table = 'filament_movements';

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'filament_code',
        'type',
        'quantity_g',
        'previous_balance_g',
        'new_balance_g',
        'source_type',
        'source_id',
        'occurred_at',
        'user_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => MovementType::class,
        'source_type' => MovementSourceType::class,
        'quantity_g' => 'decimal:2',
        'previous_balance_g' => 'decimal:2',
        'new_balance_g' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function filament(): BelongsTo
    {
        return $this->belongsTo(Filament::class, 'filament_code', 'code');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}