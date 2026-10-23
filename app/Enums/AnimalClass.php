<?php

namespace App\Enums;

enum AnimalClass: string
{
    case Mammal = 'mammal';
    case Bird = 'bird';
    case Reptile = 'reptile';
    case Amphibian = 'amphibian';
    case Insect = 'insect';
    case Arachnid = 'arachnid';
    case OtherInvertebrate = 'other_invertebrate';

    public function label(): string
    {
        return match ($this) {
            self::Mammal => 'Mammals',
            self::Bird => 'Birds',
            self::Reptile => 'Reptiles',
            self::Amphibian => 'Amphibians',
            self::Insect => 'Insects',
            self::Arachnid => 'Arachnids',
            self::OtherInvertebrate => 'Other invertebrates',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
