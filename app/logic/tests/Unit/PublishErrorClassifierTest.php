<?php

namespace Tests\Unit;

use App\Services\Post\PublishErrorClassifier;
use PHPUnit\Framework\TestCase;

final class PublishErrorClassifierTest extends TestCase
{
    public function test_classify_http_auth_codes_as_reauth(): void
    {
        $this->assertSame(PublishErrorClassifier::REAUTH, PublishErrorClassifier::classify(401, ''));
        $this->assertSame(PublishErrorClassifier::REAUTH, PublishErrorClassifier::classify(403, ''));
    }

    public function test_classify_token_message_as_reauth(): void
    {
        $this->assertSame(PublishErrorClassifier::REAUTH, PublishErrorClassifier::classify(null, 'Token rejected by provider'));
    }

    public function test_classify_rate_limit_as_retryable(): void
    {
        $this->assertSame(PublishErrorClassifier::RETRYABLE, PublishErrorClassifier::classify(429, ''));
        $this->assertSame(PublishErrorClassifier::RETRYABLE, PublishErrorClassifier::classify(null, 'Rate limit exceeded'));
    }

    public function test_classify_billing_as_permanent(): void
    {
        $this->assertSame(PublishErrorClassifier::PERMANENT, PublishErrorClassifier::classify(402, ''));
        $this->assertSame(PublishErrorClassifier::PERMANENT, PublishErrorClassifier::classify(null, 'Quota exceeded for your plan'));
    }
}
