<?php

namespace App\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $table = 'chat_tickets';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'subject',
        'status',
        'priority',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'ticket_id');
    }
}
