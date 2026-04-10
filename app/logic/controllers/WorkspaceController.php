<?php

namespace App\Controllers;

use App\Models\NotificationDelivery;
use App\Models\WorkspaceInvite;
use App\Services\Workspace\WorkspaceAccessService;
use App\Services\Workspace\WorkspaceInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use App\Jobs\SendTemplatedEmailJob;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceInviteService $inviteService,
        private readonly WorkspaceAccessService $accessService,
    ) {}

    public function invite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:member,admin'],
        ]);

        try {
            $invite = $this->inviteService->createInvite(Auth::user(), $validated['email'], $validated['role']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $delivery = NotificationDelivery::query()->create([
            'channel' => 'email',
            'template_key' => 'workspace.invite',
            'user_id' => null,
            'to_address' => $invite->email,
            'status' => 'queued',
            'metadata' => [
                'vars' => [
                    'siteName' => config('app.name'),
                    'workspaceName' => $invite->workspace?->name ?? 'Workspace',
                    'inviteUrl' => URL::temporarySignedRoute(
                        'workspace.invite.accept',
                        $invite->expires_at,
                        ['token' => $invite->token]
                    ),
                    'inviterName' => Auth::user()?->name ?? 'Workspace admin',
                    'inviteRole' => $invite->role,
                    'userName' => 'there',
                    'unsubscribeUrl' => url('/settings'),
                ],
            ],
        ]);
        SendTemplatedEmailJob::dispatchAfterResponse($delivery->id);

        return back()->with('success', 'Invite sent.');
    }

    public function showInviteAcceptance(Request $request): View|RedirectResponse
    {
        $invite = WorkspaceInvite::query()
            ->where('token', (string) $request->query('token', ''))
            ->first();
        if ($invite === null) {
            return redirect()->route('login')->with('error', 'Invalid invite link.');
        }

        if (! Auth::check()) {
            return redirect()->route('login', ['redirect' => $request->fullUrl()]);
        }

        return view('workspace-invite-accept', [
            'invite' => $invite,
            'workspaceName' => $invite->workspace?->name ?? 'Workspace',
        ]);
    }

    public function confirmInviteAcceptance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $invite = WorkspaceInvite::query()->where('token', $validated['token'])->first();
        if ($invite === null) {
            return redirect()->route('dashboard')->with('error', 'Invite not found.');
        }

        try {
            $this->inviteService->acceptInvite(Auth::user(), $invite);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('dashboard')->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard')->with('success', 'You joined the workspace successfully.');
    }
}

