<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstablecimientoSeederClass extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $establecimientos = [
            ['nombre' => 'Hospital Dr. Cesar Gavagno Burotto (Talca)', 'codigo' => '0701', 'direccion' => '1 Norte', 'comuna_id' => 1],
            ['nombre' => 'Hospital San Juan de Dios (Curicó)', 'codigo' => '07301', 'direccion' => 'Archipielago Juan Fernandez 1890', 'comuna_id' => 14],
            ['nombre' => 'Hospital Chileno Japones de Hualañe', 'codigo' => '07302', 'direccion' => 'Av Libertad 402', 'comuna_id' => 15],
            ['nombre' => 'Direccion Servicio de Salud Maule', 'codigo' => '01000', 'direccion' => 'Av. España 2000', 'comuna_id' => 1],
            // Agrega más establecimientos según sea necesario
        ];

        foreach ($establecimientos as $establecimiento) {
            \App\Models\Establecimiento::create($establecimiento);
        }
    }
}
