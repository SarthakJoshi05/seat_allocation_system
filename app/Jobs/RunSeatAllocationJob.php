<?php
namespace App\Jobs;

use App\Services\SeatAllocationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RunSeatAllocationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(SeatAllocationService $service)
    {
        $service->allocateAll();
    }
}
