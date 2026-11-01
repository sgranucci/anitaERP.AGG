<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use App\Jobs\Configuracion\ImportarPadronIibbCabaJob;
use App\Jobs\Configuracion\ImportarPadronIibbProvinciaJob;
use App\Jobs\Configuracion\ImportarPadronIibbSantaFeJob;
use App\Support\Configuracion\PadronIibb\PadronIibbParserFactory;
use App\Support\Configuracion\PadronIibbBandejaSupport;
use App\Support\Configuracion\PadronIibbCargaRegistroSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Toma un archivo estable de cada subcarpeta de la bandeja y encola el mismo
 * job que la pantalla de importación. Una jurisdicción a la vez.
 */
final class PadronIibbBandejaIngresoService
{
    /**
     * @return list<array{clave: string, estado: string, archivo?: string, mensaje?: string}>
     */
    public function vigilar(bool $dryRun = false): array
    {
        if (! (bool) config('padrones_iibb.bandeja.habilitada', true)) {
            return [];
        }

        $raiz = PadronIibbBandejaSupport::directorio();
        if (! is_dir($raiz)) {
            Log::warning('padron_iibb:bandeja:sin_directorio', ['directorio' => $raiz]);

            return [[
                'clave' => '',
                'estado' => 'sin_directorio',
                'mensaje' => "No existe la bandeja {$raiz}",
            ]];
        }

        if (! $dryRun) {
            PadronIibbBandejaSupport::asegurarDirectorios();
        }

        $resultados = [];
        foreach (PadronIibbBandejaSupport::carpetas() as $clave => $def) {
            foreach ($this->sanearCarpeta($clave, $dryRun) as $aviso) {
                $resultados[] = $aviso;
            }
            $resultado = $this->tomarCarpeta($clave, $def, $dryRun);
            if ($resultado !== null) {
                $resultados[] = $resultado;
            }
        }

        return $resultados;
    }

    /**
     * Saca de procesando lo que ya no es una carga viva: restos descomprimidos
     * y archivos tomados más viejos que el timeout del worker, si la cola no
     * los tiene. Sin esto la carpeta queda cerrada y el padrón siguiente no entra.
     *
     * @return list<array{clave: string, estado: string, archivo?: string, mensaje?: string}>
     */
    private function sanearCarpeta(string $clave, bool $dryRun): array
    {
        $avisos = [];
        $maxima = PadronIibbBandejaSupport::edadMaximaEnProceso();

        foreach (PadronIibbBandejaSupport::archivosTomados($clave) as $tomado) {
            if ($tomado['edad'] < $maxima || $this->referenciadoPorCola($tomado['ruta'])) {
                continue;
            }

            $mensaje = sprintf(
                'en procesando hace %ds, sin job en la cola. Se pasa a error para no frenar el siguiente.',
                $tomado['edad']
            );
            if ($dryRun) {
                $avisos[] = [
                    'clave' => $clave,
                    'estado' => 'liberaria',
                    'archivo' => $tomado['ruta'],
                    'mensaje' => $mensaje,
                ];
                continue;
            }

            $this->marcarCargaInterrumpida($tomado['ruta'], $mensaje);
            PadronIibbBandejaSupport::archivar($tomado['ruta'], false);
            Log::warning('padron_iibb:bandeja:liberada', [
                'clave' => $clave,
                'archivo' => $tomado['ruta'],
                'edad' => $tomado['edad'],
            ]);
            $avisos[] = [
                'clave' => $clave,
                'estado' => 'liberada',
                'archivo' => $tomado['ruta'],
                'mensaje' => $mensaje,
            ];
        }

        if ($dryRun || PadronIibbBandejaSupport::hayEnProceso($clave)) {
            return $avisos;
        }

        $sobrantes = PadronIibbBandejaSupport::borrarSobrantes($clave);
        if ($sobrantes > 0) {
            $avisos[] = [
                'clave' => $clave,
                'estado' => 'sobrante',
                'mensaje' => 'se borraron '.$sobrantes.' archivo(s) sueltos en procesando',
            ];
        }

        return $avisos;
    }

    private function referenciadoPorCola(string $ruta): bool
    {
        $nombre = basename($ruta);
        if ($nombre === '') {
            return false;
        }

        $like = '%'.addcslashes($nombre, '%_\\').'%';

        return DB::table('jobs')->where('payload', 'like', $like)->exists();
    }

    private function marcarCargaInterrumpida(string $ruta, string $mensaje): void
    {
        $id = DB::table('padron_iibb_carga')
            ->where('estado', 'en_proceso')
            ->where('archivo', $ruta)
            ->orderByDesc('id')
            ->value('id');
        if ($id === null) {
            return;
        }

        PadronIibbCargaRegistroSupport::fallar((int) $id, $mensaje);
    }

