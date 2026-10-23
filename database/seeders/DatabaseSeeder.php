<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AnimalSeeder::class,
            AnimalExpansionSeeder::class,
            AnimalPetSeeder::class,
            AnimalFilterFieldsSeeder::class,
            VarietySeeder::class,
            ConservationActionSeeder::class,
            ConservationActionExpansionSeeder::class,
            ConservationActionPetSeeder::class,
            GeneralActionsSeeder::class,
            ScanSeeder::class,
            ScanFeedbackSeeder::class,
            ScannerEventSeeder::class,
            SurveyResponseSeeder::class,
            ActionCompletionSeeder::class,
            ContactMessageSeeder::class,
        ]);
    }
}
