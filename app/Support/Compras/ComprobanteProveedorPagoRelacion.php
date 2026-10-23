<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Database\SqlDialectSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Relación factura → orden de pago. La registra ListadoRelacionCatalogo.
 * El filtro usa EXISTS (cualquier pago que cumpla). Las columnas agrupan
 * los pagos del comprobante para no duplicar la fila.
 */
final class ComprobanteProveedorPagoRelacion
{
    /** @var list<string> */
    public const CLAVES = ['pago_numero', 'pago_fecha', 'pago_estado'];

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        return [
            'pago_numero' => [
                'column' => '',
                'type' => 'texto',
                'label' => 'Nro. orden de pago',
            ],
            'pago_fecha' => [
                'column' => '',
                'type' => 'fecha',
                'label' => 'Fecha orden de pago',
            ],
            'pago_estado' => [
                'column' => '',
                'type' => 'texto',
                'label' => 'Estado orden de pago',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function debeAplicarColumnas(array $filtros): bool
    {
        foreach ((array) ($filtros['_columnas'] ?? []) as $key) {
            if (in_array((string) $key, self::CLAVES, true)) {
                return true;
            }
        }
        if (is_string($filtros['columnas'] ?? null)) {
            foreach (explode(',', (string) $filtros['columnas']) as $key) {
                if (in_array(trim($key), self::CLAVES, true)) {
                    return true;
                }
            }
        }
        foreach (['sort', 'agrupar'] as $bloque) {
            foreach ((array) ($filtros[$bloque] ?? []) as $fila) {
                $campo = is_array($fila) ? (string) ($fila['campo'] ?? '') : (string) $fila;
                if (in_array($campo, self::CLAVES, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function aplicarColumnas(Builder $query): void
    {
        $query->leftJoinSub(self::subconsulta(), 'pago_op', function ($join): void {
            $join->on('pago_op.comprobante_proveedor_id', '=', 'comprobante_proveedor.id');
        });
        $query->addSelect([
            'pago_op.pago_numero as pago_numero',
            'pago_op.pago_fecha as pago_fecha',
            'pago_op.pago_estado as pago_estado',
            'pago_op.pago_ids as pago_ids',
        ]);
    }

    public static function aplicarFiltro(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta
    ): void {
        $columna = match ($campoKey) {
            'pago_fecha' => 'pp.fecha',
            'pago_estado' => 'pp.estado',
            default => SqlDialectSupport::castTexto('pp.numerotransaccion'),
        };
        $tipo = $campoKey === 'pago_fecha' ? 'fecha' : 'texto';

        $query->whereExists(function (Builder $sub) use ($columna, $tipo, $operador, $valor, $valorHasta): void {
            $sub->selectRaw('1')
                ->from('pagoproveedor_comprobante as ppc')
                ->join('proveedor_cuentacorriente as deuda', 'deuda.id', '=', 'ppc.proveedor_cuentacorriente_id')
                ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
                ->whereColumn('deuda.comprobante_proveedor_id', 'comprobante_proveedor.id');

            if ($tipo === 'fecha') {
                ListadoQbeSupport::aplicarFecha($sub, $columna, $operador, $valor, $valorHasta);

                return;
            }

            PagoproveedorListadoFiltros::aplicarExpresionRelacion($sub, 'texto', $columna, $operador, $valor, $valorHasta);
        });
    }

    private static function subconsulta(): \Illuminate\Database\Query\Builder
    {
        $base = DB::table('pagoproveedor_comprobante as ppc')
            ->join('proveedor_cuentacorriente as deuda', 'deuda.id', '=', 'ppc.proveedor_cuentacorriente_id')
            ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
            ->whereNotNull('deuda.comprobante_proveedor_id')
            ->select([
                'deuda.comprobante_proveedor_id',
                'pp.id as pagoproveedor_id',
                'pp.numerotransaccion',
                'pp.fecha',
                'pp.estado',
            ])
            ->distinct();

        $numero = SqlDialectSupport::castTexto('numerotransaccion');
        $fecha = SqlDialectSupport::castTexto(SqlDialectSupport::fecha('fecha'));
        $estado = SqlDialectSupport::castTexto('estado');
        $ids = SqlDialectSupport::castTexto('pagoproveedor_id');

        return DB::query()
            ->fromSub($base, 'pago')
            ->groupBy('comprobante_proveedor_id')
            ->select('comprobante_proveedor_id')
            ->selectRaw(SqlDialectSupport::textosEnOrden($numero, 'pagoproveedor_id').' as pago_numero')
            ->selectRaw(SqlDialectSupport::textosEnOrden($fecha, 'pagoproveedor_id').' as pago_fecha')
            ->selectRaw(SqlDialectSupport::textosEnOrden($estado, 'pagoproveedor_id').' as pago_estado')
            ->selectRaw(SqlDialectSupport::textosEnOrden($ids, 'pagoproveedor_id').' as pago_ids');
    }
}