    /**
     * @param  array{jurisdiccion: int, tipo: ?string}  $def
     * @return array{clave: string, estado: string, archivo?: string, mensaje?: string}|null
     */
    private function tomarCarpeta(string $clave, array $def, bool $dryRun): ?array
    {
        if (PadronIibbBandejaSupport::hayEnProceso($clave)) {
            $esperando = PadronIibbBandejaSupport::archivoListo($clave);
            if ($esperando === null) {
                return null;
            }

            $edad = 0;
            $tomados = PadronIibbBandejaSupport::archivosTomados($clave);
            if ($tomados !== []) {
                $edad = max(array_column($tomados, 'edad'));
            }
            $mensaje = sprintf(
                'en curso hace %ds; espera %s',
                $edad,
                basename($esperando)
            );
            Log::warning('padron_iibb:bandeja:ocupada', [
                'clave' => $clave,
                'espera' => $esperando,
                'edad' => $edad,
            ]);

            return [
                'clave' => $clave,
                'estado' => 'ocupada',
                'archivo' => $esperando,
                'mensaje' => $mensaje,
            ];
        }

        $origen = PadronIibbBandejaSupport::archivoListo($clave);
        if ($origen === null) {
            return null;
        }

        if ($dryRun) {
            return [
                'clave' => $clave,
                'estado' => 'listo',
                'archivo' => $origen,
            ];
        }

        try {
            $archivo = PadronIibbBandejaSupport::tomar($clave, $origen);
        } catch (Throwable $e) {
            Log::warning('padron_iibb:bandeja:tomar', ['clave' => $clave, 'error' => $e->getMessage()]);

            return [
                'clave' => $clave,
                'estado' => 'error',
                'archivo' => $origen,
                'mensaje' => $e->getMessage(),
            ];
        }

        try {
            $this->encolar($clave, $def, $archivo);
        } catch (Throwable $e) {
            PadronIibbBandejaSupport::archivar($archivo, false);
            Log::error('padron_iibb:bandeja:encolar', ['clave' => $clave, 'error' => $e->getMessage()]);

            return [
                'clave' => $clave,
                'estado' => 'error',
                'archivo' => $archivo,
                'mensaje' => $e->getMessage(),
            ];
        }

        return [
            'clave' => $clave,
            'estado' => 'encolado',
            'archivo' => $archivo,
        ];
    }

    /**
     * @param  array{jurisdiccion: int, tipo: ?string}  $def
     */
    private function encolar(string $clave, array $def, string $archivo): void
    {
        $jurisdiccion = (int) $def['jurisdiccion'];
        $tipo = $def['tipo'];

        $provincia = DB::table('provincia')->where('jurisdiccion', (string) $jurisdiccion)->first();
        if ($provincia === null) {
            throw new RuntimeException("No hay provincia con jurisdicción {$jurisdiccion}.");
        }

        $etiqueta = $this->etiqueta($jurisdiccion, $tipo);
        $cargaId = PadronIibbCargaRegistroSupport::iniciar([
            'provincia_id' => (int) $provincia->id,
            'jurisdiccion' => $jurisdiccion,
            'etiqueta' => $etiqueta,
            'tipopadron' => $tipo,
            'origen' => PadronIibbCargaRegistroSupport::ORIGEN_BANDEJA,
            'archivo' => $archivo,
        ]);

        $pauseMs = (int) config('padrones_iibb.pause_ms', 20);

        if ($jurisdiccion === 901) {
            ImportarPadronIibbCabaJob::dispatch(
                $archivo,
                (int) config('padrones_iibb.batch_caba', 2000),
                $pauseMs,
                false,
                false,
                $cargaId
            );

            return;
        }

        if ($jurisdiccion === 921) {
            ImportarPadronIibbSantaFeJob::dispatch(
                $archivo,
                (int) $provincia->id,
                (int) config('padrones_iibb.batch_santafe', 3000),
                $pauseMs,
                false,
                false,
                $cargaId
            );

            return;
        }

        ImportarPadronIibbProvinciaJob::dispatch(
            $archivo,
            (int) $provincia->id,
            $jurisdiccion,
            $tipo,
            $cargaId,
            (int) config('padrones_iibb.batch_provincia', 3000),
            $pauseMs,
            false,
            false
        );
    }

    private function etiqueta(int $jurisdiccion, ?string $tipo): string
    {
        if ($jurisdiccion === 901) {
            return 'IIBB CABA (AGIP)';
        }
        if ($jurisdiccion === 921) {
            return 'IIBB Santa Fe (API PARP)';
        }
        if (PadronIibbParserFactory::esTucumanCoeficientes($jurisdiccion, $tipo)) {
            return 'IIBB Tucumán (coeficientes)';
        }

        return PadronIibbParserFactory::crear($jurisdiccion, $tipo)->etiqueta();
    }
}
