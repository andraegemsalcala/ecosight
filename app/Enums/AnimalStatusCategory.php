<?php

namespace App\Enums;

enum AnimalStatusCategory: string
{
    case LeastConcern = 'least_concern';
    case NearThreatened = 'near_threatened';
    case Vulnerable = 'vulnerable';
    case Endangered = 'endangered';
    case CriticallyEndangered = 'critically_endangered';
    case ExtinctInTheWild = 'extinct_in_the_wild';
    case Extinct = 'extinct';
    case DataDeficient = 'data_deficient';
    case NotEvaluated = 'not_evaluated';
    case Domesticated = 'domesticated';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::LeastConcern => 'Least Concern',
            self::NearThreatened => 'Near Threatened',
            self::Vulnerable => 'Vulnerable',
            self::Endangered => 'Endangered',
            self::CriticallyEndangered => 'Critically Endangered',
            self::ExtinctInTheWild => 'Extinct in the Wild',
            self::Extinct => 'Extinct',
            self::DataDeficient => 'Data Deficient',
            self::NotEvaluated => 'Not Evaluated',
            self::Domesticated => 'Domesticated',
            self::Unknown => 'Unknown',
        };
    }

    public function sortRank(): int
    {
        return match ($this) {
            self::CriticallyEndangered => 10,
            self::Endangered => 20,
            self::Vulnerable => 30,
            self::NearThreatened => 40,
            self::ExtinctInTheWild => 50,
            self::Extinct => 60,
            self::DataDeficient => 70,
            self::NotEvaluated => 80,
            self::LeastConcern => 90,
            self::Domesticated => 100,
            self::Unknown => 110,
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
