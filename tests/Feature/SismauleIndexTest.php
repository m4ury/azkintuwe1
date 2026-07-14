<?php

use App\Models\Comuna;
use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

it('proxies paciente grupo prioritario requests to the selected configured server', function () {
    config()->set('app.servers', [
        [
            'url' => 'http://sismaule.test',
            'label' => 'test',
        ],
    ]);

    Http::fake([
        'http://sismaule.test/router2.php/sismaulev1/PacienteDeGrupoPrioritarioEyD/obtenerPacienteGrupoPrioritario' => Http::response([
            'ok' => true,
        ]),
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
        ->postJson(route('sismaule.paciente-grupo-prioritario'), [
            'server_url' => 'http://sismaule.test',
            'comuna' => '07101',
            'comuna_nombre' => 'Talca',
        ])
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
        ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://sismaule.test/router2.php/sismaulev1/PacienteDeGrupoPrioritarioEyD/obtenerPacienteGrupoPrioritario'
        && $request['comuna'] === '07101'
        && $request['comuna_nombre'] === 'Talca'
        && $request['usuario'] === $user->name);
});

it('rejects paciente grupo prioritario requests to unconfigured servers', function () {
    config()->set('app.servers', [
        [
            'url' => 'http://sismaule.test',
            'label' => 'test',
        ],
    ]);

    Http::fake();

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
        ->postJson(route('sismaule.paciente-grupo-prioritario'), [
            'server_url' => 'http://otro-servidor.test',
            'comuna' => '07101',
            'comuna_nombre' => 'Talca',
        ])
        ->assertUnprocessable();

    Http::assertNothingSent();
});
