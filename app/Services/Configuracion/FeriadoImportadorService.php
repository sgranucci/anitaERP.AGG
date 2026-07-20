<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Feriado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa los feriados de Argentina desde una API pública (argentinadatos.com por defecto)
 * y los inserta en la tabla `feriado` evitando duplicados por fecha.
 */
class FeriadoImportadorService
{
    /**
     * Descarga los feriados del año indicado y crea los que aún no existan.
     *
     * @return array{ok: bool, mensaje: string, importados: int, existentes: int, total: int}
     */
    public function importarAnio(int $anio): array
    {
        if ($anio < 1900 || $anio > 2100) {
            return $this->error('El año a importar no es válido.');
        }

        $url = str_replace('{year}', (string) $anio, (string) config('feriados.api_url'));
        $timeout = (int) config('feriados.api_timeout', 20);

        try {
            $response = Http::timeout($timeout > 0 ? $timeout : 20)
                ->acceptJson()
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('Feriados: no se pudo conectar con la API', ['url' => $url, 'error' => $e->getMessage()]);

            return $this->error('No se pudo conectar con el servicio de feriados. Verifique la conexión a internet.');
        }

        if (! $response->successful()) {
            return $this->error('El servicio de feriados respondió con un error (HTTP '.$response->status().').');
        }

        $datos = $response->json();
        if (! is_array($datos) || $datos === []) {
            return $this->error('El servicio de feriados no devolvió datos para el año '.$anio.'.');
        }

        $importados = 0;
        $existentes = 0;
        $total = 0;

        foreach ($datos as $item) {
            $normalizado = $this->normalizarItem($item, $anio);
            if ($normalizado === null) {
                continue;
            }

            $total++;

            $yaExiste = Feriado::query()->where('fecha', $normalizado['fecha'])->exists();
            if ($yaExiste) {
                $existentes++;

                continue;
            }

            Feriado::create($normalizado);
            $importados++;
        }

        if ($total === 0) {
            return $this->error('No se encontraron feriados válidos para el año '.$anio.'.');
        }

        $mensaje = "Feriados {$anio}: {$importados} importados, {$existentes} ya existían (total consultados: {$total}).";

        return [
            'ok' => true,
            'mensaje' => $mensaje,
            'importados' => $importados,
            'existentes' => $existentes,
            'total' => $total,
        ];
    }

    /**
     * @param  mixed  $item
     * @return array{nombre: string, fecha: string, tipo: string|null}|null
     */
    private function normalizarItem($item, int $anio): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $fechaCruda = (string) ($item['fecha'] ?? '');
        $nombre = trim((string) ($item['nombre'] ?? ''));
        $tipo = trim((string) ($item['tipo'] ?? ''));

        if ($fechaCruda === '' || $nombre === '') {
            return null;
        }

        try {
            $fecha = Carbon::parse($fechaCruda);
        } catch (\Throwable $e) {
            return null;
        }

        if ((int) $fecha->format('Y') !== $anio) {
            return null;
        }

        return [
            'nombre' => mb_substr($nombre, 0, 255),
            'fecha' => $fecha->format('Y-m-d'),
            'tipo' => $tipo !== '' ? mb_substr($tipo, 0, 50) : null,
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string, importados: int, existentes: int, total: int}
     */
    private function error(string $mensaje): array
    {
        return [
            'ok' => false,
            'mensaje' => $mensaje,
            'importados' => 0,
            'existentes' => 0,
            'total' => 0,
        ];
    }
}
