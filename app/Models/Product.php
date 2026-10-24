<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'size',
        'version',
        'sale_price',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sale_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'part_product', 'product_id', 'part_id')
            ->withPivot('quantity');
    }
}