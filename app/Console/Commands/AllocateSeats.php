<?php

namespace App\Console\Commands;

use App\Services\SeatAllocationService;
use Illuminate\Console\Command;

class AllocateSeats extends Command
{

    protected $signature = 'seats:allocate {--json : Output JSON}';
    protected $description = 'Allocate seats across rooms using constraints';
    /**
     * Execute the console command.
     */
    public function handle(SeatAllocationService $service)
    {
        $this->info("Starting allocation...");
        $res = $service->allocateAll();
        $this->info("Done.");

        if ($this->option('json')) {
            $this->line(json_encode($res, JSON_PRETTY_PRINT));
            return 0;
        }

        // show simple summary
        foreach ($res['output'] as $roomName => $data) {
            $this->info("Room: $roomName   Gender: {$data['gender_ratio']}  Subjects: ".implode(',',$data['subject_mix']));
        }
        if (!empty($res['warnings'])) {
            $this->warn("Warnings:");
            foreach ($res['warnings'] as $w) $this->warn(" - $w");
        }
        return 0;
    }
}
