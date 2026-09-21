<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de remitos internos (Facturación Local).
 */
class RemitoInternoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'numero' => ['column' => 'remito_interno.numero', 'type' => 'entero', 'label' => 'Nº remito'],
        'estado' => ['column' => 'remito_interno.estado', 'type' => 'texto', 'label' => 'Estado'],
        'destinatario' => ['column' => 'remito_interno.destinatario', 'type' => 'texto', 'label' => 'Destinatario'],
        'leyenda' => ['column' => 'remito_interno.leyenda', 'type' => 'texto', 'label' => 'Leyenda'],
        'local' => ['column' => 'local_venta.nombre', 'type' => 'texto', 'label' => 'Local'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'remito_interno.destinatario',
        'remito_interno.leyenda',
        'local_venta.nombre',
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'vacio' => 'Vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'numero');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'numero';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }
        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'destinatario');

        $estado = trim((string) $request->input('filtro_estado', ''));
        if ($estado !== '' && ! RemitoInternoEstadosSupport::esValido($estado)) {
            $estado = '';
        }

        $localId = (int) $request->input('filtro_local_venta_id', 0);

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'estado' => $estado,
            'local_venta_id' => $localId > 0 ? $localId : 0,
        ];
    }

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'numero',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'busqueda_rapida' => false,
            'estado' => '',
            'local_venta_id' => 0,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            return true;
        }
        if ((int) ($filtros['local_venta_id'] ?? 0) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $out['filtro_valor'] = $valor;
            $out['filtro_modo'] = (string) ($filtros['modo'] ?? self::MODO_TODOS);
            $out['filtro_campo'] = (string) ($filtros['campo'] ?? 'numero');
            $out['filtro_operador'] = (string) ($filtros['operador'] ?? 'contiene');
            if (! empty($filtros['busqueda_rapida'])) {
                $out['filtro_busqueda_rapida'] = 1;
            }
        }
        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $out['filtro_estado'] = $estado;
        }
        $localId = (int) ($filtros['local_venta_id'] ?? 0);
        if ($localId > 0) {
            $out['filtro_local_venta_id'] = $localId;
        }

        return $out;
    }

    public static function aplicar(Builder $query, array $filtros): Builder
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return $query;
        }

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('remito_interno.estado', $estado);
        }

        $localId = (int) ($filtros['local_venta_id'] ?? 0);
        if ($localId > 0) {
            $query->where('remito_interno.local_venta_id', $localId);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return $query;
        }

        $modo = (string) ($filtros['modo'] ?? self::MODO_TODOS);
        $operador = (string) ($filtros['operador'] ?? 'contiene');

        if ($modo === self::MODO_CAMPO) {
            $campo = (string) ($filtros['campo'] ?? 'numero');
            $meta = self::CAMPOS[$campo] ?? self::CAMPOS['numero'];
            self::aplicarOperadorCampo($query, $meta['column'], $meta['type'], $operador, $valor);

            return $query;
        }

        $query->where(function (Builder $q) use ($valor) {
            foreach (self::CAMPOS as $meta) {
                $q->orWhere(function (Builder $sub) use ($meta, $valor) {
                    self::aplicarOperadorCampo($sub, $meta['column'], $meta['type'], 'contiene', $valor);
                });
            }
        });

        return $query;
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campo): array
    {
        $meta = self::CAMPOS[$campo] ?? null;
        if ($meta && ($meta['type'] ?? '') === 'entero') {
            return self::OPERADORES_ENTERO;
        }

        return self::OPERADORES_TEXTO;
    }

    private static function normalizarOperador(string $operador, string $campo): string
    {
        $ops = self::operadoresParaCampo($campo);
        if (! isset($ops[$operador])) {
            return ($campo === 'numero' || (self::CAMPOS[$campo]['type'] ?? '') === 'entero')
                ? 'igual'
                : 'contiene';
        }

        return $operador;
    }

    private static function aplicarOperadorCampo(
        Builder $query,
        string $column,
        string $type,
        string $operador,
        string $valor
    ): void {
        if ($type === 'entero') {
            if (! is_numeric($valor)) {
                $query->whereRaw('1 = 0');

                return;
            }
            $n = (int) $valor;
            match ($operador) {
                'mayor' => $query->where($column, '>', $n),
                'menor' => $query->where($column, '<', $n),
                default => $query->where($column, $n),
            };

            return;
        }

        match ($operador) {
            'empieza' => $query->where($column, 'like', $valor.'%'),
            'termina' => $query->where($column, 'like', '%'.$valor),
            'igual' => $query->where($column, $valor),
            'distinto' => $query->where($column, '<>', $valor),
            'vacio' => $query->where(function (Builder $q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            }),
            default => self::aplicarContiene($query, $column, $valor),
        };
    }

    private static function aplicarContiene(Builder $query, string $column, string $valor): void
    {
        $query->where(function (Builder $q) use ($column, $valor) {
            $q->where($column, 'like', '%'.$valor.'%');
            if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                CoincidenciaFlexibleTexto::aplicar($q, $column, $valor);
            }
        });
    }
}
