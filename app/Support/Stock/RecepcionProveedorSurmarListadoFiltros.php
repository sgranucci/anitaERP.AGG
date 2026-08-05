<?php

namespace App\Support\Stock;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class RecepcionProveedorSurmarListadoFiltros
{
    public const CAMPOS = [
        'numerorecepcion' => ['label' => 'Nº recepción', 'tipo' => 'entero'],
        'nombreproveedor' => ['label' => 'Proveedor', 'tipo' => 'texto'],
        'estado' => ['label' => 'Estado', 'tipo' => 'texto'],
        'fecha' => ['label' => 'Fecha', 'tipo' => 'texto'],
    ];

    public const OPERADORES_TEXTO = ['contiene', 'igual', 'empieza'];

    public const OPERADORES_ENTERO = ['igual', 'mayor', 'menor'];

    public const COLUMNAS_COINCIDENCIA_FLEXIBLE = ['nombreproveedor'];

    /** @return array<string, mixed> */
    public static function filtrosVacios(): array
    {
        return [
            'filtro_valor' => '',
            'filtro_campo' => '',
            'filtro_operador' => 'contiene',
            'filtro_busqueda_rapida' => false,
            'estado' => '',
        ];
    }

    /** @return array<string, mixed> */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $base = self::filtrosVacios();
        $base['filtro_valor'] = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $base['filtro_campo'] = (string) $request->input('filtro_campo', '');
        $base['filtro_operador'] = (string) $request->input('filtro_operador', 'contiene');
        $base['filtro_busqueda_rapida'] = (string) $request->input('filtro_busqueda_rapida', '') === '1';
        $base['estado'] = (string) $request->input('estado', '');

        return $base;
    }

    /** @param array<string, mixed> $filtros */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['filtro_valor'] ?? '')) !== '') {
            return true;
        }

        return trim((string) ($filtros['estado'] ?? '')) !== '';
    }

    /** @param array<string, mixed> $filtros @return array<string, string> */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if (trim((string) ($filtros['filtro_valor'] ?? '')) !== '') {
            $out['filtro_valor'] = (string) $filtros['filtro_valor'];
        }
        if (! empty($filtros['filtro_campo'])) {
            $out['filtro_campo'] = (string) $filtros['filtro_campo'];
        }
        if (! empty($filtros['filtro_operador']) && $filtros['filtro_operador'] !== 'contiene') {
            $out['filtro_operador'] = (string) $filtros['filtro_operador'];
        }
        if (! empty($filtros['filtro_busqueda_rapida'])) {
            $out['filtro_busqueda_rapida'] = '1';
        }
        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            $out['estado'] = (string) $filtros['estado'];
        }

        return $out;
    }

    /** @param array<string, mixed> $filtros */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return;
        }

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('recepcion_proveedor.estado', $estado);
        }

        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));
        if ($valor === '') {
            return;
        }

        $campo = (string) ($filtros['filtro_campo'] ?? '');
        $rapida = ! empty($filtros['filtro_busqueda_rapida']) || $campo === '';

        if ($rapida) {
            $query->where(function (Builder $q) use ($valor) {
                $q->where('recepcion_proveedor.numerorecepcion', 'like', '%'.$valor.'%')
                    ->orWhere('proveedor.nombre', 'like', '%'.$valor.'%')
                    ->orWhere('recepcion_proveedor.estado', 'like', '%'.$valor.'%');
                CoincidenciaFlexibleTexto::aplicar($q, 'proveedor.nombre', $valor);
            });

            return;
        }

        if ($campo === 'numerorecepcion') {
            $query->where('recepcion_proveedor.numerorecepcion', 'like', '%'.$valor.'%');
        } elseif ($campo === 'nombreproveedor') {
            $query->where(function (Builder $q) use ($valor) {
                $q->where('proveedor.nombre', 'like', '%'.$valor.'%');
                CoincidenciaFlexibleTexto::aplicar($q, 'proveedor.nombre', $valor);
            });
        } elseif ($campo === 'estado') {
            $query->where('recepcion_proveedor.estado', 'like', '%'.$valor.'%');
        } elseif ($campo === 'fecha') {
            $query->where('recepcion_proveedor.fecha', 'like', '%'.$valor.'%');
        }
    }

    /** @return array<string, array{label:string,tipo:string}> */
    public static function camposFiltro(): array
    {
        return self::CAMPOS;
    }
}
