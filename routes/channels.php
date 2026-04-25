<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = \App\Models\Ticket::withoutGlobalScopes()->find($ticketId);
    if (!$ticket) return false;
    
    // Check if user is the ticket owner or super-admin
    return (int) $user->id === (int) $ticket->user_id || $user->hasRole('super-admin');
});

Broadcast::channel('super-admin', function ($user) {
    return $user->hasRole('super-admin');
});
