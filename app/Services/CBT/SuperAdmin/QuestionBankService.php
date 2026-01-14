<?php

namespace App\Services\CBT\SuperAdmin;

use App\Repositories\CBT\SuperAdmin\QuestionBankRepository;

class QuestionBankService
{
    public function __construct(
        protected QuestionBankRepository $repository
    ) {}

    public function listQuestions(array $filters = [], int $perPage = 20)
    {
        return $this->repository->getQuestions($filters, $perPage);
    }

    public function previewQuestion(string $questionId)
    {
        return $this->repository->findQuestion($questionId);
    }
}
