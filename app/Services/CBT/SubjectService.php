<?php

namespace App\Services\CBT;

use App\Models\Subject;
use App\Repositories\CBT\SubjectRepository;

class SubjectService
{
    public function __construct(
        protected SubjectRepository $repository
    ) {}

    /**
     * List all active subjects (for users & admins)
     */
    public function listSubjects()
    {
        return $this->repository->allActive();
    }

    /**
     * Get single subject
     */
    public function getSubject(string $id): Subject
    {
        return $this->repository->findById($id);
    }

    /**
     * Create new subject (admin)
     */
    public function createSubject(array $data): Subject
    {
        // Default status to true if not provided
        $data['status'] = $data['status'] ?? true;

        return $this->repository->create($data);
    }

    /**
     * Update subject (admin)
     */
    public function updateSubject(Subject $subject, array $data): Subject
    {
        return $this->repository->update($subject, $data);
    }
}
