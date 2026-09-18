<?php

namespace App\Support;

use App\Models\Member;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Subscription;
use Carbon\Carbon;

class NotificationService
{
    public static function sendManual(
        string $channel,
        string $type,
        string $title,
        string $body,
        ?string $memberId,
        ?int $createdBy,
        ?int $templateId = null
    ): Notification {
        return Notification::create([
            'member_id' => $memberId,
            'template_id' => $templateId,
            'channel' => $channel,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'status' => 'sent',
            'scheduled_at' => now(),
            'sent_at' => now(),
            'provider_response' => json_encode([
                'provider' => 'mock',
                'message' => 'stored_only',
            ], JSON_UNESCAPED_UNICODE),
            'created_by' => $createdBy,
        ]);
    }

    public static function processSubscriptionReminders(?Carbon $today = null, ?int $createdBy = null): array
    {
        $today = $today ? $today->copy()->startOfDay() : now()->startOfDay();

        $summary = [
            'expiring_3_days' => 0,
            'expires_today' => 0,
            'expired' => 0,
        ];

        $summary['expiring_3_days'] = self::createNotificationsForDateRule(
            type: 'subscription_expiring_3_days',
            targetDate: $today->copy()->addDays(3),
            comparator: '=',
            createdBy: $createdBy
        );

        $summary['expires_today'] = self::createNotificationsForDateRule(
            type: 'subscription_expires_today',
            targetDate: $today,
            comparator: '=',
            createdBy: $createdBy
        );

        $summary['expired'] = self::createNotificationsForDateRule(
            type: 'subscription_expired',
            targetDate: $today,
            comparator: '<',
            createdBy: $createdBy
        );

        return $summary;
    }

    protected static function createNotificationsForDateRule(
        string $type,
        Carbon $targetDate,
        string $comparator,
        ?int $createdBy
    ): int {
        $query = Subscription::query()
            ->whereNotNull('member_id')
            ->whereDate('end_date', $comparator, $targetDate->toDateString());

        $count = 0;
        foreach ($query->get() as $subscription) {
            $member = Member::find($subscription->member_id);
            if (!$member) {
                continue;
            }

            $alreadySentToday = Notification::query()
                ->where('member_id', $member->id)
                ->where('type', $type)
                ->whereDate('scheduled_at', now()->toDateString())
                ->exists();

            if ($alreadySentToday) {
                continue;
            }

            [$title, $body, $templateId, $channel] = self::renderTemplateOrFallback($type, $member, $subscription);

            Notification::create([
                'member_id' => $member->id,
                'template_id' => $templateId,
                'channel' => $channel,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'status' => 'pending',
                'scheduled_at' => now(),
                'created_by' => $createdBy,
            ]);

            $count++;
        }

        return $count;
    }

    protected static function renderTemplateOrFallback(string $type, Member $member, Subscription $subscription): array
    {
        $template = NotificationTemplate::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        $context = [
            '{member_name}' => (string) $member->name,
            '{member_id}' => (string) $member->id,
            '{plan_name}' => (string) ($subscription->plan_name ?? ''),
            '{end_date}' => (string) $subscription->end_date,
            '{remaining_amount}' => number_format((float) ($subscription->remaining ?? 0), 2, '.', ''),
        ];

        if ($template) {
            return [
                strtr((string) $template->title_template, $context),
                strtr((string) $template->body_template, $context),
                (int) $template->id,
                (string) $template->channel,
            ];
        }

        return match ($type) {
            'subscription_expiring_3_days' => [
                'تذكير: اشتراكك سينتهي قريباً',
                'مرحباً ' . $member->name . '، اشتراكك ينتهي بتاريخ ' . $subscription->end_date . '. يرجى التجديد لتجنب الانقطاع.',
                null,
                'whatsapp',
            ],
            'subscription_expires_today' => [
                'تنبيه: اشتراكك ينتهي اليوم',
                'مرحباً ' . $member->name . '، ينتهي اشتراكك اليوم (' . $subscription->end_date . '). يمكنك التجديد الآن من الاستقبال.',
                null,
                'whatsapp',
            ],
            default => [
                'اشتراكك منتهي',
                'مرحباً ' . $member->name . '، اشتراكك منتهي منذ ' . $subscription->end_date . '. نرحب بك لإعادة التفعيل.',
                null,
                'whatsapp',
            ],
        };
    }
}
