<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de cambios/devoluciones marketplace (Facturación Local).
 */
class CambioDevolucionMarketplaceListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'numero' => ['column' => 'cambio_devolucion_marketplace.numero', 'type' => 'entero', 'label' => 'Nº legajo'],
        'estado' => ['column' => 'cambio_devolucion_marketplace.estado', 'type' => 'texto', 'label' => 'Estado'],
        'canal' => ['column' => 'cambio_devolucion_marketplace.canal', 'type' => 'texto', 'label' => 'Canal'],
        'receptor_nombre' => ['column' => 'cambio_devolucion_marketplace.receptor_nombre', 'type' => 'texto', 'label' => 'Receptor'],
        'receptor_documento' => ['column' => 'cambio_devolucion_marketplace.receptor_documento', 'type' => 'texto', 'label' => 'Documento'],
        'observacion' => ['column' => 'cambio_devolucion_marketplace.observacion', 'type' => 'texto', 'label' => 'Observación'],
        'local' => ['column' => 'local_venta.nombre', 'type' => 'texto', 'label' => 'Local'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'cambio_devolucion_marketplace.receptor_nombre',
        'cambio_devolucion_marketplace.observacion',
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
        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'receptor_nombre');

        $estado = trim((string) $request->input('filtro_estado', ''));
        if ($estado !== '' && ! CambioDevolucionMarketplaceEstadosSupport::esValido($estado)) {
            $estado = '';
        }

        $canal = trim((string) $request->input('filtro_canal', ''));
        $localId = (int) $request->input('filtro_local_venta_id', 0);
        $soloPendientes = $request->boolean('filtro_solo_pendientes');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'estado' => $estado,
            'canal' => $canal,
            'local_venta_id' => $localId > 0 ? $localId : 0,
            'solo_pendientes' => $soloPendientes,
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
            'canal' => '',
            'local_venta_id' => 0,
            'solo_pendientes' => false,
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
        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['canal'] ?? '')) !== '') {
            return true;
        }
        if ((int) ($filtros['local_venta_id'] ?? 0) > 0) {
            return true;
        }
        if (! empty($filtros['solo_pendientes'])) {
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

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $out['filtro_valor'] = $filtros['valor'];
        }
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            $out['filtro_modo'] = self::MODO_CAMPO;
            $out['filtro_campo'] = $filtros['campo'] ?? 'numero';
            $out['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        }
        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            $out['filtro_estado'] = $filtros['estado'];
        }
        if (trim((string) ($filtros['canal'] ?? '')) !== '') {
            $out['filtro_canal'] = $filtros['canal'];
        }
        if ((int) ($filtros['local_venta_id'] ?? 0) > 0) {
            $out['filtro_local_venta_id'] = (int) $filtros['local_venta_id'];
        }
        if (! empty($filtros['solo_pendientes'])) {
            $out['filtro_solo_pendientes'] = 1;
        }

        return $out;
    }

    public static function aplicar(Builder $query, array $filtros): Builder
    {
        if (! empty($filtros['solo_pendientes'])) {
            $query->whereNotIn('cambio_devolucion_marketplace.estado', [
                CambioDevolucionMarketplaceEstadosSupport::CERRADO,
                CambioDevolucionMarketplaceEstadosSupport::ANULADO,
            ]);
        }

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('cambio_devolucion_marketplace.estado', $estado);
        }

        $canal = trim((string) ($filtros['canal'] ?? ''));
        if ($canal !== '') {
            $query->where('cambio_devolucion_marketplace.canal', $canal);
        }

        $localId = (int) ($filtros['local_venta_id'] ?? 0);
        if ($localId > 0) {
            $query->where('cambio_devolucion_marketplace.local_venta_id', $localId);
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return $query;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            $campo = $filtros['campo'] ?? 'numero';
            $meta = self::CAMPOS[$campo] ?? null;
            if ($meta === null) {
                return $query;
            }
            self::aplicarOperadorColumna($query, $meta['column'], $meta['type'], $operador, $valor);

            return $query;
        }

        if ($valor === '') {
            return $query;
        }

        $query->where(function (Builder $q) use ($valor) {
            foreach (self::CAMPOS as $meta) {
                $col = $meta['column'];
                if ($meta['type'] === 'entero') {
                    if (ctype_digit($valor)) {
                        $q->orWhere($col, (int) $valor);
                    }

                    continue;
                }
                $q->orWhere($col, 'like', '%'.$valor.'%');
                if (in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $col, $valor, true);
                }
            }
        });

        return $query;
    }

    private static function tieneCriteriosTexto(array $filtros): bool
    {
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }

        return trim((string) ($filtros['valor'] ?? '')) !== '';
    }

    private static function normalizarOperador(string $operador, string $campo): string
    {
        $tipo = self::CAMPOS[$campo]['type'] ?? 'texto';
        $permitidos = $tipo === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
        if (! isset($permitidos[$operador])) {
            return $tipo === 'entero' ? 'igual' : 'contiene';
        }

        return $operador;
    }

    private static function aplicarOperadorColumna(
        Builder $query,
        string $columna,
        string $tipo,
        string $operador,
        string $valor
    ): void {
        if ($operador === 'vacio') {
            $query->where(function (Builder $q) use ($columna) {
                $q->whereNull($columna)->orWhere($columna, '');
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        if ($tipo === 'entero') {
            $n = (int) $valor;
            match ($operador) {
                'mayor' => $query->where($columna, '>', $n),
                'menor' => $query->where($columna, '<', $n),
                default => $query->where($columna, $n),
            };

            return;
        }

        match ($operador) {
            'empieza' => $query->where($columna, 'like', $valor.'%'),
            'termina' => $query->where($columna, 'like', '%'.$valor),
            'igual' => $query->where($columna, $valor),
            'distinto' => $query->where($columna, '!=', $valor),
            default => (function () use ($query, $columna, $valor) {
                $query->where(function (Builder $q) use ($columna, $valor) {
                    $q->where($columna, 'like', '%'.$valor.'%');
                    if (in_array($columna, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                        CoincidenciaFlexibleTexto::aplicar($q, $columna, $valor, true);
                    }
                });
            })(),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campo): array
    {
        $tipo = self::CAMPOS[$campo]['type'] ?? 'texto';

        return $tipo === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }
}
