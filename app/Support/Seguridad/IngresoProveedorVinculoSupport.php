<?php

namespace App\Support\Seguridad;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Models\Seguridad\IngresoProveedor;
use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Collection;

/**
 * Dónde mostrar la solapa de tickets de ingreso (OC / proveedor / recepción).
 */
final class IngresoProveedorVinculoSupport
{
    public static function correspondeOc(?Ordencompra $oc): bool
    {
        if (! $oc || ! $oc->id) {
            return false;
        }
        if ((bool) ($oc->es_contrato ?? false)
            || (bool) ($oc->contrato_exige_ingresos ?? false)
            || (bool) ($oc->contrato_requiere_validacion_abono ?? false)
        ) {
            return true;
        }

        return IngresoProveedor::query()->where('ordencompra_id', (int) $oc->id)->exists();
    }

    public static function correspondeProveedor(?Proveedor $proveedor): bool
    {
        if (! $proveedor || ! $proveedor->id) {
            return false;
        }
        if (IngresoProveedor::query()->where('proveedor_id', (int) $proveedor->id)->exists()) {
            return true;
        }

        return Ordencompra::query()
            ->where('proveedor_id', (int) $proveedor->id)
            ->where(function ($q) {
                $q->where('es_contrato', true)
                    ->orWhere('contrato_exige_ingresos', true)
                    ->orWhere('contrato_requiere_validacion_abono', true);
            })
            ->exists();
    }

    public static function correspondeRecepcion(?Recepcion_Proveedor $recepcion): bool
    {
        if (! $recepcion) {
            return false;
        }
        $oc = $recepcion->ordencompras ?? null;
        if ($oc && self::correspondeOc($oc)) {
            return true;
        }
        if ($recepcion->ordencompra_id) {
            return IngresoProveedor::query()
                ->where('ordencompra_id', (int) $recepcion->ordencompra_id)
                ->exists();
        }

        return false;
    }

    public static function usuarioPuedeVerSolapa(): bool
    {
        return can('listar-ingreso-proveedor', false) || can('crear-ingreso-proveedor', false);
    }

    /**
     * @param  array{empresa_id?:int|null,proveedor_id?:int|null,ordencompra_id?:int|null}  $params
     */
    public static function urlNuevoTicket(array $params): ?string
    {
        if (! can('crear-ingreso-proveedor', false)) {
            return null;
        }

        $query = array_filter([
            'empresa_id' => $params['empresa_id'] ?? null,
            'proveedor_id' => $params['proveedor_id'] ?? null,
            'ordencompra_id' => $params['ordencompra_id'] ?? null,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ], static fn ($v) => $v !== null && $v !== '' && $v !== 0 && $v !== '0');

        return route('crear_ingreso_proveedor', $query);
    }

    /**
     * @return Collection<int, IngresoProveedor>
     */
    public static function ticketsDeOc(int $ordencompraId): Collection
    {
        return self::queryBase()
            ->where('ingreso_proveedor.ordencompra_id', $ordencompraId)
            ->get();
    }

    /**
     * @return Collection<int, IngresoProveedor>
     */
    public static function ticketsDeProveedor(int $proveedorId): Collection
    {
        return self::queryBase()
            ->where('ingreso_proveedor.proveedor_id', $proveedorId)
            ->get();
    }

    /**
     * @return Collection<int, IngresoProveedor>
     */
    public static function ticketsDeRecepcion(Recepcion_Proveedor $recepcion): Collection
    {
        $ocId = (int) ($recepcion->ordencompra_id ?? 0);
        if ($ocId <= 0) {
            return collect();
        }

        return self::ticketsDeOc($ocId);
    }

    private static function queryBase()
    {
        return IngresoProveedor::query()
            ->with([
                'personas',
                'proveedores:id,codigo,nombre',
                'motivos:id,nombre',
                'puntos:id,nombre',
                'sectores:id,nombre',
                'areas:id,nombre',
                'empresas:id,nombre',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }
}
