<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Contable\Cuentacontable;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use Illuminate\Support\Facades\DB;

/**
 * Concepto de cash-flow del comprobante (com_concepto / conceptogasto_id).
 *
 * No se pide en pantalla: se toma de la primera cuenta del primer artículo de las
 * recepciones vinculadas; si no hay COM, de la primera pierna de activo o resultado
 * del asiento (o de los DEBE de conceptos IVA netos cuando todavía no hay asiento).
 */
final class ComprobanteProveedorConceptogastoResolverSupport
{
    public static function resolverYPersistir(Comprobante_Proveedor $comprobante): ?int
    {
        $conceptoId = self::resolver($comprobante);
        $nuevo = $conceptoId > 0 ? $conceptoId : null;
        $actual = (int) ($comprobante->conceptogasto_id ?? 0) ?: null;

        if ($nuevo !== $actual) {
            $comprobante->forceFill(['conceptogasto_id' => $nuevo])->saveQuietly();
            $comprobante->conceptogasto_id = $nuevo;
        }

        return $nuevo;
    }

    public static function resolver(Comprobante_Proveedor $comprobante): int
    {
        $desdeRecepcion = self::desdeRecepciones($comprobante);
        if ($desdeRecepcion > 0) {
            return $desdeRecepcion;
        }

        $desdeAsiento = self::desdeAsiento($comprobante);
        if ($desdeAsiento > 0) {
            return $desdeAsiento;
        }

        return self::desdeConceptosIvaNeto($comprobante);
    }

    private static function desdeRecepciones(Comprobante_Proveedor $comprobante): int
    {
        $comprobante->loadMissing([
            'comprobante_proveedor_recepciones.recepcion_proveedores.recepcion_proveedor_articulos.articulos.articulo_cuentacontables',
        ]);

        $links = $comprobante->comprobante_proveedor_recepciones;
        if ($links === null || $links->isEmpty()) {
            return 0;
        }

        $empresaId = (int) ($comprobante->empresa_id ?? 0);

        $articulos = $links
            ->sortBy(fn ($link) => (int) ($link->recepcion_proveedor_id ?? 0))
            ->flatMap(function ($link) {
                $recepcion = $link->recepcion_proveedores;
                if (! $recepcion) {
                    return collect();
                }

                return $recepcion->recepcion_proveedor_articulos
                    ?->sortBy([
                        ['orden', 'asc'],
                        ['id', 'asc'],
                    ]) ?? collect();
            });

        /** @var Recepcion_Proveedor_Articulo|null $primera */
        $primera = $articulos->first(fn ($linea) => (int) ($linea->articulo_id ?? 0) > 0);
        if (! $primera) {
            return 0;
        }

        $cuentaId = OrdencompraContratoRutaFacturaSupport::cuentaCompraOGastoArticulo(
            $primera->articulos,
            $empresaId
        );

        return self::conceptoDesdeCuentaId($cuentaId);
    }

    private static function desdeAsiento(Comprobante_Proveedor $comprobante): int
    {
        $asientoId = (int) ($comprobante->asiento_id ?? 0);
        if ($asientoId <= 0) {
            return 0;
        }

        $filas = DB::table('asiento_movimiento as am')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->where('am.asiento_id', $asientoId)
            ->where('am.monto', '>', 0)
            ->orderBy('am.id')
            ->get(['cc.id', 'cc.codigo', 'cc.conceptogasto_id']);

        foreach ($filas as $fila) {
            if (! self::esActivoOResultado(self::soloDigitos($fila->codigo))) {
                continue;
            }
            $concepto = (int) ($fila->conceptogasto_id ?? 0);
            if ($concepto > 0) {
                return $concepto;
            }
        }

        return 0;
    }

    /**
     * Borrador sin COM ni asiento: el DEBE del concepto IVA neto suele ser ya la
     * cuenta de gasto/activo que después va al asiento.
     */
    private static function desdeConceptosIvaNeto(Comprobante_Proveedor $comprobante): int
    {
        $comprobante->loadMissing([
            'comprobante_proveedor_conceptos.concepto_ivacompras',
            'comprobante_proveedor_conceptos.cuentacontablesdebe',
        ]);

        $empresaId = (int) ($comprobante->empresa_id ?? 0);

        foreach ($comprobante->comprobante_proveedor_conceptos ?? [] as $linea) {
            $tipo = (string) ($linea->concepto_ivacompras?->tipoconcepto ?? '');
            if (! ComprobanteProveedorConceptoIvaTipos::esNeto($tipo)) {
                continue;
            }

            $cuentaId = (int) ($linea->cuentacontabledebe_id ?? 0);
            if ($cuentaId <= 0) {
                $cuentaId = (int) ($linea->concepto_ivacompras?->cuentacontableDebeIdParaEmpresa($empresaId) ?? 0);
            }
            if ($cuentaId <= 0) {
                continue;
            }

            $cuenta = Cuentacontable::query()->find($cuentaId);
            if (! $cuenta || ! self::esActivoOResultado(self::soloDigitos($cuenta->codigo))) {
                continue;
            }

            $concepto = (int) ($cuenta->conceptogasto_id ?? 0);
            if ($concepto > 0) {
                return $concepto;
            }
        }

        return 0;
    }

    private static function conceptoDesdeCuentaId(int $cuentaId): int
    {
        if ($cuentaId <= 0) {
            return 0;
        }

        return (int) (Cuentacontable::query()->whereKey($cuentaId)->value('conceptogasto_id') ?? 0);
    }

    /**
     * Activo (1…) salvo IVA crédito fiscal; resultado (4…/5…/6…).
     * Excluye pasivo (2…) y patrimonio (3…).
     */
    private static function esActivoOResultado(int $codigo): bool
    {
        if ($codigo <= 0) {
            return false;
        }
        if ($codigo >= 114010000 && $codigo < 114020000) {
            return false;
        }
        if ($codigo >= 100000000 && $codigo < 200000000) {
            return true;
        }

        return $codigo >= 400000000 && $codigo < 700000000;
    }

    private static function soloDigitos(mixed $valor): int
    {
        return (int) preg_replace('/\D/', '', (string) ($valor ?? ''));
    }
}
