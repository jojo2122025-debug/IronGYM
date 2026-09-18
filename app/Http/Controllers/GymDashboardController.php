<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Checkin;
use App\Models\Measurement;
use App\Models\Member;
use App\Models\MembershipCard;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\NutritionProgram;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use App\Models\Trainer;
use App\Models\TrainerAssignment;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * GymDashboardController
 *
 * Handles day-to-day gym operational data:
 *   - Members, subscriptions, payments, products, check-ins
 *   - Trainers and training programs
 *   - Reports scoped to a single gym/branch
 *
 * Route prefix: /api/gym/{action}
 * Required role: any authenticated staff belonging to the gym
 */
class GymDashboardController extends Controller
{
    /**
     * Route handler for /api/gym/{action}
     */
    public function handle(Request $request, string $action): JsonResponse
    {
        $action = strtolower($action);
        $publicActions = ['login'];

        if (!in_array($action, $publicActions, true) && !session()->has('user')) {
            return response()->json(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً'], 401);
        }

        try {
            return match (true) {
                $action === 'login'                    => $this->login($request),
                $action === 'logout'                   => $this->logout($request),
                $action === 'get_state'                => $this->getState(),
                $action === 'select_branch'            => $this->selectBranch($request),
                // Members
                $action === 'add_member'               => $this->addMember($request),
                $action === 'edit_member'              => $this->editMember($request),
                $action === 'issue_membership_card'    => $this->issueMembershipCard($request),
                // Plans
                $action === 'add_plan'                 => $this->addPlan($request),
                $action === 'edit_plan'                => $this->editPlan($request),
                // Subscriptions
                $action === 'add_subscription'         => $this->addSubscription($request),
                $action === 'edit_subscription'        => $this->editSubscription($request),
                $action === 'toggle_subscription'      => $this->toggleSubscription($request),
                // Payments
                $action === 'add_payment'              => $this->addPayment($request),
                $action === 'edit_payment'             => $this->editPayment($request),
                // Products & Sales
                $action === 'add_product'              => $this->addProduct($request),
                $action === 'edit_product'             => $this->editProduct($request),
                $action === 'checkout_basket'          => $this->checkoutBasket($request),
                // Check-in / Check-out
                $action === 'check_in'                 => $this->checkIn($request),
                $action === 'scan_check_in'            => $this->checkIn($request),
                $action === 'check_out'                => $this->checkOut($request),
                // Measurements
                $action === 'add_measurement'          => $this->addMeasurement($request),
                // Password
                $action === 'change_password'          => $this->changePassword($request),
                // Trainers
                $action === 'add_trainer_profile'      => $this->addTrainerProfile($request),
                $action === 'assign_trainer_member'    => $this->assignTrainerMember($request),
                $action === 'add_training_program'     => $this->addTrainingProgram($request),
                $action === 'edit_training_program'    => $this->editTrainingProgram($request),
                $action === 'delete_training_program'  => $this->deleteTrainingProgram($request),
                $action === 'add_nutrition_program'    => $this->addNutritionProgram($request),
                $action === 'edit_nutrition_program'   => $this->editNutritionProgram($request),
                $action === 'delete_nutrition_program' => $this->deleteNutritionProgram($request),
                // Notifications
                $action === 'add_notification_template'=> $this->addNotificationTemplate($request),
                $action === 'send_notification'        => $this->sendNotification($request),
                // Gym-scoped users
                $action === 'get_users'                => $this->getGymUsers(),
                $action === 'add_user'                 => $this->addGymUser($request),
                $action === 'edit_user'                => $this->editGymUser($request),
                $action === 'delete_user'              => $this->deleteGymUser($request),
                // Search
                $action === 'global_search'            => $this->globalSearch($request),
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

    protected function requireUser(): array
    {
        $user = session('user');
        if (!$user) {
            abort(response()->json(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً'], 401));
        }
        return $user;
    }

    protected function requireNotReadOnly(): void
    {
        $user = $this->requireUser();
        if ($user['role'] === 'المدقق المالي') {
            abort(response()->json(['success' => false, 'error' => 'حسابك للقراءة فقط'], 403));
        }
    }

    protected function requireAnyRole(array $roles): void
    {
        $user = $this->requireUser();
        if (!in_array($user['role'], $roles, true)) {
            abort(response()->json(['success' => false, 'error' => 'غير مصرح لك'], 403));
        }
    }

    /** Returns the current gym/branch ID from the session. */
    protected function currentGymId(): ?int
    {
        return session('user.gym_id') ? (int) session('user.gym_id') : null;
    }

    protected function currentBranchId(): ?int
    {
        return session('user.branch_id') ? (int) session('user.branch_id') : null;
    }

    // ---------------------------------------------------------------
    // Auth
    // ---------------------------------------------------------------

    protected function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'اسم المستخدم وكلمة المرور مطلوبان'], 422);
        }

        $user = User::where('username', $request->input('username'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json(['success' => false, 'error' => 'اسم المستخدم أو كلمة المرور غير صحيحة'], 401);
        }

        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'هذا المسار مخصص لطاقم الصالة فقط',
            ], 403);
        }

        $sessionData = $user->toSession();
        session(['user' => $sessionData]);
        ActivityLog::log('تسجيل دخول (صالة)', 'تم تسجيل الدخول إلى لوحة الصالة');

        $memberData  = null;
        $trainerData = null;

        if ($user->isMember() && !empty($user->member_id)) {
            $member = Member::find($user->member_id);
            $memberData = [
                'member'        => $member,
                'subscriptions' => Subscription::where('member_id', $user->member_id)->get(),
                'payments'      => $member ? Payment::where('member_id', $member->id)->get() : collect(),
                'measurements'  => Measurement::where('member_id', $user->member_id)->orderByDesc('id')->get(),
            ];
        }

        if ($user->isTrainer()) {
            $trainerData = $this->buildTrainerDataForUserId((int) $user->id);
        }

        return response()->json([
            'success'     => true,
            'user'        => $sessionData,
            'memberData'  => $memberData,
            'trainerData' => $trainerData,
        ]);
    }

