<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Support\Facades\DB;

/**
 * Precio editable en remitos de venta (permiso especial).
 *
 * En El Bierzo solo aplica a remitos del punto de venta 00006 de Surmar.
 */
final class RemitoPrecioEditableSupport
{
    public const PERMISO = 'modificar-precio-remito';

    /** Código de PV Remitos Surmar (migración puntoventa_remitos_surmar_elbierzo). */
    public const CODIGO_PV_SURMAR_REMITOS = '00006';

    public static function codigoEsRemitosSurmar(string $codigo): bool
    {
        return (int) $codigo === 6;
    }

    /**
     * En El Bierzo el permiso solo vale para PV 6 Surmar.
     * Fuera de Bierzo no hay restricción por PV (el permiso hoy solo se seedéa ahí).
     */
    public static function puntoventaPermiteEdicion(?int $puntoventaId): bool
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return true;
        }

        if ($puntoventaId === null || $puntoventaId <= 0) {
            return false;
        }

        $pv = DB::table('puntoventa')
            ->where('id', $puntoventaId)
            ->whereNull('deleted_at')
            ->first(['codigo', 'empresa_id']);

        if ($pv === null) {
            return false;
        }

        if (! self::codigoEsRemitosSurmar((string) ($pv->codigo ?? ''))) {
            return false;
        }

        return SurmarSupport::esEmpresaSurmar((int) ($pv->empresa_id ?? 0));
    }

    public static function puedeModificarPrecio(?int $puntoventaId = null): bool
    {
        if (! can(self::PERMISO, false)) {
            return false;
        }

        return self::puntoventaPermiteEdicion($puntoventaId);
    }

    /**
     * Sin permiso (o PV no habilitado en Bierzo): conserva el precio ya grabado en líneas existentes.
     * Las líneas nuevas mantienen el precio enviado (viene de lista / asignapreciocliente).
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lineasExistentes  filas remito_articulo (update)
     * @return array<string, mixed>
     */
    public static function aplicarPreciosSegunPermiso(
        array $data,
        string $funcion,
        array $lineasExistentes = [],
        ?int $puntoventaId = null
    ): array {
        $pvId = $puntoventaId;
        if ($pvId === null || $pvId <= 0) {
            $pvId = isset($data['puntoventa_id']) ? (int) $data['puntoventa_id'] : null;
        }

        if (self::puedeModificarPrecio($pvId !== null && $pvId > 0 ? $pvId : null)) {
            return $data;
        }

        if (! isset($data['precios']) || ! is_array($data['precios'])) {
            return $data;
        }

        if ($funcion !== 'update' || $lineasExistentes === []) {
            return $data;
        }

        $porId = [];
        foreach ($lineasExistentes as $linea) {
            $lineaId = (int) ($linea['id'] ?? 0);
            if ($lineaId > 0) {
                $porId[$lineaId] = (float) ($linea['precio'] ?? 0);
            }
        }

        $ids = $data['ids'] ?? [];
        if (! is_array($ids)) {
            return $data;
        }

        foreach ($data['precios'] as $i => $precioEnviado) {
            $lineaId = (int) ($ids[$i] ?? 0);
            if ($lineaId > 0 && array_key_exists($lineaId, $porId)) {
                $data['precios'][$i] = $porId[$lineaId];
            }
        }

        return $data;
    }
}
