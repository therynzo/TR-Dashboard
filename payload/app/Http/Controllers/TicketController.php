<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        return view('tickets.index', ['tickets' => SupportTicket::where('user_id', $request->user()->id)->latest()->paginate(15)]);
    }

    public function create(): View
    {
        return view('tickets.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'category' => ['required', 'in:Technical,Billing,Account,Other'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'message' => ['required', 'string', 'min:10', 'max:10000'],
        ]);
        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'open',
        ]);
        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'is_staff' => false,
            'message' => $data['message'],
        ]);
        ActivityLogger::record($request->user()->id, $request->user()->username, 'ticket_created', 'Created support ticket #' . $ticket->id . '.', $request);
        return redirect()->route('tickets.show', $ticket)->with('success', 'Support ticket created. The team can now reply to your request.');
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        abort_unless($ticket->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $ticket->load(['user', 'replies.user']);
        return view($request->user()->isAdmin() ? 'admin.ticket-show' : 'tickets.show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:10000']]);
        if ($ticket->status === 'closed') {
            return back()->with('error', 'This ticket is closed. Reopen it from the administrator area or create a new ticket.');
        }
        $staff = $request->user()->isAdmin();
        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'is_staff' => $staff,
            'message' => $data['message'],
        ]);
        $ticket->update(['status' => $staff ? 'admin_reply' : 'client_reply']);
        ActivityLogger::record($request->user()->id, $request->user()->username, $staff ? 'ticket_staff_reply' : 'ticket_client_reply', 'Replied to support ticket #' . $ticket->id . '.', $request);
        return back()->with('success', 'Reply sent.');
    }
}
