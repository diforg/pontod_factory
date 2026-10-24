<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Filament extends Model
{
    use HasFactory;

    protected $table = 'filaments';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'brand',
        'material',
        'color',
        'weight',
        'price',
        'is_active',
        'balance',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'weight' => 'decimal:2',
        'price' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(FilamentMovement::class, 'filament_code', 'code');
    }

    public function plates(): HasMany
    {
        return $this->hasMany(Plate::class, 'filament_code', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}