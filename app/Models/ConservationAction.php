<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConservationAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'animal_id',
        'title',
        'description',
        'difficulty',
        'category',
        'is_general',
        'group',
    ];

    protected $casts = [
        'is_general' => 'boolean',
    ];

    /**
     * The shared conservation actions: the default ones plus the ones that
     * belong to a group.
     */
    public function scopeGeneral($query, ?string $group = null)
    {
        return $query->whereNull('animal_id')
            ->where(function ($inner) use ($group) {
                $inner->whereNull('group')->orWhere('group', 'default');

                if ($group !== null) {
                    $inner->orWhere('group', $group);
                }
            });
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(ActionCompletion::class);
    }
}
