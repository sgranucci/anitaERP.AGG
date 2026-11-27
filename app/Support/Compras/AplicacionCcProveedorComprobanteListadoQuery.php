<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\AplicacionCcProveedorComprobante;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoCortesSupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query única del workbench de comprobantes de aplicación CC (pantalla, export, cortes, gráfico).
 * Siempre acotada a las empresas asignadas al usuario.
 */
final class AplicacionCcProveedorComprobanteListadoQuery
{
    private const T = AplicacionCcProveedorComprobanteListadoColumnas::TABLA;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function filtrada(array $filtros, bool $conOrden = true): Builder
    {
        $query = AplicacionCcProveedorComprobante::query()
            ->select([
                self::T.'.*',
                'empresa.nombre as nombreempresa',
                'proveedor.codigo as codigoproveedor',
                'proveedor.nombre as nombreproveedor',
                'moneda.abreviatura as abreviaturamoneda',
                'usr.nombre as nombreusuario',
                'usr_anula.nombre as nombreusuarioanula',
            ])
            ->leftJoin('empresa', 'empresa.id', '=', self::T.'.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', self::T.'.proveedor_id')
            ->leftJoin('moneda', 'moneda.id', '=', self::T.'.moneda_id')
            ->leftJoin('usuario as usr', 'usr.id', '=', self::T.'.usuario_id')
            ->selectRaw(
                '(select group_concat(distinct l.credito_etiqueta order by l.credito_etiqueta separator \', \') '
                .'from aplicacion_cc_proveedor_comprobante_linea l where l.comprobante_id = '.self::T.'.id) as creditos_resumen'
            )
            ->leftJoin('usuario as usr_anula', 'usr_anula.id', '=', self::T.'.anulado_usuario_id');

        app(EmpresaRepositoryInterface::class)->aplicarFiltroEmpresasAsignadas($query, self::T.'.empresa_id');
        AplicacionCcProveedorComprobanteListadoFiltros::aplicar($query, $filtros);
        if ($conOrden) {
            AplicacionCcProveedorComprobanteListadoFiltros::aplicarOrden($query, $filtros);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, AplicacionCcProveedorComprobante>
     */
    public static function lee(array $filtros, bool $paginar)
    {
        $query = self::filtrada($filtros);
        if (! $paginar) {
            return $query->get();
        }
        $perPage = (int) ($filtros['_per_page'] ?? 15);

        return $query->paginate($perPage > 0 ? $perPage : 15);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{total: int, total_pesos: float, diferencia_cambio: float}
     */
    public static function resumen(array $filtros): array
    {
        $query = self::filtrada($filtros, false);
        $query->getQuery()->columns = null;
        $row = $query
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM('.self::T.'.total), 0) as total_pesos, COALESCE(SUM('.self::T.'.diferencia_cambio), 0) as dc')
            ->first();

        return [
            'total' => (int) ($row->cantidad ?? 0),
            'total_pesos' => round((float) ($row->total_pesos ?? 0), 2),
            'diferencia_cambio' => round((float) ($row->dc ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function cortes(array $filtros): array
    {
        $campos = AplicacionCcProveedorComprobanteListadoFiltros::camposOrdenables();

        return ListadoCortesSupport::calcular(
            self::filtrada($filtros, false),
            ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos),
            $campos,
            self::T.'.id',
            static fn (object $row, string $key): string => AplicacionCcProveedorComprobanteListadoColumnas::valorCelda($row, $key),
            static fn (string $key): ?array => AplicacionCcProveedorComprobanteListadoColumnas::sqlAgrupacion($key),
            ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
                AplicacionCcProveedorComprobanteListadoColumnas::RECURSO,
                AplicacionCcProveedorComprobanteListadoColumnas::catalogoActivo()
            ),
            AplicacionCcProveedorComprobanteListadoColumnas::medidasActivas()
        );
    }
}
