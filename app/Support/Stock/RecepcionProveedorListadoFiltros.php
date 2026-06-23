<?php

namespace App\Support\Stock;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RecepcionProveedorListadoFiltros
{
    public const CAMPOS = [
        'numerorecepcion' => ['etiqueta' => 'Nº recepción', 'tipo' => 'texto'],
        'numerofactura' => ['etiqueta' => 'Nº factura', 'tipo' => 'texto'],
        'numeroordencompra' => ['etiqueta' => 'Nº OC', 'tipo' => 'entero'],
        'nombreproveedor' => ['etiqueta' => 'Proveedor', 'tipo' => 'texto'],
        'nombreempresa' => ['etiqueta' => 'Empresa', 'tipo' => 'texto'],
        'estado' => ['etiqueta' => 'Estado', 'tipo' => 'texto'],
        'tipo' => ['etiqueta' => 'Tipo', 'tipo' => 'texto'],
    ];

    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'igual' => 'Igual a',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
    ];

    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'numerorecepcion', 'numerofactura', 'nombreproveedor', 'nombreempresa',
    ];

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = [
            'filtro_valor' => $request->input('filtro_valor'),
            'filtro_campo' => $request->input('filtro_campo'),
            'filtro_operador' => $request->input('filtro_operador'),
            'filtro_busqueda_rapida' => $request->boolean('filtro_busqueda_rapida'),
        ];

        if ($request->has('filtro_valor')) {
            return $filtros;
        }

        if ($busquedaRuta !== null && trim($busquedaRuta) !== '') {
            $filtros['filtro_valor'] = $busquedaRuta;
            $filtros['filtro_busqueda_rapida'] = true;
        }

        return $filtros;
    }

    public static function filtrosVacios(): array
    {
        return [
            'filtro_valor' => '',
            'filtro_campo' => '',
            'filtro_operador' => 'contiene',
            'filtro_busqueda_rapida' => false,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ($filtros['filtro_busqueda_rapida'] ?? false) {
            return trim((string) ($filtros['filtro_valor'] ?? '')) !== '';
        }

        $campo = (string) ($filtros['filtro_campo'] ?? '');
        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));

        return $campo !== '' && $valor !== '';
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'filtro_valor' => $filtros['filtro_valor'] ?? null,
            'filtro_campo' => $filtros['filtro_campo'] ?? null,
            'filtro_operador' => $filtros['filtro_operador'] ?? null,
            'filtro_busqueda_rapida' => ! empty($filtros['filtro_busqueda_rapida']) ? 1 : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public static function operadoresParaCampo(string $campo): array
    {
        $meta = self::CAMPOS[$campo] ?? null;
        if (! $meta) {
            return self::OPERADORES_TEXTO;
        }

        return ($meta['tipo'] ?? '') === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }

    public static function aplicar(Builder $query, array $filtros): void
    {
        if ($filtros['filtro_busqueda_rapida'] ?? false) {
            $valor = trim((string) ($filtros['filtro_valor'] ?? ''));
            if ($valor === '') {
                return;
            }
            $query->where(function ($q) use ($valor) {
                foreach (array_keys(self::CAMPOS) as $campo) {
                    self::aplicarCondicion($q, $campo, 'contiene', $valor, true);
                }
            });

            return;
        }

        $campo = (string) ($filtros['filtro_campo'] ?? '');
        $operador = (string) ($filtros['filtro_operador'] ?? 'contiene');
        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));

        if ($campo === '' || $valor === '') {
            return;
        }

        self::aplicarCondicion($query, $campo, $operador, $valor, false);
    }

    private static function aplicarCondicion(Builder $query, string $campo, string $operador, string $valor, bool $or): void
    {
        $col = match ($campo) {
            'numeroordencompra' => 'ordencompra.numeroordencompra',
            'nombreproveedor' => 'proveedor.nombre',
            'nombreempresa' => 'empresa.nombre',
            default => 'recepcion_proveedor.'.$campo,
        };

        $method = $or ? 'orWhere' : 'where';

        if ((self::CAMPOS[$campo]['tipo'] ?? '') === 'entero') {
            $query->{$method}($col, self::operadorSql($operador), (int) $valor);

            return;
        }

        if ($operador === 'contiene') {
            $query->{$method}(function ($q) use ($col, $campo, $valor) {
                $q->where($col, 'like', '%'.CoincidenciaFlexibleTexto::escapeLike($valor).'%');
                if (in_array($campo, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        false,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            });

            return;
        }

        $like = match ($operador) {
            'empieza' => $valor.'%',
            'termina' => '%'.$valor,
            default => $valor,
        };
        $query->{$method}($col, $operador === 'igual' ? '=' : 'like', $operador === 'igual' ? $valor : $like);
    }

    private static function operadorSql(string $operador): string
    {
        return match ($operador) {
            'mayor' => '>',
            'menor' => '<',
            default => '=',
        };
    }
}
