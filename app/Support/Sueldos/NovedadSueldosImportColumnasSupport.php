<?php

namespace App\Support\Sueldos;

use App\Imports\Sueldos\NovedadSueldosImportLecturaCruda;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Columnas del Excel/CSV de novedades de liquidación.
 * El mapeo automático es por alias exacto; el operador puede corregirlo en la vista previa.
 */
final class NovedadSueldosImportColumnasSupport
{
    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    /** @var array<string, array{label: string, requerido: bool, aliases: list<string>}> */
    private const CAMPOS = [
        'empresa' => [
            'label' => 'Empresa',
            'requerido' => false,
            'aliases' => ['empresa_id', 'empresa', 'codigo_empresa', 'cod_empresa', 'id_empresa'],
        ],
        'legajo' => [
            'label' => 'Legajo',
            'requerido' => true,
            'aliases' => ['legajo', 'nro_legajo', 'numero_legajo', 'nrolegajo'],
        ],
        'concepto_codigo' => [
            'label' => 'Código concepto',
            'requerido' => true,
            'aliases' => ['concepto_codigo', 'codigo_concepto', 'cod_concepto', 'concepto'],
        ],
        'liquidacion_numero' => [
            'label' => 'N° liquidación',
            'requerido' => false,
            'aliases' => ['liquidacion_numero', 'liquidacion', 'nro_liquidacion', 'numero_liquidacion', 'corrida'],
        ],
        'valor1' => [
            'label' => 'Valor 1',
            'requerido' => false,
            'aliases' => ['valor1', 'valor_1', 'importe', 'cantidad', 'unidades', 'horas'],
        ],
        'valor2' => [
            'label' => 'Valor 2',
            'requerido' => false,
            'aliases' => ['valor2', 'valor_2', 'importe_2', 'precio', 'valor_unitario'],
        ],
        'estado' => [
            'label' => 'Estado',
            'requerido' => false,
            'aliases' => ['estado'],
        ],
        'fecha_vto' => [
            'label' => 'Fecha vto.',
            'requerido' => false,
            'aliases' => ['fecha_vto', 'vencimiento', 'fecha_vencimiento', 'vto'],
        ],
        'fecha_desde' => [
            'label' => 'Fecha desde',
            'requerido' => false,
            'aliases' => ['fecha_desde', 'vigencia_desde', 'desde'],
        ],
        'fecha_hasta' => [
            'label' => 'Fecha hasta',
            'requerido' => false,
            'aliases' => ['fecha_hasta', 'vigencia_hasta', 'hasta'],
        ],
        'nro_interno' => [
            'label' => 'Nro. interno',
            'requerido' => false,
            'aliases' => ['nro_interno', 'numero_interno', 'interno'],
        ],
        'periodo' => [
            'label' => 'Período',
            'requerido' => false,
            'aliases' => ['periodo', 'anio_mes', 'aaaamm'],
        ],
        'observacion' => [
            'label' => 'Observación',
            'requerido' => false,
            'aliases' => ['observacion', 'observaciones', 'obs', 'comentario', 'detalle'],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function campos(): array
    {
        return array_keys(self::CAMPOS);
    }

    /**
     * @return array<string, array{label: string, requerido: bool}>
     */
    public static function metaCampos(): array
    {
        $out = [];
        foreach (self::CAMPOS as $campo => $meta) {
            $out[$campo] = [
                'label' => $meta['label'],
                'requerido' => $meta['requerido'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<string, int|null>
     */
    public static function resolverMapaDesdeEncabezados(array $headers): array
    {
        $normalizados = [];
        foreach ($headers as $indice => $celda) {
            $nombre = PrecioImportColumnasSupport::normalizarNombreColumna((string) $celda);
            if ($nombre !== '') {
                $normalizados[(int) $indice] = $nombre;
            }
        }

        $pares = [];
        foreach (self::CAMPOS as $campo => $meta) {
            foreach ($meta['aliases'] as $alias) {
                $normal = PrecioImportColumnasSupport::normalizarNombreColumna($alias);
                $pares[] = [$campo, $normal, strlen($normal)];
            }
        }
        usort($pares, static fn (array $a, array $b): int => $b[2] <=> $a[2]);

        $asignado = [];
        $usados = [];
        foreach ($pares as [$campo, $alias]) {
            if (isset($asignado[$campo])) {
                continue;
            }
            foreach ($normalizados as $indice => $nombre) {
                if (isset($usados[$indice]) || $nombre !== $alias) {
                    continue;
                }
                $usados[$indice] = $campo;
                $asignado[$campo] = $indice;
                break;
            }
        }

        $mapa = [];
        foreach (self::CAMPOS as $campo => $meta) {
            $mapa[$campo] = $asignado[$campo] ?? null;
        }

        return $mapa;
    }

    /**
     * @param  UploadedFile|string  $archivo
     */
    public static function detectarFilaEncabezado(
        UploadedFile|string $archivo,
        ?int $filaIndicada = null,
        int $hojaIndice = 0
    ): int {
        if ($filaIndicada !== null && $filaIndicada >= 1 && $filaIndicada <= 50) {
            return $filaIndicada;
        }

        $hoja = Excel::toArray(new NovedadSueldosImportLecturaCruda(), $archivo)[$hojaIndice] ?? [];
        $limite = min(self::MAX_FILAS_BUSQUEDA_ENCABEZADO, count($hoja));

        for ($i = 0; $i < $limite; $i++) {
            $fila = $hoja[$i] ?? [];
            if (! is_array($fila)) {
                continue;
            }
            if (self::pareceFilaEncabezado($fila)) {
                return $i + 1;
            }
        }

        return 1;
    }

    /**
     * @param  array<int, mixed>  $primeraFila
     */
    public static function pareceFilaEncabezado(array $primeraFila): bool
    {
        $mapa = self::resolverMapaDesdeEncabezados($primeraFila);

        return ($mapa['legajo'] ?? null) !== null
            && (($mapa['concepto_codigo'] ?? null) !== null || ($mapa['valor1'] ?? null) !== null);
    }

    /**
     * Si ningún override trae índice, se conserva el mapa automático.
     *
     * @param  array<string, int|null>  $auto
     * @param  array<string, mixed>  $override
     * @return array<string, int|null>
     */
    public static function fusionarMapa(array $auto, array $override): array
    {
        $hayOverride = false;
        foreach ($override as $valor) {
            if ($valor !== null && $valor !== '') {
                $hayOverride = true;
                break;
            }
        }
        if (! $hayOverride) {
            return $auto;
        }

        $mapa = $auto;
        foreach (self::campos() as $campo) {
            if (! array_key_exists($campo, $override)) {
                continue;
            }
            $valor = $override[$campo];
            if ($valor === null || $valor === '') {
                $mapa[$campo] = null;

                continue;
            }
            if (is_numeric($valor)) {
                $mapa[$campo] = (int) $valor;
            }
        }

        return $mapa;
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array<string, int|null>  $mapa
     */
    public static function valorCampo(array $fila, array $mapa, string $campo): mixed
    {
        $indice = $mapa[$campo] ?? null;
        if ($indice === null) {
            return null;
        }

        return $fila[$indice] ?? null;
    }
}
