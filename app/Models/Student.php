<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
   protected $fillable = ['name','roll_number','gender','department','subject_code','special_needs'];

    public function allocation()
    {
        return $this->hasOne(SeatAllocation::class);
    }
}
