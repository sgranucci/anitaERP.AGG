<?php

namespace App\Support\Caja;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class TicketCanjeCajaListadoFiltros
{
    public const CAMPOS = [
        'nro_documento' => ['etiqueta' => 'Documento', 'tipo' => 'texto'],
        'nombre_cliente' => ['etiqueta' => 'Cliente', 'tipo' => 'texto'],
        'movimiento_id' => ['etiqueta' => 'Movimiento', 'tipo' => 'entero'],
        'numero_ticket' => ['etiqueta' => 'Nro. ticket', 'tipo' => 'entero'],
        'estado' => ['etiqueta' => 'Estado', 'tipo' => 'texto'],
        'es_vip' => ['etiqueta' => 'VIP', 'tipo' => 'texto'],
        'fecha' => ['etiqueta' => 'Fecha', 'tipo' => 'texto'],
        'monto_ticket' => ['etiqueta' => 'Monto ticket', 'tipo' => 'texto'],
        'empresa_id' => ['etiqueta' => 'Empresa', 'tipo' => 'entero'],
    ];

    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene',
        'igual' => 'Igual a',
        'empieza' => 'Empieza con',
    ];

    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'nro_documento',
        'nombre_cliente',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'filtro_campo' => '',
            'filtro_operador' => 'contiene',
            'filtro_valor' => '',
            'filtro_busqueda_rapida' => false,
            'empresa_id' => null,
            'solo_propios' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, $busquedaLegacy = null): array
    {
        $filtros = self::filtrosVacios();
        $filtros['filtro_campo'] = trim((string) $request->input('filtro_campo', ''));
        $filtros['filtro_operador'] = trim((string) $request->input('filtro_operador', 'contiene'));
        $filtros['filtro_valor'] = FiltrosListadoRequest::valorBusqueda($request, $busquedaLegacy);
        $filtros['filtro_busqueda_rapida'] = $request->input('filtro_busqueda_rapida') == '1'
            || $request->boolean('filtro_busqueda_rapida');
        $empresaId = (int) $request->input('empresa_id', 0);
        $filtros['empresa_id'] = $empresaId > 0 ? $empresaId : null;
        $filtros['solo_propios'] = ! $request->boolean('ver_todos');

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if (! empty($filtros['filtro_campo'])) {
            $out['filtro_campo'] = $filtros['filtro_campo'];
        }
        if (! empty($filtros['filtro_operador'])) {
            $out['filtro_operador'] = $filtros['filtro_operador'];
        }
        if (($filtros['filtro_valor'] ?? '') !== '' && $filtros['filtro_valor'] !== null) {
            $out['filtro_valor'] = $filtros['filtro_valor'];
        }
        if (! empty($filtros['filtro_busqueda_rapida'])) {
            $out['filtro_busqueda_rapida'] = 1;
        }
        if (! empty($filtros['empresa_id'])) {
            $out['empresa_id'] = $filtros['empresa_id'];
        }
        if (empty($filtros['solo_propios'])) {
            $out['ver_todos'] = 1;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (($filtros['filtro_valor'] ?? '') !== '' && $filtros['filtro_valor'] !== null) {
            return true;
        }

        return ! empty($filtros['empresa_id']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! self::tieneCriteriosAplicados($filtros) && empty($filtros['filtro_busqueda_rapida'])) {
            return;
        }

        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));
        if ($valor === '') {
            return;
        }

        if (! empty($filtros['filtro_busqueda_rapida']) || empty($filtros['filtro_campo'])) {
            $query->where(function (Builder $q) use ($valor) {
                $q->where('nro_documento', 'like', '%'.$valor.'%')
                    ->orWhere('nombre_cliente', 'like', '%'.$valor.'%')
                    ->orWhere('movimiento_id', 'like', '%'.$valor.'%')
                    ->orWhere('numero_ticket', 'like', '%'.$valor.'%');
                CoincidenciaFlexibleTexto::aplicar($q, 'nro_documento', $valor);
                CoincidenciaFlexibleTexto::aplicar($q, 'nombre_cliente', $valor);
            });

            return;
        }

        $campo = (string) $filtros['filtro_campo'];
        if (! isset(self::CAMPOS[$campo])) {
            return;
        }

        $operador = (string) ($filtros['filtro_operador'] ?? 'contiene');
        $tipo = self::CAMPOS[$campo]['tipo'];

        if ($tipo === 'entero') {
            $n = (int) $valor;
            match ($operador) {
                'mayor' => $query->where($campo, '>', $n),
                'menor' => $query->where($campo, '<', $n),
                default => $query->where($campo, $n),
            };

            return;
        }

        match ($operador) {
            'igual' => $query->where($campo, $valor),
            'empieza' => $query->where($campo, 'like', $valor.'%'),
            default => $query->where(function (Builder $q) use ($campo, $valor) {
                $q->where($campo, 'like', '%'.$valor.'%');
                if (in_array($campo, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $campo, $valor);
                }
            }),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campo): array
    {
        $tipo = self::CAMPOS[$campo]['tipo'] ?? 'texto';

        return $tipo === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }
}
