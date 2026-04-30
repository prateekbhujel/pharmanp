<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserImpersonationController extends Controller
{
    public function start(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless($this->canImpersonate($actor), 403);

        if ((int) $actor->id === (int) $user->id) {
            throw ValidationException::withMessages([
                'user' => 'You are already using this account.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user' => 'Only active users can be impersonated.',
            ]);
        }

        if ($actor->tenant_id && (int) $actor->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        if ($actor->company_id && (int) $actor->company_id !== (int) $user->company_id) {
            abort(404);
        }

        $impersonatorId = $actor->id;

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('impersonator_user_id', $impersonatorId);

        return response()->json([
            'message' => "Now viewing as {$user->name}.",
        ]);
    }

    public function stop(Request $request): JsonResponse
    {
        $impersonatorId = $request->session()->get('impersonator_user_id');

        if (! $impersonatorId) {
            return response()->json(['message' => 'No impersonation session is active.']);
        }

        $impersonator = User::query()->findOrFail($impersonatorId);

        Auth::login($impersonator);
        $request->session()->regenerate();
        $request->session()->forget('impersonator_user_id');

        return response()->json([
            'message' => "Returned to {$impersonator->name}.",
        ]);
    }

    private function canImpersonate(?User $actor): bool
    {
        return (bool) $actor?->is_owner || (bool) $actor?->can('users.manage');
    }
}
