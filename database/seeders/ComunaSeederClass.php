<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ComunaSeederClass extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $comunas = [
            ['nombre' => 'Talca', 'codigo' => '07101'],
            ['nombre' => 'Constitución', 'codigo' => '07102'],
            ['nombre' => 'Curepto', 'codigo' => '07103'],
            ['nombre' => 'Empedrado', 'codigo' => '07104'],
            ['nombre' => 'Maule', 'codigo' => '07105'],
            ['nombre' => 'Pelarco', 'codigo' => '07106'],
            ['nombre' => 'Pencahue', 'codigo' => '07107'],
            ['nombre' => 'Rio Claro', 'codigo' => '07108'],
            ['nombre' => 'San Clemente', 'codigo' => '07109'],
            ['nombre' => 'San Rafael', 'codigo' => '07110'],

            ['nombre' => 'Cauquenes', 'codigo' => '07201'],
            ['nombre' => 'Chanco', 'codigo' => '07202'],
            ['nombre' => 'Pelluhue', 'codigo' => '07203'],

            ['nombre' => 'Curicó', 'codigo' => '07301'],
            ['nombre' => 'Hualañé', 'codigo' => '07302'],
            ['nombre' => 'Licantén', 'codigo' => '07303'],
            ['nombre' => 'Molina', 'codigo' => '07304'],
            ['nombre' => 'Rauco', 'codigo' => '07305'],
            ['nombre' => 'Romeral', 'codigo' => '07306'],
            ['nombre' => 'Sagrada Familia', 'codigo' => '07307'],
            ['nombre' => 'Teno', 'codigo' => '07308'],
            ['nombre' => 'Vichuquén', 'codigo' => '07309'],

            ['nombre' => 'Linares', 'codigo' => '07401'],
            ['nombre' => 'Colbún', 'codigo' => '07402'],
            ['nombre' => 'Longaví', 'codigo' => '07403'],
            ['nombre' => 'Parral', 'codigo' => '07404'],
            ['nombre' => 'Retiro', 'codigo' => '07405'],
            ['nombre' => 'San Javier', 'codigo' => '07406'],
            ['nombre' => 'Villa Alegre', 'codigo' => '07407'],
            ['nombre' => 'Yerbas Buenas', 'codigo' => '07408'],

            // Agrega más comunas según sea necesario
        ];

        foreach ($comunas as $comuna) {
            \App\Models\Comuna::create($comuna);
        }
    }
}
