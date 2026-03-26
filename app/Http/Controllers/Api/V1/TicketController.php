<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Ticket::query()->with(['user', 'tenant']);

        if (!$user->hasRole('super_admin')) {
            $query->where('user_id', $user->id);
            // Additionally, if we want to show tickets from the same tenant to admins:
            // $query->where('tenant_id', $user->tenant_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tickets = $query->orderByDesc('last_message_at')
                         ->orderByDesc('created_at')
                         ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $tickets,
        ]);
    }

    /**
     * Store a newly created ticket in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('super_admin');

        $rules = [
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'sometimes|in:low,medium,high',
        ];

        if ($isSuperAdmin) {
            $rules['tenant_id'] = 'required|exists:tenants,id';
            $rules['user_id'] = 'required|exists:users,id,tenant_id,' . $request->input('tenant_id');
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $user, $isSuperAdmin) {
            $ticket = Ticket::create([
                'user_id'         => $isSuperAdmin ? $validated['user_id'] : $user->id,
                'tenant_id'       => $isSuperAdmin ? $validated['tenant_id'] : $user->tenant_id,
                'subject'         => $validated['subject'],
                'priority'        => $validated['priority'] ?? 'medium',
                'status'          => 'open',
                'last_message_at' => now(),
            ]);

            ChatMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'message'   => $validated['message'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully.',
                'data'    => $ticket->load('messages'),
            ], 201);
        });
    }

    /**
     * Display the specified ticket.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $ticket = Ticket::with([
            'user', 
            'tenant', 
            'messages.sender' => function($query) {
                $query->withoutGlobalScopes()->with('roles');
            }
        ])->findOrFail($id);

        // Check permission
        if (!$user->hasRole('super_admin') && $ticket->user_id !== $user->id) {
             // If Admin can see tenant tickets:
             if (!($user->hasRole('admin') && $ticket->tenant_id === $user->tenant_id)) {
                 return response()->json([
                     'success' => false,
                     'message' => 'Unauthorized.',
                 ], 403);
             }
        }

        return response()->json([
            'success' => true,
            'data'    => $ticket,
        ]);
    }

    /**
     * Update the status of a ticket (Superadmin only).
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Only superadmins can change ticket status.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:open,closed,pending',
        ]);

        $ticket->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated.',
            'data'    => $ticket,
        ]);
    }
}
