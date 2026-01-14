<?php

namespace App\Repositories\CBT;

use App\Models\Subject;

class SubjectRepository
{
    public function allActive()
    {
        return Subject::where('status', true)->get(['id', 'name', 'slug']);
    }

    public function findById(string $id)
    {
        return Subject::findOrFail($id);
    }

    public function create(array $data)
    {
        return Subject::create($data);
    }

    public function update(Subject $subject, array $data)
    {
        $subject->update($data);
        return $subject;
    }
}
