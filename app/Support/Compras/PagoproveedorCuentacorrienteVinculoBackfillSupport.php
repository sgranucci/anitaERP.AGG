<?php

namespace App\Support\Compras;

use Illuminate\Support\Facades\DB;

/**
 * Enlaza el crédito sintético de cuenta corriente (import Anita, sin FK) con la
 * cabecera `pagoproveedor` ya importada.
 *
 * Solo escribe `pagoproveedor_id`. No toca totales, cotizaciones, aplicaciones
 * ni asientos: el saldo de la cuenta corriente y el contable quedan iguales.
 */
final class PagoproveedorCuentacorrienteVinculoBackfillSupport
{
    /**
     * @return array{
     *   creditos_candidatos: int,
     *   a_vincular: int,
     *   apps_a_vincular: int,
     *   vinculados_cc: int,
     *   vinculados_app: int,
     *   sin_op: int,
     *   ambiguo_etiqueta: int,
     *   ambiguo_op: int,
     *   omitidos_op_ya_tiene_cc: int,
     *   muestra: list<array{cc_id: int, pago_id: int, apps: int}>
     * }
     */
    public static function ejecutar(bool $dryRun): array
    {
        $pagosPorClave = self::indexarPagos();
        $pagosConCc = self::pagosQueYaTienenCuentaCorriente();
        $plan = self::plan(self::filasCandidatas(), $pagosPorClave, $pagosConCc);

        if ($dryRun || $plan['vinculos'] === []) {
            return self::statsDesdePlan($plan, 0, 0);
        }

        $vinculadosCc = 0;
        $vinculadosApp = 0;
        $ahora = now();

        DB::transaction(function () use ($plan, $ahora, &$vinculadosCc, &$vinculadosApp) {
            foreach (array_chunk($plan['vinculos'], 400) as $chunk) {
                foreach ($chunk as $vinculo) {
                    $vinculadosCc += DB::table('proveedor_cuentacorriente')
                        ->where('id', $vinculo['cc_id'])
                        ->whereNull('pagoproveedor_id')
                        ->whereNull('comprobante_proveedor_id')
                        ->where('total', '<', 0)
                        ->update([
                            'pagoproveedor_id' => $vinculo['pago_id'],
                            'updated_at' => $ahora,
                        ]);

                    if ($vinculo['app_ids'] === []) {
                        continue;
                    }

                    $vinculadosApp += DB::table('proveedor_cuentacorriente_aplicacion')
                        ->whereIn('id', $vinculo['app_ids'])
                        ->whereNull('pagoproveedor_id')
                        ->update([
                            'pagoproveedor_id' => $vinculo['pago_id'],
                            'updated_at' => $ahora,
                        ]);
                }
            }
        });

        return self::statsDesdePlan($plan, $vinculadosCc, $vinculadosApp);
    }

    /**
     * @param  iterable<int, object>  $filas
     * @param  array<string, list<int>>  $pagosPorClave
     * @param  array<int, true>  $pagosConCc
     * @return array{
     *   vinculos: list<array{cc_id: int, pago_id: int, app_ids: list<int>}>,
     *   creditos_candidatos: int,
     *   sin_op: int,
     *   ambiguo_etiqueta: int,
     *   ambiguo_op: int,
     *   omitidos_op_ya_tiene_cc: int
     * }
     */
    public static function plan(iterable $filas, array $pagosPorClave, array $pagosConCc): array
    {
        /** @var array<int, array{claves: array<string, true>, app_ids: array<int, true>}> $porCredito */
        $porCredito = [];

        foreach ($filas as $fila) {
            $parsed = PagoproveedorEtiquetaAnitaSupport::parse((string) ($fila->comprobanteaplicado ?? ''));
            if ($parsed === null) {
                continue;
            }

            $ccId = (int) $fila->credito_id;
            $clave = self::claveMovimiento(
                (int) $fila->proveedor_id,
                (int) $fila->empresa_id,
                $parsed
            );
            $porCredito[$ccId]['claves'][$clave] = true;
            $porCredito[$ccId]['app_ids'][(int) $fila->app_id] = true;
        }

        $vinculos = [];
        $sinOp = 0;
        $ambiguoEtiqueta = 0;
        $ambiguoOp = 0;
        $yaTieneCc = 0;

        foreach ($porCredito as $ccId => $grupo) {
            $claves = array_keys($grupo['claves']);
            if (count($claves) !== 1) {
                $ambiguoEtiqueta++;

                continue;
            }

            $pagoIds = $pagosPorClave[$claves[0]] ?? [];
            $pagoId = self::pagoUnico($pagoIds, isset($pagosConCc[(int) ($pagoIds[0] ?? 0)]));
            if (count($pagoIds) === 0) {
                $sinOp++;

                continue;
            }
            if (count($pagoIds) !== 1) {
                $ambiguoOp++;

                continue;
            }
            if (isset($pagosConCc[$pagoIds[0]])) {
                $yaTieneCc++;

                continue;
            }

            $vinculos[] = [
                'cc_id' => $ccId,
                'pago_id' => (int) $pagoId,
                'app_ids' => array_map('intval', array_keys($grupo['app_ids'])),
            ];
        }

        return [
            'vinculos' => $vinculos,
            'creditos_candidatos' => count($porCredito),
            'sin_op' => $sinOp,
            'ambiguo_etiqueta' => $ambiguoEtiqueta,
            'ambiguo_op' => $ambiguoOp,
            'omitidos_op_ya_tiene_cc' => $yaTieneCc,
        ];
    }

