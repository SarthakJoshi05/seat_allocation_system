<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
        protected $fillable = ['name','total_seats','layout'];

    public function allocations()
    {
        return $this->hasMany(SeatAllocation::class);
    }

    // parse layout string to integer array "5x6" -> [rows, cols]
    public function layoutRowsCols(): array
    {

        [$r,$c] = explode('x', $this->layout);
        return [intval($r), intval($c)];
    }
}
