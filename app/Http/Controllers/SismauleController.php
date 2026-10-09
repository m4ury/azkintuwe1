<?php

namespace App\Http\Controllers;

use App\Http\Requests\SismaulePacienteGrupoPrioritarioRequest;
use App\Models\Comuna;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class SismauleController extends Controller
{
    private const PACIENTE_GRUPO_PRIORITARIO_ENDPOINT = '/router2.php/sismaulev1/PacienteDeGrupoPrioritarioEyD/obtenerPacienteGrupoPrioritario';

    public function index(): Response
    {
        $user = Auth::user()->load('establecimiento.comuna');
        $establecimiento = $user->establecimiento;
        $comunas = Comuna::all();
        $servers = config('app.servers');
        $grupos = config('app.grupos');


        return Inertia::render('Sismaule/Index', [
            'comunas' => $comunas,
            'user' => $user,
            'establecimiento' => $establecimiento,
            'servers' => $servers,
            'grupos' => $grupos
        ]);
    }

    public function obtenerPacienteGrupoPrioritario(SismaulePacienteGrupoPrioritarioRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Log::info('SismauleController@obtenerPacienteGrupoPrioritario', [
            'request_all' => $request->all(),
            'validated' => $validated,
        ]);

        $comunasSeleccionadas = array_values(array_filter((array) ($validated['comunas'] ?? []), fn ($comuna) => is_string($comuna) && $comuna !== ''));
        if (empty($comunasSeleccionadas) && !empty($validated['comuna'])) {
            $comunasSeleccionadas = [(string) $validated['comuna']];
        }

        if (empty($comunasSeleccionadas)) {
            return response()->json([
                'message' => 'Debe seleccionar al menos una comuna.',
            ], 422);
        }

        // Buscar el nombre del grupo en la configuración para enviarlo al servicio externo
        $gruposConfig = config('app.grupos', []);
        $idGrupo = $validated['grupos'] ?? null;
        $selectedGrupo = collect($gruposConfig)->firstWhere('idgrupo', $idGrupo);
        $nombreGrupo = $selectedGrupo['nombregrupo'] ?? 'SinGrupo';

        $comunas = Comuna::whereIn('codigo', $comunasSeleccionadas)->get()->keyBy('codigo');
        $rowsConsolidados = [];
        $comunasProcesadas = [];

        foreach ($comunasSeleccionadas as $codigoComuna) {
            $comuna = $comunas->get($codigoComuna);

            if (! $comuna) {
                continue;
            }

            $payload = [
                'comuna'        => $codigoComuna,
                'comuna_nombre' => $request->input('comuna_nombre') ?? $comuna->nombre,
                'idgrupo'       => $selectedGrupo['idgrupo'] ?? $idGrupo,
                'nombregrupo'   => $selectedGrupo['nombregrupo'] ?? '',
            ];

            $usuario = Auth::user()?->name ?? 'salud';

            Log::info('Enviando petición al servicio Sismaule', [
                'url'     => $this->pacienteGrupoPrioritarioUrl($validated['server_url']),
                'payload' => $payload,
                'headers' => [
                    'usuario'        => $usuario,
                    'Modulo'         => 'SALUD',
                    'HTTP_ESREPORTE' => 'S',
                ],
            ]);

            try {
                $response = Http::acceptJson()
                    ->withHeaders([
                        'usuario'        => $usuario,
                        'Modulo'         => 'SALUD',
                        'HTTP_ESREPORTE' => 'S',
                    ])
                    ->timeout(120)
                    ->connectTimeout(30)
                    ->get($this->pacienteGrupoPrioritarioUrl($validated['server_url']), $payload);
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

            $data = $response->json() ?? ['data' => [$response->body()]];
            $rowsComuna = $this->extraerFilasDesdeRespuesta($data);

            if (! empty($rowsComuna)) {
                $rowsConsolidados = [...$rowsConsolidados, ...$rowsComuna];
            }

            $comunasProcesadas[] = $comuna;
        }

        $csvPath = null;

        if (! empty($comunasProcesadas) && ! empty($rowsConsolidados)) {
            try {
                $esConsolidadoDssm = count($comunasProcesadas) > 1 && $this->esDssm(Auth::user());

                if ($esConsolidadoDssm) {
                    $csvPath = $this->guardarComoCsvConsolidado(
                        array_map(fn ($comuna) => $comuna->codigo, $comunasProcesadas),
                        array_map(fn ($comuna) => $comuna->nombre, $comunasProcesadas),
                        $nombreGrupo,
                        $rowsConsolidados,
                    );
                } else {
                    $comunaPrincipal = $comunasProcesadas[0];
                    $csvPath = $this->guardarComoCsv(
                        codigoComuna: $comunaPrincipal->codigo,
                        nombreComuna: $comunaPrincipal->nombre,
                        nombreGrupo: $nombreGrupo,
                        data: ['respuesta' => ['datos' => $rowsConsolidados]],
                    );
                }
            } catch (RuntimeException $e) {
                report($e);
            }
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'respuesta' => [
                    'estado' => 'OK',
                    'datos' => $rowsConsolidados,
                ],
            ],
            'csv_path' => $csvPath,
        ]);
    }

    /**
     * Convierte un array de datos a CSV y lo guarda en storage/app/sismaule/{codigo_comuna}/.
     *
     * @param  string  $codigoComuna  Código de la comuna (ej: 07101)
     * @param  string  $nombreComuna  Nombre de la comuna (ej: Cauquenes)
     * @param  string  $nombreGrupo   Nombre del grupo prioritario (ej: Electrodependiente)
     * @param  array   $data          Datos obtenidos del servicio
     * @return string  Ruta relativa del archivo guardado
     *
     * @throws RuntimeException  Si no hay datos para guardar
     */
    private function guardarComoCsv(string $codigoComuna, string $nombreComuna, string $nombreGrupo, array $data): string
    {
        $rows = $this->extraerFilasDesdeRespuesta($data);

        if (empty($rows)) {
            throw new RuntimeException('No hay datos para guardar en CSV');
        }

        $nombreComunaLimpio = str_replace(' ', '_', $nombreComuna);
        $nombreGrupoLimpio = str_replace(' ', '_', $nombreGrupo);
        $fecha = now()->format('Ymd_His');
        $nombreArchivo = "$codigoComuna"."_"."$nombreComunaLimpio"."_"."$nombreGrupoLimpio"."_"."$fecha.csv";
        $directorio = "sismaule/$codigoComuna";

        return $this->guardarCsvEnDirectorio($directorio, $nombreArchivo, $rows);
    }

    private function guardarComoCsvConsolidado(array $codigosComunas, array $nombresComunas, string $nombreGrupo, array $rows): string
    {
        if (empty($rows)) {
            throw new RuntimeException('No hay datos para guardar en CSV consolidado');
        }

        $prefix = 'consolidado';
        $fecha = now()->format('Ymd_His');
        $nombreGrupoLimpio = preg_replace('/[^\pL\pN]+/u', '_', trim($nombreGrupo));
        $nombreGrupoLimpio = trim((string) $nombreGrupoLimpio, '_');
        $nombreGrupoLimpio = $nombreGrupoLimpio !== '' ? Str::limit($nombreGrupoLimpio, 20, '') : 'regional';

        $nombreArchivo = sprintf(
            '%s_%s_%s.csv',
            $prefix,
            $nombreGrupoLimpio,
            $fecha,
        );
        $directorio = 'sismaule/consolidado';

        return $this->guardarCsvEnDirectorio($directorio, $nombreArchivo, $rows);
    }

    private function guardarCsvEnDirectorio(string $directorio, string $nombreArchivo, array $rows): string
    {
        $allKeys = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $allKeys[] = array_keys($row);
            }
        }

        if (empty($allKeys)) {
            throw new RuntimeException('No hay datos válidos para guardar en CSV');
        }

        $headers = array_values(array_unique(array_merge(...$allKeys)));

        Storage::makeDirectory($directorio);

        $rutaRelativa = "$directorio/$nombreArchivo";
        $rutaAbsoluta = Storage::path($rutaRelativa);

        $handle = fopen($rutaAbsoluta, 'w');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo para escritura: $rutaAbsoluta");
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';', '"', '\\');

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $value = is_array($row) ? ($row[$header] ?? '') : '';
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $line[] = $value;
            }
            fputcsv($handle, $line, ';', '"', '\\');
        }

        fclose($handle);

        return $rutaRelativa;
    }

    private function extraerFilasDesdeRespuesta(array $data): array
    {
        $rows = $data['respuesta']['datos']
            ?? $data['data']
            ?? $data;

        if (! is_array($rows) || empty($rows)) {
            return [];
        }

        if (array_keys($rows) !== range(0, count($rows) - 1)) {
            return [$rows];
        }

        return $rows;
    }

    private function pacienteGrupoPrioritarioUrl(string $serverUrl): string
    {
        return rtrim($serverUrl, '/').self::PACIENTE_GRUPO_PRIORITARIO_ENDPOINT;
    }

    public function descargarCsv(Request $request)
{
    $request->validate([
        'path' => 'required|string',
    ]);

    $rutaSegura = $this->sanitizarRuta($request->query('path'));

    // Solo permite rutas dentro de sismaule/
    if (!Str::startsWith($rutaSegura, 'sismaule/')) {
        abort(403, 'Ruta no permitida.');
    }

    if (!Storage::exists($rutaSegura)) {
        abort(404, 'Archivo no encontrado.');
    }

    $user = Auth::user()->load('establecimiento.comuna');

    // Si no es DSSM, solo puede descargar archivos de su propia comuna
    if (!$this->esDssm($user)) {
        $comuna = $user->establecimiento?->comuna;
        $carpetaPermitida = $comuna ? "sismaule/$comuna->codigo/" : null;

        if (!$carpetaPermitida || !Str::startsWith($rutaSegura, $carpetaPermitida)) {
            abort(403, 'No tienes acceso a este archivo.');
        }
    }

    return Storage::download($rutaSegura);
}

public function listarArchivosCsv(): JsonResponse
{
    $user = Auth::user()->load('establecimiento.comuna');

    if ($this->esDssm($user)) {
        $carpetas = Storage::directories('sismaule');
    } else {
        $comuna = $user->establecimiento?->comuna;
        $carpetas = $comuna ? ["sismaule/$comuna->codigo"] : [];
    }

    $archivos = [];

    foreach ($carpetas as $carpeta) {
        foreach (Storage::files($carpeta) as $archivo) {
            $archivos[] = [
                'nombre' => basename($archivo),
                'path' => $archivo,
                'modificado' => Storage::lastModified($archivo),
                'modificado_legible' => now()
                    ->createFromTimestamp(Storage::lastModified($archivo))
                    ->format('d-m-Y H:i'),
            ];
        }
    }

    usort($archivos, fn ($a, $b) => $b['modificado'] <=> $a['modificado']);

    return response()->json(array_values($archivos));
}

private function sanitizarRuta(string $ruta): string
{
    $ruta = str_replace(['..', '\\'], '', $ruta);
    return ltrim($ruta, '/');
}

private function esDssm($user): bool
{
    return $user->establecimiento?->codigo === '01000';
}
}
