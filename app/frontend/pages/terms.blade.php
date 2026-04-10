@extends('legal')

@section('title', 'Terms of Service — ' . config('app.name'))
@section('meta-description', 'Read the Terms of Service for ' . config('app.name') . ', including acceptable use, billing terms, and account responsibilities.')

@section('content')
        <h1 class="legal-page__title">Terms of Service</h1>
        <p class="legal-page__meta">Last updated: April 3, 2026</p>

        <p>These Terms of Service (“Terms”) govern your access to and use of {{ config('app.name') }} (the “Service”) operated by us. By creating an account or using the Service, you agree to these Terms.</p>

        <h2>1. The Service</h2>
        <p>{{ config('app.name') }} provides tools to compose, schedule, and manage social content. Features may change over time. We do not guarantee uninterrupted or error-free operation.</p>

        <h2>2. Your account</h2>
        <p>You are responsible for safeguarding your login credentials and for activity under your account. You must provide accurate registration information and keep it up to date.</p>

        <h2>3. Acceptable use</h2>
        <p>You agree not to misuse the Service, including by attempting to access non-public areas, interfere with other users, distribute malware, scrape the Service in violation of our rules, or use the Service for unlawful content or activity. We may suspend or terminate access for violations.</p>

        <h2>4. Content and third-party platforms</h2>
        <p>You retain ownership of content you submit. You grant us the rights needed to operate the Service (for example, storing, processing, and transmitting content to connected platforms). You are responsible for complying with each social network’s terms and policies when you connect accounts or publish through them.</p>

        <h2>5. Subscriptions and fees</h2>
        <p>If you purchase a paid plan, fees and billing terms are presented at checkout or in your account. Unless stated otherwise, subscriptions renew according to the plan you select. You may cancel as described in the product; access may continue until the end of the paid period.</p>

        <h2>6. Disclaimers</h2>
        <p>The Service is provided “as is” without warranties of any kind, to the fullest extent permitted by law. We do not warrant that the Service will meet your requirements or that third-party APIs (including social platforms) will always be available.</p>

        <h2>7. Limitation of liability</h2>
        <p>To the maximum extent permitted by applicable law, we are not liable for indirect, incidental, special, consequential, or punitive damages, or for loss of profits, data, or goodwill. Our total liability for claims arising from the Service is limited to the greater of amounts you paid us in the twelve months before the claim or fifty dollars (USD), unless mandatory law provides otherwise.</p>

        <h2>8. Changes</h2>
        <p>We may update these Terms from time to time. We will post the revised Terms on this page and update the “Last updated” date. Continued use after changes constitutes acceptance of the updated Terms where permitted by law.</p>

        <h2>9. Contact</h2>
        <p>For questions about these Terms, contact us through the support options provided in the Service.</p>
@endsection
