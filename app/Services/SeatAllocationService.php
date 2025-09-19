<?php
namespace App\Services;

use App\Models\Student;
use App\Models\Room;
use App\Models\SeatAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeatAllocationService
{
    protected $warnings = [];

    public function allocateAll(): array
    {
        // Reset previous allocations
        SeatAllocation::truncate();

        $students = Student::all()->shuffle(); // randomize input
        $rooms = Room::all();

        // Precompute room seat grids
        $roomGrids = [];
        foreach ($rooms as $room) {
            [$rows,$cols] = $room->layoutRowsCols();
            $grid = array_fill(1, $rows, null);
            for ($r=1;$r<=$rows;$r++){
                $grid[$r] = array_fill(1, $cols, null);
            }
            $roomGrids[$room->id] = [
                'room'=>$room,
                'rows'=>$rows,'cols'=>$cols,
                'grid'=>$grid,
                'allocations'=>0,
            ];
        }

        // 1) Place special needs first at edges/corners and reserve adjacency empty seat
        $specials = $students->where('special_needs', true);
        foreach ($specials as $stu) {
            $placed = $this->placeSpecial($stu, $roomGrids);
            if (!$placed) {
                $this->warnings[] = "Could not place special needs student {$stu->roll_number} at an edge; placed anywhere";
                $this->forcePlaceAny($stu, $roomGrids);
            }
        }

        // 2) Place remaining students with heuristics
        $remaining = $students->where('special_needs', false);
        // For better mixing, sort by subject so that we try to spread large groups
        // but still greedy choose seat minimizing adjacent dept conflict
        foreach ($remaining as $stu) {
            $placed = $this->placeStudentGreedy($stu, $roomGrids);
            if (!$placed) {
                $this->warnings[] = "Relaxed constraints for {$stu->roll_number}: placed even if adjacency/subject mix violated.";
                $this->forcePlaceAny($stu, $roomGrids);
            }
        }

        // Persist allocations to DB with seat labels and compute room statistics
        $output = [];
        foreach ($roomGrids as $rg) {
            $room = $rg['room'];
            $rows = $rg['rows'];
            $cols = $rg['cols'];
            $grid = $rg['grid'];

            $map = array_fill(1, $rows, null);
            for ($r=1;$r<=$rows;$r++){
                $map[$r] = array_fill(1, $cols, null);
                for ($c=1;$c<=$cols;$c++){
                    $cell = $grid[$r][$c];
                    if ($cell && (!is_object($cell) || !isset($cell->_reserved))) {
                        SeatAllocation::create([
                            'student_id'   => $cell->id,
                            'room_id'      => $room->id,
                            'row_number'   => $r,
                            'column_number'=> $c,
                            'seat_label'   => "R{$room->id}-{$r}-{$c}",
                        ]);
                        $map[$r][$c] = $cell->roll_number;
                    } else {
                        $map[$r][$c] = null; // reserved or empty
                    }
                }
            }

            // compute gender ratio and subject mix
            $allocs = SeatAllocation::where('room_id',$room->id)->with('student')->get();
            $m = $allocs->where('student.gender','M')->count();
            $f = $allocs->where('student.gender','F')->count();
            $gTotal = max(1, $m+$f);
            $gender_ratio = round($m*100/$gTotal).'% M/'.round($f*100/$gTotal).'% F';
            $subject_mix = $allocs->pluck('student.subject_code')->unique()->values()->all();

            $output[$room->name] = [
                'layout'=>$map,
                'gender_ratio'=>$gender_ratio,
                'subject_mix'=>$subject_mix,
            ];
        }

        // log warnings
        foreach ($this->warnings as $w) {
            Log::warning($w);
        }

        return ['output'=>$output, 'warnings'=>$this->warnings];
    }

    protected function placeSpecial($student, &$roomGrids): bool
    {
        // Try each room and pick an edge/corner seat with adjacency empty seats available
        foreach ($roomGrids as &$rg) {
            $rows = $rg['rows']; $cols = $rg['cols'];
            // build list of edge positions
            $positions = [];
            for ($r=1;$r<=$rows;$r++){
                for ($c=1;$c<=$cols;$c++){
                    if ($r==1 || $r==$rows || $c==1 || $c==$cols) {
                        $positions[] = [$r,$c];
                    }
                }
            }
            // shuffle to avoid bias
            shuffle($positions);
            foreach ($positions as [$r,$c]) {
                if ($rg['grid'][$r][$c] !== null) continue;
                // check adjacency empty (we need at least one adjacent empty seat)
                $adj = $this->adjacentPositions($r,$c,$rows,$cols);
                $hasAdjEmpty = false;
                foreach ($adj as [$ar,$ac]) {
                    if ($rg['grid'][$ar][$ac] === null) {
                        $hasAdjEmpty = true; break;
                    }
                }
                if ($hasAdjEmpty) {
                    $rg['grid'][$r][$c] = $student;
                    $rg['allocations']++;
                    // reserve one adjacent seat as empty by placing placeholder (null stays but we want to block it)
                    // We'll mark reserved adjacency using a special marker to prevent other placements
                    // Use an object with property _reserved = true
                    foreach ($adj as [$ar,$ac]) {
                        if ($rg['grid'][$ar][$ac] === null) {
                            // reserve it so no one sits adjacent to special needs
                            $rg['grid'][$ar][$ac] = (object)['_reserved'=>true];
                            break;
                        }
                    }
                    return true;
                }
            }
        }
        return false;
    }

    protected function forcePlaceAny($student, &$roomGrids)
    {
        foreach ($roomGrids as &$rg) {
            $rows = $rg['rows']; $cols = $rg['cols'];
            for ($r=1;$r<=$rows;$r++){
                for ($c=1;$c<=$cols;$c++){
                    if ($rg['grid'][$r][$c] === null) {
                        $rg['grid'][$r][$c] = $student;
                        $rg['allocations']++;
                        return true;
                    }
                }
            }
        }
        // if no seat at all left
        $this->warnings[] = "No seats left for {$student->roll_number}";
        return false;
    }

    protected function placeStudentGreedy($student, &$roomGrids): bool
    {
        // For each possible seat across rooms, compute a score: penalize dept adjacency, same subject neighbors, gender imbalance potential
        $bestScore = PHP_INT_MAX; $bestRef = null;
        foreach ($roomGrids as $roomId => $rg) {
            $rows = $rg['rows']; $cols = $rg['cols'];
            // quick capacity check
            $allocatedCount = $rg['allocations'];
            if ($allocatedCount >= $rows * $cols) continue;
            for ($r=1;$r<=$rows;$r++){
                for ($c=1;$c<=$cols;$c++){
                    $cell = $rg['grid'][$r][$c];
                    if ($cell !== null) continue; // filled or reserved
                    // evaluate neighbors
                    $adjPos = $this->adjacentPositions($r,$c,$rows,$cols);
                    $deptConflict = 0;
                    $subjectConflict = 0;
                    foreach ($adjPos as [$ar,$ac]) {
                        $n = $rg['grid'][$ar][$ac];
                        if (!$n || is_object($n) && isset($n->_reserved)) continue;
                        if ($n->department == $student->department) $deptConflict++;
                        if ($n->subject_code == $student->subject_code) $subjectConflict++;
                    }
                    // gender balance check: compute after hypothetical placement
                    $allocs = $rg['allocations'];
                    // count current gender
                    $currentM = 0; $currentF = 0;
                    // count by scanning grid (small sizes)
                    foreach ($rg['grid'] as $rr) {
                        foreach ($rr as $cc) {
                            if ($cc && (!is_object($cc) || !isset($cc->_reserved))) {
                                if ($cc->gender == 'M') $currentM++; else $currentF++;
                            }
                        }
                    }
                    if ($student->gender == 'M') $currentM++; else $currentF++;
                    $total = max(1, $currentM + $currentF);
                    $ratio = max($currentM / $total, $currentF / $total); // dominant fraction
                    $genderPenalty = $ratio > 0.7 ? intval(($ratio - 0.7) * 100) : 0;

                    // score: deptConflict weighted high, subjectmoderate, gender low
                    $score = $deptConflict * 100 + $subjectConflict * 10 + $genderPenalty;

                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $bestRef = [$roomId,$r,$c];
                    }
                }
            }
        }

        if ($bestRef) {
            [$roomId,$r,$c] = $bestRef;
            $roomGrids[$roomId]['grid'][$r][$c] = $student;
            $roomGrids[$roomId]['allocations']++;
            return true;
        }
        return false;
    }

    protected function adjacentPositions($r,$c,$rows,$cols)
    {
        $ret = [];
        if ($r>1) $ret[] = [$r-1,$c];
        if ($r<$rows) $ret[] = [$r+1,$c];
        if ($c>1) $ret[] = [$r,$c-1];
        if ($c<$cols) $ret[] = [$r,$c+1];
        return $ret;
    }
}
