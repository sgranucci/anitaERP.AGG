<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MozoGastronomiaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'mozo_gastronomia.id', 'type' => 'entero', 'label' => 'ID'],
        'nombre' => ['column' => 'mozo_gastronomia.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'codigo' => ['column' => 'mozo_gastronomia.codigo', 'type' => 'texto', 'label' => 'Código Anita'],
        'sala' => ['column' => 'sala.nombre', 'type' => 'texto', 'label' => 'Sala'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'mozo_gastronomia.nombre',
        'sala.nombre',
        'empresa.nombre',
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'sala_id' => null,
            'sala_scope' => 'todas',
        ];
    }

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        [$salaId, $salaScope] = MesaGastronomiaListadoFiltros::resolverSalaExterna($request);
        [$empresaId, $empresaScope] = MesaGastronomiaListadoFiltros::resolverEmpresaExterna($request);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
                'sala_id' => $salaId,
                'sala_scope' => $salaScope,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');
        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }
        $campo = (string) $request->input('filtro_campo', 'nombre');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'nombre';
        }
        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => (string) $request->input('filtro_valor_hasta', ''),
            'busqueda' => $valor,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'sala_id' => $salaId,
            'sala_scope' => $salaScope,
        ];
    }

    public static function paraQueryString(array $filtros): array
    {
        $q = [];
        if (($filtros['valor'] ?? '') !== '') {
            $q['filtro_valor'] = $filtros['valor'];
            $q['filtro_modo'] = $filtros['modo'] ?? self::MODO_TODOS;
            $q['filtro_campo'] = $filtros['campo'] ?? 'nombre';
            $q['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        }
        if (EntornoEmpresaSupport::esCrown()) {
            if (($filtros['sala_scope'] ?? 'todas') === 'todas') {
                $q['sala_todas'] = 1;
            } elseif (! empty($filtros['sala_id'])) {
                $q['sala_id'] = (int) $filtros['sala_id'];
            }
        }
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            $q['empresa_todas'] = 1;
        } elseif (! empty($filtros['empresa_id'])) {
            $q['empresa_id'] = (int) $filtros['empresa_id'];
        }

        return $q;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['valor'] ?? '')) !== '';
    }

    public static function tieneCriteriosTexto(array $filtros): bool
    {
        return self::tieneCriteriosAplicados($filtros);
    }

    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('mozo_gastronomia.empresa_id', (int) $filtros['empresa_id']);
        }

        if (! self::tieneCriteriosAplicados($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            $campo = $filtros['campo'] ?? 'nombre';
            $meta = self::CAMPOS[$campo] ?? self::CAMPOS['nombre'];
            self::aplicarOperador($query, $meta['column'], $meta['type'], $operador, $valor);

            return;
        }

        $query->where(function (Builder $q) use ($valor, $operador) {
            foreach (self::CAMPOS as $meta) {
                $q->orWhere(function (Builder $qq) use ($meta, $operador, $valor) {
                    self::aplicarOperador($qq, $meta['column'], $meta['type'], $operador, $valor);
                });
            }
        });
    }

    private static function aplicarOperador(Builder $query, string $column, string $type, string $operador, string $valor): void
    {
        if ($type === 'entero') {
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
            default => tap($query->where($column, 'like', '%'.$valor.'%'), function () use ($query, $column, $valor) {
                if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($query, $column, $valor);
                }
            }),
        };
    }

    public static function operadoresParaCampo(string $campo): array
    {
        $type = self::CAMPOS[$campo]['type'] ?? 'texto';

        return $type === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }
}
