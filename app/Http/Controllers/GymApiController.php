<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Checkin;
use App\Models\Measurement;
use App\Models\Member;
use App\Models\MembershipCard;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use App\Models\Trainer;
use App\Models\TrainerAssignment;
use App\Models\TrainingProgram;
use App\Models\NutritionProgram;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\SyncEvent;
use App\Models\User;
use App\Support\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class GymApiController extends Controller
{
    /**
     * Route handler for the catch-all /api/{action} endpoint.
     */
    public function handle(Request $request, string $action): JsonResponse
    {
        $method = $request->method();
        $action = strtolower($action);

        // GET-only endpoints (read operations)
        $getOnly = ['get_state', 'get_users', 'get_activity_log', 'global_search'];

        // Endpoints that should still work without an active session:
        // login (and anything needed for login, e.g. global_search if it explicitly required session, etc.)
        $publicActions = ['login'];

        if (!in_array($action, $publicActions, true) && !session()->has('user')) {
            return response()->json([
                'success' => false,
                'error' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        if (!in_array($action, $publicActions, true)) {
            $this->authorizeActionAccess($action);
        }

        try {
            return match (true) {
                $action === 'get_state' => $this->getState(),
                $action === 'login' => $this->login($request),
                $action === 'logout' => $this->logout($request),
                $action === 'add_member' => $this->addMember($request),
                $action === 'edit_member' => $this->editMember($request),
                $action === 'issue_membership_card' => $this->issueMembershipCard($request),
                $action === 'add_plan' => $this->addPlan($request),
                $action === 'edit_plan' => $this->editPlan($request),
                $action === 'add_subscription' => $this->addSubscription($request),
                $action === 'edit_subscription' => $this->editSubscription($request),
                $action === 'toggle_subscription' => $this->toggleSubscription($request),
                $action === 'add_payment' => $this->addPayment($request),
                $action === 'edit_payment' => $this->editPayment($request),
                $action === 'add_product' => $this->addProduct($request),
                $action === 'edit_product' => $this->editProduct($request),
                $action === 'check_in' => $this->checkIn($request),
                $action === 'scan_check_in' => $this->checkIn($request),
                $action === 'check_out' => $this->checkOut($request),
                $action === 'delete_checkin' => $this->deleteCheckin($request),
                $action === 'checkout_basket' => $this->checkoutBasket($request),
                $action === 'get_users' => $this->getUsers(),
                $action === 'add_user' => $this->addUser($request),
                $action === 'edit_user' => $this->editUser($request),
                $action === 'delete_user' => $this->deleteUser($request),
                $action === 'get_activity_log' => $this->getActivityLog(),
                $action === 'add_measurement' => $this->addMeasurement($request),
                $action === 'change_password' => $this->changePassword($request),
                $action === 'add_trainer_profile' => $this->addTrainerProfile($request),
                $action === 'assign_trainer_member' => $this->assignTrainerMember($request),
                $action === 'add_training_program' => $this->addTrainingProgram($request),
                $action === 'edit_training_program' => $this->editTrainingProgram($request),
                $action === 'delete_training_program' => $this->deleteTrainingProgram($request),
                $action === 'add_nutrition_program' => $this->addNutritionProgram($request),
                $action === 'edit_nutrition_program' => $this->editNutritionProgram($request),
                $action === 'delete_nutrition_program' => $this->deleteNutritionProgram($request),
                $action === 'add_notification_template' => $this->addNotificationTemplate($request),
                $action === 'send_notification' => $this->sendNotification($request),
                $action === 'process_notification_jobs' => $this->processNotificationJobs(),
                $action === 'sync_pull_state' => $this->syncPullState(),
                $action === 'sync_push_queue' => $this->syncPushQueue($request),
                $action === 'sync_events_log' => $this->syncEventsLog($request),
                $action === 'import_data' => $this->importData($request),
                $action === 'global_search' => $this->globalSearch($request),
                default => response()->json([
                    'success' => false,
                    'error' => 'Action not implemented: ' . $action,
                ], 404),
            };
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->errors()[array_key_first($e->errors())][0] ?? 'بيانات غير صالحة',
            ], 422);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // -----------------------------------------------------------------
    // Authentication
    // -----------------------------------------------------------------

    protected function login(Request $request): JsonResponse
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'username' => 'required|string',
            'password' => 'required|string',
            'entry_mode' => 'nullable|in:gym,platform',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Username and password are required',
            ], 422);
        }

        $user = User::where('username', $data['username'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'error' => 'اسم المستخدم أو كلمة المرور غير صحيحة',
            ], 401);
        }

        $entryMode = $data['entry_mode'] ?? 'gym';
        if ($entryMode === 'platform' && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'هذا المسار مخصص للمشرف المركزي فقط',
            ], 403);
        }

        $sessionData = $user->toSession();
        session(['user' => $sessionData]);

        ActivityLog::log('تسجيل دخول', 'تم تسجيل الدخول بنجاح إلى النظام');

        $memberData = null;
        $trainerData = null;
        if ($user->isMember() && !empty($user->member_id)) {
            $member = Member::find($user->member_id);
            $subs = Subscription::where('member_id', $user->member_id)->get()->toArray();
            $pays = $member
                ? Payment::where('member_id', $member->id)
                    ->orWhere(function ($q) use ($member) {
                        $q->whereNull('member_id')->where('member_name', $member->name);
                    })
                    ->get()->toArray()
                : [];
            $meas = Measurement::where('member_id', $user->member_id)
                ->orderByDesc('id')->get()->toArray();
            $memberData = [
                'member' => $member?->toArray(),
                'subscriptions' => $subs,
                'payments' => $pays,
                'measurements' => $meas,
            ];
        }

        if ($user->isTrainer()) {
            $trainerData = $this->buildTrainerDataForUserId((int) $user->id);
        }

        return response()->json([
            'success' => true,
            'user' => $sessionData,
            'memberData' => $memberData,
            'trainerData' => $trainerData,
        ]);
    }

    protected function logout(Request $request): JsonResponse
    {
        ActivityLog::log('تسجيل خروج', 'تم تسجيل الخروج من النظام');
        $request->session()->forget('user');
        return response()->json(['success' => true]);
    }

    protected function changePassword(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $data = $request->all();
        $validator = Validator::make($data, [
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'كلمة المرور الحالية والجديدة مطلوبة'], 422);
        }

        $dbUser = User::find($user['id']);
        if (!$dbUser) {
            return response()->json(['success' => false, 'error' => 'المستخدم غير موجود'], 404);
        }
        if (!Hash::check($data['currentPassword'], $dbUser->password)) {
            return response()->json(['success' => false, 'error' => 'كلمة المرور الحالية غير صحيحة'], 401);
        }

        $dbUser->password = Hash::make($data['newPassword']);
        $dbUser->save();

        ActivityLog::log('تغيير كلمة المرور', 'قام المستخدم بتغيير كلمة المرور الخاصة به');
        return response()->json(['success' => true]);
    }

    // -----------------------------------------------------------------
    // State (the big one)
    // -----------------------------------------------------------------

    protected function getState(): JsonResponse
    {
        $members = Member::orderByDesc('id')->get();
        $plans = Plan::orderBy('id')->get();
        $subscriptions = Subscription::orderByDesc('id')->get();
        $payments = Payment::orderByDesc('date')->get();
        $measurements = Measurement::orderByDesc('id')->get();
        $membershipCards = MembershipCard::orderByDesc('id')->get();
        $products = Product::orderBy('id')->get();
        $sales = Sale::orderByDesc('date')->get();
        $checkins = Checkin::orderByDesc('id')->get();
        $trainers = Trainer::with('user:id,name,username,role')->orderByDesc('id')->get();
        $trainerAssignments = TrainerAssignment::orderByDesc('id')->get();
        $trainingPrograms = TrainingProgram::orderByDesc('id')->get();
        $nutritionPrograms = NutritionProgram::orderByDesc('id')->get();
        $notificationTemplates = NotificationTemplate::orderByDesc('id')->get();
        $notifications = Notification::orderByDesc('id')->limit(500)->get();
        // Daily revenues for the last 14 days
        $dailyRevenues = [];
        $dates = [];
        for ($i = 13; $i >= 0; $i--) {
            $dates[] = Carbon::now()->subDays($i)->toDateString();
        }
        foreach ($dates as $date) {
            $dailyRevenues[$date] = 0;
        }
        $paySums = DB::table('payments')
            ->selectRaw("DATE(`date`) as pay_date, SUM(amount) as sum_amt")
            ->groupByRaw('DATE(`date`)')
            ->get();
        foreach ($paySums as $row) {
            if (isset($dailyRevenues[$row->pay_date])) {
                $dailyRevenues[$row->pay_date] = (float) $row->sum_amt;
            }
        }

        // Peak hours (24h buckets)
        $peakHours = [];
        foreach (range(0, 23) as $i) {
            $h = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $peakHours[$h] = 0;
        }
        $checkinsByHour = DB::table('checkins')
            ->selectRaw("SUBSTRING(created_at, 12, 2) as chk_hour, COUNT(*) as cnt")
            ->groupBy('chk_hour')
            ->get();
        foreach ($checkinsByHour as $row) {
            if (isset($peakHours[$row->chk_hour])) {
                $peakHours[$row->chk_hour] = (int) $row->cnt;
            }
        }

        $monthly = Carbon::now()->format('Y-m');
        $today = Carbon::now()->toDateString();
        $reports = $this->buildReportsPayload(Carbon::now());

        $todayRevenue = (float) Payment::where('date', 'like', "$today%")->sum('amount');
        $monthlyRevenue = (float) Payment::where('date', 'like', "$monthly-%")->sum('amount');
        $productSales = (float) Sale::sum('total');

        $memberData = null;
        $trainerData = null;
        $currUser = session('user');
        if ($currUser && $currUser['role'] === 'مشترك' && !empty($currUser['member_id'])) {
            $mId = $currUser['member_id'];
            $member = Member::find($mId);
            $subs = Subscription::where('member_id', $mId)->get();
            $pays = $member
                ? Payment::where('member_id', $member->id)
                    ->orWhere(function ($q) use ($member) {
                        $q->whereNull('member_id')->where('member_name', $member->name);
                    })
                    ->get()
                : collect();
            $meas = Measurement::where('member_id', $mId)->orderByDesc('id')->get();
            $memberData = [
                'member' => $member,
                'subscriptions' => $subs,
                'payments' => $pays,
                'measurements' => $meas,
            ];
        }

        if ($currUser && $currUser['role'] === 'مدرب') {
            $trainerData = $this->buildTrainerDataForUserId((int) ($currUser['id'] ?? 0));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'members' => $members,
                'plans' => $plans,
                'subscriptions' => $subscriptions,
                'payments' => $payments,
                'measurements' => $measurements,
                'membershipCards' => $membershipCards,
                'products' => $products,
                'sales' => $sales,
                'checkins' => $checkins,
                'trainers' => $trainers,
                'trainerAssignments' => $trainerAssignments,
                'trainingPrograms' => $trainingPrograms,
                'nutritionPrograms' => $nutritionPrograms,
                'notificationTemplates' => $notificationTemplates,
                'notifications' => $notifications,
                'todayRevenue' => $todayRevenue,
                'monthlyRevenue' => $monthlyRevenue,
                'todayAttendance' => $checkins->count(),
                'renewalRate' => (float) ($reports['renewalRate'] ?? 0),
                'productSales' => $productSales,
                'revenueHistory' => $dailyRevenues,
                'peakHours' => $peakHours,
                'reports' => $reports,
                'currentUser' => session('user'),
                'memberData' => $memberData,
                'trainerData' => $trainerData,
            ],
        ]);
    }

    protected function getTenantState(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'tenant' => $this->buildTenantState(),
        ]);
    }

    protected function addGym(Request $request): JsonResponse
    {
        $this->requireAdmin();

        if (!Schema::hasTable('gyms') || !Schema::hasTable('branches')) {
            return response()->json(['success' => false, 'error' => 'بنية الصالات غير متوفرة بعد'], 422);
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:50|alpha_dash|unique:gyms,code',
        ]);

        $gym = Gym::create([
            'name' => $data['name'],
            'code' => strtolower($data['code']),
            'status' => 'active',
            'settings' => [
                'currency' => 'ILS',
                'locale' => 'ar',
            ],
        ]);

        $branch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'الفرع الرئيسي',
            'code' => 'main',
            'status' => 'active',
            'is_default' => true,
        ]);

        ActivityLog::log('إضافة صالة', 'تمت إضافة الصالة: ' . $gym->name . ' (' . $gym->code . ')');

        return response()->json([
            'success' => true,
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->name,
                'code' => $gym->code,
            ],
            'defaultBranch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ],
            'tenant' => $this->buildTenantState(),
        ]);
    }

    protected function addBranch(Request $request): JsonResponse
    {
        $this->requireAdmin();

        if (!Schema::hasTable('gyms') || !Schema::hasTable('branches')) {
            return response()->json(['success' => false, 'error' => 'بنية الصالات غير متوفرة بعد'], 422);
        }

        $data = $request->validate([
            'gymId' => 'required|integer|exists:gyms,id',
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:50|alpha_dash',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'isDefault' => 'nullable|boolean',
        ]);

        $gym = Gym::findOrFail($data['gymId']);
        if ($gym->status !== 'active') {
            return response()->json(['success' => false, 'error' => 'لا يمكن إضافة فرع لصالة غير نشطة'], 422);
        }

        $exists = Branch::where('gym_id', $data['gymId'])
            ->whereRaw('LOWER(code) = ?', [strtolower($data['code'])])
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'error' => 'رمز الفرع مستخدم ضمن نفس الصالة'], 422);
        }

        if (!empty($data['isDefault'])) {
            Branch::where('gym_id', $data['gymId'])->update(['is_default' => false]);
        }

        $branch = Branch::create([
            'gym_id' => $data['gymId'],
            'name' => $data['name'],
            'code' => strtolower($data['code']),
            'status' => 'active',
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_default' => (bool) ($data['isDefault'] ?? false),
        ]);

        ActivityLog::log('إضافة فرع', 'تمت إضافة الفرع: ' . $branch->name . ' (' . $branch->code . ') للصالة #' . $branch->gym_id);

        return response()->json([
            'success' => true,
            'branch' => [
                'id' => $branch->id,
                'gym_id' => $branch->gym_id,
                'name' => $branch->name,
                'code' => $branch->code,
                'is_default' => (bool) $branch->is_default,
            ],
            'tenant' => $this->buildTenantState(),
        ]);
    }

    protected function updateGym(Request $request): JsonResponse
    {
        $this->requireAdmin();

        if (!Schema::hasTable('gyms')) {
            return response()->json(['success' => false, 'error' => 'بنية الصالات غير متوفرة بعد'], 422);
        }

        $data = $request->validate([
            'gymId' => 'required|integer|exists:gyms,id',
            'name' => 'required|string|max:120',
            'status' => 'required|in:active,inactive',
        ]);

        $gym = Gym::findOrFail($data['gymId']);
        $reassignment = null;
        DB::transaction(function () use ($gym, $data, &$reassignment): void {
            $targetStatus = $data['status'];

            if ($targetStatus === 'inactive' && $gym->status !== 'inactive') {
                $reassignment = $this->reassignCurrentUserBeforeGymDeactivation($gym->id);
            }

            $gym->name = $data['name'];
            $gym->status = $targetStatus;
            $gym->save();

            if ($targetStatus === 'inactive') {
                Branch::where('gym_id', $gym->id)
                    ->update([
                        'status' => 'inactive',
                        'is_default' => false,
                    ]);
            }
        });

        ActivityLog::log('تعديل صالة', 'تم تحديث الصالة #' . $gym->id . ' إلى الحالة: ' . $gym->status . ' باسم: ' . $gym->name);

        return response()->json([
            'success' => true,
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->name,
                'code' => $gym->code,
                'status' => $gym->status,
            ],
            'reassigned' => $reassignment !== null,
            'reassignedBranch' => $reassignment,
            'tenant' => $this->buildTenantState(),
        ]);
    }

    protected function toggleGymStatus(Request $request): JsonResponse
    {
        $this->requireAdmin();

        if (!Schema::hasTable('gyms') || !Schema::hasTable('branches')) {
            return response()->json(['success' => false, 'error' => 'بنية الصالات غير متوفرة بعد'], 422);
        }

        $data = $request->validate([
            'gymId' => 'required|integer|exists:gyms,id',
            'status' => 'required|in:active,inactive',
        ]);

        $gym = Gym::findOrFail($data['gymId']);
        $targetStatus = $data['status'];

        $reassignment = null;
        DB::transaction(function () use ($gym, $targetStatus, &$reassignment): void {
            if ($targetStatus === 'inactive' && $gym->status !== 'inactive') {
                $reassignment = $this->reassignCurrentUserBeforeGymDeactivation($gym->id);
            }

            $gym->status = $targetStatus;
            $gym->save();

            if ($targetStatus === 'inactive') {
                Branch::where('gym_id', $gym->id)
                    ->update([
                        'status' => 'inactive',
                        'is_default' => false,
                    ]);
            }
        });

        ActivityLog::log('تغيير حالة صالة', 'تم تغيير حالة الصالة #' . $gym->id . ' إلى: ' . $gym->status);

        return response()->json([
            'success' => true,
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->name,
                'code' => $gym->code,
                'status' => $gym->status,
            ],
            'reassigned' => $reassignment !== null,
            'reassignedBranch' => $reassignment,
            'tenant' => $this->buildTenantState(),
        ]);
    }

    protected function updateBranch(Request $request): JsonResponse
    {
        $this->requireAdmin();

        if (!Schema::hasTable('branches')) {
            return response()->json(['success' => false, 'error' => 'بنية الصالات غير متوفرة بعد'], 422);
        }

        $data = $request->validate([
            'branchId' => 'required|integer|exists:branches,id',
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:50|alpha_dash',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'status' => 'required|in:active,inactive',
        ]);

        $branch = Branch::findOrFail($data['branchId']);
        $gym = Gym::find($branch->gym_id);
        if (($data['status'] ?? 'active') === 'active' && (!$gym || $gym->status !== 'active')) {
            return response()->json([
                'success' => false,
                'error' => 'لا يمكن تفعيل فرع داخل صالة غير نشطة',
            ], 422);
        }

        $exists = Branch::where('gym_id', $branch->gym_id)
            ->whereRaw('LOWER(code) = ?', [strtolower($data['code'])])
            ->where('id', '!=', $branch->id)
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'error' => 'رمز الفرع مستخدم ضمن نفس الصالة'], 422);
        }

        DB::transaction(function () use ($branch, $data): void {
            $targetStatus = $data['status'];

            if ($targetStatus === 'inactive' && $branch->status !== 'inactive') {
                $otherActiveCount = Branch::where('gym_id', $branch->gym_id)
                    ->where('id', '!=', $branch->id)
                    ->where('status', 'active')
                    ->count();

                if ($otherActiveCount === 0) {
                    throw new HttpResponseException(response()->json([
                        'success' => false,
                        'error' => 'لا يمكن تعطيل آخر فرع نشط في الصالة',
                    ], 422));
                }

                if ($branch->is_default) {
                    $replacement = Branch::where('gym_id', $branch->gym_id)
                        ->where('id', '!=', $branch->id)
                        ->where('status', 'active')
                        ->orderByDesc('is_default')
                        ->orderBy('id')
                        ->first();

                    if ($replacement) {
                        Branch::where('gym_id', $branch->gym_id)->update(['is_default' => false]);
                        $replacement->is_default = true;
                        $replacement->save();
                    }
                }
            }

            $branch->name = $data['name'];
            $branch->code = strtolower($data['code']);
            $branch->address = $data['address'] ?? null;
            $branch->phone = $data['phone'] ?? null;
            $branch->status = $targetStatus;
            if ($targetStatus === 'inactive') {
                $branch->is_default = false;
            }
            $branch->save();

            if ($targetStatus === 'active') {
                $hasDefault = Branch::where('gym_id', $branch->gym_id)
                    ->where('is_default', true)
                    ->exists();

                if (!$hasDefault) {
                    $branch->is_default = true;
                    $branch->save();
                }
            }
        });

        $branch->refresh();

        ActivityLog::log('تعديل فرع', 'تم تحديث الفرع #' . $branch->id . ' إلى الحالة: ' . $branch->status . ' باسم: ' . $branch->name);

        return response()->json([
            'success' => true,
            'branch' => [
                'id' => $branch->id,
                'gym_id' => $branch->gym_id,
                'name' => $branch->name,
                'code' => $branch->code,
                'status' => $branch->status,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'is_default' => (bool) $branch->is_default,
            ],
            'tenant' => $this->buildTenantState(),
        ]);
    }

    protected function setDefaultBranch(Request $request): JsonResponse
    {
        $this->requireAdmin();

        if (!Schema::hasTable('branches')) {
            return response()->json(['success' => false, 'error' => 'بنية الصالات غير متوفرة بعد'], 422);
        }

        $data = $request->validate([
            'branchId' => 'required|integer|exists:branches,id',
        ]);

        $branch = Branch::findOrFail($data['branchId']);
        if ($branch->status !== 'active') {
            return response()->json([
                'success' => false,
                'error' => 'لا يمكن تعيين فرع غير نشط كافتراضي',
            ], 422);
        }

        Branch::where('gym_id', $branch->gym_id)->update(['is_default' => false]);
        $branch->is_default = true;
        $branch->save();

        ActivityLog::log('تعيين فرع افتراضي', 'تم تعيين الفرع #' . $branch->id . ' كافتراضي للصالة #' . $branch->gym_id);

        return response()->json([
            'success' => true,
            'branch' => [
                'id' => $branch->id,
                'gym_id' => $branch->gym_id,
                'name' => $branch->name,
                'code' => $branch->code,
                'is_default' => true,
            ],
            'tenant' => $this->buildTenantState(),
        ]);
    }

    protected function toggleBranchStatus(Request $request): JsonResponse
    {
        $this->requireAdmin();

        if (!Schema::hasTable('branches')) {
            return response()->json(['success' => false, 'error' => 'بنية الصالات غير متوفرة بعد'], 422);
        }

        $data = $request->validate([
            'branchId' => 'required|integer|exists:branches,id',
            'status' => 'required|in:active,inactive',
        ]);

        $branch = Branch::findOrFail($data['branchId']);
        $targetStatus = $data['status'];

        if ($targetStatus === 'active') {
            $gym = Gym::find($branch->gym_id);
            if (!$gym || $gym->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'error' => 'لا يمكن تفعيل فرع داخل صالة غير نشطة',
                ], 422);
            }
        }

        DB::transaction(function () use ($branch, $targetStatus): void {
            if ($targetStatus === 'inactive' && $branch->status !== 'inactive') {
                $otherActiveCount = Branch::where('gym_id', $branch->gym_id)
                    ->where('id', '!=', $branch->id)
                    ->where('status', 'active')
                    ->count();

                if ($otherActiveCount === 0) {
                    throw new HttpResponseException(response()->json([
                        'success' => false,
                        'error' => 'لا يمكن تعطيل آخر فرع نشط في الصالة',
                    ], 422));
                }

                if ($branch->is_default) {
                    $replacement = Branch::where('gym_id', $branch->gym_id)
                        ->where('id', '!=', $branch->id)
                        ->where('status', 'active')
                        ->orderByDesc('is_default')
                        ->orderBy('id')
                        ->first();

                    if ($replacement) {
                        Branch::where('gym_id', $branch->gym_id)->update(['is_default' => false]);
                        $replacement->is_default = true;
                        $replacement->save();
                    }
                }
            }

            $branch->status = $targetStatus;
            if ($targetStatus === 'inactive') {
                $branch->is_default = false;
            }
            $branch->save();

            if ($targetStatus === 'active') {
                $hasDefault = Branch::where('gym_id', $branch->gym_id)
                    ->where('is_default', true)
                    ->exists();

                if (!$hasDefault) {
                    $branch->is_default = true;
                    $branch->save();
                }
            }
        });

        $branch->refresh();

        ActivityLog::log('تغيير حالة فرع', 'تم تغيير حالة الفرع #' . $branch->id . ' إلى: ' . $branch->status);

        return response()->json([
            'success' => true,
            'branch' => [
                'id' => $branch->id,
                'gym_id' => $branch->gym_id,
                'name' => $branch->name,
                'code' => $branch->code,
                'status' => $branch->status,
                'is_default' => (bool) $branch->is_default,
            ],
            'tenant' => $this->buildTenantState(),
        ]);
    }

    protected function selectBranch(Request $request): JsonResponse
    {
        $this->requireAdmin();

        if (!Schema::hasTable('gyms') || !Schema::hasTable('branches')) {
            return response()->json(['success' => false, 'error' => 'بنية الصالات غير متوفرة بعد'], 422);
        }

        $data = $request->validate([
            'branchId' => 'required|integer|exists:branches,id',
        ]);

        $branch = Branch::with('gym')->findOrFail($data['branchId']);
        if ($branch->status !== 'active') {
            return response()->json(['success' => false, 'error' => 'الفرع غير نشط'], 422);
        }
        if (!$branch->gym || $branch->gym->status !== 'active') {
            return response()->json(['success' => false, 'error' => 'الصالة غير نشطة'], 422);
        }

        $user = $this->requireUser();
        $dbUser = User::find($user['id']);
        if (!$dbUser) {
            return response()->json(['success' => false, 'error' => 'المستخدم غير موجود'], 404);
        }

        $dbUser->gym_id = $branch->gym_id;
        $dbUser->branch_id = $branch->id;
        $dbUser->save();

        session(['user' => array_merge($user, [
            'gym_id' => $branch->gym_id,
            'branch_id' => $branch->id,
        ])]);

        ActivityLog::log('اختيار فرع', 'تم اختيار الفرع #' . $branch->id . ' ضمن الصالة #' . $branch->gym_id);

        return response()->json([
            'success' => true,
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'gym' => [
                    'id' => $branch->gym?->id,
                    'name' => $branch->gym?->name,
                    'code' => $branch->gym?->code,
                ],
            ],
        ]);
    }

    protected function reassignCurrentUserBeforeGymDeactivation(int $deactivatingGymId): ?array
    {
        $sessionUser = session('user');
        if (!is_array($sessionUser)) {
            return null;
        }

        if ((int) ($sessionUser['gym_id'] ?? 0) !== (int) $deactivatingGymId) {
            return null;
        }

        $fallbackBranch = Branch::query()
            ->join('gyms', 'gyms.id', '=', 'branches.gym_id')
            ->where('branches.status', 'active')
            ->where('gyms.status', 'active')
            ->where('branches.gym_id', '!=', $deactivatingGymId)
            ->orderByDesc('branches.is_default')
            ->orderBy('branches.id')
            ->select('branches.id', 'branches.gym_id')
            ->first();

        if (!$fallbackBranch) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'error' => 'لا يمكن تعطيل الصالة الحالية لعدم توفر فرع نشط بديل',
            ], 422));
        }

        $dbUser = User::find($sessionUser['id'] ?? null);
        if (!$dbUser) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'error' => 'المستخدم غير موجود',
            ], 404));
        }

        $dbUser->gym_id = (int) $fallbackBranch->gym_id;
        $dbUser->branch_id = (int) $fallbackBranch->id;
        $dbUser->save();

        session(['user' => array_merge($sessionUser, [
            'gym_id' => (int) $fallbackBranch->gym_id,
            'branch_id' => (int) $fallbackBranch->id,
        ])]);

        return [
            'gym_id' => (int) $fallbackBranch->gym_id,
            'branch_id' => (int) $fallbackBranch->id,
        ];
    }

    protected function buildReportsPayload(Carbon $today): array
    {
        $fromDate = $today->copy()->subDays(29)->startOfDay();
        $todayDate = $today->toDateString();
        $hasSubscriptionsBranchColumn = Schema::hasColumn('subscriptions', 'branch_id');
        $currentBranchId = session('user.branch_id');

        $debtsTotal = (float) Subscription::query()
            ->selectRaw('SUM(CASE WHEN COALESCE(remaining, amount - paid) > 0 THEN COALESCE(remaining, amount - paid) ELSE 0 END) as debt_total')
            ->value('debt_total');

        $debtMembersCount = (int) Subscription::query()
            ->whereRaw('COALESCE(remaining, amount - paid) > 0')
            ->distinct('member_id')
            ->count('member_id');

        $endedLast30Days = (int) Subscription::query()
            ->whereDate('end_date', '>=', $fromDate->toDateString())
            ->whereDate('end_date', '<=', $todayDate)
            ->count();

        $renewedLast30Days = (int) Subscription::query()
            ->whereDate('start_date', '>=', $fromDate->toDateString())
            ->whereDate('start_date', '<=', $todayDate)
            ->whereExists(function ($query) use ($hasSubscriptionsBranchColumn, $currentBranchId) {
                $query->select(DB::raw(1))
                    ->from('subscriptions as prev')
                    ->whereColumn('prev.member_id', 'subscriptions.member_id')
                    ->whereColumn('prev.id', '!=', 'subscriptions.id')
                    ->when($hasSubscriptionsBranchColumn && $currentBranchId, function ($inner) use ($currentBranchId) {
                        $inner->where('prev.branch_id', $currentBranchId);
                    })
                    ->whereDate('prev.end_date', '<', 'subscriptions.start_date');
            })
            ->count();

        $renewalRate = $endedLast30Days > 0
            ? round(($renewedLast30Days / $endedLast30Days) * 100, 2)
            : 0.0;

        $topProductSales = SaleItem::query()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->selectRaw('sale_items.product_id, products.name as product_name, SUM(sale_items.quantity) as total_quantity, SUM(sale_items.total) as total_sales')
            ->groupBy('sale_items.product_id', 'products.name')
            ->orderByDesc('total_quantity')
            ->orderByDesc('total_sales')
            ->limit(7)
            ->get()
            ->map(static function ($row) {
                return [
                    'productId' => (string) $row->product_id,
                    'productName' => (string) $row->product_name,
                    'quantity' => (int) $row->total_quantity,
                    'total' => (float) $row->total_sales,
                ];
            })
            ->values()
            ->all();

        $stockAlerts = Product::query()
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'stock'])
            ->map(static function (Product $product) {
                return [
                    'id' => (string) $product->id,
                    'name' => (string) $product->name,
                    'stock' => (int) $product->stock,
                ];
            })
            ->values()
            ->all();

        $trainers = Trainer::with('user:id,name,username')->orderByDesc('id')->limit(20)->get();
        $trainerPerformance = $trainers->map(static function (Trainer $trainer) {
            $assignmentIds = TrainerAssignment::query()
                ->where('trainer_id', $trainer->id)
                ->pluck('id');

            $activeMembers = (int) TrainerAssignment::query()
                ->where('trainer_id', $trainer->id)
                ->where('status', 'active')
                ->distinct('member_id')
                ->count('member_id');

            $trainingPrograms = $assignmentIds->isEmpty()
                ? 0
                : (int) TrainingProgram::whereIn('trainer_assignment_id', $assignmentIds)->count();

            $nutritionPrograms = $assignmentIds->isEmpty()
                ? 0
                : (int) NutritionProgram::whereIn('trainer_assignment_id', $assignmentIds)->count();

            return [
                'trainerId' => (int) $trainer->id,
                'trainerName' => (string) ($trainer->user->name ?? ('مدرب #' . $trainer->id)),
                'specialty' => (string) ($trainer->specialty ?? ''),
                'activeMembers' => $activeMembers,
                'trainingPrograms' => $trainingPrograms,
                'nutritionPrograms' => $nutritionPrograms,
            ];
        })->values()->all();

        return [
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $todayDate,
            ],
            'debtsTotal' => round($debtsTotal, 2),
            'debtMembersCount' => $debtMembersCount,
            'endedLast30Days' => $endedLast30Days,
            'renewedLast30Days' => $renewedLast30Days,
            'renewalRate' => $renewalRate,
            'attendanceHeatmap' => $this->buildAttendanceHeatmap($fromDate, $today),
            'topProductSales' => $topProductSales,
            'stockAlerts' => $stockAlerts,
            'trainerPerformance' => $trainerPerformance,
        ];
    }

    protected function buildAttendanceHeatmap(Carbon $fromDate, Carbon $today): array
    {
        $weekdayLabels = [
            'الأحد',
            'الاثنين',
            'الثلاثاء',
            'الأربعاء',
            'الخميس',
            'الجمعة',
            'السبت',
        ];

        $counts = array_fill(0, 7, 0);
        $rows = Checkin::query()->get(['created_at', 'time']);
        $rangeEnd = $today->copy()->endOfDay();

        foreach ($rows as $row) {
            $rawTimestamp = $row->created_at ?: $row->time;
            if (!$rawTimestamp) {
                continue;
            }

            try {
                $checkinAt = Carbon::parse($rawTimestamp);
            } catch (\Throwable) {
                continue;
            }

            if ($checkinAt->lt($fromDate) || $checkinAt->gt($rangeEnd)) {
                continue;
            }

            $weekday = (int) $checkinAt->format('w');
            if (isset($counts[$weekday])) {
                $counts[$weekday]++;
            }
        }

        $result = [];
        foreach ($weekdayLabels as $index => $label) {
            $result[] = [
                'weekday' => $label,
                'count' => $counts[$index] ?? 0,
            ];
        }

        return $result;
    }

    protected function buildTrainerDataForUserId(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $trainer = Trainer::with('user:id,name,username,role')
            ->where('user_id', $userId)
            ->first();

        if (!$trainer) {
            return [
                'trainer' => null,
                'assignments' => [],
                'members' => [],
                'subscriptions' => [],
            ];
        }

        $assignments = TrainerAssignment::where('trainer_id', $trainer->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $memberIds = $assignments->pluck('member_id')
            ->filter(fn($id) => !empty($id))
            ->unique()
            ->values();

        $members = $memberIds->isEmpty()
            ? collect()
            : Member::whereIn('id', $memberIds)->orderBy('name')->get();

        $subscriptions = $memberIds->isEmpty()
            ? collect()
            : Subscription::whereIn('member_id', $memberIds)
                ->orderByDesc('end_date')
                ->orderByDesc('id')
                ->get();

        $assignmentIds = $assignments->pluck('id')->values();

        $trainingPrograms = $assignmentIds->isEmpty()
            ? collect()
            : TrainingProgram::whereIn('trainer_assignment_id', $assignmentIds)
                ->orderByDesc('id')
                ->get();

        $nutritionPrograms = $assignmentIds->isEmpty()
            ? collect()
            : NutritionProgram::whereIn('trainer_assignment_id', $assignmentIds)
                ->orderByDesc('id')
                ->get();

        return [
            'trainer' => $trainer,
            'assignments' => $assignments,
            'members' => $members,
            'subscriptions' => $subscriptions,
            'trainingPrograms' => $trainingPrograms,
            'nutritionPrograms' => $nutritionPrograms,
        ];
    }

    // -----------------------------------------------------------------
    // Members
    // -----------------------------------------------------------------

    protected function addMember(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'موظف الاستقبال']);

        $data = $request->all();
        $data['phone'] = trim($data['phone'] ?? '');
        $data['whatsapp'] = trim($data['whatsapp'] ?? '');
        if ($data['whatsapp'] === '') {
            $data['whatsapp'] = null;
        }
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'phone' => ['required','string','regex:/^[0-9]{10}$/'],
            'gender' => 'nullable|in:ذكر,أنثى',
            'whatsapp' => ['nullable','string','regex:/^[0-9]{14}$/'],
        ], [
            'phone.regex' => 'رقم الجوال يجب أن يكون 10 أرقام مثال 0599466586.',
            'whatsapp.regex' => 'رقم الواتساب يجب أن يكون 14 رقمًا مثال 00972599466856.',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $file = $request->file('image');
            $fileName = uniqid('member_', true) . '.' . $file->getClientOriginalExtension();
            $targetDir = public_path('uploads');
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $file->move($targetDir, $fileName);
            $imagePath = 'uploads/' . $fileName;
        }

        $nextId = Member::generateNextId();
        $cardCode = 'GYM-' . $nextId;

        DB::transaction(function () use ($data, $imagePath, $nextId, $cardCode) {
            Member::create([
                'id' => $nextId,
                'membership_number' => $nextId,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'gender' => $data['gender'] ?? 'ذكر',
                'whatsapp' => $data['whatsapp'] ?? null,
                'image_path' => $imagePath,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            MembershipCard::create([
                'member_id' => $nextId,
                'code' => $cardCode,
                'type' => 'qr',
                'status' => 'active',
                'issued_at' => now(),
            ]);
        });

        ActivityLog::log('إضافة مشترك', "تم تسجيل مشترك جديد برقم العضوية: {$nextId} باسم: {$data['name']} وجوال: {$data['phone']} والواتساب: " . ($data['whatsapp'] ?? '—') . " والجنس: " . ($data['gender'] ?? 'ذكر'));

        return response()->json(['success' => true, 'id' => $nextId, 'cardCode' => $cardCode]);
    }

    protected function editMember(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'موظف الاستقبال']);

        $data = $request->all();
        $data['id'] = $data['id'] ?? $data['memberId'] ?? $data['member_id'] ?? null;
        $data['phone'] = trim($data['phone'] ?? '');
        $data['whatsapp'] = trim($data['whatsapp'] ?? '');
        if ($data['whatsapp'] === '') {
            $data['whatsapp'] = null;
        }
        $validator = Validator::make($data, [
            'id' => 'required|string',
            'name' => 'required|string|max:255',
            'phone' => ['required','string','regex:/^[0-9]{10}$/'],
            'gender' => 'nullable|in:ذكر,أنثى',
            'whatsapp' => ['nullable','string','regex:/^[0-9]{14}$/'],
        ], [
            'id.required' => 'معرّف المشترك مطلوب.',
            'phone.regex' => 'رقم الجوال يجب أن يكون 10 أرقام مثال 0599466586.',
            'whatsapp.regex' => 'رقم الواتساب يجب أن يكون 14 رقمًا مثال 00972599466856.',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $member = Member::find($data['id']);
        if (!$member) {
            return response()->json(['success' => false, 'error' => 'المشترك غير موجود'], 404);
        }

        $imagePath = $member->image_path;
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $file = $request->file('image');
            $fileName = uniqid('member_', true) . '.' . $file->getClientOriginalExtension();
            $targetDir = public_path('uploads');
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $file->move($targetDir, $fileName);
            $imagePath = 'uploads/' . $fileName;
        }

        $member->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'gender' => $data['gender'] ?? 'ذكر',
            'whatsapp' => $data['whatsapp'] ?? null,
            'image_path' => $imagePath,
        ]);

        ActivityLog::log('تعديل مشترك', "تم تعديل بيانات المشترك: {$data['id']} ليصبح الاسم: {$data['name']} والجوال: {$data['phone']} والواتساب: " . ($data['whatsapp'] ?? '—') . " والجنس: " . ($data['gender'] ?? 'ذكر'));

        return response()->json(['success' => true]);
    }

    protected function issueMembershipCard(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'موظف الاستقبال']);

        $data = $request->all();
        $data['memberId'] = $data['memberId'] ?? $data['member_id'] ?? null;
        $validator = Validator::make($data, [
            'memberId' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $member = Member::find($data['memberId']);
        if (!$member) {
            return response()->json(['success' => false, 'error' => 'المشترك غير موجود'], 404);
        }

        $newCode = $this->generateUniqueMembershipCardCode($member->id);

        DB::transaction(function () use ($member, $newCode) {
            MembershipCard::where('member_id', $member->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            MembershipCard::create([
                'member_id' => $member->id,
                'code' => $newCode,
                'type' => 'qr',
                'status' => 'active',
                'issued_at' => now(),
            ]);
        });

        ActivityLog::log('إعادة إصدار بطاقة', "تم إصدار بطاقة عضوية جديدة للمشترك: {$member->name} ({$member->id}) بالكود: {$newCode}");

        return response()->json([
            'success' => true,
            'memberId' => $member->id,
            'cardCode' => $newCode,
        ]);
    }

    protected function generateUniqueMembershipCardCode(string $memberId): string
    {
        do {
            $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $code = 'GYM-' . $memberId . '-' . $suffix;
        } while (MembershipCard::where('code', $code)->exists());

        return $code;
    }

    // -----------------------------------------------------------------
    // Plans
    // -----------------------------------------------------------------

    protected function addPlan(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المحاسب']);

        $data = $request->all();
        $validator = Validator::make($data, [
            'name' => 'required|string|max:100',
            'desc' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'days' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $nextId = Plan::generateNextId();
        Plan::create([
            'id' => $nextId,
            'name' => $data['name'],
            'description' => $data['desc'] ?? null,
            'price' => $data['price'],
            'days' => $data['days'],
            'created_at' => now(),
        ]);

        ActivityLog::log('إضافة خطة', "تم إضافة باقة اشتراك جديدة: {$nextId} باسم: {$data['name']} بسعر: {$data['price']} شيكل ولمدة: {$data['days']} يوم");

        return response()->json(['success' => true, 'id' => $nextId]);
    }

    protected function editPlan(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المحاسب']);

        $data = $request->all();
        $data['id'] = $data['id'] ?? $data['planId'] ?? $data['plan_id'] ?? null;
        $validator = Validator::make($data, [
            'id' => 'required|string',
            'name' => 'required|string|max:100',
            'desc' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'days' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $plan = Plan::find($data['id']);
        if (!$plan) {
            return response()->json(['success' => false, 'error' => 'الخطة غير موجودة'], 404);
        }
        $plan->update([
            'name' => $data['name'],
            'description' => $data['desc'] ?? null,
            'price' => $data['price'],
            'days' => $data['days'],
        ]);

        ActivityLog::log('تعديل خطة', "تم تعديل باقة الاشتراك: {$data['id']} لتصبح الاسم: {$data['name']}، السعر: {$data['price']} شيكل، المدة: {$data['days']} يوم");

        return response()->json(['success' => true]);
    }

    // -----------------------------------------------------------------
    // Subscriptions
    // -----------------------------------------------------------------

    protected function addSubscription(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المحاسب', 'موظف الاستقبال']);

        $data = $request->all();
        // Keep compatibility with forms saved by older versions of the interface.
        $data['memberId'] = $data['memberId'] ?? $data['member_id'] ?? null;
        $data['planId'] = $data['planId'] ?? $data['plan_id'] ?? null;
        $data['startDate'] = $data['startDate'] ?? $data['start_date'] ?? null;
        $validator = Validator::make($data, [
            'memberId' => 'required|string',
            'planId' => 'required|string',
            'startDate' => 'required|date',
            'paid' => 'required|numeric|min:0',
        ], [
            'memberId.required' => 'يرجى اختيار المشترك أولًا.',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $plan = Plan::find($data['planId']);
        $member = Member::find($data['memberId']);
        if (!$plan || !$member) {
            return response()->json(['success' => false, 'error' => 'بيانات غير صالحة'], 422);
        }

        $start = Carbon::parse($data['startDate']);
        $end = $start->copy()->addDays($plan->days);
        $amount = (float) $plan->price;
        $paid = (float) $data['paid'];
        $remaining = $amount - $paid;
        $nextSubId = Subscription::generateNextId();

        DB::transaction(function () use ($data, $plan, $member, $start, $end, $amount, $paid, $remaining, $nextSubId) {
            Subscription::create([
                'id' => $nextSubId,
                'member_id' => $data['memberId'],
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'amount' => $amount,
                'paid' => $paid,
                'remaining' => $remaining,
                'status' => 'فعال',
                'created_at' => now(),
            ]);

            if ($paid > 0) {
                Payment::create([
                    'id' => Payment::generateNextId(),
                    'member_id' => $member->id,
                    'subscription_id' => $nextSubId,
                    'member_name' => $member->name,
                    'date' => now()->format('Y-m-d H:i:s'),
                    'amount' => $paid,
                    'method' => 'نقدي',
                    'note' => 'دفعة اشتراك',
                ]);
            }
        });

        ActivityLog::log('تفعيل اشتراك', "تم تفعيل اشتراك جديد رقم {$nextSubId} للمشترك: {$member->name} ({$data['memberId']}) في باقة: {$plan->name} بقيمة: {$amount} شيكل (تم دفع {$paid})");

        return response()->json(['success' => true, 'id' => $nextSubId]);
    }

    protected function editSubscription(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المحاسب']); // Auditor is read-only
        $this->requireNotReadOnly();

        $data = $request->all();
        $validator = Validator::make($data, [
            'subId' => 'required|string',
            'planId' => 'nullable|string|max:10',
            'planName' => 'nullable|string|max:100',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'paid' => 'required|numeric|min:0',
            'status' => 'required|in:فعال,منتهي,مجمد',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $sub = Subscription::find($data['subId']);
        if (!$sub) {
            return response()->json(['success' => false, 'error' => 'الاشتراك غير موجود'], 404);
        }

        $selectedPlan = null;
        if (!empty($data['planId'])) {
            $selectedPlan = Plan::find($data['planId']);
            if (!$selectedPlan) {
                return response()->json(['success' => false, 'error' => 'الخطة غير موجودة'], 422);
            }
        } elseif (!empty($data['planName'])) {
            $selectedPlan = Plan::where('name', $data['planName'])->first();
        }

        if (!$selectedPlan && empty($data['planName'])) {
            return response()->json(['success' => false, 'error' => 'الخطة مطلوبة'], 422);
        }

        $targetPlanName = $selectedPlan?->name ?? $data['planName'];
        $targetPlanId = $selectedPlan?->id;

        $remaining = max(0.0, (float) $data['amount'] - (float) $data['paid']);
        $sub->update([
            'plan_id' => $targetPlanId,
            'plan_name' => $targetPlanName,
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'],
            'amount' => $data['amount'],
            'paid' => $data['paid'],
            'remaining' => $remaining,
            'status' => $data['status'],
        ]);

        $details = "تعديل الاشتراك رقم {$data['subId']}: الباقة ({$sub->getOriginal('plan_name')} -> {$targetPlanName})، تاريخ البدء ({$sub->getOriginal('start_date')} -> {$data['startDate']})، تاريخ الانتهاء ({$sub->getOriginal('end_date')} -> {$data['endDate']})، القيمة (" . number_format((float) $sub->getOriginal('amount'), 2) . " -> " . number_format((float) $data['amount'], 2) . " ₪)، المدفوع (" . number_format((float) $sub->getOriginal('paid'), 2) . " -> " . number_format((float) $data['paid'], 2) . " ₪)، المتبقي (" . number_format((float) $sub->getOriginal('remaining'), 2) . " -> " . number_format($remaining, 2) . " ₪)، الحالة ({$sub->getOriginal('status')} -> {$data['status']})";
        ActivityLog::log('تعديل اشتراك', $details);

        return response()->json(['success' => true]);
    }

    protected function toggleSubscription(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المحاسب', 'موظف الاستقبال']);
        $this->requireNotReadOnly();

        $data = $request->all();
        $subId = $data['subId'] ?? '';
        $sub = Subscription::find($subId);
        if (!$sub) {
            return response()->json(['success' => false, 'error' => 'الاشتراك غير موجود'], 404);
        }

        $newStatus = $sub->status === 'فعال' ? 'مجمد' : 'فعال';
        $sub->status = $newStatus;
        $sub->save();

        return response()->json(['success' => true, 'newStatus' => $newStatus]);
    }

    // -----------------------------------------------------------------
    // Payments
    // -----------------------------------------------------------------

    protected function addPayment(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المحاسب', 'موظف الاستقبال']);
        $this->requireNotReadOnly();

        $data = $request->all();
        $data['memberId'] = $data['memberId'] ?? $data['member_id'] ?? null;
        $validator = Validator::make($data, [
            'memberId' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:نقدي,تحويل',
            'transferFromAccount' => 'required_if:method,تحويل|string|max:255',
            'note' => 'nullable|string|max:255',
        ], [
            'memberId.required' => 'يرجى اختيار المشترك أولًا.',
            'transferFromAccount.required_if' => 'يرجى إدخال اسم الشخص أو الحساب المُحوِّل.',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $member = Member::find($data['memberId']);
        if (!$member) {
            return response()->json(['success' => false, 'error' => 'المشترك غير موجود'], 404);
        }

        $amount = (float) $data['amount'];
        $nextPayId = Payment::generateNextId();

        DB::transaction(function () use ($data, $member, $amount, $nextPayId) {
            $sub = Subscription::where('member_id', $member->id)
                ->where('remaining', '>', 0)
                ->orderBy('start_date', 'asc')
                ->first();

            Payment::create([
                'id' => $nextPayId,
                'member_id' => $member->id,
                'subscription_id' => $sub?->id,
                'member_name' => $member->name,
                'date' => now()->format('Y-m-d H:i:s'),
                'amount' => $amount,
                'method' => $data['method'],
                'transfer_from_account' => $data['method'] === 'تحويل' ? ($data['transferFromAccount'] ?? null) : null,
                'note' => $data['note'] ?? '—',
            ]);

            // Deduct from oldest active/frozen subscription with remaining balance
            if ($sub) {
                $newPaid = (float) $sub->paid + $amount;
                $newRemaining = max(0, (float) $sub->amount - $newPaid);
                $sub->paid = $newPaid;
                $sub->remaining = $newRemaining;
                $sub->save();
            }
        });

        ActivityLog::log('تسديد دفعة', "تم دفع {$amount} شيكل من المشترك: {$member->name} ({$member->id}) بطريقة: {$data['method']}، ملاحظة: " . ($data['note'] ?? '—'));

        return response()->json(['success' => true, 'id' => $nextPayId]);
    }

    protected function editPayment(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المحاسب', 'المدقق المالي']);
        $this->requireNotReadOnly();

        $data = $request->all();
        $validator = Validator::make($data, [
            'payId' => 'required|string',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:نقدي,تحويل',
            'transferFromAccount' => 'required_if:method,تحويل|string|max:255',
            'note' => 'nullable|string|max:255',
        ], [
            'transferFromAccount.required_if' => 'يرجى إدخال اسم الشخص أو الحساب المُحوِّل.',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $payment = Payment::find($data['payId']);
        if (!$payment) {
            return response()->json(['success' => false, 'error' => 'الدفعة غير موجودة'], 404);
        }

        $oldAmount = (float) $payment->amount;
        $newAmount = (float) $data['amount'];
        $diff = $newAmount - $oldAmount;

        DB::transaction(function () use ($data, $payment, $newAmount, $diff) {
            $payment->update([
                'date' => $data['date'],
                'amount' => $newAmount,
                'method' => $data['method'],
                'transfer_from_account' => $data['method'] === 'تحويل' ? ($data['transferFromAccount'] ?? $payment->transfer_from_account) : null,
                'note' => $data['note'] ?? '',
            ]);

            if ($diff != 0) {
                $this->adjustMemberSubscriptionBalances($payment->member_id, $payment->member_name, $diff);
            }
        });

        $oldNote = (string) $payment->getOriginal('note');
        $newNote = (string) ($data['note'] ?? '');
        $details = "تعديل دفعة مالية رقم {$data['payId']}: العضو ({$payment->member_name})، التاريخ ({$payment->getOriginal('date')} -> {$data['date']})، المبلغ ({$oldAmount} -> {$newAmount} ₪)، الطريقة ({$payment->getOriginal('method')} -> {$data['method']})، الملاحظة (\"{$oldNote}\" -> \"{$newNote}\")";
        ActivityLog::log('تعديل دفعة', $details);

        return response()->json(['success' => true]);
    }

    /**
     * When a payment amount is edited, adjust the linked subscription balances accordingly.
     * Mirrors the adjustMemberSubscriptionBalances() function from the old api.php.
     */
    protected function adjustMemberSubscriptionBalances(?string $memberId, ?string $memberName, float $diff): void
    {
        if ($diff == 0.0) {
            return;
        }

        $member = null;
        if (!empty($memberId)) {
            $member = Member::find($memberId);
        }
        if (!$member && !empty($memberName)) {
            $member = Member::where('name', $memberName)->first();
        }
        if (!$member) {
            return;
        }

        if ($diff > 0) {
            $remainingDiff = $diff;
            $subs = Subscription::where('member_id', $member->id)
                ->where('remaining', '>', 0)
                ->orderBy('start_date', 'asc')
                ->get();
            foreach ($subs as $sub) {
                if ($remainingDiff <= 0) {
                    break;
                }
                $deduct = min($remainingDiff, (float) $sub->remaining);
                $sub->paid = (float) $sub->paid + $deduct;
                $sub->remaining = max(0.0, (float) $sub->remaining - $deduct);
                $sub->save();
                $remainingDiff -= $deduct;
            }
        } else {
            $reduction = abs($diff);
            $subs = Subscription::where('member_id', $member->id)
                ->where('paid', '>', 0)
                ->orderBy('start_date', 'desc')
                ->get();
            foreach ($subs as $sub) {
                if ($reduction <= 0) {
                    break;
                }
                $deduct = min($reduction, (float) $sub->paid);
                $sub->paid = max(0.0, (float) $sub->paid - $deduct);
                $sub->remaining = (float) $sub->remaining + $deduct;
                $sub->save();
                $reduction -= $deduct;
            }
        }
    }

    // -----------------------------------------------------------------
    // Products & Sales (Cashier)
    // -----------------------------------------------------------------

    protected function addProduct(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المحاسب']);

        $data = $request->all();
        $validator = Validator::make($data, [
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $nextId = Product::generateNextId();
        Product::create([
            'id' => $nextId,
            'name' => $data['name'],
            'price' => $data['price'],
            'stock' => $data['stock'],
            'created_at' => now(),
        ]);

        ActivityLog::log('إضافة منتج', "تم إضافة منتج جديد: {$nextId} باسم: {$data['name']} بسعر: {$data['price']} شيكل ومخزون: {$data['stock']}");

        return response()->json(['success' => true, 'id' => $nextId]);
    }

    protected function editProduct(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المحاسب']);
        $this->requireNotReadOnly();

        $data = $request->all();
        $data['id'] = $data['id'] ?? $data['productId'] ?? $data['product_id'] ?? null;
        $validator = Validator::make($data, [
            'id' => 'required|string',
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $product = Product::find($data['id']);
        if (!$product) {
            return response()->json(['success' => false, 'error' => 'المنتج غير موجود'], 404);
        }
        $product->update([
            'name' => $data['name'],
            'price' => $data['price'],
            'stock' => $data['stock'],
        ]);

        ActivityLog::log('تعديل منتج', "تم تعديل المنتج: {$data['id']} - الاسم: {$data['name']}، السعر: {$data['price']} شيكل، المخزون: {$data['stock']}");

        return response()->json(['success' => true]);
    }

    protected function checkoutBasket(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المحاسب', 'موظف الاستقبال']);
        $this->requireNotReadOnly();

        $data = $request->all();
        $basket = $data['basket'] ?? [];
        $paymentMethod = $data['paymentMethod'] ?? 'تحويل';
        $memberId = $data['memberId'] ?? '';

        if (empty($basket)) {
            return response()->json(['success' => false, 'error' => 'السلة فارغة'], 422);
        }
        if (!in_array($paymentMethod, ['نقدي', 'تحويل'])) {
            return response()->json(['success' => false, 'error' => 'طريقة دفع غير صالحة'], 422);
        }

        $totalCost = 0.0;
        $itemsSummary = [];

        DB::transaction(function () use ($basket, $paymentMethod, $memberId, &$totalCost, &$itemsSummary) {
            $saleItems = [];

            foreach ($basket as $item) {
                $pId = $item['product']['id'];
                $qty = (int) ($item['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new \Exception('Invalid quantity for a basket item');
                }
                $product = Product::find($pId);
                if (!$product) {
                    throw new \Exception("Product {$pId} not found");
                }
                if ($product->stock < $qty) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }
                $product->stock = $product->stock - $qty;
                $product->save();

                $lineTotal = (float) $product->price * $qty;
                $totalCost += $lineTotal;
                $itemsSummary[] = $product->name . " ×" . $qty;
                $saleItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => (float) $product->price,
                    'total' => $lineTotal,
                ];
            }

            $productsDesc = implode('، ', $itemsSummary);
            $dateStr = now()->format('Y-m-d H:i:s');

            $sale = Sale::create([
                'date' => $dateStr,
                'products' => $productsDesc,
                'total' => $totalCost,
                'method' => $paymentMethod,
            ]);

            foreach ($saleItems as $saleItem) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $saleItem['product_id'],
                    'quantity' => $saleItem['quantity'],
                    'unit_price' => $saleItem['unit_price'],
                    'total' => $saleItem['total'],
                ]);
            }

            $buyerName = 'مبيعات منتجات';
            if (!empty($memberId)) {
                $mName = Member::where('id', $memberId)->value('name');
                if ($mName) {
                    $buyerName = $mName;
                }
            }

            Payment::create([
                'id' => Payment::generateNextId(),
                'member_id' => !empty($memberId) ? $memberId : null,
                'sale_id' => $sale->id,
                'member_name' => $buyerName,
                'date' => $dateStr,
                'amount' => $totalCost,
                'method' => $paymentMethod,
                'note' => 'شراء: ' . $productsDesc,
            ]);
        });

        ActivityLog::log('شراء منتجات', "تم بيع منتجات بقيمة {$totalCost} شيكل");

        return response()->json(['success' => true, 'total' => $totalCost]);
    }

    // -----------------------------------------------------------------
    // Check-ins
    // -----------------------------------------------------------------

    protected function checkIn(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'موظف الاستقبال']);

        $data = $request->all();
        $member = $this->findMemberForGate($data['memberId'] ?? null, $data['code'] ?? null);
        if (!$member) {
            return response()->json(['success' => false, 'error' => 'المشترك أو كود البطاقة غير موجود'], 404);
        }

        $dateStr = now()->format('Y-m-d H:i:s');
        $timeStr = now()->format('h:i ') . (now()->format('A') === 'AM' ? 'ص' : 'م');
        $subscription = $this->findGateSubscription($member->id);

        if (!$subscription) {
            Checkin::create([
                'member_id' => $member->id,
                'member_name' => $member->name,
                'time' => $timeStr,
                'source' => !empty($data['code']) ? 'qr' : 'manual',
                'status' => 'denied',
                'denial_reason' => 'لا يوجد اشتراك فعال',
                'created_at' => $dateStr,
                'updated_at' => $dateStr,
            ]);

            ActivityLog::log('رفض دخول', "تم رفض دخول المشترك: {$member->name} ({$member->id}) بسبب عدم وجود اشتراك فعال");

            return response()->json([
                'success' => false,
                'error' => 'لا يوجد اشتراك فعال لهذا المشترك',
                'member' => $this->formatGateMember($member, null),
            ], 403);
        }

        Checkin::create([
            'member_id' => $member->id,
            'subscription_id' => $subscription->id,
            'member_name' => $member->name,
            'time' => $timeStr,
            'source' => !empty($data['code']) ? 'qr' : 'manual',
            'status' => 'allowed',
            'created_at' => $dateStr,
            'updated_at' => $dateStr,
        ]);

        ActivityLog::log('تسجيل حضور', "تم تسجيل حضور للمشترك: {$member->name} ({$member->id}) في الساعة: {$timeStr}");

        return response()->json([
            'success' => true,
            'member' => $this->formatGateMember($member, $subscription),
            'time' => $timeStr,
        ]);
    }

    protected function checkOut(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'موظف الاستقبال']);

        $data = $request->all();
        $member = $this->findMemberForGate($data['memberId'] ?? null, $data['code'] ?? null);
        if (!$member) {
            return response()->json(['success' => false, 'error' => 'المشترك أو كود البطاقة غير موجود'], 404);
        }

        $checkin = Checkin::where('member_id', $member->id)
            ->where('status', 'allowed')
            ->whereNull('checkout_at')
            ->orderByDesc('id')
            ->first();

        if (!$checkin) {
            return response()->json(['success' => false, 'error' => 'لا يوجد دخول مفتوح لهذا المشترك'], 404);
        }

        $checkin->checkout_at = now();
        $checkin->updated_at = now();
        $checkin->save();

        ActivityLog::log('تسجيل خروج', "تم تسجيل خروج للمشترك: {$member->name} ({$member->id})");

        return response()->json([
            'success' => true,
            'member' => $this->formatGateMember($member, $checkin->subscription),
            'checkoutAt' => $checkin->checkout_at?->format('Y-m-d H:i:s'),
        ]);
    }

    protected function deleteCheckin(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'موظف الاستقبال']);
        $this->requireNotReadOnly();

        $checkin = Checkin::find((int) $request->input('id'));
        if (!$checkin) {
            return response()->json(['success' => false, 'error' => 'سجل الحضور غير موجود'], 404);
        }

        $memberName = $checkin->member_name;
        $memberId = $checkin->member_id;
        $checkin->delete();

        ActivityLog::log('حذف سجل حضور', "تم حذف سجل الحضور والمغادرة للمشترك: {$memberName} ({$memberId})");

        return response()->json(['success' => true]);
    }

    protected function findMemberForGate(?string $memberId, ?string $code): ?Member
    {
        $memberId = trim((string) $memberId);
        $code = trim((string) $code);

        if ($memberId !== '') {
            $member = Member::find($memberId);
            if ($member) {
                return $member;
            }
        }

        if ($code === '') {
            return null;
        }

        $card = MembershipCard::where('code', $code)
            ->where('status', 'active')
            ->first();

        if ($card) {
            return $card->member;
        }

        return Member::where('id', $code)
            ->orWhere('membership_number', $code)
            ->first();
    }

    protected function findGateSubscription(string $memberId): ?Subscription
    {
        $today = now()->toDateString();

        return Subscription::where('member_id', $memberId)
            ->where('status', 'فعال')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date')
            ->first();
    }

    protected function formatGateMember(Member $member, ?Subscription $subscription): array
    {
        return [
            'id' => $member->id,
            'membershipNumber' => $member->membership_number ?: $member->id,
            'name' => $member->name,
            'phone' => $member->phone,
            'imagePath' => $member->image_path,
            'subscriptionStatus' => $subscription ? $subscription->status : 'غير فعال',
            'subscriptionEndDate' => $subscription ? $subscription->end_date?->toDateString() : null,
        ];
    }

    // -----------------------------------------------------------------
    // Users
    // -----------------------------------------------------------------

    protected function getUsers(): JsonResponse
    {
        $this->requireAdmin();

        $users = User::orderByDesc('id')->get(['id', 'username', 'name', 'role', 'member_id', 'created_at']);
        return response()->json(['success' => true, 'users' => $users]);
    }

    protected function addUser(Request $request): JsonResponse
    {
        $this->requireAdmin();

        $data = $request->all();
        $validator = Validator::make($data, [
            'username' => 'required|string|max:50|unique:users,username',
            'password' => 'required|string|min:4',
            'name' => 'required|string|max:100',
            'role' => 'required|in:' . implode(',', User::ROLES),
            'memberId' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        User::create([
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'name' => $data['name'],
            'role' => $data['role'],
            'member_id' => $data['memberId'] ?: null,
            'created_at' => now(),
        ]);

        ActivityLog::log('إضافة مستخدم', "تم إضافة مستخدم جديد: {$data['username']} بصلاحية {$data['role']}");

        return response()->json(['success' => true]);
    }

    protected function editUser(Request $request): JsonResponse
    {
        $this->requireAdmin();

        $data = $request->all();
        $data['id'] = $data['id'] ?? $data['userId'] ?? $data['user_id'] ?? null;
        $validator = Validator::make($data, [
            'id' => 'required|integer',
            'username' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'role' => 'required|in:' . implode(',', User::ROLES),
            'memberId' => 'nullable|string',
            'password' => 'nullable|string|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $user = User::find($data['id']);
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'المستخدم غير موجود'], 404);
        }

        $updateData = [
            'username' => $data['username'],
            'name' => $data['name'],
            'role' => $data['role'],
            'member_id' => $data['memberId'] ?: null,
        ];
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }
        $user->update($updateData);

        ActivityLog::log('تعديل مستخدم', "تم تعديل بيانات المستخدم: {$data['username']}");

        return response()->json(['success' => true]);
    }

    protected function deleteUser(Request $request): JsonResponse
    {
        $this->requireAdmin();

        $data = $request->all();
        $id = $data['id'] ?? null;
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'المستخدم غير موجود'], 404);
        }
        if ($user->username === 'admin') {
            return response()->json(['success' => false, 'error' => 'لا يمكن حذف المستخدم المسؤول الرئيسي admin'], 403);
        }

        $username = $user->username;
        $user->delete();

        ActivityLog::log('حذف مستخدم', "تم حذف المستخدم: {$username}");

        return response()->json(['success' => true]);
    }

    // -----------------------------------------------------------------
    // Activity Log
    // -----------------------------------------------------------------

    protected function getActivityLog(): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'المدقق المالي']);

        $logs = ActivityLog::orderByDesc('id')->limit(500)->get();
        return response()->json(['success' => true, 'logs' => $logs]);
    }

    // -----------------------------------------------------------------
    // Measurements
    // -----------------------------------------------------------------

    protected function addMeasurement(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'موظف الاستقبال', 'المحاسب']);

        $data = $request->all();
        $data['memberId'] = $data['memberId'] ?? $data['member_id'] ?? null;
        $validator = Validator::make($data, [
            'memberId' => 'required|string',
            'weight' => 'required|numeric|min:0',
            'height' => 'required|numeric|min:0',
            'fat' => 'nullable|numeric|min:0',
            'muscle' => 'nullable|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        if (!Member::find($data['memberId'])) {
            return response()->json(['success' => false, 'error' => 'المشترك غير موجود'], 404);
        }

        Measurement::create([
            'member_id' => $data['memberId'],
            'weight' => $data['weight'],
            'height' => $data['height'],
            'fat_percentage' => $data['fat'] ?? 0,
            'muscle_mass' => $data['muscle'] ?? 0,
            'created_at' => now(),
        ]);

        ActivityLog::log('إضافة قياسات', "تم إضافة قياسات جديدة للمشترك: {$data['memberId']} (وزن: {$data['weight']}، طول: {$data['height']}، دهون: " . ($data['fat'] ?? 0) . "%، عضل: " . ($data['muscle'] ?? 0) . ")");

        return response()->json(['success' => true]);
    }

    // -----------------------------------------------------------------
    // Trainers (Phase 3 foundation)
    // -----------------------------------------------------------------

    protected function addTrainerProfile(Request $request): JsonResponse
    {
        $this->requireAdmin();

        $data = $request->all();
        $validator = Validator::make($data, [
            'userId' => 'nullable|integer|exists:users,id',
            'trainerName' => 'nullable|string|max:100|required_without:userId',
            'specialty' => 'nullable|string|max:150',
            'bio' => 'nullable|string|max:2000',
            'commissionRate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:active,inactive',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $userId = !empty($data['userId']) ? (int) $data['userId'] : null;
        $autoCreatedUser = null;

        if (!empty($userId)) {
            $alreadyLinked = Trainer::where('user_id', $userId)->exists();
            if ($alreadyLinked) {
                return response()->json(['success' => false, 'error' => 'هذا المستخدم مرتبط بملف مدرب بالفعل'], 422);
            }

            $linkedUser = User::find($userId);
            if (!$linkedUser) {
                return response()->json(['success' => false, 'error' => 'المستخدم غير موجود'], 404);
            }
            if ($linkedUser->role !== 'مدرب') {
                $linkedUser->role = 'مدرب';
                $linkedUser->save();
            }
        } else {
            $trainerName = trim((string) ($data['trainerName'] ?? ''));
            if ($trainerName === '') {
                return response()->json(['success' => false, 'error' => 'يرجى إدخال اسم المدرب'], 422);
            }

            $generatedUsername = $this->generateUniqueTrainerUsername($trainerName);
            $plainPassword = strtoupper(Str::random(8));

            $createdUser = User::create([
                'username' => $generatedUsername,
                'password' => Hash::make($plainPassword),
                'name' => $trainerName,
                'role' => 'مدرب',
                'member_id' => null,
                'created_at' => now(),
            ]);

            $userId = (int) $createdUser->id;
            $autoCreatedUser = [
                'id' => $createdUser->id,
                'name' => $createdUser->name,
                'username' => $createdUser->username,
                'password' => $plainPassword,
                'role' => $createdUser->role,
            ];
        }

        $trainer = Trainer::create([
            'user_id' => $userId,
            'specialty' => $data['specialty'] ?? null,
            'bio' => $data['bio'] ?? null,
            'commission_rate' => $data['commissionRate'] ?? 0,
            'status' => $data['status'] ?? 'active',
        ]);

        ActivityLog::log('إضافة ملف مدرب', 'تم إنشاء ملف مدرب جديد برقم: ' . $trainer->id);

        return response()->json([
            'success' => true,
            'trainer' => $trainer,
            'autoCreatedUser' => $autoCreatedUser,
        ]);
    }

    protected function generateUniqueTrainerUsername(string $trainerName): string
    {
        $normalized = Str::of($trainerName)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        if ($normalized === '') {
            $normalized = 'trainer';
        }

        $base = Str::limit('trainer_' . $normalized, 40, '');
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $suffix = '_' . $counter;
            $username = Str::limit($base, 50 - strlen($suffix), '') . $suffix;
            $counter++;
        }

        return $username;
    }

    protected function assignTrainerMember(Request $request): JsonResponse
    {
        $this->requireAdmin();

        $data = $request->all();
        $data['memberId'] = $data['memberId'] ?? $data['member_id'] ?? null;
        $validator = Validator::make($data, [
            'trainerId' => 'required|integer|exists:trainers,id',
            'memberId' => 'required|string|exists:members,id',
            'startDate' => 'required|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'status' => 'nullable|in:active,inactive,completed',
            'note' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $trainer = Trainer::find($data['trainerId']);
        if (!$trainer) {
            return response()->json(['success' => false, 'error' => 'المدرب غير موجود'], 404);
        }

        $hasOpenAssignment = TrainerAssignment::query()
            ->where('trainer_id', $data['trainerId'])
            ->where('member_id', $data['memberId'])
            ->where('status', 'active')
            ->whereNull('end_date')
            ->exists();

        if ($hasOpenAssignment) {
            return response()->json([
                'success' => false,
                'error' => 'يوجد ربط نشط مفتوح بالفعل بين هذا المدرب وهذا المشترك',
            ], 422);
        }

        $trainerSpecialty = trim((string) ($trainer->specialty ?? ''));
        if ($trainerSpecialty !== '') {
            $newStartDate = Carbon::parse($data['startDate'])->toDateString();
            $newEndDate = !empty($data['endDate'])
                ? Carbon::parse($data['endDate'])->toDateString()
                : null;

            $sameSpecialtyConflict = TrainerAssignment::query()
                ->join('trainers', 'trainers.id', '=', 'trainer_assignments.trainer_id')
                ->where('trainer_assignments.member_id', $data['memberId'])
                ->where('trainer_assignments.trainer_id', '!=', $data['trainerId'])
                ->where('trainer_assignments.status', 'active')
                ->where('trainers.specialty', $trainerSpecialty)
                ->where(function ($q) use ($newStartDate) {
                    $q->whereNull('trainer_assignments.end_date')
                        ->orWhereDate('trainer_assignments.end_date', '>=', $newStartDate);
                })
                ->when(!empty($newEndDate), function ($q) use ($newEndDate) {
                    $q->whereDate('trainer_assignments.start_date', '<=', $newEndDate);
                })
                ->exists();

            if ($sameSpecialtyConflict) {
                return response()->json([
                    'success' => false,
                    'error' => 'لا يمكن ربط المشترك بأكثر من مدرب نشط في نفس التخصص',
                ], 422);
            }
        }

        $assignment = TrainerAssignment::create([
            'trainer_id' => $data['trainerId'],
            'member_id' => $data['memberId'],
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'] ?? null,
            'status' => $data['status'] ?? 'active',
            'note' => $data['note'] ?? null,
        ]);

        ActivityLog::log('ربط مدرب بمشترك', "تم ربط المدرب {$data['trainerId']} بالمشترك {$data['memberId']}");

        return response()->json([
            'success' => true,
            'assignment' => $assignment,
        ]);
    }

    protected function addTrainingProgram(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدرب']);
        $user = $this->requireUser();

        $data = $request->all();
        $validator = Validator::make($data, [
            'trainerAssignmentId' => 'required|integer|exists:trainer_assignments,id',
            'title' => 'required|string|max:180',
            'goal' => 'nullable|string|max:255',
            'content' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $this->authorizeTrainerAssignmentWrite((int) $data['trainerAssignmentId'], $user);

        $program = TrainingProgram::create([
            'trainer_assignment_id' => (int) $data['trainerAssignmentId'],
            'title' => trim((string) $data['title']),
            'goal' => isset($data['goal']) ? trim((string) $data['goal']) : null,
            'content_json' => $this->normalizeProgramContent($data['content'] ?? null),
        ]);

        ActivityLog::log('إضافة برنامج تدريبي', "تم إنشاء برنامج تدريبي جديد برقم {$program->id}");

        return response()->json(['success' => true, 'program' => $program]);
    }

    protected function editTrainingProgram(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدرب']);
        $user = $this->requireUser();

        $data = $request->all();
        $data['id'] = $data['id'] ?? $data['programId'] ?? $data['program_id'] ?? null;
        $validator = Validator::make($data, [
            'id' => 'required|integer|exists:training_programs,id',
            'trainerAssignmentId' => 'required|integer|exists:trainer_assignments,id',
            'title' => 'required|string|max:180',
            'goal' => 'nullable|string|max:255',
            'content' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $program = TrainingProgram::find((int) $data['id']);
        if (!$program) {
            return response()->json(['success' => false, 'error' => 'البرنامج التدريبي غير موجود'], 404);
        }

        $this->authorizeTrainingProgramWrite($program, $user);
        $this->authorizeTrainerAssignmentWrite((int) $data['trainerAssignmentId'], $user);

        $program->trainer_assignment_id = (int) $data['trainerAssignmentId'];
        $program->title = trim((string) $data['title']);
        $program->goal = isset($data['goal']) ? trim((string) $data['goal']) : null;
        $program->content_json = $this->normalizeProgramContent($data['content'] ?? null);
        $program->save();

        ActivityLog::log('تعديل برنامج تدريبي', "تم تعديل البرنامج التدريبي رقم {$program->id}");

        return response()->json(['success' => true, 'program' => $program]);
    }

    protected function deleteTrainingProgram(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدرب']);
        $user = $this->requireUser();

        $data = $request->all();
        $data['id'] = $data['id'] ?? $data['programId'] ?? $data['program_id'] ?? null;
        $validator = Validator::make($data, [
            'id' => 'required|integer|exists:training_programs,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $program = TrainingProgram::find((int) $data['id']);
        if (!$program) {
            return response()->json(['success' => false, 'error' => 'البرنامج التدريبي غير موجود'], 404);
        }

        $this->authorizeTrainingProgramWrite($program, $user);

        $programId = $program->id;
        $program->delete();

        ActivityLog::log('حذف برنامج تدريبي', "تم حذف البرنامج التدريبي رقم {$programId}");

        return response()->json(['success' => true]);
    }

    protected function addNutritionProgram(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدرب']);
        $user = $this->requireUser();

        $data = $request->all();
        $validator = Validator::make($data, [
            'trainerAssignmentId' => 'required|integer|exists:trainer_assignments,id',
            'title' => 'required|string|max:180',
            'goal' => 'nullable|string|max:255',
            'content' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $this->authorizeTrainerAssignmentWrite((int) $data['trainerAssignmentId'], $user);

        $program = NutritionProgram::create([
            'trainer_assignment_id' => (int) $data['trainerAssignmentId'],
            'title' => trim((string) $data['title']),
            'goal' => isset($data['goal']) ? trim((string) $data['goal']) : null,
            'content_json' => $this->normalizeProgramContent($data['content'] ?? null),
        ]);

        ActivityLog::log('إضافة برنامج غذائي', "تم إنشاء برنامج غذائي جديد برقم {$program->id}");

        return response()->json(['success' => true, 'program' => $program]);
    }

    protected function editNutritionProgram(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدرب']);
        $user = $this->requireUser();

        $data = $request->all();
        $data['id'] = $data['id'] ?? $data['programId'] ?? $data['program_id'] ?? null;
        $validator = Validator::make($data, [
            'id' => 'required|integer|exists:nutrition_programs,id',
            'trainerAssignmentId' => 'required|integer|exists:trainer_assignments,id',
            'title' => 'required|string|max:180',
            'goal' => 'nullable|string|max:255',
            'content' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $program = NutritionProgram::find((int) $data['id']);
        if (!$program) {
            return response()->json(['success' => false, 'error' => 'البرنامج الغذائي غير موجود'], 404);
        }

        $this->authorizeNutritionProgramWrite($program, $user);
        $this->authorizeTrainerAssignmentWrite((int) $data['trainerAssignmentId'], $user);

        $program->trainer_assignment_id = (int) $data['trainerAssignmentId'];
        $program->title = trim((string) $data['title']);
        $program->goal = isset($data['goal']) ? trim((string) $data['goal']) : null;
        $program->content_json = $this->normalizeProgramContent($data['content'] ?? null);
        $program->save();

        ActivityLog::log('تعديل برنامج غذائي', "تم تعديل البرنامج الغذائي رقم {$program->id}");

        return response()->json(['success' => true, 'program' => $program]);
    }

    protected function deleteNutritionProgram(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدرب']);
        $user = $this->requireUser();

        $data = $request->all();
        $data['id'] = $data['id'] ?? $data['programId'] ?? $data['program_id'] ?? null;
        $validator = Validator::make($data, [
            'id' => 'required|integer|exists:nutrition_programs,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $program = NutritionProgram::find((int) $data['id']);
        if (!$program) {
            return response()->json(['success' => false, 'error' => 'البرنامج الغذائي غير موجود'], 404);
        }

        $this->authorizeNutritionProgramWrite($program, $user);

        $programId = $program->id;
        $program->delete();

        ActivityLog::log('حذف برنامج غذائي', "تم حذف البرنامج الغذائي رقم {$programId}");

        return response()->json(['success' => true]);
    }

    protected function normalizeProgramContent(mixed $content): ?array
    {
        if ($content === null || $content === '') {
            return null;
        }

        if (is_array($content)) {
            return $content;
        }

        if (is_string($content)) {
            $trimmed = trim($content);
            if ($trimmed === '') {
                return null;
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return ['notes' => $trimmed];
        }

        return null;
    }

    protected function getTrainerProfileForUser(array $user): ?Trainer
    {
        if (($user['role'] ?? '') !== 'مدرب') {
            return null;
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        return Trainer::where('user_id', $userId)->first();
    }

    protected function authorizeTrainerAssignmentWrite(int $trainerAssignmentId, array $user): void
    {
        if (($user['role'] ?? '') === 'مدير النظام') {
            return;
        }

        $trainer = $this->getTrainerProfileForUser($user);
        if (!$trainer) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'error' => 'حساب المدرب غير مرتبط بملف مدرب',
            ], 403));
        }

        $assignment = TrainerAssignment::find($trainerAssignmentId);
        if (!$assignment || (int) $assignment->trainer_id !== (int) $trainer->id) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'error' => 'غير مصرح لك بالتعديل على هذا الربط',
            ], 403));
        }
    }

    protected function authorizeTrainingProgramWrite(TrainingProgram $program, array $user): void
    {
        if (($user['role'] ?? '') === 'مدير النظام') {
            return;
        }

        $this->authorizeTrainerAssignmentWrite((int) $program->trainer_assignment_id, $user);
    }

    protected function authorizeNutritionProgramWrite(NutritionProgram $program, array $user): void
    {
        if (($user['role'] ?? '') === 'مدير النظام') {
            return;
        }

        $this->authorizeTrainerAssignmentWrite((int) $program->trainer_assignment_id, $user);
    }

    // -----------------------------------------------------------------
    // Notifications (Phase 4)
    // -----------------------------------------------------------------

    protected function addNotificationTemplate(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $this->requireNotReadOnly();

        $data = $request->all();
        $validator = Validator::make($data, [
            'name' => 'required|string|max:120',
            'channel' => 'required|in:whatsapp,sms,email,in_app',
            'type' => 'required|string|max:80',
            'titleTemplate' => 'required|string|max:180',
            'bodyTemplate' => 'required|string|max:4000',
            'isActive' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $template = NotificationTemplate::updateOrCreate(
            [
                'type' => trim((string) $data['type']),
                'channel' => trim((string) $data['channel']),
            ],
            [
                'name' => trim((string) $data['name']),
                'title_template' => trim((string) $data['titleTemplate']),
                'body_template' => trim((string) $data['bodyTemplate']),
                'is_active' => array_key_exists('isActive', $data) ? (bool) $data['isActive'] : true,
            ]
        );

        ActivityLog::log('إعدادات الإشعارات', 'تم حفظ قالب إشعار: ' . $template->name);

        return response()->json(['success' => true, 'template' => $template]);
    }

    protected function sendNotification(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'موظف الاستقبال', 'المحاسب']);
        $this->requireNotReadOnly();
        $user = $this->requireUser();

        $data = $request->all();
        $validator = Validator::make($data, [
            'templateId' => 'nullable|integer|exists:notification_templates,id',
            'memberId' => 'nullable|string|exists:members,id',
            'channel' => 'nullable|in:whatsapp,sms,email,in_app',
            'type' => 'nullable|string|max:80',
            'title' => 'nullable|string|max:180',
            'body' => 'nullable|string|max:4000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $templateId = !empty($data['templateId']) ? (int) $data['templateId'] : null;
        $memberId = !empty($data['memberId']) ? (string) $data['memberId'] : null;
        $member = $memberId ? Member::find($memberId) : null;

        if ($templateId) {
            $template = NotificationTemplate::find($templateId);
            if (!$template || !$template->is_active) {
                return response()->json(['success' => false, 'error' => 'قالب الإشعار غير متاح'], 422);
            }

            $context = [
                '{member_name}' => $member?->name ?? 'المشترك',
                '{member_id}' => $member?->id ?? '',
                '{today}' => now()->toDateString(),
            ];

            $title = strtr((string) $template->title_template, $context);
            $body = strtr((string) $template->body_template, $context);

            $notification = NotificationService::sendManual(
                channel: (string) $template->channel,
                type: (string) $template->type,
                title: $title,
                body: $body,
                memberId: $memberId,
                createdBy: (int) ($user['id'] ?? 0),
                templateId: $template->id
            );
        } else {
            if (empty($data['title']) || empty($data['body'])) {
                return response()->json(['success' => false, 'error' => 'العنوان والمحتوى مطلوبان عند الإرسال اليدوي'], 422);
            }

            $notification = NotificationService::sendManual(
                channel: (string) ($data['channel'] ?? 'whatsapp'),
                type: (string) ($data['type'] ?? 'manual'),
                title: trim((string) $data['title']),
                body: trim((string) $data['body']),
                memberId: $memberId,
                createdBy: (int) ($user['id'] ?? 0),
                templateId: null
            );
        }

        ActivityLog::log('إرسال إشعار', 'تم إرسال إشعار رقم: ' . $notification->id);

        return response()->json([
            'success' => true,
            'notification' => $notification,
        ]);
    }

    protected function processNotificationJobs(): JsonResponse
    {
        $this->requireAdmin();
        $this->requireNotReadOnly();
        $user = $this->requireUser();

        $summary = NotificationService::processSubscriptionReminders(createdBy: (int) ($user['id'] ?? 0));

        ActivityLog::log('تشغيل مهام الإشعارات', 'تم تشغيل معالجة تذكيرات الاشتراكات');

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    // -----------------------------------------------------------------
    // Offline Sync (Phase 6 foundation)
    // -----------------------------------------------------------------

    protected function syncPullState(): JsonResponse
    {
        $state = $this->getState()->getData(true);

        return response()->json([
            'success' => true,
            'data' => [
                'serverTime' => now()->toIso8601String(),
                'lastSyncEventId' => (int) (SyncEvent::max('id') ?? 0),
                'state' => $state['data'] ?? [],
            ],
        ]);
    }

    protected function syncPushQueue(Request $request): JsonResponse
    {
        $this->requireNotReadOnly();
        $user = $this->requireUser();

        $data = $request->all();
        $validator = Validator::make($data, [
            'events' => 'required|array|min:1|max:100',
            'events.*.action' => 'required|string|max:80',
            'events.*.payload' => 'nullable|array',
            'events.*.clientEventId' => 'nullable|string|max:120',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $allowedActions = ['check_in', 'check_out', 'add_measurement', 'add_payment', 'checkout_basket'];
        $processed = 0;
        $failed = 0;
        $skipped = 0;
        $results = [];

        foreach ($data['events'] as $eventInput) {
            $action = strtolower(trim((string) ($eventInput['action'] ?? '')));
            $payload = (array) ($eventInput['payload'] ?? []);
            $clientEventId = trim((string) ($eventInput['clientEventId'] ?? ''));

            if (!in_array($action, $allowedActions, true)) {
                $skipped++;
                $results[] = [
                    'clientEventId' => $clientEventId !== '' ? $clientEventId : null,
                    'action' => $action,
                    'status' => 'skipped',
                    'message' => 'العملية غير مدعومة في المزامنة',
                ];
                continue;
            }

            $syncEvent = null;
            if ($clientEventId !== '') {
                $syncEvent = SyncEvent::where('client_event_id', $clientEventId)->first();
            }

            if (!$syncEvent) {
                $syncEvent = SyncEvent::create([
                    'client_event_id' => $clientEventId !== '' ? $clientEventId : null,
                    'action' => $action,
                    'payload' => $payload,
                    'status' => 'pending',
                    'created_by' => (int) ($user['id'] ?? 0),
                ]);
            }

            if ($syncEvent->status === 'processed') {
                $skipped++;
                $results[] = [
                    'eventId' => $syncEvent->id,
                    'clientEventId' => $syncEvent->client_event_id,
                    'action' => $action,
                    'status' => 'processed',
                    'message' => $syncEvent->result_message ?: 'تمت معالجته مسبقاً',
                ];
                continue;
            }

            try {
                $response = $this->processSyncEventAction($action, $payload);
                $responseData = $response->getData(true);
                $ok = (bool) ($responseData['success'] ?? false);

                $syncEvent->status = $ok ? 'processed' : 'failed';
                $syncEvent->result_message = $ok
                    ? 'تمت المعالجة'
                    : (string) ($responseData['error'] ?? 'فشل غير معروف');
                $syncEvent->processed_at = now();
                $syncEvent->save();

                if ($ok) {
                    $processed++;
                } else {
                    $failed++;
                }

                $results[] = [
                    'eventId' => $syncEvent->id,
                    'clientEventId' => $syncEvent->client_event_id,
                    'action' => $action,
                    'status' => $syncEvent->status,
                    'message' => $syncEvent->result_message,
                ];
            } catch (\Throwable $e) {
                $syncEvent->status = 'failed';
                $syncEvent->result_message = $e->getMessage();
                $syncEvent->processed_at = now();
                $syncEvent->save();

                $failed++;
                $results[] = [
                    'eventId' => $syncEvent->id,
                    'clientEventId' => $syncEvent->client_event_id,
                    'action' => $action,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'summary' => [
                'processed' => $processed,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
            'results' => $results,
        ]);
    }

    protected function syncEventsLog(Request $request): JsonResponse
    {
        $query = SyncEvent::orderByDesc('id');

        $status = trim((string) $request->query('status', ''));
        $action = trim((string) $request->query('action', ''));
        $eventId = (int) $request->query('id', 0);
        $fromDate = trim((string) $request->query('from', ''));
        $toDate = trim((string) $request->query('to', ''));

        if ($eventId > 0) {
            $query->where('id', $eventId);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($fromDate !== '') {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate !== '') {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $rows = $query->limit(200)->get();
        $processedRows = $rows->where('status', 'processed');
        $pendingRows = $rows->where('status', 'pending');

        $averageProcessingSeconds = 0.0;
        $processedCountForAverage = 0;
        $rows->each(function (SyncEvent $row) use (&$averageProcessingSeconds, &$processedCountForAverage) {
            if (!$row->processed_at || !$row->created_at) {
                return;
            }

            $averageProcessingSeconds += max(0, Carbon::parse($row->created_at)->diffInSeconds(Carbon::parse($row->processed_at)));
            $processedCountForAverage++;
        });
        $averageProcessingSeconds = $processedCountForAverage > 0
            ? round($averageProcessingSeconds / $processedCountForAverage, 2)
            : 0.0;

        $oldestPendingMinutes = 0;
        if ($pendingRows->isNotEmpty()) {
            $oldestPending = $pendingRows->sortBy('created_at')->first();
            if ($oldestPending?->created_at) {
                $oldestPendingMinutes = max(0, Carbon::parse($oldestPending->created_at)->diffInMinutes(now()));
            }
        }

        return response()->json([
            'success' => true,
            'events' => $rows,
            'stats' => [
                'total' => $rows->count(),
                'processed' => $processedRows->count(),
                'failed' => $rows->where('status', 'failed')->count(),
                'skipped' => $rows->where('status', 'skipped')->count(),
                'pending' => $pendingRows->count(),
                'averageProcessingSeconds' => $averageProcessingSeconds,
                'oldestPendingMinutes' => $oldestPendingMinutes,
            ],
        ]);
    }

    protected function processSyncEventAction(string $action, array $payload): JsonResponse
    {
        $request = new Request($payload);

        return match ($action) {
            'check_in' => $this->checkIn($request),
            'check_out' => $this->checkOut($request),
            'add_measurement' => $this->addMeasurement($request),
            'add_payment' => $this->addPayment($request),
            'checkout_basket' => $this->checkoutBasket($request),
            default => response()->json([
                'success' => false,
                'error' => 'عملية مزامنة غير مدعومة',
            ], 422),
        };
    }

    // -----------------------------------------------------------------
    // Import & Global Search
    // -----------------------------------------------------------------

    protected function importData(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $this->requireNotReadOnly();

        $data = $request->all();
        $members = $data['members'] ?? [];
        $subscriptions = $data['subscriptions'] ?? [];
        $payments = $data['payments'] ?? [];

        $importedMembers = 0;
        $updatedMembers = 0;
        $importedSubs = 0;
        $skippedSubs = 0;
        $importedPays = 0;
        $skippedPays = 0;

        DB::transaction(function () use ($members, $subscriptions, $payments, &$importedMembers, &$updatedMembers, &$importedSubs, &$skippedSubs, &$importedPays, &$skippedPays) {
            // 1. Process Members
            foreach ($members as $m) {
                $name = trim($m['name'] ?? '');
                $phone = trim($m['phone'] ?? '');
                $whatsapp = trim($m['whatsapp'] ?? '');
                $gender = trim($m['gender'] ?? 'ذكر');
                if (empty($name) || empty($phone)) {
                    continue;
                }
                if (!in_array($gender, ['ذكر', 'أنثى'])) {
                    $gender = 'ذكر';
                }
                $memberId = trim($m['id'] ?? '');
                $existing = !empty($memberId) ? Member::find($memberId) : null;
                if ($existing) {
                    $existing->update([
                        'name' => $name,
                        'phone' => $phone,
                        'whatsapp' => $whatsapp,
                        'gender' => $gender,
                    ]);
                    $updatedMembers++;
                } else {
                    if (empty($memberId)) {
                        $memberId = Member::generateNextId();
                    } elseif (Member::find($memberId)) {
                        $memberId = Member::generateNextId();
                    }
                    Member::create([
                        'id' => $memberId,
                        'name' => $name,
                        'phone' => $phone,
                        'whatsapp' => $whatsapp,
                        'gender' => $gender,
                        'created_at' => now(),
                    ]);
                    $importedMembers++;
                }
            }

            // 2. Process Subscriptions
            foreach ($subscriptions as $s) {
                $memberId = trim($s['member_id'] ?? '');
                $planName = trim($s['plan_name'] ?? '');
                $amount = (float) ($s['amount'] ?? 0);
                $paid = (float) ($s['paid'] ?? 0);
                $remaining = (float) ($s['remaining'] ?? 0);
                $status = trim($s['status'] ?? 'فعال');
                $startDate = trim($s['start_date'] ?? '');
                $endDate = trim($s['end_date'] ?? '');
                if (empty($memberId) || empty($planName) || empty($startDate) || empty($endDate)) {
                    continue;
                }
                if (!Member::find($memberId)) {
                    continue;
                }
                if (!in_array($status, ['فعال', 'منتهي', 'مجمد'])) {
                    $status = 'فعال';
                }
                $existing = Subscription::where('member_id', $memberId)
                    ->where('plan_name', $planName)
                    ->where('start_date', $startDate)
                    ->where('end_date', $endDate)
                    ->first();
                if ($existing) {
                    $skippedSubs++;
                    continue;
                }
                Subscription::create([
                    'id' => Subscription::generateNextId(),
                    'member_id' => $memberId,
                    'plan_name' => $planName,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'amount' => $amount,
                    'paid' => $paid,
                    'remaining' => $remaining,
                    'status' => $status,
                    'created_at' => now(),
                ]);
                $importedSubs++;
            }

            // 3. Process Payments
            foreach ($payments as $p) {
                $memberName = trim($p['member_name'] ?? '');
                $amount = (float) ($p['amount'] ?? 0);
                $method = trim($p['method'] ?? 'نقدي');
                $date = trim($p['date'] ?? '');
                $note = trim($p['note'] ?? '');
                if (empty($memberName) || $amount <= 0 || empty($date)) {
                    continue;
                }
                if (!in_array($method, ['نقدي', 'تحويل'])) {
                    $method = 'نقدي';
                }
                $existing = Payment::where('member_name', $memberName)
                    ->where('amount', $amount)
                    ->where('date', $date)
                    ->where('note', $note)
                    ->first();
                if ($existing) {
                    $skippedPays++;
                    continue;
                }
                Payment::create([
                    'id' => Payment::generateNextId(),
                    'member_name' => $memberName,
                    'date' => $date,
                    'amount' => $amount,
                    'method' => $method,
                    'note' => $note,
                ]);
                $importedPays++;
            }
        });

        ActivityLog::log('استيراد ذكي', "تم تنفيذ عملية الاستيراد الذكي للبيانات من ملف إكسل. تم إدخال {$importedMembers} مشترك جديد وتحديث {$updatedMembers}، وتم إدخال {$importedSubs} اشتراك (وتخطي {$skippedSubs})، وتم إدخال {$importedPays} سند قبض (وتخطي {$skippedPays})");

        return response()->json([
            'success' => true,
            'imported_members_count' => $importedMembers,
            'updated_members_count' => $updatedMembers,
            'imported_subs_count' => $importedSubs,
            'skipped_subs_count' => $skippedSubs,
            'imported_pays_count' => $importedPays,
            'skipped_pays_count' => $skippedPays,
        ]);
    }

    protected function globalSearch(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'موظف الاستقبال', 'المحاسب', 'المدقق المالي']);

        $data = $request->all();
        $queryStr = trim($data['query'] ?? '');
        $category = trim($data['category'] ?? 'all');
        $startDate = trim($data['startDate'] ?? '');
        $endDate = trim($data['endDate'] ?? '');
        $status = trim($data['status'] ?? 'all');
        $role = session('user.role');
        $branchId = session('user.branch_id');
        $hasSubscriptionsBranchColumn = Schema::hasColumn('subscriptions', 'branch_id');

        $queryLike = '%' . $queryStr . '%';
        $startDateTime = $startDate ? $startDate . ' 00:00:00' : '';
        $endDateTime = $endDate ? $endDate . ' 23:59:59' : '';

        // Revenues summary (for finance roles)
        $revenues = null;
        if (in_array($role, ['مدير النظام', 'المحاسب', 'المدقق المالي'])) {
            $today = Carbon::now()->toDateString();
            $sevenDaysAgo = Carbon::now()->subDays(7)->toDateString();
            $firstDayOfMonth = Carbon::now()->startOfMonth()->toDateString();
            $firstDayOfYear = Carbon::now()->startOfYear()->toDateString();

            $revenues = [
                'daily' => $this->formatPeriod(Payment::where('date', 'like', "$today%")
                    ->selectRaw('method, SUM(amount) as total')->groupBy('method')->get()),
                'weekly' => $this->formatPeriod(Payment::where('date', '>=', $sevenDaysAgo)
                    ->selectRaw('method, SUM(amount) as total')->groupBy('method')->get()),
                'monthly' => $this->formatPeriod(Payment::where('date', '>=', $firstDayOfMonth)
                    ->selectRaw('method, SUM(amount) as total')->groupBy('method')->get()),
                'yearly' => $this->formatPeriod(Payment::where('date', '>=', $firstDayOfYear)
                    ->selectRaw('method, SUM(amount) as total')->groupBy('method')->get()),
            ];
        }

        $results = [];
        if (!empty($queryStr) || !empty($startDate) || !empty($endDate) || $status !== 'all') {
            $allowedCategories = match ($role) {
                'موظف الاستقبال' => ['members', 'checkins'],
                'المحاسب' => ['members', 'subscriptions', 'payments', 'sales', 'products'],
                default => ['members', 'subscriptions', 'payments', 'sales', 'products', 'checkins', 'logs'],
            };

            $categoriesToSearch = $category !== 'all' ? [$category] : $allowedCategories;
            foreach ($categoriesToSearch as $cat) {
                if (!in_array($cat, $allowedCategories)) {
                    return response()->json(['success' => false, 'error' => 'غير مصرح لك باستعراض هذه الفئة'], 403);
                }

                if ($cat === 'members') {
                    $q = Member::where(function ($q) use ($queryLike) {
                        $q->where('name', 'like', $queryLike)
                            ->orWhere('phone', 'like', $queryLike)
                            ->orWhere('whatsapp', 'like', $queryLike)
                            ->orWhere('id', 'like', $queryLike);
                    });
                    if ($startDate) {
                        $q->where('created_at', '>=', $startDateTime);
                    }
                    if ($endDate) {
                        $q->where('created_at', '<=', $endDateTime);
                    }
                    if ($status !== 'all') {
                        $q->whereIn('id', function ($sub) use ($status, $branchId, $hasSubscriptionsBranchColumn) {
                            $sub->select('member_id')->from('subscriptions')->where('status', $status);
                            if ($hasSubscriptionsBranchColumn && $branchId) {
                                $sub->where('branch_id', $branchId);
                            }
                        });
                    }
                    $results['members'] = $q->orderByDesc('id')->limit(50)->get();
                } elseif ($cat === 'subscriptions') {
                    $q = Subscription::query()
                        ->join('members', 'subscriptions.member_id', '=', 'members.id')
                        ->select('subscriptions.*', 'members.name as member_name', 'members.phone as member_phone')
                        ->where(function ($q) use ($queryLike) {
                            $q->where('subscriptions.id', 'like', $queryLike)
                                ->orWhere('subscriptions.plan_name', 'like', $queryLike)
                                ->orWhere('subscriptions.member_id', 'like', $queryLike)
                                ->orWhere('members.name', 'like', $queryLike)
                                ->orWhere('members.phone', 'like', $queryLike);
                        });
                    if ($startDate) {
                        $q->where('subscriptions.start_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->where('subscriptions.start_date', '<=', $endDate);
                    }
                    if ($status !== 'all') {
                        $q->where('subscriptions.status', $status);
                    }
                    $results['subscriptions'] = $q->orderByDesc('subscriptions.id')->limit(50)->get();
                } elseif ($cat === 'payments') {
                    $q = Payment::where(function ($q) use ($queryLike) {
                        $q->where('id', 'like', $queryLike)
                            ->orWhere('member_name', 'like', $queryLike)
                            ->orWhere('note', 'like', $queryLike)
                            ->orWhere('method', 'like', $queryLike);
                    });
                    if ($startDate) {
                        $q->where('date', '>=', $startDateTime);
                    }
                    if ($endDate) {
                        $q->where('date', '<=', $endDateTime);
                    }
                    $results['payments'] = $q->orderByDesc('date')->limit(50)->get();
                } elseif ($cat === 'sales') {
                    $q = Sale::where(function ($q) use ($queryLike) {
                        $q->where('id', 'like', $queryLike)
                            ->orWhere('products', 'like', $queryLike)
                            ->orWhere('method', 'like', $queryLike);
                    });
                    if ($startDate) {
                        $q->where('date', '>=', $startDateTime);
                    }
                    if ($endDate) {
                        $q->where('date', '<=', $endDateTime);
                    }
                    $results['sales'] = $q->orderByDesc('date')->limit(50)->get();
                } elseif ($cat === 'products') {
                    $q = Product::where(function ($q) use ($queryLike) {
                        $q->where('id', 'like', $queryLike)
                            ->orWhere('name', 'like', $queryLike);
                    });
                    if ($startDate) {
                        $q->where('created_at', '>=', $startDateTime);
                    }
                    if ($endDate) {
                        $q->where('created_at', '<=', $endDateTime);
                    }
                    $results['products'] = $q->orderByDesc('id')->limit(50)->get();
                } elseif ($cat === 'checkins') {
                    $q = Checkin::where(function ($q) use ($queryLike) {
                        $q->where('member_id', 'like', $queryLike)
                            ->orWhere('member_name', 'like', $queryLike);
                    });
                    if ($startDate) {
                        $q->where('created_at', '>=', $startDateTime);
                    }
                    if ($endDate) {
                        $q->where('created_at', '<=', $endDateTime);
                    }
                    $results['checkins'] = $q->orderByDesc('id')->limit(50)->get();
                } elseif ($cat === 'logs') {
                    $q = ActivityLog::where(function ($q) use ($queryLike) {
                        $q->where('username', 'like', $queryLike)
                            ->orWhere('name', 'like', $queryLike)
                            ->orWhere('action', 'like', $queryLike)
                            ->orWhere('details', 'like', $queryLike);
                    });
                    if ($startDate) {
                        $q->where('created_at', '>=', $startDateTime);
                    }
                    if ($endDate) {
                        $q->where('created_at', '<=', $endDateTime);
                    }
                    $results['logs'] = $q->orderByDesc('id')->limit(50)->get();
                }
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'revenues' => $revenues,
        ]);
    }

    /**
     * Format revenue data for global_search response.
     */
    protected function formatPeriod($rows): array
    {
        $total = 0.0;
        $cash = 0.0;
        $transfer = 0.0;
        foreach ($rows as $row) {
            $amt = (float) $row->total;
            $total += $amt;
            if ($row->method === 'نقدي') {
                $cash += $amt;
            } elseif ($row->method === 'تحويل') {
                $transfer += $amt;
            }
        }
        return ['total' => $total, 'cash' => $cash, 'transfer' => $transfer];
    }

    // -----------------------------------------------------------------
    // Authorization helpers
    // -----------------------------------------------------------------

    protected function requireUser(): array
    {
        $user = session('user');
        if (!$user) {
            abort(response()->json(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً'], 401));
        }
        return $user;
    }

    protected function requireAdmin(): void
    {
        $user = $this->requireUser();
        if (!in_array($user['role'], ['مدير النظام', 'مدير الصالة'], true)) {
            abort(response()->json(['success' => false, 'error' => 'غير مصرح لك'], 403));
        }
    }

    /**
     * Centralized guard for high-risk actions.
     */
    protected function authorizeActionAccess(string $action): void
    {
        $platformOnlyActions = ['import_data', 'get_activity_log'];

        if (in_array($action, $platformOnlyActions, true)) {
            $this->requireAdmin();
        }
    }

    protected function requireAnyRole(array $roles): void
    {
        $user = $this->requireUser();
        $isGymManagerAllowed = $user['role'] === 'مدير الصالة'
            && in_array('مدير النظام', $roles, true);

        if (!in_array($user['role'], $roles, true) && !$isGymManagerAllowed) {
            abort(response()->json(['success' => false, 'error' => 'غير مصرح لك'], 403));
        }
    }

    protected function requireNotReadOnly(): void
    {
        $user = $this->requireUser();
        if ($user['role'] === 'المدقق المالي') {
            abort(response()->json(['success' => false, 'error' => 'حسابك للقراءة فقط ولا يمكنه التعديل'], 403));
        }
    }

    protected function buildTenantState(): array
    {
        if (!Schema::hasTable('gyms') || !Schema::hasTable('branches')) {
            return [
                'gyms' => [],
                'selectedGymId' => null,
                'selectedBranchId' => null,
            ];
        }

        $gyms = Gym::with(['branches' => function ($query) {
            $query->orderByDesc('is_default')->orderBy('id');
        }])->orderBy('id')->get();

        $currentUser = session('user') ?: [];
        $selectedGym = null;
        $selectedBranch = null;

        foreach ($gyms as $gym) {
            if ((string) $gym->id === (string) ($currentUser['gym_id'] ?? null)) {
                $selectedGym = $gym;
            }
            foreach ($gym->branches as $branch) {
                if ((string) $branch->id === (string) ($currentUser['branch_id'] ?? null)) {
                    $selectedBranch = $branch;
                    $selectedGym = $selectedGym ?: $gym;
                }
            }
        }

        if (!$selectedGym && $gyms->isNotEmpty()) {
            $selectedGym = $gyms->first();
        }
        if (!$selectedBranch && $selectedGym && $selectedGym->branches->isNotEmpty()) {
            $selectedBranch = $selectedGym->branches->firstWhere('is_default', true) ?: $selectedGym->branches->first();
        }

        return [
            'gyms' => $gyms->map(function ($gym) {
                return [
                    'id' => $gym->id,
                    'name' => $gym->name,
                    'code' => $gym->code,
                    'status' => $gym->status,
                    'settings' => $gym->settings,
                    'branches' => $gym->branches->map(function ($branch) {
                        return [
                            'id' => $branch->id,
                            'gym_id' => $branch->gym_id,
                            'name' => $branch->name,
                            'code' => $branch->code,
                            'status' => $branch->status,
                            'address' => $branch->address,
                            'phone' => $branch->phone,
                            'is_default' => (bool) $branch->is_default,
                        ];
                    })->values(),
                ];
            })->values(),
            'selectedGymId' => $selectedGym?->id,
            'selectedBranchId' => $selectedBranch?->id,
        ];
    }
}
