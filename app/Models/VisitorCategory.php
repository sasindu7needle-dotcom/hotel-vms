<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitorCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'badge_color',
        'entrance_fee',
        'access_schedule',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'entrance_fee' => 'decimal:2',
        'access_schedule' => 'array',
    ];

    /** People issued a pass under this category. */
    public function visitors(): HasMany
    {
        return $this->hasMany(VerifiedVisitor::class, 'visitor_category_id');
    }
}
