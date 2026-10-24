<?php

namespace App\Models;

use App\Enums\PlateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plate extends Model
{
    use HasFactory;

    protected $table = 'plates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'filament_code',
        'status',
        'sort_order',
        'consumed_filament_g',
        'completed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => PlateStatus::class,
        'sort_order' => 'integer',
        'consumed_filament_g' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function filament(): BelongsTo
    {
        return $this->belongsTo(Filament::class, 'filament_code', 'code');
    }

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'part_plate', 'plate_id', 'part_id')
            ->withPivot('quantity', 'consumed_filament_g');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'plate_id');
    }
}