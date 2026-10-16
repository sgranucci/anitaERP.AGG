<?php

namespace App\Support\Contable;

use App\Models\Compras\Comprobante_Proveedor_Difcambio;
use App\Repositories\Configuracion\EmpresaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class DiferenciaCambioFacturaReporteSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function query(array $filtros): Builder
    {
        $query = Comprobante_Proveedor_Difcambio::query()
            ->with([
                'comprobantes.proveedores',
                'comprobantes.empresas',
                'comprobantes.ordencompras',
                'recepciones',
                'usuariosAprobacion',
            ])
            ->orderByDesc('id');

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'comprobante_proveedor_difcambio.empresa_id');
        }

        $estado = (string) ($filtros['estado'] ?? '');
        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde !== '' || $hasta !== '') {
            $query->whereHas('comprobantes', function ($q) use ($desde, $hasta) {
                if ($desde !== '') {
                    $q->where('fechacomprobante', '>=', $desde);
                }
                if ($hasta !== '') {
                    $q->where('fechacomprobante', '<=', $hasta);
                }
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function paginar(array $filtros): LengthAwarePaginator
    {
        return self::query($filtros)->paginate(20);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function listar(array $filtros): Collection
    {
        return self::query($filtros)->get();
    }
}
