<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            // Core Subjects (WAEC/NECO/JAMB)
            ['name' => 'Mathematics', 'slug' => 'mathematics'],
            ['name' => 'English Language', 'slug' => 'english-language'],
            ['name' => 'Physics', 'slug' => 'physics'],
            ['name' => 'Chemistry', 'slug' => 'chemistry'],
            ['name' => 'Biology', 'slug' => 'biology'],

            // Commercial Subjects
            ['name' => 'Economics', 'slug' => 'economics'],
            ['name' => 'Commerce', 'slug' => 'commerce'],
            ['name' => 'Accounting', 'slug' => 'accounting'],
            ['name' => 'Government', 'slug' => 'government'],
            ['name' => 'Business Studies', 'slug' => 'business-studies'],

            // Arts/Social Sciences
            ['name' => 'Literature in English', 'slug' => 'literature-english'],
            ['name' => 'History', 'slug' => 'history'],
            ['name' => 'Geography', 'slug' => 'geography'],
            ['name' => 'Civic Education', 'slug' => 'civic-education'],
            ['name' => 'C.R.K (Christian Religious Knowledge)', 'slug' => 'crk'],
            ['name' => 'I.R.K (Islamic Religious Knowledge)', 'slug' => 'irk'],

            // Agricultural & Technical
            ['name' => 'Agricultural Science', 'slug' => 'agricultural-science'],
            ['name' => 'Animal Husbandry', 'slug' => 'animal-husbandry'],
            ['name' => 'Fishery', 'slug' => 'fishery'],

            // Technical/Vocational
            ['name' => 'Computer Science', 'slug' => 'computer-science'],
            ['name' => 'Data Processing', 'slug' => 'data-processing'],
            ['name' => 'Technical Drawing', 'slug' => 'technical-drawing'],
            ['name' => 'Building Construction', 'slug' => 'building-construction'],
            ['name' => 'Auto Mechanical Works', 'slug' => 'auto-mechanical-works'],

            // Languages
            ['name' => 'Hausa', 'slug' => 'hausa'],
            ['name' => 'Yoruba', 'slug' => 'yoruba'],
            ['name' => 'Igbo', 'slug' => 'igbo'],
            ['name' => 'French', 'slug' => 'french'],

            // Sciences (Additional)
            ['name' => 'Further Mathematics', 'slug' => 'further-mathematics'],
            ['name' => 'Foods & Nutrition', 'slug' => 'foods-nutrition'],
            ['name' => 'Health Education', 'slug' => 'health-education'],

            // Arts
            ['name' => 'Visual Arts', 'slug' => 'visual-arts'],
            ['name' => 'Music', 'slug' => 'music'],

            // Nigerian-specific
            ['name' => 'Physical Education', 'slug' => 'physical-education'],
        ];

        foreach ($subjects as $subject) {
            Subject::firstOrCreate(
                ['slug' => $subject['slug']], // Unique by slug
                [
                    'id' => (string) Str::uuid(),
                    'name' => $subject['name'],
                    'status' => true
                ]
            );
        }
    }
}
