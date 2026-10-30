<?php

namespace App\Support\Stock;

use App\Support\Listado\ReporteResultadoTextoSupport;
use Illuminate\Http\Request;

/**
 * Parámetros de la consulta Stock por OT y del filtro sobre el resultado.
 */
final class ReporteStockOtListadoFiltros
{
    /** @var array<string, array{label: string, type: string}> */
    public const CAMPOS_TEXTO = [
        'linea' => ['label' => 'Línea', 'type' => 'texto'],
        'sku' => ['label' => 'Artículo', 'type' => 'texto'],
        'descripcion' => ['label' => 'Descripción', 'type' => 'texto'],
        'situacion' => ['label' => 'Situación', 'type' => 'texto'],
        'lote' => ['label' => 'OT / lote', 'type' => 'texto'],
        'deposito' => ['label' => 'Depósito', 'type' => 'texto'],
        'pedido' => ['label' => 'Pedido', 'type' => 'texto'],
    ];

    /**
     * @return array<string, array{prop: string|\Closure, label: string, flexible?: bool}>
     */
    public static function camposFiltroFilas(): array
    {
        return [
            'linea' => ['prop' => 'nombrelinea', 'label' => 'Línea', 'flexible' => true],
            'sku' => [
                'prop' => static fn (array $fila): string => trim(
                    (string) ($fila['sku_excel'] ?? '').' '.(string) ($fila['sku'] ?? '')
                ),
                'label' => 'Artículo',
                'flexible' => true,
            ],
            'descripcion' => ['prop' => 'descripcion_excel', 'label' => 'Descripción', 'flexible' => true],
            'situacion' => ['prop' => 'situacion', 'label' => 'Situación', 'flexible' => true],
            'lote' => ['prop' => 'lote', 'label' => 'OT / lote'],
            'deposito' => [
                'prop' => static fn (array $fila): string => trim(
                    (string) ($fila['deposito_codigo'] ?? '').' '.(string) ($fila['deposito_nombre'] ?? '')
                ),
                'label' => 'Depósito',
                'flexible' => true,
            ],
            'pedido' => ['prop' => 'pedido', 'label' => 'Pedido'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $texto = ReporteResultadoTextoSupport::resolverParams(
            $request,
            'sku',
            array_keys(self::CAMPOS_TEXTO)
        );

        return array_merge([
            'estado' => self::enum($request->input('estado'), ['ACTIVAS', 'INACTIVAS', 'TODAS'], 'TODAS'),
            'mventa_id' => (int) $request->input('mventa_id', 0),
            'desdearticulo_id' => self::idOVacio($request->input('desdearticulo_id')),
            'hastaarticulo_id' => self::idOVacio($request->input('hastaarticulo_id')),
            'desdelinea_id' => self::idOVacio($request->input('desdelinea_id')),
            'hastalinea_id' => self::idOVacio($request->input('hastalinea_id')),
            'desdecategoria_id' => self::idOVacio($request->input('desdecategoria_id')),
            'hastacategoria_id' => self::idOVacio($request->input('hastacategoria_id')),
            'desdelote' => trim((string) $request->input('desdelote', '')),
            'hastalote' => trim((string) $request->input('hastalote', '')),
            'imprimefoto' => self::enum($request->input('imprimefoto'), ['SIN_FOTO', 'CON_FOTO'], 'SIN_FOTO'),
            'estadoOt' => self::enum($request->input('estadoOt'), ['TODAS', 'ENTREGA', 'PRODUCCION'], 'TODAS'),
            'apertura' => self::enum($request->input('apertura'), ['TOTALIZADO', 'MOVIMIENTOS'], 'TOTALIZADO'),
            'deposito_id' => (int) $request->input('deposito_id', 0),
            'ambito_deposito' => ReporteStockOtDepositoAmbitoSupport::normalizar((string) $request->input('ambito_deposito', 'TODOS')),
        ], $texto);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach ([
            'estado', 'mventa_id', 'desdearticulo_id', 'hastaarticulo_id',
            'desdelinea_id', 'hastalinea_id', 'desdecategoria_id', 'hastacategoria_id',
            'desdelote', 'hastalote', 'imprimefoto', 'estadoOt', 'apertura',
            'deposito_id', 'ambito_deposito',
        ] as $key) {
            $valor = $filtros[$key] ?? null;
            if ($valor === null || $valor === '' || $valor === 0 || $valor === '0') {
                if (in_array($key, ['estado', 'imprimefoto', 'estadoOt', 'apertura', 'ambito_deposito'], true) && $valor !== null && $valor !== '') {
                    $out[$key] = $valor;
                }
                continue;
            }
            $out[$key] = $valor;
        }

        return ReporteResultadoTextoSupport::mergeParaQueryString($out, $filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function sinTexto(array $filtros): array
    {
        return ReporteResultadoTextoSupport::sinTexto($filtros);
    }

    /**
     * @param  list<string>  $permitidos
     */
    private static function enum(mixed $valor, array $permitidos, string $default): string
    {
        $texto = strtoupper(trim((string) $valor));

        return in_array($texto, $permitidos, true) ? $texto : $default;
    }

    private static function idOVacio(mixed $valor): int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : 0;
    }
}
