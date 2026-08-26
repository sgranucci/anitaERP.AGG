<?php

declare(strict_types=1);

namespace App\Services\Configuracion;

use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Support\Configuracion\PadronIibb\PadronIibbArchivoSupport;
use App\Support\Configuracion\PadronMipyme\PadronMipymeArchivoSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Preanaliza (ZIP → CSV/TXT) e importa el padrón MiPyME en lotes.
 * No toca la base si el preanálisis rechaza el archivo.
 */
class PadronMipymeImportadorService
{
    private const TAMANIO_LOTE = 1000;

    public function __construct(
        private ClienteRepositoryInterface $clienteRepository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function analizar(UploadedFile|string $archivo): array
    {
        [$rutaOrigen, $nombreOriginal] = $this->rutaYNombre($archivo);
        if ($rutaOrigen === null) {
            return $this->errorAnalisis('No se pudo leer el archivo de importación.');
        }

        if (! PadronMipymeArchivoSupport::extensionPermitida($nombreOriginal)
            && ! $this->origenEsZipPorFirma($rutaOrigen, $nombreOriginal)) {
            return $this->errorAnalisis('El archivo debe ser CSV, TXT o ZIP con el padrón adentro.');
        }

        $rutaDatos = $rutaOrigen;
        try {
            $resuelto = PadronMipymeArchivoSupport::resolver($rutaOrigen, $nombreOriginal);
            $rutaDatos = $resuelto['ruta'];
            $analisis = PadronMipymeArchivoSupport::analizar($rutaDatos, $resuelto);
            $analisis['tamanio_origen_texto'] = PadronMipymeArchivoSupport::formatearTamanio((int) $analisis['tamanio_origen']);
            $analisis['tamanio_datos_texto'] = PadronMipymeArchivoSupport::formatearTamanio((int) $analisis['tamanio_datos']);

            return $analisis;
        } catch (Throwable $e) {
            return $this->errorAnalisis($e->getMessage());
        } finally {
            if ($rutaDatos !== $rutaOrigen) {
                PadronMipymeArchivoSupport::limpiarTemporal($rutaDatos);
            }
        }
    }

    /**
     * @return array{ok: bool, mensaje: string, importados: int, omitidas: int, clientes_actualizados: int}
     */
    public function importarDesdeArchivo(UploadedFile|string $archivo): array
    {
        [$rutaOrigen, $nombreOriginal] = $this->rutaYNombre($archivo);
        if ($rutaOrigen === null) {
            return $this->errorImportacion('No se pudo leer el archivo de importación.');
        }

        if (! PadronMipymeArchivoSupport::extensionPermitida($nombreOriginal)
            && ! $this->origenEsZipPorFirma($rutaOrigen, $nombreOriginal)) {
            return $this->errorImportacion('El archivo debe ser CSV, TXT o ZIP con el padrón adentro.');
        }

        $rutaDatos = $rutaOrigen;
        try {
            $resuelto = PadronMipymeArchivoSupport::resolver($rutaOrigen, $nombreOriginal);
            $rutaDatos = $resuelto['ruta'];
            $analisis = PadronMipymeArchivoSupport::analizar($rutaDatos, $resuelto);
            if (! ($analisis['ok'] ?? false)) {
                return $this->errorImportacion((string) ($analisis['mensaje'] ?? 'El archivo no es un padrón MiPyME válido.'));
            }

            return $this->persistir($rutaDatos, $analisis);
        } catch (Throwable $e) {
            return $this->errorImportacion('Error al importar el padrón: ' . $e->getMessage());
        } finally {
            if ($rutaDatos !== $rutaOrigen) {
                PadronMipymeArchivoSupport::limpiarTemporal($rutaDatos);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $analisis
     * @return array{ok: bool, mensaje: string, importados: int, omitidas: int, clientes_actualizados: int}
     */
    private function persistir(string $rutaDatos, array $analisis): array
    {
        $ahora = now()->format('Y-m-d H:i:s');
        $lote = [];
        $importados = 0;
        $omitidas = 0;
        $id = 1;
        $cuitsVistos = [];

        try {
            DB::beginTransaction();

            $this->clienteRepository->actualizaPadronMipyme('N');
            DB::table('padron_mipyme')->delete();

            foreach (PadronMipymeArchivoSupport::iterarFilasValidas($rutaDatos, $analisis) as $fila) {
                if (isset($cuitsVistos[$fila['cuit']])) {
                    $omitidas++;

                    continue;
                }
                $cuitsVistos[$fila['cuit']] = true;

                $lote[] = [
                    'id' => $id++,
                    'cuit' => $fila['cuit'],
                    'nombre' => $fila['nombre'],
                    'actividad' => $fila['actividad'],
                    'fechainicio' => $fila['fechainicio'],
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];

                if (count($lote) >= self::TAMANIO_LOTE) {
                    DB::table('padron_mipyme')->insert($lote);
                    $importados += count($lote);
                    $lote = [];
                }
            }

            if ($lote !== []) {
                DB::table('padron_mipyme')->insert($lote);
                $importados += count($lote);
            }

            if ($importados === 0) {
                DB::rollBack();

                return $this->errorImportacion('No se importó ningún registro válido del archivo.');
            }

            $lineasDatos = (int) ($analisis['lineas_datos'] ?? 0);
            $omitidas += max(0, $lineasDatos - $importados - $omitidas);

            $clientes = $this->clienteRepository->actualizaPadronMipymeDesdePadron('C');

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorImportacion('Error al importar el padrón: ' . $e->getMessage());
        }

        $mensaje = "Padrón MiPyME importado: {$importados} registros";
        if ($omitidas > 0) {
            $mensaje .= " ({$omitidas} filas omitidas)";
        }
        $mensaje .= ", {$clientes} cliente(s) con factura de crédito.";
        if ($analisis['era_zip'] ?? false) {
            $extraido = (string) ($analisis['nombre_extraido'] ?? 'el archivo de datos');
            $mensaje .= ' Se descomprimió el ZIP y se usó ' . $extraido . '.';
        }

        return [
            'ok' => true,
            'mensaje' => $mensaje,
            'importados' => $importados,
            'omitidas' => $omitidas,
            'clientes_actualizados' => $clientes,
        ];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function rutaYNombre(UploadedFile|string $archivo): array
    {
        if ($archivo instanceof UploadedFile) {
            $ruta = $archivo->getRealPath();

            return [
                is_string($ruta) && $ruta !== '' ? $ruta : null,
                $archivo->getClientOriginalName(),
            ];
        }

        $ruta = (string) $archivo;

        return [$ruta !== '' && is_readable($ruta) ? $ruta : null, basename($ruta)];
    }

    private function origenEsZipPorFirma(string $ruta, string $nombreOriginal): bool
    {
        $ext = strtolower((string) pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if ($ext !== '' && $ext !== 'zip') {
            return false;
        }

        return PadronIibbArchivoSupport::pareceZip($ruta);
    }

    /**
     * @return array<string, mixed>
     */
    private function errorAnalisis(string $mensaje): array
    {
        return [
            'ok' => false,
            'mensaje' => $mensaje,
            'era_zip' => false,
            'extraido' => false,
            'nombre_origen' => '',
            'nombre_extraido' => null,
            'columnas' => [],
            'muestra' => [],
            'advertencias' => [],
            'lineas_totales' => 0,
            'lineas_datos' => 0,
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string, importados: int, omitidas: int, clientes_actualizados: int}
     */
    private function errorImportacion(string $mensaje): array
    {
        return [
            'ok' => false,
            'mensaje' => $mensaje,
            'importados' => 0,
            'omitidas' => 0,
            'clientes_actualizados' => 0,
        ];
    }
}
