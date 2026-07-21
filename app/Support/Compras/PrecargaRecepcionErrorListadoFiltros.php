<?php

namespace App\Support\Compras;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de errores de recepción de precarga (API / PDF+IA).
 */
class PrecargaRecepcionErrorListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'precarga_comprobante_recepcion_error.id', 'type' => 'entero', 'label' => 'ID'],
        'origen' => ['column' => 'precarga_comprobante_recepcion_error.origen', 'type' => 'texto', 'label' => 'Origen'],
        'fase' => ['column' => 'precarga_comprobante_recepcion_error.fase', 'type' => 'texto', 'label' => 'Fase'],
        'mensaje' => ['column' => 'precarga_comprobante_recepcion_error.mensaje', 'type' => 'texto', 'label' => 'Mensaje'],
        'numero_oc' => ['column' => 'precarga_comprobante_recepcion_error.numero_oc', 'type' => 'texto', 'label' => 'Nº OC'],
        'cuit_proveedor' => ['column' => 'precarga_comprobante_recepcion_error.cuit_proveedor', 'type' => 'texto', 'label' => 'CUIT proveedor'],
        'cuit_empresa' => ['column' => 'precarga_comprobante_recepcion_error.cuit_empresa', 'type' => 'texto', 'label' => 'CUIT empresa'],
        'tipo_comprobante' => ['column' => 'precarga_comprobante_recepcion_error.tipo_comprobante', 'type' => 'texto', 'label' => 'Tipo'],
        'archivo_nombre' => ['column' => 'precarga_comprobante_recepcion_error.archivo_nombre', 'type' => 'texto', 'label' => 'Archivo'],
        'http_status' => ['column' => 'precarga_comprobante_recepcion_error.http_status', 'type' => 'entero', 'label' => 'HTTP'],
        'trace_id' => ['column' => 'precarga_comprobante_recepcion_error.trace_id', 'type' => 'texto', 'label' => 'Trace'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'precarga_comprobante_recepcion_error.mensaje',
        'precarga_comprobante_recepcion_error.archivo_nombre',
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

        $campo = (string) $request->input('filtro_campo', 'mensaje');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'mensaje';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'mensaje');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }
        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        return false;
    }

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'mensaje',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'mensaje';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        } elseif (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            $params['filtro_operador'] = $filtros['operador'];
        }
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Compras\Precarga_Comprobante_Recepcion_Error>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'mensaje', $operador, $valor);

            return;
        }

        if ($operador === 'vacio' || $valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('precarga_comprobante_recepcion_error.id', (int) $id)
                    ->orWhere('precarga_comprobante_recepcion_error.http_status', (int) $id);
            }

            foreach ([
                'precarga_comprobante_recepcion_error.origen',
                'precarga_comprobante_recepcion_error.fase',
                'precarga_comprobante_recepcion_error.mensaje',
                'precarga_comprobante_recepcion_error.numero_oc',
                'precarga_comprobante_recepcion_error.cuit_proveedor',
                'precarga_comprobante_recepcion_error.cuit_empresa',
                'precarga_comprobante_recepcion_error.tipo_comprobante',
                'precarga_comprobante_recepcion_error.archivo_nombre',
                'precarga_comprobante_recepcion_error.trace_id',
                'precarga_comprobante_recepcion_error.evento',
            ] as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene' && in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            }
        });
    }

    public static function operadoresParaCampo(string $campo): array
    {
        $tipo = self::CAMPOS[$campo]['type'] ?? 'texto';

        return $tipo === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }

    private static function normalizarOperador(string $operador, string $campo): string
    {
        $ops = self::operadoresParaCampo($campo);
        if (! isset($ops[$operador])) {
            return $campo === 'id' || $campo === 'http_status' ? 'igual' : 'contiene';
        }

        return $operador;
    }

    /**
     * @param  Builder<\App\Models\Compras\Precarga_Comprobante_Recepcion_Error>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campo, string $operador, string $valor): void
    {
        $meta = self::CAMPOS[$campo] ?? null;
        if ($meta === null) {
            return;
        }

        $col = $meta['column'];
        $tipo = $meta['type'];

        if ($operador === 'vacio') {
            $query->where(function ($q) use ($col) {
                $q->whereNull($col)->orWhere($col, '');
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        if ($tipo === 'entero') {
            $n = filter_var($valor, FILTER_VALIDATE_INT);
            if ($n === false) {
                return;
            }
            match ($operador) {
                'mayor' => $query->where($col, '>', (int) $n),
                'menor' => $query->where($col, '<', (int) $n),
                default => $query->where($col, (int) $n),
            };

            return;
        }

        $like = self::patronLike($operador, $valor);
        match ($operador) {
            'igual' => $query->where($col, $valor),
            'distinto' => $query->where($col, '!=', $valor),
            default => $query->where(function ($q) use ($col, $like, $operador, $valor) {
                $q->where($col, 'like', $like);
                if ($operador === 'contiene' && in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            }),
        };
    }

    private static function patronLike(string $operador, string $valor): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);

        return match ($operador) {
            'empieza' => $escaped.'%',
            'termina' => '%'.$escaped,
            'igual' => $escaped,
            default => '%'.$escaped.'%',
        };
    }
}
