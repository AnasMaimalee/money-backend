<?php

namespace App\Services\CBT\SuperAdmin;

use App\Repositories\CBT\SuperAdmin\CbtSettingRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CbtSettingService
{
    public function __construct(
        private CbtSettingRepository $repository
    ) {}

    public function getSettings(): array
    {
        $settings = $this->repository->get();
        return [
            'subjects_count' => (int) $settings->subjects_count,
            'questions_per_subject' => (int) $settings->questions_per_subject,
            'total_questions' => (int) $settings->subjects_count * $settings->questions_per_subject,
            'duration_minutes' => (int) $settings->duration_minutes,
            'exam_fee' => (float) $settings->exam_fee,
        ];
    }

    public function updateSettings(array $data): array
    {
        $validator = Validator::make($data, [
            'subjects_count' => 'required|integer|min:1|max:20',
            'questions_per_subject' => 'required|integer|min:5|max:50',
            'duration_minutes' => 'required|integer|min:30|max:300',
            'exam_fee' => 'required|numeric|min:0|max:10000',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $this->repository->validateData($data);
        $this->repository->update($validated);

        return $this->getSettings();
    }
}
