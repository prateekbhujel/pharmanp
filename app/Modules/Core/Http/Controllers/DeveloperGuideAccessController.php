<?php

namespace App\Modules\Core\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Http\Controllers\ModularController;
use App\Modules\Core\Http\Requests\DeveloperGuideAccessRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="CORE - Developer Guide",
 *     description="Developer guide access verification"
 * )
 */
class DeveloperGuideAccessController extends ModularController
{
    /**
     * @OA\Post(
     *     path="/developer-guide/access",
     *     summary="Verify developer guide access code",
     *     tags={"CORE - Developer Guide"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DeveloperGuideAccessRequest")),
     *
     *     @OA\Response(response=200, description="Developer guide unlocked"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function __invoke(DeveloperGuideAccessRequest $request): JsonResponse
    {
        $expected = (string) config('pharmanp.developer_guide.access_code');

        $payload = $request->validated();

        if (! hash_equals($expected, (string) $payload['code'])) {
            throw ValidationException::withMessages([
                'code' => 'The developer access code is not valid.',
            ]);
        }

        return response()->json([
            'message' => 'Developer guide unlocked.',
            'data' => [
                'unlocked' => true,
            ],
        ]);
    }
}
