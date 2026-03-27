<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserUpsertRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AdminUserController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(): JsonResponse
    {
        $admins = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->orderByDesc('role')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->transformUser($user));

        return response()->json(['data' => $admins]);
    }

    public function store(AdminUserUpsertRequest $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Only super admins can manage admins.');

        try {
            $normalizedPhone = PhoneNumber::normalizeIndian($request->string('phone')->toString());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'phone' => $exception->getMessage(),
            ]);
        }

        $payload = [
            'name' => trim($request->string('name')->toString()),
            'city' => $request->filled('city') ? trim($request->input('city')) : null,
            'role' => $request->input('role', User::ROLE_ADMIN),
            'approved_at' => now(),
            'is_active' => $request->boolean('is_active', true),
        ];

        $user = User::query()->updateOrCreate(
            ['phone' => $normalizedPhone],
            $payload,
        );

        $this->auditLogService->record('admin.created', $request->user(), $user, $this->transformUser($user), $request);

        return response()->json([
            'message' => 'Admin saved successfully.',
            'data' => $this->transformUser($user),
        ], $user->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Only super admins can manage admins.');
        abort_if($user->id === $request->user()?->id, 422, 'You cannot remove yourself.');
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true), 404);

        $meta = $this->transformUser($user);
        $user->update([
            'role' => User::ROLE_CUSTOMER,
            'approved_at' => null,
            'is_active' => false,
        ]);

        $this->auditLogService->record('admin.removed', $request->user(), $user, $meta, $request);

        return response()->json(['message' => 'Admin removed successfully.']);
    }

    protected function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'city' => $user->city,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'last_login_at' => optional($user->last_login_at)?->toIso8601String(),
        ];
    }
}
