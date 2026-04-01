<?php

namespace App\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportTicketController extends Controller
{
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

        return response()->json([
            'success' => true,
            'message' => 'Ticket #' . $ticket->id . ' submitted. We will reply within one business day.',
        ], 201);
    }
}
