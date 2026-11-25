<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ReporteSueldosDefinibleListadoFiltros
{
    public const CAMPOS = [
        'codigo' => 'Código',
        'titulo' => 'Título',
        'tipo' => 'Tipo',
        'origen' => 'Origen',
    ];

    public const OPERADORES_TEXTO = ['contiene', 'igual', 'empieza', 'termina'];

    public const OPERADORES_ENTERO = ['igual', 'mayor', 'menor'];

    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = ['titulo'];

    /**
     * @return array{filtro_campo:?string,filtro_operador:?string,filtro_valor:?string,filtro_busqueda_rapida:?string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'filtro_campo' => null,
            'filtro_operador' => null,
            'filtro_valor' => null,
            'filtro_busqueda_rapida' => null,
        ];
    }

    /**
     * @return array{filtro_campo:?string,filtro_operador:?string,filtro_valor:?string,filtro_busqueda_rapida:?string}
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = self::filtrosVacios();
        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida')
            || ($busquedaRuta !== null && $busquedaRuta !== '' && ! $request->has('filtro_valor'));

        if ($valor === '' && ! $request->filled('filtro_campo') && ! $busquedaRapida) {
            return $filtros;
        }

        $campo = (string) $request->input('filtro_campo', '');
        if ($busquedaRapida) {
            $campo = '';
        }

        $filtros['filtro_campo'] = $campo !== '' ? $campo : null;
        $filtros['filtro_operador'] = $request->input('filtro_operador', 'contiene');
        $filtros['filtro_valor'] = $valor !== '' ? $valor : null;
        $filtros['filtro_busqueda_rapida'] = ($busquedaRapida || $campo === '') ? '1' : null;

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));

        return $valor !== '' || ! empty($filtros['filtro_busqueda_rapida']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['filtro_campo', 'filtro_operador', 'filtro_valor', 'filtro_busqueda_rapida'] as $k) {
            if (($filtros[$k] ?? null) !== null && $filtros[$k] !== '') {
                $out[$k] = (string) $filtros[$k];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));
        $rapida = ! empty($filtros['filtro_busqueda_rapida']);
        $campo = (string) ($filtros['filtro_campo'] ?? '');
        $op = (string) ($filtros['filtro_operador'] ?? 'contiene');

        if ($rapida || $campo === '' || ! isset(self::CAMPOS[$campo])) {
            $query->where(function (Builder $q) use ($valor) {
                $q->where('titulo', 'like', '%'.$valor.'%')
                    ->orWhere('codigo', 'like', '%'.$valor.'%')
                    ->orWhere('tipo', 'like', '%'.$valor.'%')
                    ->orWhere('origen', 'like', '%'.$valor.'%');
                // Código entero: match exacto además del LIKE (búsqueda por nro de listado).
                if ($valor !== '' && ctype_digit($valor)) {
                    $q->orWhere('codigo', (int) $valor);
                }
                if (in_array('titulo', self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, 'titulo', $valor);
                }
            });

            return;
        }

        if ($campo === 'codigo') {
            $n = (int) $valor;
            match ($op) {
                'mayor' => $query->where('codigo', '>', $n),
                'menor' => $query->where('codigo', '<', $n),
                default => $query->where('codigo', $n),
            };

            return;
        }

        match ($op) {
            'igual' => $query->where($campo, $valor),
            'empieza' => $query->where($campo, 'like', $valor.'%'),
            'termina' => $query->where($campo, 'like', '%'.$valor),
            default => $query->where(function (Builder $q) use ($campo, $valor) {
                $q->where($campo, 'like', '%'.$valor.'%');
                if (in_array($campo, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $campo, $valor);
                }
            }),
        };
    }

    /**
     * @return list<string>
     */
    public static function operadoresParaCampo(string $campo): array
    {
        return $campo === 'codigo' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }
}