    protected function logout(Request $request): JsonResponse
    {
        ActivityLog::log('تسجيل خروج (صالة)', 'تسجيل خروج من لوحة الصالة');
        $request->session()->forget('user');
        return response()->json(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Gym State (operational data for one gym/branch)
    // ---------------------------------------------------------------

    protected function getState(): JsonResponse
    {
        $branchId = $this->currentBranchId();

        $members            = Member::orderByDesc('id')->get();
        $plans              = Plan::orderBy('id')->get();
        $subscriptions      = Subscription::orderByDesc('id')->get();
        $payments           = Payment::orderByDesc('date')->get();
        $measurements       = Measurement::orderByDesc('id')->get();
        $membershipCards    = MembershipCard::orderByDesc('id')->get();
        $products           = Product::orderBy('id')->get();
        $sales              = Sale::orderByDesc('date')->get();
        $checkins           = Checkin::orderByDesc('id')->get();
        $trainers           = Trainer::with('user:id,name,username,role')->orderByDesc('id')->get();
        $trainerAssignments = TrainerAssignment::orderByDesc('id')->get();
        $trainingPrograms   = TrainingProgram::orderByDesc('id')->get();
        $nutritionPrograms  = NutritionProgram::orderByDesc('id')->get();
        $notificationTemplates = NotificationTemplate::orderByDesc('id')->get();
        $notifications      = Notification::orderByDesc('id')->limit(500)->get();

        // Revenue history (last 14 days)
        $dates = collect(range(13, 0))->map(fn ($i) => Carbon::now()->subDays($i)->toDateString())->toArray();
        $dailyRevenues = array_fill_keys($dates, 0);
        DB::table('payments')
            ->selectRaw("DATE(`date`) as pay_date, SUM(amount) as sum_amt")
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupByRaw('DATE(`date`)')
            ->get()
            ->each(function ($row) use (&$dailyRevenues) {
                if (isset($dailyRevenues[$row->pay_date])) {
                    $dailyRevenues[$row->pay_date] = (float) $row->sum_amt;
                }
            });

        // Peak hours
        $peakHours = array_fill_keys(array_map(fn ($i) => str_pad((string) $i, 2, '0', STR_PAD_LEFT), range(0, 23)), 0);
        DB::table('checkins')
            ->selectRaw("SUBSTRING(created_at, 12, 2) as chk_hour, COUNT(*) as cnt")
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('chk_hour')
            ->get()
            ->each(function ($row) use (&$peakHours) {
                if (isset($peakHours[$row->chk_hour])) {
                    $peakHours[$row->chk_hour] = (int) $row->cnt;
                }
            });

        $today         = Carbon::now()->toDateString();
        $monthly       = Carbon::now()->format('Y-m');
        $todayRevenue  = (float) Payment::where('date', 'like', "$today%")->sum('amount');
        $monthlyRevenue= (float) Payment::where('date', 'like', "$monthly-%")->sum('amount');
        $productSales  = (float) Sale::sum('total');

        $activeMembers    = Subscription::where('status', 'فعال')->distinct('member_id')->count('member_id');
        $todayAttendance  = Checkin::where('created_at', 'like', "$today%")->count();

        $currUser   = session('user');
        $memberData = null;
        $trainerData = null;

        if ($currUser && $currUser['role'] === 'مشترك' && !empty($currUser['member_id'])) {
            $member = Member::find($currUser['member_id']);
            $memberData = [
                'member'        => $member,
                'subscriptions' => Subscription::where('member_id', $currUser['member_id'])->get(),
                'payments'      => $member ? Payment::where('member_id', $member->id)->get() : collect(),
                'measurements'  => Measurement::where('member_id', $currUser['member_id'])->orderByDesc('id')->get(),
            ];
        }

        if ($currUser && $currUser['role'] === 'مدرب') {
            $trainerData = $this->buildTrainerDataForUserId((int) ($currUser['id'] ?? 0));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'members'               => $members,
                'plans'                 => $plans,
                'subscriptions'         => $subscriptions,
                'payments'              => $payments,
                'measurements'          => $measurements,
                'membershipCards'       => $membershipCards,
                'products'              => $products,
                'sales'                 => $sales,
                'checkins'              => $checkins,
                'trainers'              => $trainers,
                'trainerAssignments'    => $trainerAssignments,
                'trainingPrograms'      => $trainingPrograms,
                'nutritionPrograms'     => $nutritionPrograms,
                'notificationTemplates' => $notificationTemplates,
                'notifications'         => $notifications,
                'todayRevenue'          => $todayRevenue,
                'monthlyRevenue'        => $monthlyRevenue,
                'productSales'          => $productSales,
                'activeMembers'         => $activeMembers,
                'todayAttendance'       => $todayAttendance,
                'revenueHistory'        => $dailyRevenues,
                'peakHours'             => $peakHours,
                'currentUser'           => $currUser,
                'memberData'            => $memberData,
                'trainerData'           => $trainerData,
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // Branch selection
    // ---------------------------------------------------------------

    protected function selectBranch(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'gym_id'    => 'required|exists:gyms,id',
            'branch_id' => 'required|exists:branches,id',
        ])->validate();

        $user              = $this->requireUser();
        $user['gym_id']    = (int) $validated['gym_id'];
        $user['branch_id'] = (int) $validated['branch_id'];
        session(['user' => $user]);

        return response()->json(['success' => true, 'gym_id' => $user['gym_id'], 'branch_id' => $user['branch_id']]);
    }

    // ---------------------------------------------------------------
    // Members
    // ---------------------------------------------------------------

    protected function addMember(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة', 'موظف الاستقبال']);
        $this->requireNotReadOnly();

        $data = $request->all();
        $validator = Validator::make($data, [
            'name'   => 'required|string|max:100',
            'phone'  => 'required|string|max:20',
            'gender' => 'required|in:ذكر,أنثى',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $gymId    = $this->currentGymId();
        $branchId = $this->currentBranchId();

        $latestId = Member::where('id', 'like', 'M%')
            ->orderByDesc('id')->value('id');
        $nextNum  = $latestId ? (int) ltrim(substr($latestId, 1), '0') + 1 : 1;
        $newId    = 'M' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file      = $request->file('image');
            $filename  = 'member_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads'), $filename);
            $imagePath = 'uploads/' . $filename;
        }

        $member = Member::create([
            'id'         => $newId,
            'name'       => $data['name'],
            'phone'      => $data['phone'],
            'gender'     => $data['gender'],
            'whatsapp'   => $data['whatsapp'] ?? null,
            'notes'      => $data['notes'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'image_path' => $imagePath,
            'gym_id'     => $gymId,
            'branch_id'  => $branchId,
        ]);

        ActivityLog::log('إضافة مشترك', "تم إضافة مشترك: {$member->name}");

        return response()->json(['success' => true, 'member' => $member]);
    }

    protected function editMember(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة', 'موظف الاستقبال']);
        $this->requireNotReadOnly();

        $data = $request->all();
        $validator = Validator::make($data, [
            'id'   => 'required|exists:members,id',
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $member = Member::findOrFail($data['id']);

        $imagePath = $member->image_path;
        if ($request->hasFile('image')) {
            $file      = $request->file('image');
            $filename  = 'member_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads'), $filename);
            $imagePath = 'uploads/' . $filename;
        }

        $member->update([
            'name'       => $data['name'],
            'phone'      => $data['phone'] ?? $member->phone,
            'gender'     => $data['gender'] ?? $member->gender,
            'whatsapp'   => $data['whatsapp'] ?? $member->whatsapp,
            'notes'      => $data['notes'] ?? $member->notes,
            'birth_date' => $data['birth_date'] ?? $member->birth_date,
            'image_path' => $imagePath,
        ]);

        ActivityLog::log('تعديل مشترك', "تم تعديل مشترك: {$member->name}");

        return response()->json(['success' => true, 'member' => $member]);
    }

    protected function issueMembershipCard(Request $request): JsonResponse
    {
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
        ])->validate();

        $card = MembershipCard::where('member_id', $validated['member_id'])->first();
        if (!$card) {
            $card = MembershipCard::create([
                'member_id'  => $validated['member_id'],
                'card_number'=> 'GYM-' . strtoupper(Str::random(8)),
                'issued_at'  => now(),
            ]);
        }

        return response()->json(['success' => true, 'card' => $card]);
    }

