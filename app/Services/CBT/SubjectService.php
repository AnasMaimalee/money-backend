<?php

namespace App\Services\CBT;

use App\Repositories\CBT\SubjectRepository;
use Illuminate\Support\Str;
use App\Models\Subject;

class SubjectService
{
    public function __construct(
        protected SubjectRepository $repository
    ) {}

    // Get all active subjects
    public function listSubjects()
    {
        return $this->repository->allActive();
    }

    // Get a single subject
    public function getSubject(string $id): Subject
    {
        return $this->repository->findById($id);
    }

    // Create a new subject
    public function createSubject(array $data): Subject
    {
        $data['id'] = (string) Str::uuid();
        $data['status'] = $data['status'] ?? true;

        return $this->repository->create($data);
    }

    // Update a subject
    public function updateSubject(Subject $subject, array $data): Subject
    {
        return $this->repository->update($subject, $data);
    }
}
