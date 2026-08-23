<?php

namespace App\Support\Seguridad;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Models\Seguridad\IngresoProveedor;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\OrdencompraEstados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Dónde mostrar la solapa de tickets de ingreso (OC / proveedor / recepción).
 */
final class IngresoProveedorVinculoSupport
{
    /**
     * Personas se cargan desde una OC solo si es contrato activo.
     * `contrato_exige_ingresos` no habilita la entrada: solo corta el pago.
     */
    public static function ocPermiteCargarPersonas(?Ordencompra $oc, ?string $fechaYmd = null): bool
    {
        if (! $oc || ! $oc->id || ! (bool) ($oc->es_contrato ?? false)) {
            return false;
        }

        $estado = strtoupper(trim((string) ($oc->estadoordencompra ?? '')));
        if (! in_array($estado, [OrdencompraEstados::APROBADA, OrdencompraEstados::CUMPLIDA], true)) {
            return false;
        }

        $fecha = $fechaYmd ?: date('Y-m-d');
        $desde = self::fechaYmd($oc->contrato_vigencia_desde ?? null);
        $hasta = self::fechaYmd($oc->contrato_vigencia_hasta ?? null);
        if ($desde !== null && $fecha < $desde) {
            return false;
        }
        if ($hasta !== null && $fecha > $hasta) {
            return false;
        }

        return true;
    }

    public static function correspondeOc(?Ordencompra $oc): bool
    {
        if (! $oc || ! $oc->id) {
            return false;
        }
        if (self::ocPermiteCargarPersonas($oc)) {
            return true;
        }

        return IngresoProveedor::query()->where('ordencompra_id', (int) $oc->id)->exists();
    }

    /**
     * @return Builder<Ordencompra>
     */
    public static function queryContratosActivos(?int $proveedorId = null, ?int $empresaId = null): Builder
    {
        $fecha = date('Y-m-d');
        $query = Ordencompra::query()
            ->where('es_contrato', true)
            ->whereIn('estadoordencompra', [OrdencompraEstados::APROBADA, OrdencompraEstados::CUMPLIDA])
            ->where(function ($q) use ($fecha) {
                $q->whereNull('contrato_vigencia_desde')
                    ->orWhereDate('contrato_vigencia_desde', '<=', $fecha);
            })
            ->where(function ($q) use ($fecha) {
                $q->whereNull('contrato_vigencia_hasta')
                    ->orWhereDate('contrato_vigencia_hasta', '>=', $fecha);
            });

        if ($proveedorId && $proveedorId > 0) {
            $query->where('proveedor_id', $proveedorId);
        }
        if ($empresaId && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
    }

    private static function fechaYmd(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        $s = (string) $valor;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s, $m)) {
            return substr($m[0], 0, 10);
        }

        return null;
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
                'proveedores' => static fn ($q) => $q->withTrashed()->select('id', 'codigo', 'nombre'),
                'motivos:id,nombre',
                'puntos:id,nombre',
                'sectores:id,nombre',
                'areas:id,nombre',
                'empresas:id,nombre',
                'ordencompras:id,numeroordencompra',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }
}
