<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatMessageController extends Controller
{
    /**
     * Store a newly created message in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket_id' => 'required|exists:chat_tickets,id',
            'message'   => 'required|string',
        ]);

        $user = Auth::user();
        $ticket = Ticket::findOrFail($validated['ticket_id']);

        // Check permission
        if (!$user->hasRole('super_admin') && $ticket->user_id !== $user->id) {
            // Admin of the same tenant
            if (!($user->hasRole('admin') && $ticket->tenant_id === $user->tenant_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }
        }

        // Prevent messaging in closed tickets unless superadmin?
        if ($ticket->status === 'closed' && !$user->hasRole('super_admin')) {
             return response()->json([
                'success' => false,
                'message' => 'Cannot send message to a closed ticket.',
            ], 422);
        }

        $message = ChatMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'message'   => $validated['message'],
        ]);

        // Update ticket's last_message_at
        $ticket->update(['last_message_at' => now()]);
        
        // If it was pending, maybe reopen it if user replies?
        if ($ticket->status === 'pending' && $ticket->user_id === $user->id) {
            $ticket->update(['status' => 'open']);
        }

        // Broadcast the message
        event(new \App\Events\MessageSent($message));
        
        // Also broadcast ticket update for the list
        event(new \App\Events\TicketUpdated($ticket, 'message'));

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'data'    => $message->load('sender'),
        ], 201);
    }
}
