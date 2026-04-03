<?php

namespace App\Controllers;

use App\Jobs\QueueTemplatedEmailForUserJob;
use App\Jobs\SendMarketingCampaignBatchJob;
use App\Models\EmailTemplate;
use App\Models\MarketingCampaign;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminMarketingController extends Controller
{
    public function index(): View
    {
        $campaigns = MarketingCampaign::query()
            ->with('emailTemplate:id,key,name')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.marketing-campaigns', [
            'activePage' => 'admin-marketing-campaigns',
            'campaigns'  => $campaigns,
        ]);
    }

    public function create(): View
    {
        $templates = EmailTemplate::query()->orderBy('key')->get();
        $planSlugs = Plan::query()->where('is_active', true)->orderBy('sort_order')->pluck('slug');

        return view('admin.marketing-campaign-form', [
            'activePage' => 'admin-marketing-campaigns',
            'campaign'   => null,
            'templates'  => $templates,
            'planSlugs'  => $planSlugs,
            'seg'        => [
                'paid'          => false,
                'free_only'     => false,
                'plan_slugs'    => [],
                'active_days'   => '',
                'inactive_days' => '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'template_key'  => 'required|string|exists:email_templates,key',
            'status'        => 'required|in:draft,scheduled',
            'scheduled_at'  => 'nullable|date',
        ]);

        $tpl = EmailTemplate::query()->where('key', $validated['template_key'])->firstOrFail();

        $rules = $this->segmentRulesFromRequest($request);

        if ($rules === []) {
            return redirect()->back()->withInput()->withErrors(['segment' => 'Choose at least one audience criterion.']);
        }

        MarketingCampaign::query()->create([
            'name'              => $validated['name'],
            'template_key'      => $tpl->key,
            'email_template_id' => $tpl->id,
            'segment_rules'     => $rules,
            'status'            => $validated['status'],
            'scheduled_at'      => $validated['scheduled_at'] ?? null,
            'created_by'        => Auth::id(),
        ]);

        return redirect()->route('admin.marketing-campaigns.index')->with('success', 'Campaign created.');
    }

    public function edit(int $id): View
    {
        $campaign  = MarketingCampaign::query()->findOrFail($id);
        $templates = EmailTemplate::query()->orderBy('key')->get();
        $planSlugs = Plan::query()->where('is_active', true)->orderBy('sort_order')->pluck('slug');
        $rules     = $campaign->segment_rules ?? [];

        return view('admin.marketing-campaign-form', [
            'activePage' => 'admin-marketing-campaigns',
            'campaign'   => $campaign,
            'templates'  => $templates,
            'planSlugs'  => $planSlugs,
            'seg'        => [
                'paid'          => ! empty($rules['paid_subscribers']),
                'free_only'     => ! empty($rules['free_only']),
                'plan_slugs'    => $rules['plan_slugs'] ?? [],
                'active_days'   => $rules['active_last_n_days'] ?? '',
                'inactive_days' => $rules['inactive_last_n_days'] ?? '',
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $campaign = MarketingCampaign::query()->findOrFail($id);

        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return redirect()->route('admin.marketing-campaigns.edit', $id)->with('error', 'Only draft or scheduled campaigns can be edited.');
        }

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'template_key' => 'required|string|exists:email_templates,key',
            'status'       => 'required|in:draft,scheduled',
            'scheduled_at' => 'nullable|date',
        ]);

        $tpl = EmailTemplate::query()->where('key', $validated['template_key'])->firstOrFail();

        $rules = $this->segmentRulesFromRequest($request);

        if ($rules === []) {
            return redirect()->back()->withInput()->withErrors(['segment' => 'Choose at least one audience criterion.']);
        }

        $campaign->update([
            'name'              => $validated['name'],
            'template_key'      => $tpl->key,
            'email_template_id' => $tpl->id,
            'segment_rules'     => $rules,
            'status'            => $validated['status'],
            'scheduled_at'      => $validated['scheduled_at'] ?? null,
        ]);

        return redirect()->route('admin.marketing-campaigns.edit', $id)->with('success', 'Campaign saved.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $campaign = MarketingCampaign::query()->findOrFail($id);

        if ($campaign->status !== 'draft') {
            return redirect()->route('admin.marketing-campaigns.index')->with('error', 'Only draft campaigns can be deleted.');
        }

        $campaign->delete();

        return redirect()->route('admin.marketing-campaigns.index')->with('success', 'Campaign deleted.');
    }

    public function sendTest(int $id): RedirectResponse
    {
        $campaign = MarketingCampaign::query()->with('emailTemplate')->findOrFail($id);
        $user     = Auth::user();

        if ($user === null || $user->email === null || $user->email === '') {
            return redirect()->route('admin.marketing-campaigns.edit', $id)->with('error', 'Your account needs an email address.');
        }

        $templateKey = $campaign->template_key ?? $campaign->emailTemplate?->key;

        if ($templateKey === null || $templateKey === '') {
            return redirect()->route('admin.marketing-campaigns.edit', $id)->with('error', 'Campaign has no template.');
        }

        QueueTemplatedEmailForUserJob::dispatch($user->id, $templateKey, [
            'campaignName' => $campaign->name,
            'isTest'       => true,
        ], [
            'campaign_id' => $campaign->id,
            'kind'        => 'marketing_test',
        ]);

        return redirect()->route('admin.marketing-campaigns.edit', $id)->with('success', 'Test email queued to your address.');
    }

    public function startSend(int $id): RedirectResponse
    {
        $campaign = MarketingCampaign::query()->findOrFail($id);

        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return redirect()->route('admin.marketing-campaigns.index')->with('error', 'Campaign cannot be sent in its current state.');
        }

        SendMarketingCampaignBatchJob::dispatch($campaign->id);

        return redirect()->route('admin.marketing-campaigns.index')->with('success', 'Campaign send has been queued.');
    }

    /**
     * @return array<string, mixed>
     */
    private function segmentRulesFromRequest(Request $request): array
    {
        $out = [];

        if ($request->boolean('seg_paid_subscribers')) {
            $out['paid_subscribers'] = true;
        }

        if ($request->boolean('seg_free_only')) {
            $out['free_only'] = true;
        }

        $slugs = array_values(array_filter(array_map('strval', (array) $request->input('seg_plan_slugs', []))));
        if ($slugs !== []) {
            $out['plan_slugs'] = $slugs;
        }

        if ($request->filled('seg_active_days')) {
            $out['active_last_n_days'] = (int) $request->input('seg_active_days');
        }

        if ($request->filled('seg_inactive_days')) {
            $out['inactive_last_n_days'] = (int) $request->input('seg_inactive_days');
        }

        return $out;
    }
}
