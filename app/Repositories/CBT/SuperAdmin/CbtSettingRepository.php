<?php

namespace App\Repositories\CBT\SuperAdmin;

use App\Models\CbtSetting;

class CbtSettingRepository
{
    public function get(): CbtSetting
    {
        return CbtSetting::getSettings();
    }

    public function update(array $data): bool
    {
        $settings = $this->get();
        return $settings->update($data);
    }

    public function validateData(array $data): array
    {
        return [
            'subjects_count' => $data['subjects_count'] ?? 4,
            'questions_per_subject' => $data['questions_per_subject'] ?? 15,
            'duration_minutes' => $data['duration_minutes'] ?? 120,
            'exam_fee' => (float) ($data['exam_fee'] ?? 0),
        ];
    }
}
