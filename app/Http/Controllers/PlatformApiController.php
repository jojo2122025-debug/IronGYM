<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * PlatformApiController
 *
 * Handles single-gym platform-level operations:
 *   - Manage the gym and its branches
 *   - Manage users and their roles
 *   - View gym-level reports
 *   - Authentication and session state
 *
 * Route prefix: /api/platform/{action}
 * Required role: مدير النظام
 */
class PlatformApiController extends Controller
{
    /**
     * Route handler for /api/platform/{action}
     */
    public function handle(Request $request, string $action): JsonResponse
    {
        $action = strtolower($action);

        $publicActions = ['login'];

        if (!in_array($action, $publicActions, true) && !session()->has('user')) {
            return response()->json(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً'], 401);
        }

        // All platform actions except login require platform admin
        if (!in_array($action, $publicActions, true)) {
            $this->requirePlatformAdmin();
        }

        try {
            return match (true) {
                $action === 'login'                => $this->login($request),
                $action === 'logout'               => $this->logout($request),
                $action === 'get_state'            => $this->getState(),
                $action === 'get_gyms'             => $this->getGyms(),
                $action === 'add_gym'              => $this->addGym($request),
                $action === 'update_gym'           => $this->updateGym($request),
                $action === 'toggle_gym_status'    => $this->toggleGymStatus($request),
                $action === 'add_branch'           => $this->addBranch($request),
                $action === 'update_branch'        => $this->updateBranch($request),
                $action === 'toggle_branch_status' => $this->toggleBranchStatus($request),
                $action === 'set_default_branch'   => $this->setDefaultBranch($request),
                $action === 'get_users'            => $this->getUsers(),
                $action === 'add_user'             => $this->addUser($request),
                $action === 'edit_user'            => $this->editUser($request),
                $action === 'delete_user'          => $this->deleteUser($request),
                $action === 'get_activity_log'     => $this->getActivityLog(),
                $action === 'get_platform_stats'   => $this->getPlatformStats(),
                default => response()->json(['success' => false, 'error' => 'Action not implemented: ' . $action], 404),
            };
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => $e->errors()[array_key_first($e->errors())][0] ?? 'بيانات غير صالحة'], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ---------------------------------------------------------------
    // Guards
    // ---------------------------------------------------------------

    protected function requirePlatformAdmin(): void
    {
        $user = session('user');
        if (!$user || $user['role'] !== 'مدير النظام') {
            abort(response()->json(['success' => false, 'error' => 'هذا المسار مخصص لمشرف المنصة فقط'], 403));
        }
    }

    protected function requireUser(): array
    {
        $user = session('user');
        if (!$user) {
            abort(response()->json(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً'], 401));
        }
        return $user;
    }

    // ---------------------------------------------------------------
    // Auth
    // ---------------------------------------------------------------

    protected function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username'   => 'required|string',
            'password'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'اسم المستخدم وكلمة المرور مطلوبان'], 422);
        }

        $user = User::where('username', $request->input('username'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json(['success' => false, 'error' => 'اسم المستخدم أو كلمة المرور غير صحيحة'], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'هذا المسار مخصص لمشرف المنصة فقط'], 403);
        }

        $sessionData = $user->toSession();
        session(['user' => $sessionData]);
        ActivityLog::log('تسجيل دخول (منصة)', 'دخل المشرف إلى لوحة إدارة المنصة');

        return response()->json([
            'success' => true,
            'user'    => $sessionData,
        ]);
    }

