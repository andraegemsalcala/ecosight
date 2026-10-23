<?php

namespace App\Models;

use App\Enums\AnimalClass;
use App\Enums\AnimalStatusCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Animal extends Model
{
    use HasFactory;

    protected $fillable = [
        'common_name',
        'scientific_name',
        'coco_label',
        'model_source',
        'group',
        'animal_class',
        'is_domestic',
        'model_labels',
        'clip_names',
        'is_scannable',
        'description',
        'habitat',
        'climate_impact',
        'conservation_status',
        'status_category',
        'image_url',
        'image_path',
        'image_alt',
        'image_credit',
        'image_license',
        'image_source_url',
        'image_reviewed_at',
        'wikidata_qid',
        'source_url',
        'source_note',
        'origin',
        'is_reviewed',
        'added_by',
    ];

    protected $casts = [
        'model_labels' => 'array',
        'clip_names' => 'array',
        'is_scannable' => 'boolean',
        'is_reviewed' => 'boolean',
        'is_domestic' => 'boolean',
        'image_reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Animal $animal): void {
            if (blank($animal->status_category)) {
                $animal->status_category = self::statusCategoryFromText($animal->conservation_status);
            }
        });
    }

    public static function statusCategoryFromText(?string $text): string
    {
        $value = Str::lower(trim((string) $text));

        if ($value === '') {
            return AnimalStatusCategory::Unknown->value;
        }

        if (Str::startsWith($value, 'domesticated')) {
            return AnimalStatusCategory::Domesticated->value;
        }

        $categories = [
            AnimalStatusCategory::CriticallyEndangered->value => 'critically endangered',
            AnimalStatusCategory::Endangered->value => 'endangered',
            AnimalStatusCategory::NearThreatened->value => 'near threatened',
            AnimalStatusCategory::Vulnerable->value => 'vulnerable',
            AnimalStatusCategory::LeastConcern->value => 'least concern',
            AnimalStatusCategory::DataDeficient->value => 'data deficient',
            AnimalStatusCategory::ExtinctInTheWild->value => 'extinct in the wild',
            AnimalStatusCategory::Extinct->value => 'extinct',
            AnimalStatusCategory::NotEvaluated->value => 'not evaluated',
        ];

        foreach ($categories as $category => $needle) {
            if (Str::contains($value, $needle)) {
                return $category;
            }
        }

        return AnimalStatusCategory::Unknown->value;
    }

    public function conservationActions(): HasMany
    {
        return $this->hasMany(ConservationAction::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function varieties(): HasMany
    {
        return $this->hasMany(AnimalVariety::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function scopeScannable($query)
    {
        return $query->where('is_scannable', true);
    }

    public function scopeReviewed($query)
    {
        return $query->where('is_reviewed', true);
    }

    public function scopeUnreviewed($query)
    {
        return $query->where('is_reviewed', false);
    }

    public function scopeWithImage($query)
    {
        return $query->where(function ($inner) {
            $inner->whereNotNull('image_path')
                ->orWhereNotNull('image_url');
        });
    }

    public function scopeWithoutImage($query)
    {
        return $query->whereNull('image_path')->whereNull('image_url');
    }

    public function isUserAdded(): bool
    {
        return $this->origin === 'user-added';
    }

    public function hasImage(): bool
    {
        return filled($this->image_path) || filled($this->image_url);
    }

    public function animalClassLabel(): ?string
    {
        return AnimalClass::tryFrom((string) $this->animal_class)?->label();
    }

    public function statusCategoryLabel(): string
    {
        return AnimalStatusCategory::tryFrom((string) $this->status_category)?->label()
            ?? AnimalStatusCategory::Unknown->label();
    }

    protected function imageSrc(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (filled($this->image_path)) {
                return Storage::disk('public')->url($this->image_path);
            }

            return filled($this->image_url) ? $this->image_url : null;
        });
    }

    protected function imageAlt(): Attribute
    {
        return Attribute::get(function (): ?string {
            $alt = $this->attributes['image_alt'] ?? null;

            if (filled($alt)) {
                return $alt;
            }

            return $this->hasImage() ? 'Photo of a '.$this->common_name : null;
        });
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public function imageDimensions(): ?array
    {
        if (blank($this->image_path)) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($this->image_path)) {
            return null;
        }

        $size = getimagesize($disk->path($this->image_path));

        if ($size === false) {
            return null;
        }

        return ['width' => $size[0], 'height' => $size[1]];
    }

    public function groupLabel(): ?string
    {
        if (blank($this->group)) {
            return null;
        }

        return config("ecosight.scope.groups.{$this->group}.label") ?? $this->group;
    }

    /**
     * The two shared conservation actions selected for this animal's group.
     */
    public function generalActions()
    {
        $group = filled($this->group) ? $this->group : 'default';

        $actions = ConservationAction::query()
            ->whereNull('animal_id')
            ->where('is_general', true)
            ->where('group', $group)
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($actions->isEmpty() && $group !== 'default') {
            $actions = ConservationAction::query()
                ->whereNull('animal_id')
                ->where('is_general', true)
                ->where('group', 'default')
                ->orderBy('id')
                ->limit(2)
                ->get();
        }

        return $actions;
    }
}
