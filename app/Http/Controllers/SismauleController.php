<?php

namespace App\Http\Controllers;

use App\Http\Requests\SismaulePacienteGrupoPrioritarioRequest;
use App\Models\Comuna;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
                ->withHeaders([
                    'usuario' => 'salud',
                    'Modulo' => 'SALUD',
                ])
                ->timeout(30)
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

        // Buscar comuna por código para obtener el nombre
        $comuna = Comuna::where('codigo', $validated['comuna'])->first();
        $csvPath = null;

        if ($comuna) {
            try {
                $csvPath = $this->guardarComoCsv(
                    codigoComuna: $comuna->codigo,
                    nombreComuna: $comuna->nombre,
                    data: $data,
                );
            } catch (\RuntimeException $e) {
                report($e);
            }
        }

        return response()->json([
            'data' => $data,
            'csv_path' => $csvPath,
        ]);
    }

    /**
     * Convierte un array de datos a CSV y lo guarda en storage/app/sismaule/{codigo_comuna}/.
     *
     * @param  string  $codigoComuna  Código de la comuna (ej: 07101)
     * @param  string  $nombreComuna  Nombre de la comuna (ej: Cauquenes)
     * @param  array   $data          Datos obtenidos del servicio
     * @return string  Ruta relativa del archivo guardado
     *
     * @throws \RuntimeException  Si no hay datos para guardar
     */
    private function guardarComoCsv(string $codigoComuna, string $nombreComuna, array $data): string
    {
        // La respuesta del servicio viene como: {"respuesta": {"estado": "OK", "datos": [...]}}
        // Extraer el array de registros desde respuesta.datos
        $rows = $data['respuesta']['datos']
            ?? $data['data']
            ?? $data;

        if (!is_array($rows) || empty($rows)) {
            throw new \RuntimeException('No hay datos para guardar en CSV');
        }

        // Si es un array asociativo (un solo registro), lo normalizamos
        if (array_keys($rows) !== range(0, count($rows) - 1)) {
            $rows = [$rows];
        }

        // Recolectar todos los headers posibles de todas las filas
        $allKeys = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $allKeys[] = array_keys($row);
            }
        }

        if (empty($allKeys)) {
            throw new \RuntimeException('No hay datos válidos para guardar en CSV');
        }

        // Unificar headers: todos los campos que aparezcan en al menos una fila
        $headers = array_unique(array_merge(...$allKeys));

        // Generar nombre de archivo: codigo_nombre_YYYYMMDD_HHmmss.csv
        $fecha = now()->format('Ymd_His');
        $nombreArchivo = "{$codigoComuna}_{$nombreComuna}_{$fecha}.csv";

        // Directorio: sismaule/{codigo_comuna}/
        $directorio = "sismaule/{$codigoComuna}";

        Storage::makeDirectory($directorio);

        $rutaRelativa = "{$directorio}/{$nombreArchivo}";
        $rutaAbsoluta = Storage::path($rutaRelativa);

        $handle = fopen($rutaAbsoluta, 'w');
        if ($handle === false) {
            throw new \RuntimeException("No se pudo abrir el archivo para escritura: {$rutaAbsoluta}");
        }

        // BOM UTF-8 para que Excel reconozca caracteres especiales
        fwrite($handle, "\xEF\xBB\xBF");

        // Escribir headers con separador punto y coma (estándar Chile/Latam)
        fputcsv($handle, $headers, ';', '"', '\\');

        // Escribir cada fila
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $value = is_array($row) ? ($row[$header] ?? '') : '';
                // Si el valor es un array, lo serializamos como JSON string
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

    private function pacienteGrupoPrioritarioUrl(string $serverUrl): string
    {
        return rtrim($serverUrl, '/').self::PACIENTE_GRUPO_PRIORITARIO_ENDPOINT;
    }
}
