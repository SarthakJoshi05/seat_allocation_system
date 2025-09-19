<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\RunSeatAllocationJob;
// use App\Services\SeatAllocationService;
use App\Models\Room;
// use App\Models\SeatAllocation;
use Illuminate\Support\Facades\Artisan;

class SeatController extends Controller
{
    public function index()
    {
        // show summary list of rooms
        $rooms = Room::all();
        return view('seats.index', compact('rooms'));
    }

    public function allocate(Request $request)
    {
        // If you have a working queue, dispatch job (background)
        // if (config('queue.default') !== 'sync') {
        //     RunSeatAllocationJob::dispatch();
        //     return redirect()->route('seats.map')->with('status','Allocation job dispatched. Refresh seat map after worker completes.');
        // }

        // fallback: synchronous run (calls the service)
        Artisan::call('seats:allocate');
        return redirect()->route('seats.map')->with('status','Allocation completed synchronously.');
    }

    public function map()
    {
        // Show room-wise seat map from DB
        $rooms = Room::with(['allocations.student'])->get();
        // Build grid per room
        $roomMaps = [];
        foreach ($rooms as $room) {
            [$rows,$cols] = $room->layoutRowsCols();
            $map = array_fill(1,$rows,null);
            for ($r=1;$r<=$rows;$r++){
                $map[$r] = array_fill(1,$cols,null);
            }
            foreach ($room->allocations as $alloc) {
                $map[$alloc->row_number][$alloc->column_number] = $alloc->student;
            }
            $roomMaps[] = ['room'=>$room,'map'=>$map];
        }
        return view('seats.map', compact('roomMaps'));
    }
}
