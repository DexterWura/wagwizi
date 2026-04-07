<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Models\Faq;
use App\Models\Plan;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Caches read-mostly marketing/catalog queries used on the landing page and plans UI.
 * Invalidated from admin when the underlying records change.
 */
final class PublicCatalogCache
{
    public const TTL_SECONDS = 300;

    private const K_PLANS = 'catalog:active_plans:v2';

    private const K_TESTIMONIALS = 'catalog:active_testimonials:v1';

    private const K_FAQS = 'catalog:active_faqs:v1';

    /** @return Collection<int, Plan> */
    public static function activePlans(): Collection
    {
        /** @var Collection<int, Plan> */
        return Cache::remember(self::K_PLANS, self::TTL_SECONDS, static fn (): Collection => Plan::active()->get());
    }

    /** @return Collection<int, Testimonial> */
    public static function activeTestimonials(): Collection
    {
        /** @var Collection<int, Testimonial> */
        return Cache::remember(self::K_TESTIMONIALS, self::TTL_SECONDS, static fn (): Collection => Testimonial::active()->ordered()->get());
    }

    /** @return Collection<int, Faq> */
    public static function activeFaqs(): Collection
    {
        /** @var Collection<int, Faq> */
        return Cache::remember(self::K_FAQS, self::TTL_SECONDS, static fn (): Collection => Faq::active()->ordered()->get());
    }

    public static function forgetPlans(): void
    {
        Cache::forget(self::K_PLANS);
        Cache::forget('global:free_plan_slug');
    }

    public static function forgetTestimonials(): void
    {
        Cache::forget(self::K_TESTIMONIALS);
    }

    public static function forgetFaqs(): void
    {
        Cache::forget(self::K_FAQS);
    }
}
