<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    use HasFactory;

    protected $table = 'parts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'description',
        'size',
        'filament_g',
        'production_time_min',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'filament_g' => 'decimal:2',
        'production_time_min' => 'integer',
        'is_active' => 'boolean',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'part_product', 'part_id', 'product_id')
            ->withPivot('quantity');
    }

    public function plates(): BelongsToMany
    {
        return $this->belongsToMany(Plate::class, 'part_plate', 'part_id', 'plate_id')
            ->withPivot('quantity', 'consumed_filament_g');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'part_id');
    }
}
