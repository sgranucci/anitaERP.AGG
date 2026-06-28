<?php

namespace App\Support\Caja;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RendicionMaquinavendingCajaListadoFiltros
{
    /** @var list<string> */
    public const CAMPOS = ['codigo', 'empresa', 'caja', 'maquinavending_rendicion_id', 'fecharendicion'];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
        }

        return [
            'empresa_id' => $request->input('empresa_id'),
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'filtro_busqueda_rapida' => $request->input('filtro_busqueda_rapida'),
            'filtro_campo' => $request->input('filtro_campo', 'codigo'),
            'filtro_operador' => $request->input('filtro_operador', 'contiene'),
            'fecha_desde' => $request->input('fecha_desde'),
            'fecha_hasta' => $request->input('fecha_hasta'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'empresa_id' => '',
            'valor' => '',
            'filtro_busqueda_rapida' => '',
            'filtro_campo' => 'codigo',
            'filtro_operador' => 'contiene',
            'fecha_desde' => '',
            'fecha_hasta' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'empresa_id' => $filtros['empresa_id'] ?? null,
            'filtro_valor' => $filtros['valor'] ?? null,
            'filtro_busqueda_rapida' => $filtros['filtro_busqueda_rapida'] ?? null,
            'filtro_campo' => $filtros['filtro_campo'] ?? null,
            'filtro_operador' => $filtros['filtro_operador'] ?? null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosUsuario(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            return true;
        }
        if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta'])) {
            return true;
        }

        return trim((string) ($filtros['valor'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosUsuario($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarScopeEmpresasAsignadas(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('rendicion_maquinavending_caja.empresa_id', $empresaId);

            return;
        }

        $asignadas = array_values(array_filter(
            array_map('intval', (array) ($filtros['empresas_asignadas'] ?? [])),
            fn (int $id) => $id > 0,
        ));

        if ($asignadas !== []) {
            $query->whereIn('rendicion_maquinavending_caja.empresa_id', $asignadas);
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): Builder
    {
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('rendicion_maquinavending_caja.fecharendicion', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('rendicion_maquinavending_caja.fecharendicion', '<=', $filtros['fecha_hasta']);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return $query;
        }

        if (! empty($filtros['filtro_busqueda_rapida'])) {
            $query->where(function ($q) use ($valor) {
                $q->where('rendicion_maquinavending_caja.codigo', 'like', '%'.$valor.'%')
                    ->orWhere('rendicion_maquinavending_caja.maquinavending_rendicion_id', 'like', '%'.$valor.'%');
                CoincidenciaFlexibleTexto::aplicar($q, 'rendicion_maquinavending_caja.codigo', $valor);
            });

            return $query;
        }

        $campo = (string) ($filtros['filtro_campo'] ?? 'codigo');
        if ($campo === 'maquinavending_rendicion_id' && ctype_digit($valor)) {
            $query->where('rendicion_maquinavending_caja.maquinavending_rendicion_id', (int) $valor);
        } elseif ($campo === 'fecharendicion') {
            $query->whereDate('rendicion_maquinavending_caja.fecharendicion', $valor);
        } else {
            $query->where('rendicion_maquinavending_caja.codigo', 'like', '%'.$valor.'%');
            CoincidenciaFlexibleTexto::aplicar($query, 'rendicion_maquinavending_caja.codigo', $valor);
        }

        return $query;
    }
}
