<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'conservation_action_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conservationAction(): BelongsTo
    {
        return $this->belongsTo(ConservationAction::class);
    }
}
