<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['plan_type', 'level', 'percentage', 'cap', 'is_active', 'settings'])]
class CommissionConfiguration extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'cap' => 'decimal:2',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
