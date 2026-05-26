<?php

namespace App\Support\Http;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EliminacionRegistroSupport
{
    /** @var array<string, string> */
    private const ETIQUETAS_TABLA = [
        'proveedor' => 'proveedor(es)',
        'cliente' => 'cliente(s)',
        'provincia' => 'provincia(s)',
        'puntoventa' => 'punto(s) de venta',
        'venta' => 'venta(s)',
        'lote' => 'lote(s)',
        'cliente_entrega' => 'domicilio(s) de entrega de cliente',
        'guia' => 'guía(s)',
        'ordenventa' => 'orden(es) de venta',
    ];

    /**
     * @param  array<string, string>|null  $tablasEtiqueta  tabla hija => descripción legible
     */
    public static function mensajeSiReferenciado(
        string $columnaFk,
        int $id,
        ?array $tablasEtiqueta = null,
        string $nombreEntidad = 'el registro'
    ): ?string {
        $mapa = $tablasEtiqueta ?? self::ETIQUETAS_TABLA;

        foreach ($mapa as $tabla => $etiqueta) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columnaFk)) {
                continue;
            }
            if (DB::table($tabla)->where($columnaFk, $id)->exists()) {
                return 'No se puede eliminar '.$nombreEntidad.' porque está asignado a '.$etiqueta.'.';
            }
        }

        return null;
    }

    public static function mensajeDesdeExcepcion(\Throwable $e, string $nombreEntidad = 'el registro'): string
    {
        if ($e instanceof QueryException) {
            return self::mensajeDesdeQueryException($e, $nombreEntidad);
        }

        $mensaje = trim($e->getMessage());
        if ($mensaje !== '' && ! str_contains($mensaje, 'SQLSTATE')) {
            return $mensaje;
        }

        return 'No se pudo eliminar '.$nombreEntidad.'.';
    }

    public static function mensajeDesdeQueryException(QueryException $e, string $nombreEntidad = 'el registro'): string
    {
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        if (in_array($driverCode, [1451, 1452, 2292], true)) {
            $tabla = self::extraerTablaReferenciadora($e->getMessage());
            if ($tabla !== null) {
                $etiqueta = self::ETIQUETAS_TABLA[$tabla] ?? str_replace('_', ' ', $tabla);

                return 'No se puede eliminar '.$nombreEntidad.' porque está asignado a '.$etiqueta.'.';
            }

            return 'No se puede eliminar '.$nombreEntidad.' porque está referenciado por otros datos del sistema.';
        }

        return 'No se pudo eliminar '.$nombreEntidad.'.';
    }

    public static function respuestaJsonError(string $mensaje, int $status = 422): JsonResponse
    {
        return response()->json([
            'mensaje' => 'error',
            'error' => $mensaje,
        ], $status);
    }

    public static function respuestaJsonOk(): JsonResponse
    {
        return response()->json(['mensaje' => 'ok']);
    }

    private static function extraerTablaReferenciadora(string $message): ?string
    {
        if (preg_match('/foreign key constraint fails.*?`[^`]+`\.`([^`]+)`/i', $message, $coincidencias)) {
            return $coincidencias[1];
        }

        return null;
    }
}
