<?php

namespace Database\Seeders;

use App\Models\Candidate;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Candidate::firstOrCreate(['name' => 'Бекполатов Билол Закиржанович'], ['group' => 'СА-26-1Б', 'photo' => '/assets/candidate-1.png', 'active' => true]);
        Candidate::firstOrCreate(['name' => 'Абдугаппаров Тоймурат Эльёржонұлы'], ['group' => 'СА-25-2А', 'photo' => '/assets/candidate-2.png', 'active' => true]);
        Candidate::firstOrCreate(['name' => 'Узақбай Ернұр Тағайбайұлы'], ['group' => 'СА-26-1Б', 'photo' => '/assets/candidate-3.png', 'active' => true]);
        Candidate::firstOrCreate(['name' => 'Бекмұрат Есдаулет Ержанұлы'], ['group' => 'АС-25-2А', 'photo' => '/assets/candidate-4.png', 'active' => true]);
        Candidate::firstOrCreate(['name' => 'Аман Бақдәулет Сансызбайұлы'], ['group' => 'АС-25-2А', 'photo' => '/assets/candidate-5.png', 'active' => true]);
        Candidate::firstOrCreate(['name' => 'Қалдарбек Кәусар Медетбекқызы'], ['group' => 'СА-26-1Б', 'photo' => '/assets/candidate-6.png', 'active' => true]);
        Candidate::firstOrCreate(['name' => 'Сапархан Ерасыл Оразалыұлы'], ['group' => 'АС-26-1А', 'photo' => '/assets/candidate-7.png', 'active' => true]);
    }
}
