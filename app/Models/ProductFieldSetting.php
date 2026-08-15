<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFieldSetting extends Model
{
    protected $fillable = ['field_key', 'label', 'enabled_regular', 'enabled_sale', 'required_regular', 'required_sale', 'show_customer', 'show_exports', 'sort_order'];

    protected function casts(): array
    {
        return ['enabled_regular' => 'boolean', 'enabled_sale' => 'boolean', 'required_regular' => 'boolean', 'required_sale' => 'boolean', 'show_customer' => 'boolean', 'show_exports' => 'boolean'];
    }
}
