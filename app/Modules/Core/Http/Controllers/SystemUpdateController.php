<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SystemUpdateController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'current_version' => '1.0.0',
                'channel' => config('app.env') == 'production' ? 'stable' : 'development',
                'strategy' => 'Manual backup-first update. No production git pull or migration is run from the browser.',
                'commands' => [
                    'backup' => 'php artisan pharmanp:backup',
                    'update' => 'php artisan pharmanp:update',
                ],
            ],
        ]);
    }
}
