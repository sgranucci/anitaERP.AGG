<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use Illuminate\Support\Facades\Log;

/**
 * Anita stkley: leyendas por artículo. Línea 100 = descripción de exportación (FAE).
 */
final class ArticuloStkleyAnitaBridgeSupport
{
    public const LINEA_EXPORTACION = 100;

    public static function claveAnita(string $sku): string
    {
        return str_pad(trim($sku), 13, '0', STR_PAD_LEFT);
    }

    public static function leerLeyendaExportacion(string $sku, ?ApiAnita $apiAnita = null): ?string
    {
        $apiAnita ??= new ApiAnita;
        $clave = self::claveAnita($sku);
        $raw = $apiAnita->apiCall([
            'acc' => 'list',
            'tabla' => 'stkley',
            'campos' => 'stkl_articulo,stkl_linea,stkl_leyenda',
            'whereArmado' => " WHERE stkl_articulo='".$clave."' AND stkl_linea=".self::LINEA_EXPORTACION.' ',
        ]);
        $filas = json_decode((string) $raw, true);
        if (! is_array($filas) || $filas === [] || isset($filas['Error'])) {
            return null;
        }
        $texto = trim((string) ($filas[0]['stkl_leyenda'] ?? ''));

        return $texto !== '' ? $texto : null;
    }

    /**
     * @return array{ok: bool, sku: string, valor: string, errores: list<string>}
     */
    public static function grabarLeyendaExportacion(Articulo $articulo, ?ApiAnita $apiAnita = null): array
    {
        $apiAnita ??= new ApiAnita;
        $clave = self::claveAnita((string) $articulo->sku);
        $valor = trim((string) ($articulo->descripcion_exportacion ?? ''));
        $valorSql = str_replace("'", "''", $valor);

        $existente = json_decode((string) $apiAnita->apiCall([
            'acc' => 'list',
            'tabla' => 'stkley',
            'campos' => 'stkl_articulo,stkl_linea',
            'whereArmado' => " WHERE stkl_articulo='".$clave."' AND stkl_linea=".self::LINEA_EXPORTACION.' ',
        ]), true);

        if (! is_array($existente) || isset($existente['Error'])) {
            return [
                'ok' => false,
                'sku' => (string) $articulo->sku,
                'valor' => $valor,
                'errores' => ['respuesta list inválida: '.(string) json_encode($existente)],
            ];
        }

        if (count($existente) > 0) {
            $payload = [
                'acc' => 'update',
                'tabla' => 'stkley',
                'valores' => " stkl_leyenda = '".$valorSql."' ",
                'whereArmado' => " WHERE stkl_articulo='".$clave."' AND stkl_linea=".self::LINEA_EXPORTACION.' ',
            ];
        } else {
            $payload = [
                'acc' => 'insert',
                'tabla' => 'stkley',
                'campos' => 'stkl_articulo,stkl_linea,stkl_leyenda',
                'valores' => "'".$clave."',".self::LINEA_EXPORTACION.",'".$valorSql."'",
            ];
        }

        try {
            $apiAnita->apiCallEscritura($payload, 'stkley '.self::LINEA_EXPORTACION, 'articulo.stkley_anita.fallo');
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'sku' => (string) $articulo->sku,
                'valor' => $valor,
                'errores' => [$e->getMessage()],
            ];
        }

        return [
            'ok' => true,
            'sku' => (string) $articulo->sku,
            'valor' => $valor,
            'errores' => [],
        ];
    }

    /**
     * Importa stkley línea 100 → articulo.descripcion_exportacion (match por SKU padded).
     *
     * @return array{
     *   total_anita: int,
     *   actualizados: int,
     *   sin_match: int,
     *   sin_cambio: int,
     *   detalle_actualizados: list<array{sku: string, antes: string, despues: string}>,
     *   detalle_sin_match: list<string>
     * }
     */
    public static function sincronizarDesdeAnita(bool $ejecutar = false, ?ApiAnita $apiAnita = null): array
    {
        $apiAnita ??= new ApiAnita;
        $raw = $apiAnita->apiCall([
            'acc' => 'list',
            'tabla' => 'stkley',
            'campos' => 'stkl_articulo,stkl_linea,stkl_leyenda',
            'whereArmado' => ' WHERE stkl_linea='.self::LINEA_EXPORTACION
                ." AND stkl_leyenda IS NOT NULL AND TRIM(stkl_leyenda) <> '' ",
        ]);
        $filas = json_decode((string) $raw, true);
        if (! is_array($filas) || isset($filas['Error'])) {
            throw new \RuntimeException('No se pudo leer stkley línea '.self::LINEA_EXPORTACION.': '.(string) $raw);
        }

        $porClave = [];
        foreach (Articulo::query()->get(['id', 'sku', 'descripcion_exportacion']) as $art) {
            $porClave[self::claveAnita((string) $art->sku)] = $art;
        }

        $actualizados = 0;
        $sinMatch = 0;
        $sinCambio = 0;
        $detalleAct = [];
        $detalleSin = [];

        foreach ($filas as $fila) {
            $clave = trim((string) ($fila['stkl_articulo'] ?? ''));
            $texto = trim((string) ($fila['stkl_leyenda'] ?? ''));
            if ($clave === '' || $texto === '') {
                continue;
            }
            $art = $porClave[$clave] ?? null;
            if ($art === null) {
                $sinMatch++;
                if (count($detalleSin) < 30) {
                    $detalleSin[] = $clave;
                }
                continue;
            }
            $antes = trim((string) ($art->descripcion_exportacion ?? ''));
            if ($antes === $texto) {
                $sinCambio++;
                continue;
            }
            $detalleAct[] = [
                'sku' => (string) $art->sku,
                'antes' => $antes,
                'despues' => $texto,
            ];
            if ($ejecutar) {
                $art->descripcion_exportacion = mb_substr($texto, 0, 255);
                $art->save();
            }
            $actualizados++;
        }

        if ($ejecutar && $actualizados > 0) {
            Log::info('articulo.stkley_anita.sync_exportacion', [
                'actualizados' => $actualizados,
                'sin_match' => $sinMatch,
            ]);
        }

        return [
            'total_anita' => count($filas),
            'actualizados' => $actualizados,
            'sin_match' => $sinMatch,
            'sin_cambio' => $sinCambio,
            'detalle_actualizados' => $detalleAct,
            'detalle_sin_match' => $detalleSin,
        ];
    }
}
