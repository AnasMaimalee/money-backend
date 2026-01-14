<?php
namespace App\Services\CBT;

use App\Repositories\CBT\CbtSettingRepository;

class CbtSettingService
{
    public function __construct(
        protected CbtSettingRepository $repository
    ) {}

    public function getSettings()
    {
        return $this->repository->get();
    }
}
