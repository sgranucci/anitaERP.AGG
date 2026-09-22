<?php

namespace App\Support\Compras\AnitaImport;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Guarda el importador de deuda Anita: no tocar créditos de OP/OPA operativos.
 *
 * Incidente OPP 2-57815 (21/09/2026): alinear residuales / scripts ad-hoc
 * pisaron CC con pagoproveedor_id y borraron apps del lado pago.
 */
final class ProveedorCuentacorrienteAnitaImportCcGuardSupport
{
    /**
     * Fila de deuda/crédito del comprobante (no espejo de un pago ERP).
     */
    public static function esDeudaDocumento(?object $cc): bool
    {
        if ($cc === null) {
            return false;
        }

        return (int) ($cc->pagoproveedor_id ?? 0) <= 0;
    }

    /**
     * ¿Se puede pisar total / limpiar apps sintéticas de esta CC?
     * No si es fila de pago o si ya tiene aplicaciones con pagoproveedor_id.
     */
    public static function puedeAlinearSaldo(?object $cc, bool $tieneAplicacionOperativa): bool
    {
        if (! self::esDeudaDocumento($cc)) {
            return false;
        }

        return ! $tieneAplicacionOperativa;
    }

    /**
     * @param  Builder<Proveedor_Cuentacorriente>  $query
     * @return Builder<Proveedor_Cuentacorriente>
     */
    public static function soloDeudaDocumento(Builder $query): Builder
    {
        return $query->whereNull('pagoproveedor_id');
    }

    /**
     * Fragmento SQL para scripts ad-hoc (misma regla que soloDeudaDocumento).
     * Ej.: "... WHERE ... ".self::sqlAndSoloDeudaDocumento('cc')
     */
    public static function sqlAndSoloDeudaDocumento(string $alias = 'cc'): string
    {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'cc';

        return ' AND '.$alias.'.pagoproveedor_id IS NULL';
    }

    public static function tieneAplicacionOperativa(int $ccId): bool
    {
        if ($ccId <= 0) {
            return false;
        }

        return Proveedor_Cuentacorriente_Aplicacion::query()
            ->where(function ($q) use ($ccId) {
                $q->where('proveedor_cuentacorriente_id', $ccId)
                    ->orWhere('proveedor_cuentacorriente_aplicado_id', $ccId);
            })
            ->where('pagoproveedor_id', '>', 0)
            ->exists();
    }

    /**
     * Borra solo apps sin pagoproveedor (sintéticas del import).
     * Nunca toca aplicaciones de OP/OPA ERP.
     */
    public static function borrarAppsNoOperativas(int $ccId): void
    {
        if ($ccId <= 0) {
            return;
        }

        EloquentAuditDeleteSupport::each(
            Proveedor_Cuentacorriente_Aplicacion::query()
                ->where(function ($q) use ($ccId) {
                    $q->where('proveedor_cuentacorriente_id', $ccId)
                        ->orWhere('proveedor_cuentacorriente_aplicado_id', $ccId);
                })
                ->where(function ($q) {
                    $q->whereNull('pagoproveedor_id')
                        ->orWhere('pagoproveedor_id', '<=', 0);
                })
        );
    }

    /**
     * Alinea total de una CC de deuda documento. No-op si es fila de pago
     * o si ya tiene apps operativas de OP.
     *
     * @return bool true si se alineó
     */
    public static function alinearTotalDeudaDocumento(
        Proveedor_Cuentacorriente $cc,
        float $objetivo,
        bool $borrarAppsSinteticas = true,
    ): bool {
        if (! self::puedeAlinearSaldo($cc, self::tieneAplicacionOperativa((int) $cc->id))) {
            return false;
        }

        $cc->total = round($objetivo, 4);
        $cc->save();

        if ($borrarAppsSinteticas) {
            self::borrarAppsNoOperativas((int) $cc->id);
        }

        return true;
    }
}
