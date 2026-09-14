<?php

namespace App\Support\Caja;

use App\Imports\Stock\PrecioImportLecturaCruda;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Nombres de columnas del Excel/CSV de ingreso masivo de cheques (CHT).
 */
final class ChequeImportColumnasSupport
{
    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    /** @var array<string, array{label: string, requerido: bool, aliases: list<string>}> */
    private const CAMPOS = [
        'numerocheque' => [
            'label' => 'Número de cheque',
            'requerido' => true,
            'aliases' => [
                'numerocheque', 'nro', 'numero', 'nro_cheque', 'numero_cheque', 'nrocheque',
                'cheque', 'nro_cht', 'nrocht', 'nro_fisico',
            ],
        ],
        'fechapago' => [
            'label' => 'Fecha de pago',
            'requerido' => true,
            'aliases' => [
                'fechapago', 'fecha_pago', 'vencimiento', 'fecha_vencimiento', 'vto', 'fecha_vto',
                'pago', 'fecha_cobro',
            ],
        ],
        'fechaemision' => [
            'label' => 'Fecha de emisión',
            'requerido' => false,
            'aliases' => [
                'fechaemision', 'fecha_emision', 'emision', 'fecha_emi', 'fecha',
            ],
        ],
        'monto' => [
            'label' => 'Monto',
            'requerido' => true,
            'aliases' => [
                'monto', 'importe', 'valor', 'importe_cheque', 'monto_cheque', 'total',
            ],
        ],
        'banco_codigo' => [
            'label' => 'Código banco',
            'requerido' => true,
            'aliases' => [
                'banco_codigo', 'banco', 'cod_banco', 'codigo_banco', 'nrobanco', 'nro_banco',
            ],
        ],
        'cliente_codigo' => [
            'label' => 'Código cliente',
            'requerido' => false,
            'aliases' => [
                'cliente_codigo', 'cliente', 'cod_cliente', 'codigo_cliente', 'nro_cliente',
            ],
        ],
        'moneda_codigo' => [
            'label' => 'Moneda',
            'requerido' => false,
            'aliases' => [
                'moneda_codigo', 'moneda', 'cod_moneda', 'codigo_moneda', 'abreviatura', 'divisa',
            ],
        ],
        'entregado' => [
            'label' => 'Entregado',
            'requerido' => false,
            'aliases' => [
                'entregado', 'entregado_por', 'librador', 'firmante',
            ],
        ],
        'anombrede' => [
            'label' => 'A nombre de',
            'requerido' => false,
            'aliases' => [
                'anombrede', 'a_nombre_de', 'beneficiario', 'a_la_orden', 'orden',
            ],
        ],
        'sucursalpago' => [
            'label' => 'Sucursal pago',
            'requerido' => false,
            'aliases' => [
                'sucursalpago', 'sucursal_pago', 'sucursal', 'sucursal_banco',
            ],
        ],
        'cuentalibradora' => [
            'label' => 'Cuenta libradora',
            'requerido' => false,
            'aliases' => [
                'cuentalibradora', 'cuenta_libradora', 'cuenta', 'nro_cuenta', 'cuenta_corriente',
            ],
        ],
        'empresa_codigo' => [
            'label' => 'Código empresa',
            'requerido' => false,
            'aliases' => [
                'empresa_codigo', 'empresa', 'cod_empresa', 'codigo_empresa',
            ],
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
     * @return array<string, list<string>>
     */
    public static function aliasesPorCampo(): array
    {
        $out = [];
        foreach (self::CAMPOS as $campo => $meta) {
            $out[$campo] = $meta['aliases'];
        }

        return $out;
    }

    public static function normalizar(string $nombre): string
    {
        return PrecioImportColumnasSupport::normalizarNombreColumna($nombre);
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<string, int|null> campo => índice de columna (0-based) o null
     */
    public static function resolverMapaDesdeEncabezados(array $headers): array
    {
        $mapa = [];
        $usados = [];

        foreach (self::CAMPOS as $campo => $meta) {
            $resuelto = PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
                $headers,
                $campo,
                $campo,
                $meta['aliases']
            );

            if ($resuelto === null) {
                $mapa[$campo] = null;

                continue;
            }

            $indice = (int) $resuelto['indice'];
            if (isset($usados[$indice])) {
                $mapa[$campo] = null;

                continue;
            }

            $usados[$indice] = $campo;
            $mapa[$campo] = $indice;
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

        $hoja = Excel::toArray(new PrecioImportLecturaCruda(), $archivo)[$hojaIndice] ?? [];
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
        $hits = 0;
        foreach (['numerocheque', 'fechapago', 'monto', 'banco_codigo'] as $campo) {
            if (($mapa[$campo] ?? null) !== null) {
                $hits++;
            }
        }

        return $hits >= 2;
    }

    /**
     * Fusiona mapeo automático con overrides del request (índices 0-based o vacíos).
     *
     * @param  array<string, int|null>  $auto
     * @param  array<string, mixed>  $override
     * @return array<string, int|null>
     */
    public static function fusionarMapa(array $auto, array $override): array
    {
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
