<?php

namespace App\Services\CBT\SuperAdmin;

use App\Repositories\CBT\SuperAdmin\CbtSettingRepository;

class CbtSettingService
{
    public function __construct(
        private CbtSettingRepository $repository
    ) {}

    /**
     * Fetch settings for frontend
     */
    public function fetch(): array
    {
        $s = $this->repository->get();

        return [
            'subjects_count' => $s->subjects_count,
            'questions_per_subject' => $s->questions_per_subject,
            'duration_minutes' => $s->duration_minutes,
            'exam_fee' => $s->exam_fee,
        ];
    }

    /**
     * Update settings from frontend
     */
    public function update(array $data): array
    {
        $s = $this->repository->update($data);

        return [
            'subjects_count' => $s->subjects_count,
            'questions_per_subject' => $s->questions_per_subject,
            'duration_minutes' => $s->duration_minutes,
            'exam_fee' => $s->exam_fee,
        ];
    }
}
