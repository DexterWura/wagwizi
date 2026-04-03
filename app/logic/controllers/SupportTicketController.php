<?php

namespace App\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = SupportTicket::query()
            ->where('user_id', $user->id)
            ->withCount('replies');

        $status = $request->query('status');
        if ($status === 'active') {
            $query->whereIn('status', ['open', 'in_progress']);
        } elseif (is_string($status) && in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            $query->where('status', $status);
        }

        $tickets = $query->orderByDesc('updated_at')->paginate(15)->appends($request->only('status'));

        return view('support-tickets', compact('tickets'));
    }

    public function show(int $id): View
    {
        $ticket = SupportTicket::query()
            ->where('user_id', Auth::id())
            ->with(['replies' => fn ($q) => $q->orderBy('created_at')->with('user:id,name')])
            ->findOrFail($id);

        return view('support-ticket-show', compact('ticket'));
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $ticket = SupportTicket::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        if ($ticket->status === 'closed') {
            return back()->with('error', 'This ticket is closed. Open a new ticket if you still need help.');
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'message'   => $validated['message'],
        ]);

        if (in_array($ticket->status, ['open', 'resolved'], true)) {
            $ticket->update(['status' => 'in_progress']);
        } else {
            $ticket->touch();
        }

        return back()->with('success', 'Your reply was sent.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject'         => 'required|string|max:255',
            'category'        => 'required|string|in:publishing,accounts,technical,billing,other',
            'message'         => 'required|string|max:5000',
            'include_context' => 'boolean',
            'page_url'        => 'nullable|string|max:500',
            'user_agent'      => 'nullable|string|max:500',
        ]);

        $ticket = SupportTicket::create([
            'user_id'  => Auth::id(),
            'subject'  => $validated['subject'],
            'category' => $validated['category'],
            'message'  => $validated['message'],
            'status'   => 'open',
            'priority' => 'normal',
        ]);

        try {
            app(InAppNotificationService::class)->notifyStaffNewSupportTicket($ticket);
        } catch (\Throwable) {
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket #' . $ticket->id . ' submitted. We will reply within one business day.',
        ], 201);
    }
}
