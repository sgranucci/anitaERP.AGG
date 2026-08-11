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
        'numeroordencompra' => ['label' => 'Nº OC', 'tipo' => 'entero'],
        'nombreproveedor' => ['label' => 'Proveedor', 'tipo' => 'texto'],
        'estado' => ['label' => 'Estado', 'tipo' => 'texto'],
        'origen_carga' => ['label' => 'Origen', 'tipo' => 'texto'],
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
            'origen_carga' => '',
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
        $base['origen_carga'] = (string) $request->input('origen_carga', '');

        return $base;
    }

    /** @param array<string, mixed> $filtros */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['filtro_valor'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            return true;
        }

        return trim((string) ($filtros['origen_carga'] ?? '')) !== '';
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
        if (trim((string) ($filtros['origen_carga'] ?? '')) !== '') {
            $out['origen_carga'] = (string) $filtros['origen_carga'];
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

        $origen = trim((string) ($filtros['origen_carga'] ?? ''));
        if ($origen !== '') {
            $query->where('recepcion_proveedor.origen_carga', $origen);
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
                    ->orWhere('ordencompra.numeroordencompra', 'like', '%'.$valor.'%')
                    ->orWhere('proveedor.nombre', 'like', '%'.$valor.'%')
                    ->orWhere('recepcion_proveedor.estado', 'like', '%'.$valor.'%')
                    ->orWhere('recepcion_proveedor.origen_carga', 'like', '%'.$valor.'%');
                if (ctype_digit($valor)) {
                    $q->orWhere('ordencompra.numeroordencompra', (int) $valor)
                        ->orWhere('recepcion_proveedor.anita_nro', (int) $valor);
                }
                CoincidenciaFlexibleTexto::aplicar($q, 'proveedor.nombre', $valor);
            });

            return;
        }

        if ($campo === 'numerorecepcion') {
            $query->where('recepcion_proveedor.numerorecepcion', 'like', '%'.$valor.'%');
        } elseif ($campo === 'numeroordencompra') {
            if (ctype_digit($valor)) {
                $query->where('ordencompra.numeroordencompra', (int) $valor);
            } else {
                $query->where('ordencompra.numeroordencompra', 'like', '%'.$valor.'%');
            }
        } elseif ($campo === 'nombreproveedor') {
            $query->where(function (Builder $q) use ($valor) {
                $q->where('proveedor.nombre', 'like', '%'.$valor.'%');
                CoincidenciaFlexibleTexto::aplicar($q, 'proveedor.nombre', $valor);
            });
        } elseif ($campo === 'estado') {
            $query->where('recepcion_proveedor.estado', 'like', '%'.$valor.'%');
        } elseif ($campo === 'origen_carga') {
            $query->where('recepcion_proveedor.origen_carga', 'like', '%'.$valor.'%');
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
