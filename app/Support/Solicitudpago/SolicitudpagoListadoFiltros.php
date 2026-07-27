<?php

namespace App\Support\Solicitudpago;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Http\Request;

final class SolicitudpagoListadoFiltros
{
    /**
     * Estado y tratamiento van por selects dedicados del panel (no como campo de texto).
     */
    public const CAMPOS = [
        'codigo' => ['etiqueta' => 'Código', 'tipo' => 'entero', 'columna' => 'solicitudpago.codigo'],
        'detalle' => ['etiqueta' => 'Detalle', 'tipo' => 'texto', 'columna' => 'solicitudpago.detalle'],
        'beneficiario' => ['etiqueta' => 'Beneficiario', 'tipo' => 'texto', 'columna' => 'solicitudpago.beneficiario'],
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
     * @return array{
     *     campo: ?string,
     *     operador: string,
     *     valor: string,
     *     busqueda_rapida: bool,
     *     madre_hija: string,
     *     estado: string,
     *     tratamiento: string,
     *     fecha_desde: string,
     *     fecha_hasta: string,
     *     alcance: string
     * }
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
            'alcance' => SolicitudpagoVisibilidadSupport::ALCANCE_TODAS,
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
        $filtros['alcance'] = self::normalizarAlcance($request->input('alcance'));

        return $filtros;
    }

    public static function normalizarAlcance(mixed $valor): string
    {
        $alcance = (string) ($valor ?? SolicitudpagoVisibilidadSupport::ALCANCE_TODAS);

        return $alcance === SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC
            ? SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC
            : SolicitudpagoVisibilidadSupport::ALCANCE_TODAS;
    }

    public static function tieneAlcanceMiCentrocosto(array $filtros): bool
    {
        return self::normalizarAlcance($filtros['alcance'] ?? null) === SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC;
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
        if (self::tieneAlcanceMiCentrocosto($filtros)) {
            $out['alcance'] = SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC;
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
        } elseif (($filtros['madre_hija'] ?? '') === 'madres_con_plan') {
            $query->whereNull('solicitudpago.solicitudpago_madre_id')
                ->whereHas('cuotas');
        } elseif (($filtros['madre_hija'] ?? '') === 'familia') {
            $query->where(function ($q) {
                $q->whereNotNull('solicitudpago.solicitudpago_madre_id')
                    ->orWhereHas('cuotas')
                    ->orWhereHas('hijas');
            });
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return $query;
        }

        // Si ya filtró por el select de estado/tratamiento y el texto es solo ese enum,
        // no AND-ear búsqueda de texto (evita 0 resultados al escribir "autorizada").
        $estadoSelect = trim((string) ($filtros['estado'] ?? ''));
        $tratSelect = trim((string) ($filtros['tratamiento'] ?? ''));
        $estadosValor = self::codigosEnumQueCoinciden(SolicitudpagoEstados::opciones(), $valor);
        $tratsValor = self::codigosEnumQueCoinciden(SolicitudpagoTratamientos::opciones(), $valor);
        if ($estadoSelect !== '' && $estadosValor !== [] && $tratsValor === [] && ! self::pareceCodigoOTextoLibre($valor)) {
            return $query;
        }
        if ($tratSelect !== '' && $tratsValor !== [] && $estadosValor === [] && ! self::pareceCodigoOTextoLibre($valor)) {
            return $query;
        }

        if (! empty($filtros['busqueda_rapida']) || empty($filtros['campo'])) {
            $query->where(function ($q) use ($valor, $estadosValor, $tratsValor) {
                $q->where('solicitudpago.codigo', 'like', '%'.$valor.'%')
                    ->orWhere('solicitudpago.detalle', 'like', '%'.$valor.'%')
                    ->orWhere('solicitudpago.beneficiario', 'like', '%'.$valor.'%')
                    ->orWhere('solicitudpago.observacion', 'like', '%'.$valor.'%');
                if ($estadosValor !== []) {
                    $q->orWhereIn('solicitudpago.estado', $estadosValor);
                }
                if ($tratsValor !== []) {
                    $q->orWhereIn('solicitudpago.tratamiento', $tratsValor);
                }
                CoincidenciaFlexibleTexto::aplicar($q, 'solicitudpago.detalle', $valor);
            });

            return $query;
        }

        $campo = self::CAMPOS[$filtros['campo']] ?? null;
        if ($campo === null) {
            // Compat URLs viejas filtro_campo=estado|tratamiento
            if (($filtros['campo'] ?? '') === 'estado' && $estadosValor !== []) {
                return $query->whereIn('solicitudpago.estado', $estadosValor);
            }
            if (($filtros['campo'] ?? '') === 'tratamiento' && $tratsValor !== []) {
                return $query->whereIn('solicitudpago.tratamiento', $tratsValor);
            }

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

    /**
     * @param  list<array{valor: string, nombre: string}>  $opciones
     * @return list<string>
     */
    private static function codigosEnumQueCoinciden(array $opciones, string $valor): array
    {
        $needle = mb_strtoupper(trim($valor), 'UTF-8');
        if ($needle === '') {
            return [];
        }

        $out = [];
        $len = mb_strlen($needle, 'UTF-8');
        foreach ($opciones as $opt) {
            $codigo = mb_strtoupper((string) $opt['valor'], 'UTF-8');
            $nombre = mb_strtoupper((string) $opt['nombre'], 'UTF-8');
            $igual = $codigo === $needle || $nombre === $needle;
            // Contiene solo con 4+ caracteres para no matchear "A" → casi todos
            $parcial = $len >= 4 && (str_contains($codigo, $needle) || str_contains($nombre, $needle));
            if ($igual || $parcial) {
                $out[] = (string) $opt['valor'];
            }
        }

        return array_values(array_unique($out));
    }

    /** true si el valor parece un código numérico u otro texto libre (no solo etiqueta de enum). */
    private static function pareceCodigoOTextoLibre(string $valor): bool
    {
        $v = trim($valor);

        return $v !== '' && preg_match('/\d/', $v) === 1;
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
