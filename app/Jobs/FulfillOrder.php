<?php

namespace App\Jobs;

use App\Domain\FulfillmentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FulfillOrder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public int $tries = 3;

    public function __construct(
        public string $orderId,
    ) {}

    public function uniqueId(): string
    {
        return $this->orderId;
    }

    public function handle(FulfillmentService $fulfillment): void
    {
        $fulfillment->fulfill($this->orderId);
    }
}
