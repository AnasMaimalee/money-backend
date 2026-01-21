<?php

namespace App\Repositories\CBT\SuperAdmin;

use App\Models\CbtSetting;

class CbtSettingRepository
{
    /**
     * Fetch the single global CBT settings row
     */
    public function get(): CbtSetting
    {
        // Always fetch id=1, create if missing
        return CbtSetting::firstOrCreate(
            ['id' => 1], // single row
            [
                'subjects_count' => 4,
                'questions_per_subject' => 15,
                'duration_minutes' => 120,
                'exam_fee' => 0,
            ]
        );
    }

    /**
     * Update the single global CBT settings row
     */
    public function update(array $data): CbtSetting
    {
        $setting = $this->get();

        $setting->update([
            'subjects_count' => $data['subjects_count'],
            'questions_per_subject' => $data['questions_per_subject'],
            'duration_minutes' => $data['duration_minutes'],
            'exam_fee' => $data['exam_fee'],
        ]);

        return $setting;
    }
}
