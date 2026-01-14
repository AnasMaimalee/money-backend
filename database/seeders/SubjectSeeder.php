<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['id' => '20b61489-c034-4375-93c4-f36a4c53755e', 'name' => 'Mathematics', 'slug' => 'mathematics'],
            ['id' => 'c272240c-aad5-4164-9c0d-4908aaa6d6e0', 'name' => 'English Language', 'slug' => 'english-language'],
            ['id' => '574fd6ae-1a01-4726-a4bb-67e48f7bab6c', 'name' => 'Physics', 'slug' => 'physics'],
            ['id' => 'c36938d0-e9b6-4d85-9bb2-b8e4b8f81065', 'name' => 'Chemistry', 'slug' => 'chemistry'],
            ['id' => '1ae81861-95cf-42fa-a492-462d6467dde6', 'name' => 'Biology', 'slug' => 'biology'],

            ['id' => '69c97515-7c93-4d90-8c54-6c749a9fbc5b', 'name' => 'Economics', 'slug' => 'economics'],
            ['id' => '0c636dcf-fa5e-400f-b861-657adb36cdf9', 'name' => 'Commerce', 'slug' => 'commerce'],
            ['id' => '55d2400a-e60f-4b6e-adcd-34cde06f349d', 'name' => 'Accounting', 'slug' => 'accounting'],
            ['id' => '53d4f415-c0e9-4ce6-a487-f971980be3c1', 'name' => 'Government', 'slug' => 'government'],
            ['id' => 'c1f6cf51-9ac3-4286-bd9f-1cbfd56eae82', 'name' => 'Business Studies', 'slug' => 'business-studies'],

            ['id' => '16e2c3a7-3c98-4aea-819f-f165e0427dc1', 'name' => 'Literature in English', 'slug' => 'literature-english'],
            ['id' => '68526eb7-0253-4764-9c41-c2503228df08', 'name' => 'History', 'slug' => 'history'],
            ['id' => '97ecd61a-02ce-45b4-90b8-1d670ee1ccbd', 'name' => 'Geography', 'slug' => 'geography'],
            ['id' => '0ba4ff51-06eb-4965-8970-5fa52bd72fee', 'name' => 'Civic Education', 'slug' => 'civic-education'],
            ['id' => '00636dd4-0104-4fab-bdf3-074c0d0c27fc', 'name' => 'C.R.K (Christian Religious Knowledge)', 'slug' => 'crk'],
            ['id' => '710f2f42-a0e3-4525-b4ec-18e8fd0de9d2', 'name' => 'I.R.K (Islamic Religious Knowledge)', 'slug' => 'irk'],

            ['id' => '55285311-027e-419d-a518-24b8483a67ea', 'name' => 'Agricultural Science', 'slug' => 'agricultural-science'],
            ['id' => 'bfe492e5-55a3-469e-b97e-4c86c94ea7da', 'name' => 'Animal Husbandry', 'slug' => 'animal-husbandry'],
            ['id' => '13864c99-7c2d-4379-bc78-ee667b9ef2de', 'name' => 'Fishery', 'slug' => 'fishery'],

            ['id' => '47aeb4b7-9449-4a8b-83f6-3d76efffb1a3', 'name' => 'Computer Science', 'slug' => 'computer-science'],
            ['id' => 'ee5705fc-d15c-4591-b3de-6e4bc22ee82f', 'name' => 'Data Processing', 'slug' => 'data-processing'],
            ['id' => 'eeb80ce2-0c11-4fe2-a55f-c5f241342c08', 'name' => 'Technical Drawing', 'slug' => 'technical-drawing'],
            ['id' => 'e808c38c-9f63-4f82-8f53-7e04b1b19873', 'name' => 'Building Construction', 'slug' => 'building-construction'],
            ['id' => 'e60a8a07-5629-4203-91e6-b0c06d957c20', 'name' => 'Auto Mechanical Works', 'slug' => 'auto-mechanical-works'],

            ['id' => 'f6e89e28-5983-4358-b254-4651c5f9b913', 'name' => 'Hausa', 'slug' => 'hausa'],
            ['id' => '8986d623-e9fb-4c12-87b3-b5bc719b91a5', 'name' => 'Yoruba', 'slug' => 'yoruba'],
            ['id' => 'cc1ceebc-5faa-4c24-9ddf-8a578c407222', 'name' => 'Igbo', 'slug' => 'igbo'],
            ['id' => 'a792b35d-fd86-4cdf-9e7f-b8539ca7fa44', 'name' => 'French', 'slug' => 'french'],

            ['id' => 'bf1fda17-def7-40c1-b9ad-cb3ccb174604', 'name' => 'Further Mathematics', 'slug' => 'further-mathematics'],
            ['id' => '705a4ea9-4782-4484-9df5-e9b3678e4df2', 'name' => 'Foods & Nutrition', 'slug' => 'foods-nutrition'],
            ['id' => 'e6c3ac13-53d8-4ee7-916b-6be18399a7d0', 'name' => 'Health Education', 'slug' => 'health-education'],

            ['id' => '5254b551-21b0-42ae-8143-302647c3a536', 'name' => 'Visual Arts', 'slug' => 'visual-arts'],
            ['id' => '79badda7-d324-4d9f-be4f-c9e8e9b50250', 'name' => 'Music', 'slug' => 'music'],
            ['id' => '5ae22511-c052-4210-add1-b983a21a8a07', 'name' => 'Physical Education', 'slug' => 'physical-education'],
        ];

        foreach ($subjects as $subject) {
            Subject::updateOrCreate(
                ['id' => $subject['id']], // 🔒 FIXED UUID
                [
                    'name' => $subject['name'],
                    'slug' => $subject['slug'],
                    'status' => true,
                ]
            );
        }
    }
}
