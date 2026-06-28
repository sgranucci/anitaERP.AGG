<?php

namespace App\Support\Ventas;

use App\Support\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;

final class MaquinavendingRendicionListadoFiltros
{
    /** @var list<string> */
    public const CAMPOS = [
        'empresa_id',
        'maquinavending_id',
        'numero_cierre',
        'fecha_rendicion',
        'maquina_nombre',
        'puntoventa_codigo',
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'maquina_nombre',
        'puntoventa_codigo',
    ];

    public static function resolverDesdeRequest(\Illuminate\Http\Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = [
            'empresa_id' => $request->input('empresa_id'),
            'maquinavending_id' => $request->input('maquinavending_id'),
            'numero_cierre' => $request->input('numero_cierre'),
            'fecha_desde' => $request->input('fecha_desde'),
            'fecha_hasta' => $request->input('fecha_hasta'),
            'pendiente_caja' => $request->input('pendiente_caja'),
            'filtro_valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'filtro_busqueda_rapida' => $request->input('filtro_busqueda_rapida'),
            'filtro_campo' => $request->input('filtro_campo'),
            'filtro_operador' => $request->input('filtro_operador'),
        ];

        return $filtros;
    }

    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'empresa_id' => $filtros['empresa_id'] ?? null,
            'maquinavending_id' => $filtros['maquinavending_id'] ?? null,
            'numero_cierre' => $filtros['numero_cierre'] ?? null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'pendiente_caja' => $filtros['pendiente_caja'] ?? null,
            'filtro_valor' => $filtros['filtro_valor'] ?? null,
            'filtro_busqueda_rapida' => $filtros['filtro_busqueda_rapida'] ?? null,
            'filtro_campo' => $filtros['filtro_campo'] ?? null,
            'filtro_operador' => $filtros['filtro_operador'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            return true;
        }
        if ((int) ($filtros['maquinavending_id'] ?? 0) > 0) {
            return true;
        }
        if ((int) ($filtros['numero_cierre'] ?? 0) > 0) {
            return true;
        }
        if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta'])) {
            return true;
        }
        if ((string) ($filtros['pendiente_caja'] ?? '') === '1') {
            return true;
        }
        if (trim((string) ($filtros['filtro_valor'] ?? '')) !== '') {
            return true;
        }

        return false;
    }

    public static function aplicar(Builder $query, array $filtros): Builder
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('maquinavending_rendicion.empresa_id', $empresaId);
        }

        $maquinaId = (int) ($filtros['maquinavending_id'] ?? 0);
        if ($maquinaId > 0) {
            $query->where('maquinavending_rendicion.maquinavending_id', $maquinaId);
        }

        $numeroCierre = (int) ($filtros['numero_cierre'] ?? 0);
        if ($numeroCierre > 0) {
            $query->where('maquinavending_rendicion.numero_cierre', $numeroCierre);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('maquinavending_rendicion.fecha_rendicion', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('maquinavending_rendicion.fecha_rendicion', '<=', $filtros['fecha_hasta']);
        }

        if ((string) ($filtros['pendiente_caja'] ?? '') === '1') {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('rendicion_maquinavending_caja')
                    ->whereColumn(
                        'rendicion_maquinavending_caja.maquinavending_rendicion_id',
                        'maquinavending_rendicion.id'
                    );
            });
        }

        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));
        if ($valor !== '') {
            if (! empty($filtros['filtro_busqueda_rapida'])) {
                $query->where(function ($q) use ($valor) {
                    $q->where('maquinavending.nombre', 'like', '%'.$valor.'%')
                        ->orWhere('puntoventa.codigo', 'like', '%'.$valor.'%')
                        ->orWhere('maquinavending_rendicion.numero_cierre', 'like', '%'.$valor.'%');
                    CoincidenciaFlexibleTexto::aplicar($q, 'maquinavending.nombre', $valor);
                    CoincidenciaFlexibleTexto::aplicar($q, 'puntoventa.codigo', $valor);
                });
            } else {
                $campo = (string) ($filtros['filtro_campo'] ?? 'maquina_nombre');
                $operador = (string) ($filtros['filtro_operador'] ?? 'contiene');
                self::aplicarFiltroCampo($query, $campo, $operador, $valor);
            }
        }

        return $query;
    }

    private static function aplicarFiltroCampo(Builder $query, string $campo, string $operador, string $valor): void
    {
        $map = [
            'maquina_nombre' => 'maquinavending.nombre',
            'puntoventa_codigo' => 'puntoventa.codigo',
            'numero_cierre' => 'maquinavending_rendicion.numero_cierre',
        ];
        $columna = $map[$campo] ?? 'maquinavending.nombre';

        if ($operador === 'igual' && $campo === 'numero_cierre') {
            $query->where($columna, (int) $valor);

            return;
        }

        $query->where($columna, 'like', '%'.$valor.'%');
        if (in_array($campo, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
            CoincidenciaFlexibleTexto::aplicar($query, $columna, $valor);
        }
    }
}
