<?php

namespace App\Enums;

enum AnimalKind: string
{
    case Wild = 'wild';
    case Domestic = 'domestic';

    public function label(): string
    {
        return match ($this) {
            self::Wild => 'Wild',
            self::Domestic => 'Domestic',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
