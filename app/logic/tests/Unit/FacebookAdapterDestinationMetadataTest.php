<?php

namespace Tests\Unit;

use App\Models\SocialAccount;
use App\Services\Platform\Adapters\FacebookAdapter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class FacebookAdapterDestinationMetadataTest extends TestCase
{
    public function test_page_destination_uses_page_token_from_metadata(): void
    {
        $adapter = new FacebookAdapter();
        $account = new SocialAccount([
            'platform_user_id' => '123',
            'access_token' => 'user-token',
            'metadata' => [
                'account_type' => 'page',
                'page_access_token' => 'page-token',
            ],
        ]);

        $resolved = $this->callPrivate($adapter, 'facebookPublishingAccount', [$account]);

        $this->assertInstanceOf(SocialAccount::class, $resolved);
        $this->assertSame('page-token', (string) $resolved->access_token);
    }

    public function test_non_page_destination_is_rejected(): void
    {
        $adapter = new FacebookAdapter();
        $account = new SocialAccount([
            'platform_user_id' => '123',
            'access_token' => 'user-token',
            'metadata' => [
                'account_type' => 'person',
            ],
        ]);

        $resolved = $this->callPrivate($adapter, 'facebookPublishingAccount', [$account]);

        $this->assertNull($resolved);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function callPrivate(object $instance, string $method, array $arguments): mixed
    {
        $ref = new ReflectionMethod($instance, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($instance, $arguments);
    }
}
