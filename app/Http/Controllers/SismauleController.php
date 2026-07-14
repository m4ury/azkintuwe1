<?php

namespace App\Http\Controllers;

use App\Http\Requests\SismaulePacienteGrupoPrioritarioRequest;
use App\Models\Comuna;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class SismauleController extends Controller
{
    private const PACIENTE_GRUPO_PRIORITARIO_ENDPOINT = '/router2.php/sismaulev1/PacienteDeGrupoPrioritarioEyD/obtenerPacienteGrupoPrioritario';

    public function index(): Response
    {
        $user = Auth::user()->load('establecimiento.comuna');
        $establecimiento = $user->establecimiento;
        $comunas = Comuna::all();
        $servers = config('app.servers');

        return Inertia::render('Sismaule/Index', [
            'comunas' => $comunas,
            'user' => $user,
            'establecimiento' => $establecimiento,
            'servers' => $servers,
        ]);
    }

    public function obtenerPacienteGrupoPrioritario(SismaulePacienteGrupoPrioritarioRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $payload = [
            'comuna' => $validated['comuna'],
        ];

        try {
            $response = Http::acceptJson()
                ->timeout(30)
                ->post($this->pacienteGrupoPrioritarioUrl($validated['server_url']), $payload);
        } catch (ConnectionException $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo conectar con el servicio Sismaule.',
            ], 502);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => "El servicio Sismaule respondió con error {$response->status()}.",
                'response' => $response->json() ?? $response->body(),
            ], $response->status());
        }

        return response()->json($response->json() ?? [
            'data' => $response->body(),
        ]);
    }

    private function pacienteGrupoPrioritarioUrl(string $serverUrl): string
    {
        return rtrim($serverUrl, '/').self::PACIENTE_GRUPO_PRIORITARIO_ENDPOINT;
    }
}