    /**
     * @param  list<int>  $pagoIds
     */
    public static function pagoUnico(array $pagoIds, bool $yaTieneOtroCc): ?int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $pagoIds))));
        if (count($ids) !== 1 || $yaTieneOtroCc) {
            return null;
        }

        return $ids[0];
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, numero: int}  $parsed
     */
    public static function claveMovimiento(int $proveedorId, int $empresaId, array $parsed): string
    {
        return $proveedorId.'|'.$empresaId.'|'.PagoproveedorEtiquetaAnitaSupport::clave($parsed);
    }

    /**
     * @return array<string, list<int>>
     */
    private static function indexarPagos(): array
    {
        $mapa = [];
        $filas = DB::table('pagoproveedor')->select([
            'id', 'proveedor_id', 'empresa_id', 'tipocomprobante', 'letra', 'sucursal', 'numerotransaccion',
        ])->orderBy('id')->get();

        foreach ($filas as $fila) {
            $parsed = [
                'tipo' => strtoupper(trim((string) $fila->tipocomprobante)),
                'letra' => strtoupper(trim((string) ($fila->letra ?? 'A'))) ?: 'A',
                'sucursal' => (int) $fila->sucursal,
                'numero' => (int) $fila->numerotransaccion,
            ];
            if ($parsed['tipo'] === '' || $parsed['numero'] <= 0) {
                continue;
            }
            $clave = self::claveMovimiento((int) $fila->proveedor_id, (int) $fila->empresa_id, $parsed);
            $mapa[$clave][] = (int) $fila->id;
        }

        return $mapa;
    }

    /**
     * @return array<int, true>
     */
    private static function pagosQueYaTienenCuentaCorriente(): array
    {
        $ids = DB::table('proveedor_cuentacorriente')
            ->where('pagoproveedor_id', '>', 0)
            ->distinct()
            ->pluck('pagoproveedor_id');

        $set = [];
        foreach ($ids as $id) {
            $set[(int) $id] = true;
        }

        return $set;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function filasCandidatas()
    {
        return DB::table('proveedor_cuentacorriente as credito')
            ->join('proveedor_cuentacorriente_aplicacion as app', function ($join) {
                $join->on('app.proveedor_cuentacorriente_aplicado_id', '=', 'credito.id')
                    ->orOn('app.proveedor_cuentacorriente_id', '=', 'credito.id');
            })
            ->whereNull('credito.pagoproveedor_id')
            ->whereNull('credito.comprobante_proveedor_id')
            ->where('credito.total', '<', 0)
            ->whereNull('app.pagoproveedor_id')
            ->where(function ($q) {
                $q->where('app.comprobanteaplicado', 'like', 'OPP%')
                    ->orWhere('app.comprobanteaplicado', 'like', 'OPA%')
                    ->orWhere('app.comprobanteaplicado', 'like', 'AOP%')
                    ->orWhere('app.comprobanteaplicado', 'like', 'OPV%');
            })
            ->select([
                'credito.id as credito_id',
                'credito.proveedor_id',
                'credito.empresa_id',
                'app.id as app_id',
                'app.comprobanteaplicado',
            ])
            ->orderBy('credito.id')
            ->get();
    }

    /**
     * @param  array{
     *   vinculos: list<array{cc_id: int, pago_id: int, app_ids: list<int>}>,
     *   creditos_candidatos: int,
     *   sin_op: int,
     *   ambiguo_etiqueta: int,
     *   ambiguo_op: int,
     *   omitidos_op_ya_tiene_cc: int
     * }  $plan
     * @return array{
     *   creditos_candidatos: int,
     *   a_vincular: int,
     *   apps_a_vincular: int,
     *   vinculados_cc: int,
     *   vinculados_app: int,
     *   sin_op: int,
     *   ambiguo_etiqueta: int,
     *   ambiguo_op: int,
     *   omitidos_op_ya_tiene_cc: int,
     *   muestra: list<array{cc_id: int, pago_id: int, apps: int}>
     * }
     */
    private static function statsDesdePlan(array $plan, int $vinculadosCc, int $vinculadosApp): array
    {
        $apps = 0;
        $muestra = [];
        foreach ($plan['vinculos'] as $vinculo) {
            $apps += count($vinculo['app_ids']);
            if (count($muestra) < 25) {
                $muestra[] = [
                    'cc_id' => $vinculo['cc_id'],
                    'pago_id' => $vinculo['pago_id'],
                    'apps' => count($vinculo['app_ids']),
                ];
            }
        }

        return [
            'creditos_candidatos' => $plan['creditos_candidatos'],
            'a_vincular' => count($plan['vinculos']),
            'apps_a_vincular' => $apps,
            'vinculados_cc' => $vinculadosCc,
            'vinculados_app' => $vinculadosApp,
            'sin_op' => $plan['sin_op'],
            'ambiguo_etiqueta' => $plan['ambiguo_etiqueta'],
            'ambiguo_op' => $plan['ambiguo_op'],
            'omitidos_op_ya_tiene_cc' => $plan['omitidos_op_ya_tiene_cc'],
            'muestra' => $muestra,
        ];
    }
}