    // ---------------------------------------------------------------
    // Plans
    // ---------------------------------------------------------------

    protected function addPlan(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة', 'المحاسب']);
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), [
            'name'  => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'days'  => 'required|integer|min:1',
        ])->validate();

        $latestId = Plan::where('id', 'like', 'P%')->orderByDesc('id')->value('id');
        $nextNum  = $latestId ? (int) ltrim(substr($latestId, 1), '0') + 1 : 1;
        $newId    = 'P' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $plan = Plan::create([
            'id'          => $newId,
            'name'        => $validated['name'],
            'description' => $request->input('description'),
            'price'       => $validated['price'],
            'days'        => $validated['days'],
            'gym_id'      => $this->currentGymId(),
            'branch_id'   => $this->currentBranchId(),
        ]);

        ActivityLog::log('إضافة خطة', "خطة: {$plan->name}");
        return response()->json(['success' => true, 'plan' => $plan]);
    }

    protected function editPlan(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة', 'المحاسب']);
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), [
            'id'    => 'required|exists:plans,id',
            'name'  => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'days'  => 'required|integer|min:1',
        ])->validate();

        $plan = Plan::findOrFail($validated['id']);
        $plan->update([
            'name'        => $validated['name'],
            'description' => $request->input('description', $plan->description),
            'price'       => $validated['price'],
            'days'        => $validated['days'],
        ]);

        ActivityLog::log('تعديل خطة', "خطة: {$plan->name}");
        return response()->json(['success' => true, 'plan' => $plan]);
    }

    // ---------------------------------------------------------------
    // Subscriptions
    // ---------------------------------------------------------------

    protected function addSubscription(Request $request): JsonResponse
    {
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), [
            'member_id'  => 'required|exists:members,id',
            'plan_id'    => 'nullable|exists:plans,id',
            'start_date' => 'required|date',
            'paid'       => 'nullable|numeric|min:0',
        ])->validate();

        $latestId = Subscription::where('id', 'like', 'S%')->orderByDesc('id')->value('id');
        $nextNum  = $latestId ? (int) ltrim(substr($latestId, 1), '0') + 1 : 1;
        $newId    = 'S' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $plan     = !empty($validated['plan_id']) ? Plan::find($validated['plan_id']) : null;
        $planName = $plan?->name ?? $request->input('plan_name', '');
        $days     = $plan?->days ?? (int) $request->input('days', 30);
        $amount   = $plan?->price ?? (float) $request->input('amount', 0);
        $paid     = (float) ($validated['paid'] ?? 0);
        $startDate= Carbon::parse($validated['start_date']);
        $endDate  = $startDate->copy()->addDays($days);

        $sub = Subscription::create([
            'id'         => $newId,
            'member_id'  => $validated['member_id'],
            'plan_id'    => $validated['plan_id'] ?? null,
            'plan_name'  => $planName,
            'start_date' => $startDate->toDateString(),
            'end_date'   => $endDate->toDateString(),
            'amount'     => $amount,
            'paid'       => $paid,
            'remaining'  => max(0, $amount - $paid),
            'status'     => 'فعال',
            'gym_id'     => $this->currentGymId(),
            'branch_id'  => $this->currentBranchId(),
        ]);

        ActivityLog::log('إضافة اشتراك', "اشتراك: {$sub->id} للعضو: {$sub->member_id}");
        return response()->json(['success' => true, 'subscription' => $sub]);
    }

    protected function editSubscription(Request $request): JsonResponse
    {
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), [
            'id'         => 'required|exists:subscriptions,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
            'amount'     => 'required|numeric|min:0',
            'paid'       => 'required|numeric|min:0',
            'status'     => 'required|in:فعال,مجمد,منتهي',
        ])->validate();

        $sub = Subscription::findOrFail($validated['id']);
        $sub->update([
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'amount'     => $validated['amount'],
            'paid'       => $validated['paid'],
            'remaining'  => max(0, $validated['amount'] - $validated['paid']),
            'status'     => $validated['status'],
            'plan_name'  => $request->input('plan_name', $sub->plan_name),
        ]);

        ActivityLog::log('تعديل اشتراك', "اشتراك: {$sub->id}");
        return response()->json(['success' => true, 'subscription' => $sub]);
    }

    protected function toggleSubscription(Request $request): JsonResponse
    {
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), ['id' => 'required|exists:subscriptions,id'])->validate();

        $sub = Subscription::findOrFail($validated['id']);
        $newStatus = $sub->status === 'مجمد' ? 'فعال' : 'مجمد';
        $sub->update(['status' => $newStatus]);

        ActivityLog::log('تغيير حالة اشتراك', "اشتراك: {$sub->id} → {$newStatus}");
        return response()->json(['success' => true, 'subscription' => $sub]);
    }

    // ---------------------------------------------------------------
    // Payments
    // ---------------------------------------------------------------

    protected function addPayment(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة', 'المحاسب']);
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), [
            'member_id' => 'nullable|exists:members,id',
            'memberId' => 'nullable|string',
            'amount'    => 'required|numeric|min:0',
            'method'    => 'required|in:نقدي,تحويل',
            'transferFromAccount' => 'nullable|string|max:255',
        ])->validate();

        $memberId = $validated['member_id'] ?? $validated['memberId'] ?? null;
        $member = $memberId ? Member::find($memberId) : null;
        $paymentId = Payment::generateNextId();

        $payment = Payment::create([
            'id'                   => $paymentId,
            'member_id'            => $member?->id,
            'member_name'          => $member?->name ?? $request->input('member_name'),
            'amount'               => $validated['amount'],
            'method'               => $validated['method'],
            'transfer_from_account' => $validated['method'] === 'تحويل' ? ($request->input('transferFromAccount') ?? null) : null,
            'note'                 => $request->input('note'),
            'date'                 => now()->toDateTimeString(),
            'gym_id'               => $this->currentGymId(),
            'branch_id'            => $this->currentBranchId(),
        ]);

        ActivityLog::log('إضافة دفعة', "مبلغ: {$payment->amount}");
        return response()->json(['success' => true, 'payment' => $payment]);
    }

    protected function editPayment(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة', 'المحاسب']);
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), [
            'id'     => 'required|exists:payments,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:نقدي,تحويل',
            'transferFromAccount' => 'nullable|string|max:255',
        ])->validate();

        $payment = Payment::findOrFail($validated['id']);
        $payment->update([
            'amount'               => $validated['amount'],
            'method'               => $validated['method'],
            'transfer_from_account' => $validated['method'] === 'تحويل' ? ($request->input('transferFromAccount', $payment->transfer_from_account)) : null,
            'note'                 => $request->input('note', $payment->note),
        ]);

        ActivityLog::log('تعديل دفعة', "دفعة: {$payment->id}");
        return response()->json(['success' => true, 'payment' => $payment]);
    }

    // ---------------------------------------------------------------
    // Products & Sales
    // ---------------------------------------------------------------

    protected function addProduct(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة', 'المحاسب']);
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), [
            'name'  => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ])->validate();

        $product = Product::create([
            'name'      => $validated['name'],
            'price'     => $validated['price'],
            'stock'     => $validated['stock'],
            'gym_id'    => $this->currentGymId(),
            'branch_id' => $this->currentBranchId(),
        ]);

        ActivityLog::log('إضافة منتج', "منتج: {$product->name}");
        return response()->json(['success' => true, 'product' => $product]);
    }

    protected function editProduct(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة', 'المحاسب']);
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), [
            'id'    => 'required|exists:products,id',
            'name'  => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ])->validate();

        $product = Product::findOrFail($validated['id']);
        $product->update([
            'name'  => $validated['name'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
        ]);

        ActivityLog::log('تعديل منتج', "منتج: {$product->name}");
        return response()->json(['success' => true, 'product' => $product]);
    }

    protected function checkoutBasket(Request $request): JsonResponse
    {
        $this->requireNotReadOnly();

        $validated = Validator::make($request->all(), [
            'items'  => 'required|array|min:1',
            'method' => 'required|in:نقدي,تحويل',
        ])->validate();

        $total     = 0;
        $saleItems = [];

        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $qty     = (int) $item['qty'];
            $product->decrement('stock', $qty);
            $lineTotal = $product->price * $qty;
            $total    += $lineTotal;
            $saleItems[] = ['product_id' => $product->id, 'name' => $product->name, 'qty' => $qty, 'price' => $product->price, 'total' => $lineTotal];
        }

        $sale = Sale::create([
            'date'      => now()->toDateTimeString(),
            'products'  => json_encode($saleItems),
            'total'     => $total,
            'method'    => $validated['method'],
            'gym_id'    => $this->currentGymId(),
            'branch_id' => $this->currentBranchId(),
        ]);

        ActivityLog::log('مبيعات', "إجمالي: {$total}");
        return response()->json(['success' => true, 'sale' => $sale, 'total' => $total]);
    }

    // ---------------------------------------------------------------
    // Check-in / Check-out
    // ---------------------------------------------------------------

    protected function checkIn(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة', 'موظف الاستقبال']);

        $validated = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
        ])->validate();

        $memberId = $validated['member_id'];
        $member   = Member::findOrFail($memberId);

        // Check active subscription
        $today = Carbon::now()->toDateString();
        $sub   = Subscription::where('member_id', $memberId)
            ->where('status', 'فعال')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->first();

        $checkin = Checkin::create([
            'member_id'   => $memberId,
            'member_name' => $member->name,
            'time'        => now()->toTimeString(),
            'gym_id'      => $this->currentGymId(),
            'branch_id'   => $this->currentBranchId(),
        ]);

        ActivityLog::log('تسجيل حضور', "مشترك: {$member->name}");

        return response()->json([
            'success'      => true,
            'checkin'      => $checkin,
            'member'       => $member,
            'subscription' => $sub,
            'hasActiveSub' => (bool) $sub,
        ]);
    }

    protected function checkOut(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
        ])->validate();

        $last = Checkin::where('member_id', $validated['member_id'])
            ->whereNull('checkout_time')
            ->latest()
            ->first();

        if ($last) {
            $last->update(['checkout_time' => now()->toTimeString()]);
        }

        return response()->json(['success' => true, 'checkin' => $last]);
    }

    // ---------------------------------------------------------------
    // Measurements
    // ---------------------------------------------------------------

    protected function addMeasurement(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'member_id'    => 'required|exists:members,id',
            'weight'       => 'required|numeric',
            'height'       => 'required|numeric',
            'fat_percentage' => 'nullable|numeric',
            'muscle_mass'  => 'nullable|numeric',
        ])->validate();

        $m = Measurement::create([
            'member_id'      => $validated['member_id'],
            'weight'         => $validated['weight'],
            'height'         => $validated['height'],
            'fat_percentage' => $validated['fat_percentage'] ?? 0,
            'muscle_mass'    => $validated['muscle_mass'] ?? 0,
            'gym_id'         => $this->currentGymId(),
            'branch_id'      => $this->currentBranchId(),
        ]);

        ActivityLog::log('إضافة قياس', "عضو: {$m->member_id}");
        return response()->json(['success' => true, 'measurement' => $m]);
    }

    // ---------------------------------------------------------------
    // Password
    // ---------------------------------------------------------------

    protected function changePassword(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $validated = Validator::make($request->all(), [
            'currentPassword' => 'required|string',
            'newPassword'     => 'required|string|min:6',
        ])->validate();

        $userModel = User::findOrFail($user['id']);
        if (!Hash::check($validated['currentPassword'], $userModel->password)) {
            return response()->json(['success' => false, 'error' => 'كلمة المرور الحالية غير صحيحة'], 422);
        }

        $userModel->update(['password' => Hash::make($validated['newPassword'])]);
        ActivityLog::log('تغيير كلمة المرور', "المستخدم: {$userModel->username}");
        return response()->json(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Trainers
    // ---------------------------------------------------------------

    protected function addTrainerProfile(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة']);

        $validated = Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'speciality' => 'nullable|string',
        ])->validate();

        $trainer = Trainer::firstOrCreate(
            ['user_id' => $validated['user_id']],
            [
                'speciality' => $validated['speciality'] ?? null,
                'gym_id'     => $this->currentGymId(),
                'branch_id'  => $this->currentBranchId(),
            ]
        );

        return response()->json(['success' => true, 'trainer' => $trainer]);
    }

    protected function assignTrainerMember(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة']);

        $validated = Validator::make($request->all(), [
            'trainer_id' => 'required|exists:trainers,id',
            'member_id'  => 'required|exists:members,id',
        ])->validate();

        $assignment = TrainerAssignment::firstOrCreate($validated);
        return response()->json(['success' => true, 'assignment' => $assignment]);
    }

    protected function addTrainingProgram(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'trainer_id' => 'required|exists:trainers,id',
            'member_id'  => 'required|exists:members,id',
            'title'      => 'required|string',
        ])->validate();

        $prog = TrainingProgram::create(array_merge($validated, ['details' => $request->input('details')]));
        return response()->json(['success' => true, 'program' => $prog]);
    }

    protected function editTrainingProgram(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), ['id' => 'required|exists:training_programs,id', 'title' => 'required|string'])->validate();
        $prog = TrainingProgram::findOrFail($validated['id']);
        $prog->update(['title' => $validated['title'], 'details' => $request->input('details', $prog->details)]);
        return response()->json(['success' => true, 'program' => $prog]);
    }

    protected function deleteTrainingProgram(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), ['id' => 'required|exists:training_programs,id'])->validate();
        TrainingProgram::findOrFail($validated['id'])->delete();
        return response()->json(['success' => true]);
    }

    protected function addNutritionProgram(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'trainer_id' => 'required|exists:trainers,id',
            'member_id'  => 'required|exists:members,id',
            'title'      => 'required|string',
        ])->validate();

        $prog = NutritionProgram::create(array_merge($validated, ['details' => $request->input('details')]));
        return response()->json(['success' => true, 'program' => $prog]);
    }

    protected function editNutritionProgram(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), ['id' => 'required|exists:nutrition_programs,id', 'title' => 'required|string'])->validate();
        $prog = NutritionProgram::findOrFail($validated['id']);
        $prog->update(['title' => $validated['title'], 'details' => $request->input('details', $prog->details)]);
        return response()->json(['success' => true, 'program' => $prog]);
    }

    protected function deleteNutritionProgram(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), ['id' => 'required|exists:nutrition_programs,id'])->validate();
        NutritionProgram::findOrFail($validated['id'])->delete();
        return response()->json(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Notifications
    // ---------------------------------------------------------------

    protected function addNotificationTemplate(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'name'    => 'required|string',
            'message' => 'required|string',
        ])->validate();

        $tpl = NotificationTemplate::create($validated);
        return response()->json(['success' => true, 'template' => $tpl]);
    }

    protected function sendNotification(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'member_id'   => 'required|exists:members,id',
            'template_id' => 'nullable|exists:notification_templates,id',
            'message'     => 'nullable|string',
        ])->validate();

        $message = $validated['message']
            ?? NotificationTemplate::find($validated['template_id'])?->message
            ?? '';

        $notification = Notification::create([
            'member_id'   => $validated['member_id'],
            'template_id' => $validated['template_id'] ?? null,
            'message'     => $message,
            'status'      => 'pending',
        ]);

        return response()->json(['success' => true, 'notification' => $notification]);
    }

    // ---------------------------------------------------------------
    // Gym-scoped Users (staff of the gym)
    // ---------------------------------------------------------------

    protected function getGymUsers(): JsonResponse
    {
        $gymId = $this->currentGymId();
        $users = User::where('gym_id', $gymId)
            ->select('id', 'username', 'name', 'role', 'gym_id', 'branch_id')
            ->get();
        return response()->json(['success' => true, 'users' => $users]);
    }

    protected function addGymUser(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة']);

        $gymId = $this->currentGymId();

        $validated = Validator::make($request->all(), [
            'username'  => 'required|string|max:50|unique:users,username',
            'name'      => 'required|string|max:100',
            'password'  => 'required|string|min:6',
            'role'      => 'required|in:' . implode(',', array_diff(User::ROLES, ['مدير النظام'])),
            'branch_id' => 'nullable|exists:branches,id',
        ])->validate();

        $user = User::create([
            'username'  => $validated['username'],
            'name'      => $validated['name'],
            'password'  => Hash::make($validated['password']),
            'role'      => $validated['role'],
            'gym_id'    => $gymId,
            'branch_id' => $validated['branch_id'] ?? $this->currentBranchId(),
        ]);

        ActivityLog::log('إضافة مستخدم صالة', "مستخدم: {$user->username}");
        return response()->json(['success' => true, 'user' => $user->only(['id','username','name','role','gym_id','branch_id'])]);
    }

    protected function editGymUser(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة']);

        $validated = Validator::make($request->all(), [
            'id'       => 'required|exists:users,id',
            'name'     => 'required|string|max:100',
            'role'     => 'required|in:' . implode(',', array_diff(User::ROLES, ['مدير النظام'])),
            'password' => 'nullable|string|min:6',
        ])->validate();

        $user = User::findOrFail($validated['id']);
        $data = ['name' => $validated['name'], 'role' => $validated['role']];
        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }
        $user->update($data);

        ActivityLog::log('تعديل مستخدم صالة', "مستخدم: {$user->username}");
        return response()->json(['success' => true, 'user' => $user->only(['id','username','name','role','gym_id','branch_id'])]);
    }

    protected function deleteGymUser(Request $request): JsonResponse
    {
        $this->requireAnyRole(['مدير النظام', 'مدير الصالة']);

        $validated = Validator::make($request->all(), ['id' => 'required|exists:users,id'])->validate();
        $user = User::findOrFail($validated['id']);
        $current = session('user');

        if ((int) $user->id === (int) ($current['id'] ?? 0)) {
            return response()->json(['success' => false, 'error' => 'لا يمكنك حذف حسابك الحالي'], 422);
        }

        $username = $user->username;
        $user->delete();

        ActivityLog::log('حذف مستخدم صالة', "حُذف: {$username}");
        return response()->json(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Global search (scoped to this gym)
    // ---------------------------------------------------------------

    protected function globalSearch(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        $members = Member::where('name', 'like', "%{$q}%")
            ->orWhere('id', 'like', "%{$q}%")
            ->orWhere('phone', 'like', "%{$q}%")
            ->limit(20)->get(['id','name','phone']);

        $subscriptions = Subscription::where('id', 'like', "%{$q}%")
            ->orWhere('member_id', 'like', "%{$q}%")
            ->limit(10)->get();

        return response()->json([
            'success' => true,
            'results' => [
                'members'       => $members,
                'subscriptions' => $subscriptions,
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildTrainerDataForUserId(int $userId): ?array
    {
        $trainer = Trainer::with('user:id,name,username,role')->where('user_id', $userId)->first();
        if (!$trainer) return null;

        $assignments    = TrainerAssignment::where('trainer_id', $trainer->id)->get();
        $memberIds      = $assignments->pluck('member_id');
        $members        = Member::whereIn('id', $memberIds)->get();
        $trainingProgs  = TrainingProgram::where('trainer_id', $trainer->id)->get();
        $nutritionProgs = NutritionProgram::where('trainer_id', $trainer->id)->get();

        return [
            'trainer'           => $trainer,
            'assignments'       => $assignments,
            'members'           => $members,
            'trainingPrograms'  => $trainingProgs,
            'nutritionPrograms' => $nutritionProgs,
        ];
    }
}
