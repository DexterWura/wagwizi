<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $this->remapPlatforms(
            fromPlatform: 'linkedin',
            toPlatform: 'linkedin_pages',
            accountType: 'organization',
        );

        $this->remapPlatforms(
            fromPlatform: 'facebook',
            toPlatform: 'facebook_pages',
            accountType: 'page',
        );
    }

    public function down(): void
    {
        $this->remapPlatforms(
            fromPlatform: 'linkedin_pages',
            toPlatform: 'linkedin',
            accountType: 'organization',
        );

        $this->remapPlatforms(
            fromPlatform: 'facebook_pages',
            toPlatform: 'facebook',
            accountType: 'page',
        );
    }

    private function remapPlatforms(string $fromPlatform, string $toPlatform, string $accountType): void
    {
        $migratedSocialAccountIds = [];

        DB::table('social_accounts')
            ->select(['id', 'user_id', 'platform_user_id', 'metadata'])
            ->where('platform', $fromPlatform)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($toPlatform, $accountType, &$migratedSocialAccountIds): void {
                foreach ($rows as $row) {
                    $metadata = json_decode((string) ($row->metadata ?? ''), true);
                    if (!is_array($metadata)) {
                        continue;
                    }

                    $detectedType = strtolower(trim((string) ($metadata['account_type'] ?? '')));
                    if ($detectedType !== $accountType) {
                        continue;
                    }

                    $existingTarget = DB::table('social_accounts')
                        ->select('id')
                        ->where('user_id', (int) $row->user_id)
                        ->where('platform', $toPlatform)
                        ->where('platform_user_id', (string) $row->platform_user_id)
                        ->first();

                    if ($existingTarget !== null && (int) $existingTarget->id !== (int) $row->id) {
                        DB::table('post_platforms')
                            ->where('social_account_id', (int) $row->id)
                            ->update([
                                'social_account_id' => (int) $existingTarget->id,
                                'platform' => $toPlatform,
                            ]);

                        DB::table('social_accounts')
                            ->where('id', (int) $row->id)
                            ->delete();

                        continue;
                    }

                    DB::table('social_accounts')
                        ->where('id', (int) $row->id)
                        ->update(['platform' => $toPlatform]);

                    $migratedSocialAccountIds[] = (int) $row->id;
                }
            });

        if ($migratedSocialAccountIds === []) {
            return;
        }

        DB::table('post_platforms')
            ->whereIn('social_account_id', array_values(array_unique($migratedSocialAccountIds)))
            ->update(['platform' => $toPlatform]);
    }
};
