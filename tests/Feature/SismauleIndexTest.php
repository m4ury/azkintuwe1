<?php

use App\Models\Comuna;
use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        'http://sismaule.test/router2.php/sismaulev1/PacienteDeGrupoPrioritarioEyD/obtenerPacienteGrupoPrioritario*' => Http::response([
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
        ->assertJsonPath('ok', true);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/sismaulev1/PacienteDeGrupoPrioritarioEyD/obtenerPacienteGrupoPrioritario')
        && str_contains($request->url(), 'comuna=07101')
        && str_contains($request->url(), 'comuna_nombre=Talca'));
});

it('consolidates multiple selected communes into a single csv file', function () {
    config()->set('app.servers', [
        [
            'url' => 'http://sismaule.test',
            'label' => 'test',
        ],
    ]);

    config()->set('app.grupos', [
        [
            'idgrupo' => '3',
            'nombregrupo' => 'Electrodependiente',
        ],
    ]);

    Http::fake([
        'http://sismaule.test/router2.php/sismaulev1/PacienteDeGrupoPrioritarioEyD/obtenerPacienteGrupoPrioritario*' => Http::sequence()
            ->push([
                'respuesta' => [
                    'estado' => 'OK',
                    'datos' => [
                        ['CODIGO_IDENTIFICACION' => '11111111', 'NOMBRES' => 'Ana', 'COMUNA' => 'Talca'],
                    ],
                ],
            ])
            ->push([
                'respuesta' => [
                    'estado' => 'OK',
                    'datos' => [
                        ['CODIGO_IDENTIFICACION' => '22222222', 'NOMBRES' => 'Luis', 'COMUNA' => 'Curicó'],
                    ],
                ],
            ]),
    ]);

    $talca = Comuna::create(['nombre' => 'Talca', 'codigo' => '07101']);
    $curico = Comuna::create(['nombre' => 'Curicó', 'codigo' => '07301']);

    $establecimiento = Establecimiento::create([
        'nombre' => 'DSSM',
        'codigo' => '01000',
        'direccion' => '1 Norte',
        'comuna_id' => $talca->id,
    ]);

    $user = User::factory()->create([
        'establecimiento_id' => $establecimiento->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('sismaule.paciente-grupo-prioritario', [
            'server_url' => 'http://sismaule.test',
            'comunas' => ['07101', '07301'],
            'grupos' => '3',
        ]));

    $response->assertSuccessful();
    $response->assertJsonPath('data.respuesta.estado', 'OK');

    $csvPath = $response->json('csv_path');
    expect($csvPath)->not->toBeNull()
        ->and($csvPath)->toContain('consolidado')
        ->and(Storage::exists($csvPath))->toBeTrue();

    $csvContents = Storage::get($csvPath);
    expect($csvContents)->toContain('CODIGO_IDENTIFICACION')
        ->and($csvContents)->toContain('11111111')
        ->and($csvContents)->toContain('22222222');
});

it('creates a consolidated csv when all communes are selected with long names', function () {
    config()->set('app.servers', [
        [
            'url' => 'http://sismaule.test',
            'label' => 'test',
        ],
    ]);

    config()->set('app.grupos', [
        [
            'idgrupo' => '3',
            'nombregrupo' => 'Electrodependiente',
        ],
    ]);

    Http::fake([
        'http://sismaule.test/router2.php/sismaulev1/PacienteDeGrupoPrioritarioEyD/obtenerPacienteGrupoPrioritario*' => Http::sequence()
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '111', 'NOMBRES' => 'Ana', 'COMUNA' => 'Talca']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '222', 'NOMBRES' => 'Luis', 'COMUNA' => 'Constitución']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '333', 'NOMBRES' => 'Marta', 'COMUNA' => 'Curepto']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '444', 'NOMBRES' => 'José', 'COMUNA' => 'Empedrado']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '555', 'NOMBRES' => 'Lucía', 'COMUNA' => 'Maule']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '666', 'NOMBRES' => 'Paula', 'COMUNA' => 'Pelarco']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '777', 'NOMBRES' => 'Diego', 'COMUNA' => 'Pencahue']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '888', 'NOMBRES' => 'Ximena', 'COMUNA' => 'Rio Claro']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '999', 'NOMBRES' => 'Carla', 'COMUNA' => 'San Clemente']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '101', 'NOMBRES' => 'Juan', 'COMUNA' => 'San Rafael']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '102', 'NOMBRES' => 'María', 'COMUNA' => 'Cauquenes']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '103', 'NOMBRES' => 'Fernanda', 'COMUNA' => 'Chanco']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '104', 'NOMBRES' => 'Andrés', 'COMUNA' => 'Pelluhue']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '105', 'NOMBRES' => 'Bastián', 'COMUNA' => 'Curicó']]],
            ])
            ->push([
                'respuesta' => ['estado' => 'OK', 'datos' => [['CODIGO_IDENTIFICACION' => '106', 'NOMBRES' => 'Nicolás', 'COMUNA' => 'Hualañé']]],
            ]),
    ]);

    $codes = ['07101', '07102', '07103', '07104', '07105', '07106', '07107', '07108', '07109', '07110', '07201', '07202', '07301', '07303', '07304'];
    $names = ['Talca', 'Constitución', 'Curepto', 'Empedrado', 'Maule', 'Pelarco', 'Pencahue', 'Rio Claro', 'San Clemente', 'San Rafael', 'Cauquenes', 'Chanco', 'Curicó', 'Hualañé', 'Molina'];

    foreach ($codes as $index => $code) {
        Comuna::create(['nombre' => $names[$index], 'codigo' => $code]);
    }

    $establecimiento = Establecimiento::create([
        'nombre' => 'Hospital Talca',
        'codigo' => '0701',
        'direccion' => '1 Norte',
        'comuna_id' => Comuna::where('codigo', '07101')->first()->id,
    ]);

    $user = User::factory()->create(['establecimiento_id' => $establecimiento->id]);

    $response = $this->actingAs($user)
        ->getJson(route('sismaule.paciente-grupo-prioritario', [
            'server_url' => 'http://sismaule.test',
            'comunas' => $codes,
            'grupos' => '3',
        ]));

    $response->assertSuccessful();
    $response->assertJsonPath('data.respuesta.estado', 'OK');

    $csvPath = $response->json('csv_path');
    expect($csvPath)->not->toBeNull()->and(Storage::exists($csvPath))->toBeTrue();
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
        ->assertStatus(422);

    Http::assertNothingSent();
});
