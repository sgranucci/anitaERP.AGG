<?php

namespace App\Support\Sueldos;

use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de presentaciones SiRADIG (F572) — index paginado.
 *
 * Filtro de texto (CUIL / apellido / nombre / legajo) + filtros externos:
 * empresa, período (año fiscal), sección (A/B) y solo vigentes (default true).
 */
class SiradigListadoFiltros
{
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresa($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);

        $periodo = $request->filled('filtro_periodo') ? (int) $request->input('filtro_periodo') : null;
        $seccion = strtoupper((string) $request->input('filtro_seccion', ''));
        if (! in_array($seccion, ['A', 'B'], true)) {
            $seccion = '';
        }

        // Solo vigentes por defecto; se desactiva con filtro_vigentes=0 / todas=1
        $soloVigentes = ! ($request->input('filtro_vigentes') === '0' || $request->boolean('vigentes_todas'));

        return [
            'valor' => $valor,
            'busqueda' => $valor,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'periodo' => $periodo,
            'seccion' => $seccion,
            'solo_vigentes' => $soloVigentes,
        ];
    }

    /**
     * @return array{0:?int,1:string}  [empresa_id, empresa_scope]
     */
    private static function resolverEmpresa(Request $request, ?int $empresaDefault): array
    {
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [(int) $request->input('empresa_id'), 'una'];
        }
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [$empresaDefault, 'una'];
        }

        return [null, 'todas'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'periodo' => null,
            'seccion' => '',
            'solo_vigentes' => true,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (! empty($filtros['periodo'])) {
            return true;
        }
        if (($filtros['seccion'] ?? '') !== '') {
            return true;
        }
        if (($filtros['solo_vigentes'] ?? true) === false) {
            return true;
        }
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if (! empty($filtros['periodo'])) {
            $params['filtro_periodo'] = (int) $filtros['periodo'];
        }
        if (($filtros['seccion'] ?? '') !== '') {
            $params['filtro_seccion'] = $filtros['seccion'];
        }
        if (($filtros['solo_vigentes'] ?? true) === false) {
            $params['filtro_vigentes'] = '0';
        }
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            $params['empresa_todas'] = 1;
        } elseif (! empty($filtros['empresa_id'])) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Siradig_Presentacion_Sueldos>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('siradig_presentacion_sueldos.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['periodo'])) {
            $query->where('siradig_presentacion_sueldos.periodo', (int) $filtros['periodo']);
        }
        if (($filtros['seccion'] ?? '') !== '') {
            $query->where('siradig_presentacion_sueldos.seccion', $filtros['seccion']);
        }
        if (($filtros['solo_vigentes'] ?? true) === true) {
            $query->where('siradig_presentacion_sueldos.vigente', true);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return;
        }

        $like = '%'.addcslashes($valor, '%_\\').'%';
        $digitos = preg_replace('/\D+/', '', $valor) ?? '';
        $id = filter_var($valor, FILTER_VALIDATE_INT);

        $query->where(function ($q) use ($like, $digitos, $id) {
            $q->where('siradig_presentacion_sueldos.empleado_apellido', 'like', $like)
                ->orWhere('siradig_presentacion_sueldos.empleado_nombre', 'like', $like)
                ->orWhere('siradig_presentacion_sueldos.agente_retencion_denominacion', 'like', $like);
            if ($digitos !== '') {
                $q->orWhere('siradig_presentacion_sueldos.empleado_cuit', 'like', '%'.$digitos.'%');
            }
            if ($id !== false) {
                $q->orWhere('siradig_presentacion_sueldos.id', (int) $id);
            }
        });
    }
}
