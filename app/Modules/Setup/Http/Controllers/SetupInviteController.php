<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Setup\Http\Requests\CreateSetupInviteRequest;
use App\Modules\Setup\Models\SetupInvite;
use App\Modules\Setup\Services\SetupInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetupInviteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_owner, 403);

        $invites = SetupInvite::query()
            ->latest()
            ->limit(25)
            ->get(['id', 'client_name', 'client_email', 'status', 'requested_features', 'expires_on', 'used_at', 'created_at']);

        return response()->json(['data' => $invites]);
    }

    public function store(CreateSetupInviteRequest $request, SetupInviteService $service): JsonResponse
    {
        return response()->json([
            'message' => 'Setup invite created.',
            'data' => $service->create($request->validated(), $request->user()),
        ], 201);
    }

    public function revoke(Request $request, SetupInvite $invite, SetupInviteService $service): JsonResponse
    {
        abort_unless($request->user()?->is_owner, 403);

        return response()->json([
            'message' => 'Setup invite revoked.',
            'data' => $service->revoke($invite),
        ]);
    }
}
