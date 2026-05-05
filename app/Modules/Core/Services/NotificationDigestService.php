<?php

namespace App\Modules\Core\Services;

use App\Models\User;

class NotificationDigestService
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function adminDigest(?User $user = null): array
    {
        $summary = $this->dashboard->summary([], $user);

        return [
            'generated_at' => now()->toDateTimeString(),
            'stats' => $summary['stats'] ?? [],
            'low_stock_rows' => $summary['low_stock_rows'] ?? [],
            'expiry_rows' => $summary['expiry_rows'] ?? [],
            'recent_sales' => $summary['recent_sales'] ?? [],
            'recent_purchases' => $summary['recent_purchases'] ?? [],
        ];
    }
}
