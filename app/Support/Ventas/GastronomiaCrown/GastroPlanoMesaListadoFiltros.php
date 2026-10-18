<?php

namespace App\Support\Ventas\GastronomiaCrown;

use App\Models\Ventas\GastronomiaCrown\GastroPlanoMesa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Configuracion\EmpresaFiltroExternoSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GastroPlanoMesaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'gastro_plano_mesa.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'gastro_plano_mesa.codigo', 'type' => 'texto', 'label' => 'Código'],
        'nombre' => ['column' => 'gastro_plano_mesa.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'sala' => ['column' => 'sala.nombre', 'type' => 'texto', 'label' => 'Sala'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'gastro_plano_mesa.nombre',
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
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'sala_id' => null,
            'sala_scope' => 'todas',
        ];
    }

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        [$salaId, $salaScope] = self::resolverSalaExterna($request);
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request);

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
            'busqueda' => $valor,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'sala_id' => $salaId,
            'sala_scope' => $salaScope,
        ];
    }

    /**
     * @return array{0: ?int, 1: string}
     */
    public static function resolverEmpresaExterna(Request $request): array
    {
        if ($request->boolean('empresa_todas') || $request->input('empresa_todas') === '1'
            || $request->input('empresa_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [(int) $request->input('empresa_id'), 'una'];
        }
        $default = EmpresaFiltroExternoSupport::empresaDefaultDesdeSesion();
        if ($default !== null) {
            return [$default, 'una'];
        }

        return [null, 'todas'];
    }

    /**
     * @return array{0: ?int, 1: string}
     */
    public static function resolverSalaExterna(Request $request): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return [null, 'todas'];
        }
        if ($request->boolean('sala_todas') || $request->input('sala_todas') === '1') {
            return [null, 'todas'];
        }
        if ($request->filled('sala_id')) {
            return [(int) $request->input('sala_id'), 'una'];
        }

        return [null, 'todas'];
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

    /**
     * Query base del listado (joins + filtros + empresas asignadas), compartida por index y exportaciones.
     *
     * @return Builder<\App\Models\Ventas\GastronomiaCrown\GastroPlanoMesa>
     */
    public static function consulta(array $filtros): Builder
    {
        $query = GastroPlanoMesa::query()
            ->select('gastro_plano_mesa.*')
            ->leftJoin('sala', 'sala.id', '=', 'gastro_plano_mesa.sala_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'gastro_plano_mesa.empresa_id')
            ->with(['empresa:id,nombre', 'sala:id,codigo,nombre']);

        app(EmpresaRepositoryInterface::class)
            ->aplicarFiltroEmpresasAsignadas($query, 'gastro_plano_mesa.empresa_id');

        self::aplicar($query, $filtros);

        return $query
            ->orderBy('gastro_plano_mesa.orden')
            ->orderBy('gastro_plano_mesa.nombre');
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
            $query->where('gastro_plano_mesa.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['sala_id'])) {
            $query->where('gastro_plano_mesa.sala_id', (int) $filtros['sala_id']);
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
