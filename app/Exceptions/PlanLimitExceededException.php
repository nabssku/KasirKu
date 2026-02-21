<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class PlanLimitExceededException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $maxAllowed = 0,
        public readonly int $currentCount = 0,
        public readonly string $resource = ''
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success'       => false,
            'message'       => $this->getMessage(),
            'current_count' => $this->currentCount,
            'max_allowed'   => $this->maxAllowed,
        ], 403);
    }
}
