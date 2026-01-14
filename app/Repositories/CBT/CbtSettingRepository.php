<?php
namespace App\Repositories\CBT;

use App\Models\CbtSetting;

class CbtSettingRepository
{
    public function get(): CbtSetting
    {
        return CbtSetting::query()->firstOrFail();
    }
}
