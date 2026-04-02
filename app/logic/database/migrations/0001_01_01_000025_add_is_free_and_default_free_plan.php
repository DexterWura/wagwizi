<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('is_active');
        });

        $now = now();

        DB::table('plans')->updateOrInsert(
            ['slug' => 'free'],
            [
                'name'                          => 'Free',
                'description'                   => 'Get started with core scheduling — up to 3 connected accounts.',
                'monthly_price_cents'           => 0,
                'yearly_price_cents'            => 0,
                'max_social_profiles'           => 3,
                'max_scheduled_posts_per_month' => 30,
                'features'                      => json_encode([
                    '3 social profiles',
                    '30 scheduled posts / month',
                    'Basic analytics',
                    'Email support',
                ]),
                'allowed_platforms'           => null,
                'is_active'                   => true,
                'is_free'                     => true,
                'is_lifetime'                 => false,
                'lifetime_max_subscribers'    => null,
                'lifetime_current_count'      => 0,
                'sort_order'                  => 0,
                'updated_at'                  => $now,
                'created_at'                  => $now,
            ],
        );

        $freePlanId = (int) DB::table('plans')->where('slug', 'free')->value('id');

        if ($freePlanId === 0) {
            return;
        }

        DB::table('subscriptions')->whereNull('plan_id')->update([
            'plan_id' => $freePlanId,
            'plan'    => 'free',
        ]);

        $userIdsWithSub = DB::table('subscriptions')->pluck('user_id')->all();
        $usersQuery = DB::table('users');
        if ($userIdsWithSub !== []) {
            $usersQuery->whereNotIn('id', $userIdsWithSub);
        }
        $userIdsNeeding = $usersQuery->pluck('id');

        foreach ($userIdsNeeding as $userId) {
            DB::table('subscriptions')->insert([
                'user_id'              => $userId,
                'plan_id'              => $freePlanId,
                'plan'                 => 'free',
                'gateway'              => null,
                'gateway_subscription_id' => null,
                'status'               => 'active',
                'current_period_start'   => $now,
                'current_period_end'     => null,
                'trial_ends_at'        => null,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });
    }
};
