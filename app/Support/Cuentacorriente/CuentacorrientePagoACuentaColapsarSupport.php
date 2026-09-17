<?php

namespace App\Support\Cuentacorriente;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\DB;

/**
 * Deuda importada con pago a cuenta: el CC debe quedar con el saldo pendiente,
 * sin filas de aplicación que representen el cobrado/pagado parcial de Anita.
 */
final class CuentacorrientePagoACuentaColapsarSupport
{
    /**
     * @return array{candidatos:int,aplicaciones:int,muestra:list<array<string,mixed>>,actualizados:int,aplicaciones_borradas:int,modo:string}
     */
    public static function colapsarClientes(bool $dryRun = true, int $muestraLimite = 15, float $tolerancia = 0.05): array
    {
        $filas = DB::select('
            SELECT
                cc.id AS cc_id,
                cc.cliente_id,
                cc.venta_id,
                cc.total AS total_actual,
                COALESCE(apl.aplicado, 0) AS aplicado,
                (cc.total + COALESCE(apl.aplicado, 0)) AS saldo,
                COALESCE(apl.n_apl, 0) AS n_apl
            FROM cliente_cuentacorriente cc
            INNER JOIN (
                SELECT
                    cliente_cuentacorriente_id,
                    SUM(total) AS aplicado,
                    COUNT(*) AS n_apl
                FROM cliente_cuentacorriente_aplicacion
                GROUP BY cliente_cuentacorriente_id
            ) apl ON apl.cliente_cuentacorriente_id = cc.id
            WHERE cc.venta_id IS NOT NULL
              AND ABS(COALESCE(apl.aplicado, 0)) > ?
              AND ABS(cc.total + COALESCE(apl.aplicado, 0)) >= ?
            ORDER BY cc.id
        ', [$tolerancia, $tolerancia]);

        return self::aplicarColapso(
            $filas,
            $dryRun,
            $muestraLimite,
            static function (object $fila): void {
                $cc = Cliente_Cuentacorriente::query()->find((int) $fila->cc_id);
                if ($cc === null) {
                    return;
                }
                $cc->total = round((float) $fila->saldo, 4);
                $cc->save();
                EloquentAuditDeleteSupport::each(
                    Cliente_Cuentacorriente_Aplicacion::query()
                        ->where('cliente_cuentacorriente_id', (int) $fila->cc_id)
                );
            },
            'cliente'
        );
    }

    /**
     * @return array{candidatos:int,aplicaciones:int,muestra:list<array<string,mixed>>,actualizados:int,aplicaciones_borradas:int,modo:string}
     */
    public static function colapsarProveedores(bool $dryRun = true, int $muestraLimite = 15, float $tolerancia = 0.05): array
    {
        $filas = DB::select('
            SELECT
                cc.id AS cc_id,
                cc.proveedor_id,
                cc.comprobante_proveedor_id,
                cc.total AS total_actual,
                COALESCE(apl.aplicado, 0) AS aplicado,
                (cc.total + COALESCE(apl.aplicado, 0)) AS saldo,
                COALESCE(apl.n_apl, 0) AS n_apl
            FROM proveedor_cuentacorriente cc
            INNER JOIN (
                SELECT
                    proveedor_cuentacorriente_id,
                    SUM(total) AS aplicado,
                    COUNT(*) AS n_apl
                FROM proveedor_cuentacorriente_aplicacion
                GROUP BY proveedor_cuentacorriente_id
            ) apl ON apl.proveedor_cuentacorriente_id = cc.id
            WHERE cc.comprobante_proveedor_id IS NOT NULL
              AND ABS(COALESCE(apl.aplicado, 0)) > ?
              AND ABS(cc.total + COALESCE(apl.aplicado, 0)) >= ?
            ORDER BY cc.id
        ', [$tolerancia, $tolerancia]);

        return self::aplicarColapso(
            $filas,
            $dryRun,
            $muestraLimite,
            static function (object $fila): void {
                $cc = Proveedor_Cuentacorriente::query()->find((int) $fila->cc_id);
                if ($cc === null) {
                    return;
                }
                $cc->total = round((float) $fila->saldo, 4);
                $cc->save();
                EloquentAuditDeleteSupport::each(
                    Proveedor_Cuentacorriente_Aplicacion::query()
                        ->where('proveedor_cuentacorriente_id', (int) $fila->cc_id)
                );
            },
            'proveedor'
        );
    }

    /**
     * @param  list<object>  $filas
     * @param  callable(object): void  $aplicar
     * @return array{candidatos:int,aplicaciones:int,muestra:list<array<string,mixed>>,actualizados:int,aplicaciones_borradas:int,modo:string}
     */
    private static function aplicarColapso(
        array $filas,
        bool $dryRun,
        int $muestraLimite,
        callable $aplicar,
        string $lado,
    ): array {
        $muestra = [];
        $aplicaciones = 0;
        foreach ($filas as $fila) {
            $aplicaciones += (int) $fila->n_apl;
            if (count($muestra) < max(1, $muestraLimite)) {
                $muestra[] = [
                    'lado' => $lado,
                    'cc_id' => (int) $fila->cc_id,
                    'total_actual' => round((float) $fila->total_actual, 4),
                    'aplicado' => round((float) $fila->aplicado, 4),
                    'saldo_nuevo' => round((float) $fila->saldo, 4),
                    'n_apl' => (int) $fila->n_apl,
                ];
            }
        }

        $stats = [
            'candidatos' => count($filas),
            'aplicaciones' => $aplicaciones,
            'muestra' => $muestra,
            'actualizados' => 0,
            'aplicaciones_borradas' => 0,
            'modo' => $dryRun ? 'dry-run' : 'ejecutar',
        ];

        if ($dryRun || $filas === []) {
            return $stats;
        }

        DB::transaction(function () use ($filas, $aplicar, &$stats) {
            foreach ($filas as $fila) {
                $aplicar($fila);
                $stats['actualizados']++;
                $stats['aplicaciones_borradas'] += (int) $fila->n_apl;
            }
        });

        return $stats;
    }
}
