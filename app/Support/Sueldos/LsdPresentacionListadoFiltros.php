<?php

namespace App\Support\Sueldos;

use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LsdPresentacionListadoFiltros
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
        $estado = trim((string) $request->input('filtro_estado', ''));
        if (! in_array($estado, ['generada', 'presentada', 'rechazada'], true)) {
            $estado = '';
        }

        return [
            'valor' => $valor,
            'busqueda' => $valor,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'periodo' => $periodo,
            'estado' => $estado,
        ];
    }

    /** @return array{0:?int,1:string} */
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

    /** @return array<string, mixed> */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'periodo' => null,
            'estado' => '',
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
        if (($filtros['estado'] ?? '') !== '') {
            return true;
        }

        return ($filtros['empresa_scope'] ?? 'una') === 'todas';
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        $q = [];
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $q['filtro_valor'] = $filtros['valor'];
        }
        if (! empty($filtros['periodo'])) {
            $q['filtro_periodo'] = $filtros['periodo'];
        }
        if (($filtros['estado'] ?? '') !== '') {
            $q['filtro_estado'] = $filtros['estado'];
        }
        if (($filtros['empresa_scope'] ?? '') === 'todas') {
            $q['empresa_scope'] = 'todas';
        } elseif (! empty($filtros['empresa_id'])) {
            $q['empresa_id'] = $filtros['empresa_id'];
        }

        return $q;
    }

    public static function aplicar(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $query->where(function (Builder $q) use ($valor) {
                $q->where('lsd_presentacion_sueldos.nro_liquidacion_afip', 'like', '%'.$valor.'%')
                    ->orWhere('lsd_presentacion_sueldos.archivo_nombre', 'like', '%'.$valor.'%')
                    ->orWhere('lsd_presentacion_sueldos.observacion', 'like', '%'.$valor.'%');
            });
        }
        if (! empty($filtros['periodo'])) {
            $query->where('lsd_presentacion_sueldos.periodo', (int) $filtros['periodo']);
        }
        if (($filtros['estado'] ?? '') !== '') {
            $query->where('lsd_presentacion_sueldos.estado', $filtros['estado']);
        }
        if (($filtros['empresa_scope'] ?? 'una') === 'una' && ! empty($filtros['empresa_id'])) {
            $query->where('lsd_presentacion_sueldos.empresa_id', (int) $filtros['empresa_id']);
        }
    }
}
