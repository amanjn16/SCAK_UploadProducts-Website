<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fabric extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'type'];

    public function topFabricProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'top_fabric_id');
    }

    public function dupattaFabricProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'dupatta_fabric_id');
    }
}
