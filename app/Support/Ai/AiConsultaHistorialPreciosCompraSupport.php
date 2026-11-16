<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\Stock\Articulo;
use App\Services\Compras\HistorialPreciosArticuloService;
use App\Support\Compras\HistorialPreciosArticuloFiltros;
use Throwable;

/**
 * Últimas N compras (COM) de un artículo/insumo con variación de precio de costo.
 * Fuente: HistorialPreciosArticuloService::consultarCompras (recepciones confirmadas).
 */
final class AiConsultaHistorialPreciosCompraSupport
{
    public const LIMITE_DEFAULT = 3;

    public const LIMITE_MIN = 1;

    public const LIMITE_MAX = 12;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function ejecutar(Articulo $articulo, array $params): array
    {
        $intent = AiConsultaOperativaSupport::INTENT_HISTORIAL_PRECIOS_COMPRA;
        $limite = self::limiteDesdeParams($params);

        $filtros = HistorialPreciosArticuloFiltros::defaults();
        $filtros['articulo_id'] = (int) $articulo->id;
        $filtros['sku'] = '';
        $filtros['fecha_desde'] = null;
        $filtros['fecha_hasta'] = null;

        try {
            $compras = app(HistorialPreciosArticuloService::class)->consultarCompras($filtros);
        } catch (Throwable $e) {
            return self::fallo($intent, 'No se pudo leer el historial de precios: '.$e->getMessage());
        }

        // Una fila por COM (recepción), la más reciente primero.
        $porRecepcion = [];
        foreach ($compras as $compra) {
            $rid = (int) ($compra->recepcion_id ?? 0);
            if ($rid <= 0 || isset($porRecepcion[$rid])) {
                continue;
            }
            $porRecepcion[$rid] = $compra;
        }
        $unicas = array_values($porRecepcion);
        usort($unicas, static function (object $a, object $b): int {
            $cmp = strcmp((string) ($b->fecha ?? ''), (string) ($a->fecha ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($b->recepcion_id ?? 0)) <=> ((int) ($a->recepcion_id ?? 0));
        });

        $totalCompras = count($unicas);
        $seleccion = array_slice($unicas, 0, $limite);

        $filas = [];
        $conSalto = 0;
        foreach ($seleccion as $i => $actual) {
            $anterior = $unicas[$i + 1] ?? null;
            $precio = round((float) ($actual->precio ?? 0), 6);
            $precioAnt = $anterior !== null ? round((float) ($anterior->precio ?? 0), 6) : null;
            $varAbs = null;
            $varPct = null;
            if ($precioAnt !== null && $precioAnt > 0) {
                $varAbs = round($precio - $precioAnt, 6);
                $varPct = round(($varAbs / $precioAnt) * 100, 2);
                if ($varPct != 0.0) {
                    $conSalto++;
                }
            }

            $filas[] = [
                'fecha' => self::fmtFecha((string) ($actual->fecha ?? '')),
                'com' => (string) ($actual->numerorecepcion ?? '—'),
                'proveedor' => trim(
                    ((string) ($actual->codigoproveedor ?? '')).' — '.((string) ($actual->nombreproveedor ?? ''))
                ),
                'precio' => self::fmtImporte($precio, (string) ($actual->moneda_abrev ?? '')),
                'anterior' => $precioAnt !== null
                    ? self::fmtImporte($precioAnt, (string) ($anterior->moneda_abrev ?? $actual->moneda_abrev ?? ''))
                    : '—',
                'variacion' => $varPct === null ? '—' : self::fmtVariacion((float) $varAbs, (float) $varPct),
                'empresa' => (string) ($actual->nombreempresa ?? ''),
            ];
        }

        $parrafos = [
            'Precios de costo de '.$articulo->sku.' — '.($articulo->descripcion ?? ''),
            'Últimas '.count($filas).' compra(s) confirmadas (COM), de más reciente a más antigua.',
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay recepciones confirmadas con precio > 0 para este artículo en sus empresas.';
        } else {
            $parrafos[] = $conSalto > 0
                ? $conSalto.' de '.count($filas).' con variación vs la compra anterior.'
                : 'Sin variación de precio entre estas compras (o es la primera COM).';
            if ($totalCompras > count($filas)) {
                $parrafos[] = 'Hay '.$totalCompras.' COM en total; se muestran '.count($filas).'.';
            }
        }

        $links = [];
        if (can('listar-reporte-historial-precios-compra', false)) {
            $links[] = [
                'etiqueta' => 'Historial de precios (reporte)',
                'url' => route('reporte_historial_precios_articulo', HistorialPreciosArticuloFiltros::paraQueryString([
                    'articulo_id' => (int) $articulo->id,
                    'modo' => HistorialPreciosArticuloFiltros::MODO_DETALLE,
                    'agrupacion' => HistorialPreciosArticuloFiltros::AGRUPACION_ARTICULO,
                    'fecha_desde' => date('Y-m-d', strtotime('-2 years')),
                    'fecha_hasta' => date('Y-m-d'),
                    'consolidar_empresas' => true,
                ])),
            ];
        }
        if (can('editar-articulos', false) || can('listar-articulos', false)) {
            try {
                $links[] = [
                    'etiqueta' => 'Artículo '.$articulo->sku,
                    'url' => route('editar_articulo', ['id' => (int) $articulo->id])
                        .'?origen=modal_consulta&vista=consulta',
                ];
            } catch (Throwable) {
                //
            }
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'score' => 0.92,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'articulo_id' => (int) $articulo->id,
                'sku' => (string) $articulo->sku,
                'limite' => $limite,
                'cantidad' => count($filas),
                'total_compras' => $totalCompras,
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'fecha', 'label' => 'Fecha'],
                    ['key' => 'com', 'label' => 'COM'],
                    ['key' => 'proveedor', 'label' => 'Proveedor'],
                    ['key' => 'precio', 'label' => 'Precio'],
                    ['key' => 'anterior', 'label' => 'Anterior'],
                    ['key' => 'variacion', 'label' => 'Variación'],
                    ['key' => 'empresa', 'label' => 'Empresa'],
                ],
                'filas' => $filas,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function limiteDesdeParams(array $params): int
    {
        $n = isset($params['limite']) && is_numeric($params['limite'])
            ? (int) $params['limite']
            : self::LIMITE_DEFAULT;

        return max(self::LIMITE_MIN, min(self::LIMITE_MAX, $n > 0 ? $n : self::LIMITE_DEFAULT));
    }

    private static function fmtFecha(string $iso): string
    {
        if ($iso === '') {
            return '—';
        }
        $ts = strtotime($iso);

        return $ts ? date('d/m/Y', $ts) : $iso;
    }

    private static function fmtImporte(float $valor, string $moneda): string
    {
        $txt = number_format($valor, 4, ',', '.');
        $txt = rtrim(rtrim($txt, '0'), ',');

        return $moneda !== '' ? $txt.' '.$moneda : $txt;
    }

    private static function fmtVariacion(float $abs, float $pct): string
    {
        $signo = $abs > 0 ? '+' : '';
        $absTxt = number_format($abs, 4, ',', '.');
        $absTxt = rtrim(rtrim($absTxt, '0'), ',');
        $pctTxt = number_format($pct, 2, ',', '.');

        return $signo.$absTxt.' ('.$signo.$pctTxt.'%)';
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallo(string $intent, string $mensaje): array
    {
        return [
            'ok' => false,
            'intent' => $intent,
            'score' => 0.0,
            'parrafos' => [],
            'links' => [],
            'datos' => [],
            'error' => $mensaje,
        ];
    }
}
