<?php

use App\Models\Comuna;
use App\Models\Establecimiento;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('renders the sismaule page with the authenticated user comuna and servers', function () {
    config()->set('app.servers', [
        [
            'url' => 'http://sismaule.test',
            'label' => 'test',
        ],
    ]);

    $comuna = Comuna::create([
        'nombre' => 'Talca',
        'codigo' => '07101',
    ]);

    $establecimiento = Establecimiento::create([
        'nombre' => 'Hospital Talca',
        'codigo' => '0701',
        'direccion' => '1 Norte',
        'comuna_id' => $comuna->id,
    ]);

    $user = User::factory()->create([
        'establecimiento_id' => $establecimiento->id,
    ]);

    $this->actingAs($user)
        ->get(route('sismaule.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Sismaule/Index')
            ->has('comunas', 1)
            ->where('servers.0.url', 'http://sismaule.test')
            ->where('user.id', $user->id)
            ->where('user.establecimiento.codigo', '0701')
            ->where('user.establecimiento.comuna.codigo', '07101'));
});
