<?php

namespace App\Http\Requests\CBT\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCbtSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subjects_count' => 'required|integer|min:1',
            'questions_per_subject' => 'required|integer|min:1',
            'duration_minutes' => 'required|integer|min:1',
            'exam_fee' => 'required|numeric|min:0',
        ];
    }
}
