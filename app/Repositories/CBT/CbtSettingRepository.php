<?php

namespace App\Repositories\CBT;

use App\Models\CbtSetting;

class CbtSettingRepository
{
    /**
     * Get the single global CBT settings row
     */
    public function get(): CbtSetting
    {
        // ✅ Look for row with id 1, create it if missing
        return CbtSetting::firstOrCreate(
            ['id' => 1], // only one global row
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
        $setting = $this->get(); // fetch existing row

        $setting->update([
            'subjects_count' => $data['subjects_count'],
            'questions_per_subject' => $data['questions_per_subject'],
            'duration_minutes' => $data['duration_minutes'],
            'exam_fee' => $data['exam_fee'],
        ]);

        return $setting;
    }
}
