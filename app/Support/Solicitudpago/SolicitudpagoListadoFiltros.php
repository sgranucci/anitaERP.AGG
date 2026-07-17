<?php

namespace App\Support\Solicitudpago;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Http\Request;

final class SolicitudpagoListadoFiltros
{
    public const CAMPOS = [
        'codigo' => ['etiqueta' => 'Código', 'tipo' => 'entero', 'columna' => 'solicitudpago.codigo'],
        'detalle' => ['etiqueta' => 'Detalle', 'tipo' => 'texto', 'columna' => 'solicitudpago.detalle'],
        'beneficiario' => ['etiqueta' => 'Beneficiario', 'tipo' => 'texto', 'columna' => 'solicitudpago.beneficiario'],
        'estado' => ['etiqueta' => 'Estado', 'tipo' => 'texto', 'columna' => 'solicitudpago.estado'],
        'tratamiento' => ['etiqueta' => 'Tratamiento', 'tipo' => 'texto', 'columna' => 'solicitudpago.tratamiento'],
        'fecha' => ['etiqueta' => 'Fecha', 'tipo' => 'texto', 'columna' => 'solicitudpago.fecha'],
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
        'mayor_igual' => 'Mayor o igual',
        'menor_igual' => 'Menor o igual',
    ];

    public const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'solicitudpago.detalle',
        'solicitudpago.beneficiario',
        'solicitudpago.observacion',
    ];

    /**
     * @return array{campo: ?string, operador: string, valor: string, busqueda_rapida: bool, madre_hija: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'campo' => null,
            'operador' => 'contiene',
            'valor' => '',
            'busqueda_rapida' => false,
            'madre_hija' => '',
            'estado' => '',
            'tratamiento' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
        ];
    }

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = self::filtrosVacios();
        $filtros['valor'] = (string) FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $filtros['campo'] = $request->input('filtro_campo') ?: null;
        $filtros['operador'] = (string) ($request->input('filtro_operador') ?: 'contiene');
        $filtros['busqueda_rapida'] = (string) $request->input('filtro_busqueda_rapida', '') === '1';
        $filtros['madre_hija'] = (string) $request->input('madre_hija', '');
        $filtros['estado'] = (string) $request->input('estado', '');
        $filtros['tratamiento'] = (string) $request->input('tratamiento', '');
        $filtros['fecha_desde'] = (string) $request->input('fecha_desde', '');
        $filtros['fecha_hasta'] = (string) $request->input('fecha_hasta', '');

        return $filtros;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        foreach (['madre_hija', 'estado', 'tratamiento', 'fecha_desde', 'fecha_hasta'] as $k) {
            if (trim((string) ($filtros[$k] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $out['filtro_valor'] = $filtros['valor'];
        }
        if (! empty($filtros['campo'])) {
            $out['filtro_campo'] = $filtros['campo'];
        }
        if (! empty($filtros['operador']) && $filtros['operador'] !== 'contiene') {
            $out['filtro_operador'] = $filtros['operador'];
        }
        if (! empty($filtros['busqueda_rapida'])) {
            $out['filtro_busqueda_rapida'] = 1;
        }
        foreach (['madre_hija', 'estado', 'tratamiento', 'fecha_desde', 'fecha_hasta'] as $k) {
            if (trim((string) ($filtros[$k] ?? '')) !== '') {
                $out[$k] = $filtros[$k];
            }
        }

        return $out;
    }

    public static function aplicar($query, array $filtros)
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return $query;
        }

        if (($filtros['estado'] ?? '') !== '') {
            $query->where('solicitudpago.estado', $filtros['estado']);
        }
        if (($filtros['tratamiento'] ?? '') !== '') {
            $query->where('solicitudpago.tratamiento', $filtros['tratamiento']);
        }
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $query->whereDate('solicitudpago.fecha', '>=', $filtros['fecha_desde']);
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $query->whereDate('solicitudpago.fecha', '<=', $filtros['fecha_hasta']);
        }
        if (($filtros['madre_hija'] ?? '') === 'madres') {
            $query->whereNull('solicitudpago.solicitudpago_madre_id');
        } elseif (($filtros['madre_hija'] ?? '') === 'hijas') {
            $query->whereNotNull('solicitudpago.solicitudpago_madre_id');
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return $query;
        }

        if (! empty($filtros['busqueda_rapida']) || empty($filtros['campo'])) {
            $query->where(function ($q) use ($valor) {
                $q->where('solicitudpago.codigo', 'like', '%'.$valor.'%')
                    ->orWhere('solicitudpago.detalle', 'like', '%'.$valor.'%')
                    ->orWhere('solicitudpago.beneficiario', 'like', '%'.$valor.'%')
                    ->orWhere('solicitudpago.observacion', 'like', '%'.$valor.'%');
                CoincidenciaFlexibleTexto::aplicar($q, 'solicitudpago.detalle', $valor);
            });

            return $query;
        }

        $campo = self::CAMPOS[$filtros['campo']] ?? null;
        if ($campo === null) {
            return $query;
        }

        $col = $campo['columna'];
        $op = $filtros['operador'] ?? 'contiene';
        if (($campo['tipo'] ?? '') === 'entero') {
            $n = (int) $valor;
            match ($op) {
                'mayor' => $query->where($col, '>', $n),
                'menor' => $query->where($col, '<', $n),
                'mayor_igual' => $query->where($col, '>=', $n),
                'menor_igual' => $query->where($col, '<=', $n),
                default => $query->where($col, $n),
            };

            return $query;
        }

        match ($op) {
            'igual' => $query->where($col, $valor),
            'empieza' => $query->where($col, 'like', $valor.'%'),
            'termina' => $query->where($col, 'like', '%'.$valor),
            default => $query->where(function ($q) use ($col, $valor) {
                $q->where($col, 'like', '%'.$valor.'%');
                if (in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $col, $valor);
                }
            }),
        };

        return $query;
    }

    public static function operadoresParaCampo(string $campo): array
    {
        $meta = self::CAMPOS[$campo] ?? null;
        if (($meta['tipo'] ?? '') === 'entero') {
            return self::OPERADORES_ENTERO;
        }

        return self::OPERADORES_TEXTO;
    }
}