    protected function logout(Request $request): JsonResponse
    {
        ActivityLog::log('تسجيل خروج (منصة)', 'خرج المشرف من لوحة إدارة المنصة');
        $request->session()->forget('user');
        return response()->json(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Platform State (summary for the platform dashboard)
    // ---------------------------------------------------------------

    protected function getState(): JsonResponse
    {
        $gyms = $this->buildTenantState();
        $stats = $this->buildRawPlatformStats();

        return response()->json([
            'success'      => true,
            'tenant'       => $gyms,
            'platformStats'=> $stats,
            'currentUser'  => session('user'),
        ]);
    }

    protected function getPlatformStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'stats'   => $this->buildRawPlatformStats(),
        ]);
    }

    private function buildRawPlatformStats(): array
    {
        $gymCount    = Schema::hasTable('gyms')    ? Gym::count()    : 0;
        $branchCount = Schema::hasTable('branches') ? Branch::count() : 0;
        $userCount   = User::count();

        return [
            'totalGyms'    => $gymCount,
            'totalBranches'=> $branchCount,
            'totalUsers'   => $userCount,
        ];
    }

    private function buildTenantState(): array
    {
        if (!Schema::hasTable('gyms') || !Schema::hasTable('branches')) {
            return ['gyms' => [], 'selectedGymId' => null, 'selectedBranchId' => null];
        }

        $gyms = Gym::with(['branches' => fn ($q) => $q->orderByDesc('is_default')->orderBy('id')])->orderBy('id')->get();

        $currentUser   = session('user') ?: [];
        $selectedGym   = null;
        $selectedBranch = null;

        foreach ($gyms as $gym) {
            if ((string) $gym->id === (string) ($currentUser['gym_id'] ?? null)) {
                $selectedGym = $gym;
            }
            foreach ($gym->branches as $branch) {
                if ((string) $branch->id === (string) ($currentUser['branch_id'] ?? null)) {
                    $selectedBranch = $branch;
                    $selectedGym    = $selectedGym ?: $gym;
                }
            }
        }

        $selectedGym    ??= $gyms->first();
        $selectedBranch ??= $selectedGym?->branches?->firstWhere('is_default', true)
                         ?? $selectedGym?->branches?->first();

        return [
            'gyms' => $gyms->map(fn ($gym) => [
                'id'       => $gym->id,
                'name'     => $gym->name,
                'code'     => $gym->code,
                'status'   => $gym->status,
                'settings' => $gym->settings,
                'branches' => $gym->branches->map(fn ($b) => [
                    'id'         => $b->id,
                    'gym_id'     => $b->gym_id,
                    'name'       => $b->name,
                    'code'       => $b->code,
                    'status'     => $b->status,
                    'address'    => $b->address,
                    'phone'      => $b->phone,
                    'is_default' => (bool) $b->is_default,
                ])->values(),
            ])->values(),
            'selectedGymId'    => $selectedGym?->id,
            'selectedBranchId' => $selectedBranch?->id,
        ];
    }

    // ---------------------------------------------------------------
    // Gyms
    // ---------------------------------------------------------------

    protected function getGyms(): JsonResponse
    {
        $gyms = Gym::with('branches')->orderBy('id')->get();
        return response()->json(['success' => true, 'gyms' => $gyms]);
    }

    protected function addGym(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:gyms,code',
        ])->validate();

        $gym = Gym::create([
            'name'     => $validated['name'],
            'code'     => $validated['code'],
            'status'   => 'active',
            'settings' => ['currency' => 'ILS', 'locale' => 'ar'],
        ]);

        $branch = Branch::create([
            'gym_id'     => $gym->id,
            'name'       => 'الفرع الرئيسي',
            'code'       => 'main',
            'status'     => 'active',
            'is_default' => true,
        ]);

        ActivityLog::log('إضافة صالة', "تم إضافة صالة: {$gym->name}");

        return response()->json([
            'success'       => true,
            'gym'           => $gym,
            'defaultBranch' => $branch,
            'tenant'        => $this->buildTenantState(),
        ]);
    }

    protected function updateGym(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'id'   => 'required|exists:gyms,id',
            'name' => 'required|string|max:100',
        ])->validate();

        $gym = Gym::findOrFail($validated['id']);
        $gym->update(['name' => $validated['name']]);
        ActivityLog::log('تعديل صالة', "تم تعديل صالة: {$gym->name}");

        return response()->json(['success' => true, 'gym' => $gym, 'tenant' => $this->buildTenantState()]);
    }

    protected function toggleGymStatus(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), ['id' => 'required|exists:gyms,id'])->validate();

        $gym = Gym::findOrFail($validated['id']);
        $gym->update(['status' => $gym->status === 'active' ? 'inactive' : 'active']);
        ActivityLog::log('تغيير حالة صالة', "الصالة: {$gym->name} → {$gym->status}");

        return response()->json(['success' => true, 'gym' => $gym, 'tenant' => $this->buildTenantState()]);
    }

    // ---------------------------------------------------------------
    // Branches
    // ---------------------------------------------------------------

    protected function addBranch(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'gym_id' => 'required|exists:gyms,id',
            'name'   => 'required|string|max:100',
            'code'   => 'required|string|max:50',
        ])->validate();

        $branch = Branch::create([
            'gym_id'     => $validated['gym_id'],
            'name'       => $validated['name'],
            'code'       => $validated['code'],
            'status'     => 'active',
            'is_default' => false,
        ]);

        ActivityLog::log('إضافة فرع', "تم إضافة فرع: {$branch->name}");

        return response()->json(['success' => true, 'branch' => $branch, 'tenant' => $this->buildTenantState()]);
    }

    protected function updateBranch(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'id'      => 'required|exists:branches,id',
            'name'    => 'required|string|max:100',
            'address' => 'nullable|string',
            'phone'   => 'nullable|string',
        ])->validate();

        $branch = Branch::findOrFail($validated['id']);
        $branch->update([
            'name'    => $validated['name'],
            'address' => $validated['address'] ?? $branch->address,
            'phone'   => $validated['phone'] ?? $branch->phone,
        ]);

        ActivityLog::log('تعديل فرع', "تم تعديل فرع: {$branch->name}");

        return response()->json(['success' => true, 'branch' => $branch, 'tenant' => $this->buildTenantState()]);
    }

    protected function toggleBranchStatus(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), ['id' => 'required|exists:branches,id'])->validate();

        $branch = Branch::findOrFail($validated['id']);
        $branch->update(['status' => $branch->status === 'active' ? 'inactive' : 'active']);
        ActivityLog::log('تغيير حالة فرع', "الفرع: {$branch->name} → {$branch->status}");

        return response()->json(['success' => true, 'branch' => $branch, 'tenant' => $this->buildTenantState()]);
    }

    protected function setDefaultBranch(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'gym_id'    => 'required|exists:gyms,id',
            'branch_id' => 'required|exists:branches,id',
        ])->validate();

        Branch::where('gym_id', $validated['gym_id'])->update(['is_default' => false]);
        Branch::where('id', $validated['branch_id'])->update(['is_default' => true]);

        ActivityLog::log('تغيير الفرع الافتراضي', "فرع ID: {$validated['branch_id']}");

        return response()->json(['success' => true, 'tenant' => $this->buildTenantState()]);
    }

    // ---------------------------------------------------------------
    // Platform Users
    // ---------------------------------------------------------------

    protected function getUsers(): JsonResponse
    {
        $users = User::select('id', 'username', 'name', 'role', 'gym_id', 'branch_id', 'created_at')->get();
        return response()->json(['success' => true, 'users' => $users]);
    }

    protected function addUser(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'username'  => 'required|string|max:50|unique:users,username',
            'name'      => 'required|string|max:100',
            'password'  => 'required|string|min:6',
            'role'      => 'required|in:' . implode(',', User::ROLES),
            'gym_id'    => 'nullable|exists:gyms,id',
            'branch_id' => 'nullable|exists:branches,id',
        ])->validate();

        $user = User::create([
            'username'  => $validated['username'],
            'name'      => $validated['name'],
            'password'  => Hash::make($validated['password']),
            'role'      => $validated['role'],
            'gym_id'    => $validated['gym_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
        ]);

        ActivityLog::log('إضافة مستخدم', "تم إضافة مستخدم: {$user->username} [{$user->role}]");

        return response()->json(['success' => true, 'user' => $user->only(['id','username','name','role','gym_id','branch_id'])]);
    }

    protected function editUser(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'id'        => 'required|exists:users,id',
            'name'      => 'required|string|max:100',
            'role'      => 'required|in:' . implode(',', User::ROLES),
            'gym_id'    => 'nullable|exists:gyms,id',
            'branch_id' => 'nullable|exists:branches,id',
            'password'  => 'nullable|string|min:6',
        ])->validate();

        $user = User::findOrFail($validated['id']);
        $data = [
            'name'      => $validated['name'],
            'role'      => $validated['role'],
            'gym_id'    => $validated['gym_id'] ?? $user->gym_id,
            'branch_id' => $validated['branch_id'] ?? $user->branch_id,
        ];
        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);
        ActivityLog::log('تعديل مستخدم', "تم تعديل: {$user->username}");

        return response()->json(['success' => true, 'user' => $user->only(['id','username','name','role','gym_id','branch_id'])]);
    }

    protected function deleteUser(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), ['id' => 'required|exists:users,id'])->validate();

        $user = User::findOrFail($validated['id']);
        $current = session('user');
        if ((int) $user->id === (int) ($current['id'] ?? 0)) {
            return response()->json(['success' => false, 'error' => 'لا يمكنك حذف حسابك الحالي'], 422);
        }

        $username = $user->username;
        $user->delete();
        ActivityLog::log('حذف مستخدم', "تم حذف المستخدم: {$username}");

        return response()->json(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Activity Log
    // ---------------------------------------------------------------

    protected function getActivityLog(): JsonResponse
    {
        $log = DB::table('activity_log')->orderByDesc('id')->limit(500)->get();
        return response()->json(['success' => true, 'log' => $log]);
    }
}
