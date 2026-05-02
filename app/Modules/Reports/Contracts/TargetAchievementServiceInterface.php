<?php

namespace App\Modules\Reports\Contracts;

use Illuminate\Http\Request;

interface TargetAchievementServiceInterface
{
    public function achievement(Request $request, int $perPage): array;
}
