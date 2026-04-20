<?php

namespace Tests\Unit;

use App\Models\SocialAccount;
use App\Services\Platform\Adapters\LinkedInAdapter;
use App\Services\Platform\Adapters\LinkedInPagesAdapter;
use App\Services\Platform\Platform;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class LinkedInAdapterDestinationMetadataTest extends TestCase
{
    public function test_author_urn_defaults_to_person_when_no_metadata(): void
    {
        $adapter = new LinkedInAdapter();
        $account = new SocialAccount([
            'platform_user_id' => '12345',
            'metadata' => [],
        ]);

        $urn = $this->callPrivate($adapter, 'linkedInAuthorUrn', [$account]);

        $this->assertSame('urn:li:person:12345', $urn);
    }

    public function test_author_urn_uses_organization_account_type(): void
    {
        $adapter = new LinkedInAdapter();
        $account = new SocialAccount([
            'platform_user_id' => '778899',
            'metadata' => ['account_type' => 'organization'],
        ]);

        $urn = $this->callPrivate($adapter, 'linkedInAuthorUrn', [$account]);

        $this->assertSame('urn:li:organization:778899', $urn);
    }

    public function test_owner_urn_prefers_explicit_metadata_value(): void
    {
        $adapter = new LinkedInAdapter();
        $account = new SocialAccount([
            'platform_user_id' => '778899',
            'metadata' => ['owner_urn' => 'urn:li:organization:445566'],
        ]);

        $ownerUrn = $this->callPrivate($adapter, 'linkedInOwnerUrn', [$account, 'urn:li:person:12345']);

        $this->assertSame('urn:li:organization:445566', $ownerUrn);
    }

    public function test_linkedin_pages_adapter_uses_linkedin_pages_platform_slug(): void
    {
        $adapter = new LinkedInPagesAdapter();

        $this->assertSame(Platform::LinkedInPages, $adapter->platform());
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
