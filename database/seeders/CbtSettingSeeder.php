<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CbtSetting;
use Illuminate\Support\Str;

class CbtSettingSeeder extends Seeder
{
    public function run(): void
    {
        CbtSetting::create([
            'subjects_count' => 4,
            'questions_per_subject' => 15,
            'duration_minutes' => 120,
            'exam_fee' => 1000,
        ]);
    }
}
