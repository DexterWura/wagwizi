@extends('legal')

@section('title', 'Privacy Policy — ' . config('app.name'))

@section('content')
        <h1 class="legal-page__title">Privacy Policy</h1>
        <p class="legal-page__meta">Last updated: April 3, 2026</p>

        <p>This Privacy Policy describes how {{ config('app.name') }} (“we”, “us”) collects, uses, and shares information when you use our website and application (the “Service”).</p>

        <h2>1. Information we collect</h2>
        <ul class="legal-page__list">
          <li><strong>Account data:</strong> such as name, email address, and password (stored using appropriate security practices).</li>
          <li><strong>Profile and workspace settings:</strong> preferences you configure in the Service.</li>
          <li><strong>Content:</strong> posts, drafts, media you upload, and related metadata needed to provide scheduling and previews.</li>
          <li><strong>Connected accounts:</strong> when you link social accounts, we receive tokens and profile identifiers as required by those platforms’ APIs.</li>
          <li><strong>Usage and technical data:</strong> such as IP address, browser type, device information, and logs used for security, reliability, and improvement.</li>
          <li><strong>Support communications:</strong> information you send when you contact support.</li>
        </ul>

        <h2>2. How we use information</h2>
        <p>We use the information above to provide and improve the Service, authenticate users, process subscriptions, connect to third-party platforms you authorize, send service-related messages, detect abuse, comply with law, and analyze aggregated usage.</p>

        <h2>3. Sharing</h2>
        <p>We may share information with service providers who assist us (hosting, email, analytics, payment processing) under contractual obligations. We may disclose information if required by law or to protect rights and safety. If we are involved in a merger or acquisition, information may be transferred as part of that transaction.</p>
        <p>We do not sell your personal information as commonly understood under U.S. state privacy laws.</p>

        <h2>4. Third-party platforms</h2>
        <p>When you connect or publish to social networks, those platforms process data under their own privacy policies. We only access data needed to perform the actions you request.</p>

        <h2>5. Retention</h2>
        <p>We retain information for as long as your account is active and as needed to provide the Service, comply with legal obligations, resolve disputes, and enforce agreements. You may request deletion of your account subject to legal and technical constraints.</p>

        <h2>6. Security</h2>
        <p>We implement technical and organizational measures designed to protect your information. No method of transmission or storage is completely secure.</p>

        <h2>7. Your rights</h2>
        <p>Depending on where you live, you may have rights to access, correct, delete, or export certain personal data, or to object to or restrict certain processing. Contact us to exercise these rights. You may also have the right to lodge a complaint with a supervisory authority.</p>

        <h2>8. International transfers</h2>
        <p>If you access the Service from outside the country where we operate, your information may be processed in other countries with different data protection laws.</p>

        <h2>9. Children</h2>
        <p>The Service is not directed at children under 13 (or the minimum age in your jurisdiction). We do not knowingly collect personal information from children.</p>

        <h2>10. Changes</h2>
        <p>We may update this policy from time to time. We will post the revised policy on this page and update the “Last updated” date.</p>

        <h2>11. Contact</h2>
        <p>For privacy-related requests or questions, contact us through the support options provided in the Service.</p>
@endsection
