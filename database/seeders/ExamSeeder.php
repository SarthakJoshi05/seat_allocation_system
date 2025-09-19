<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\Student;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create rooms
        Room::create(['name'=>'Room A','total_seats'=>30,'layout'=>'5x6']);
        Room::create(['name'=>'Room B','total_seats'=>25,'layout'=>'5x5']);
        Room::create(['name'=>'Room C','total_seats'=>20,'layout'=>'4x5']);
        Room::create(['name'=>'Room D','total_seats'=>25,'layout'=>'5x5']);

        // Departments and subjects
        $departments = ['CS','ME','EE','CE','AE','CH','BT','IS','IT']; // 9 depts
        $subjects = ['CS101','ME202','EE305',"ME404","IT303","AE201","CH102","BT204","IS301"]; // 9 subjects

        // make 100 students with approx gender ratio 60M/40F and 5 special needs
        $total = 100;
        $males = 60;
        $females = 40;
        $specials = 5;
        $students = [];

        $faker = Faker::create();

        $i = 1;
        // generate males
        for ($k=0;$k<$males;$k++) {
            $students[] = [
                'name'=> $faker->name('male'),
                'roll_number'=> "R" . $i,
                'gender'=>'M',
                'department'=>$departments[array_rand($departments)],
                'subject_code'=>$subjects[array_rand($subjects)],
                'special_needs'=> false
            ];
            $i++;
        }
        // generate females
        for ($k=0;$k<$females;$k++) {
            $students[] = [
                'name'=>$faker->name('female'),
                'roll_number'=>"R" . $i,
                'gender'=>'F',
                'department'=>$departments[array_rand($departments)],
                'subject_code'=>$subjects[array_rand($subjects)],
                'special_needs'=> false
            ];
            $i++;
        }

        shuffle($students);
        for ($s = 0; $s < $specials; $s++) {
            $students[$s]['special_needs'] = true;
        }

        foreach ($students as $st) {
            Student::create($st);
        }
    }
}
