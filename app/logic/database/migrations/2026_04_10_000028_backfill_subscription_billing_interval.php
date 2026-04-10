<?php

use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Subscription::query()
            ->whereNull('billing_interval')
            ->whereNotNull('plan_id')
            ->whereHas('planModel', static function ($q): void {
                $q->where('is_free', false)->where('is_lifetime', false);
            })
            ->update(['billing_interval' => 'monthly']);
    }

    public function down(): void
    {
        // Non-destructive: cannot know which rows were backfilled vs user-set.
    }
};
