<?php

namespace App\Support\Contable;

use App\Imports\Contable\AsientoImportLecturaCruda;
use App\Support\Archivo\TextoUtf8Support;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Nombres de columnas del Excel de importación de asientos contables.
 */
final class AsientoImportColumnasSupport
{
    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    public const COL_CUENTA_DEFAULT = 'cuenta';

    public const COL_DEBE_DEFAULT = 'debe';

    public const COL_HABER_DEFAULT = 'haber';

    public const COL_CENTROCOSTO_DEFAULT = 'centrocosto';

    public const COL_MONEDA_DEFAULT = 'moneda';

    public const COL_COTIZACION_DEFAULT = 'cotizacion';

    public const COL_DETALLE_DEFAULT = 'detalle';

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_CUENTA = [
        'cuenta',
        'cuenta_contable',
        'codigo_cuenta',
        'cod_cuenta',
        'cuentacontable',
        'cta',
        'cta_contable',
        'codigo',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_DEBE = [
        'debe',
        'debito',
        'deb',
        'importe_debe',
        'monto_debe',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_HABER = [
        'haber',
        'credito',
        'hab',
        'importe_haber',
        'monto_haber',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_CENTROCOSTO = [
        'centrocosto',
        'centro_costo',
        'centro_de_costo',
        'cc',
        'ccos',
        'ccosto',
        'codigo_cc',
        'cod_cc',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_MONEDA = [
        'moneda',
        'mon',
        'currency',
        'divisa',
        'cod_moneda',
        'codigo_moneda',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_COTIZACION = [
        'cotizacion',
        'cotización',
        'tipo_cambio',
        'tc',
        'cambio',
        'cotiz',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_DETALLE = [
        'detalle',
        'observacion',
        'observación',
        'concepto',
        'descripcion',
        'descripción',
        'glosa',
        'leyenda',
        'comentario',
    ];

    public static function normalizarNombreColumna(string $nombre): string
    {
        return PrecioImportColumnasSupport::normalizarNombreColumna($nombre);
    }

    /**
     * @param  UploadedFile|string  $archivo
     * @return list<array{indice: int, nombre: string}>
     */
    public static function hojasParaSelector(UploadedFile|string $archivo): array
    {
        return PrecioImportColumnasSupport::hojasParaSelector($archivo);
    }

    public static function indiceHojaDesdeRequest(?int $hojaIndice1Based, int $cantidadHojas): int
    {
        return PrecioImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, $cantidadHojas);
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

        $hoja = Excel::toArray(new AsientoImportLecturaCruda(), $archivo)[$hojaIndice] ?? [];
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
        $celdas = array_values(array_filter(array_map(
            static fn ($v) => self::normalizarNombreColumna((string) $v),
            $primeraFila
        ), static fn ($v) => $v !== ''));

        if ($celdas === []) {
            return false;
        }

        $grupos = [
            self::ALIAS_ENCABEZADO_CUENTA,
            self::ALIAS_ENCABEZADO_DEBE,
            self::ALIAS_ENCABEZADO_HABER,
            self::ALIAS_ENCABEZADO_CENTROCOSTO,
            self::ALIAS_ENCABEZADO_MONEDA,
            self::ALIAS_ENCABEZADO_DETALLE,
        ];

        foreach ($celdas as $celda) {
            foreach ($grupos as $alias) {
                $info = PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
                    [$celda],
                    $alias[0],
                    $alias[0],
                    $alias
                );
                if ($info !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $celdasEncabezado
     * @param  list<string>  $alias
     * @return array{indice: int, titulo: string, clave_normalizada: string}|null
     */
    public static function resolverColumna(
        array $celdasEncabezado,
        string $nombreConfigurado,
        string $default,
        array $alias
    ): ?array {
        return PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
            $celdasEncabezado,
            $nombreConfigurado,
            $default,
            $alias
        );
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $columna
     */
    public static function valorCeldaFila(array $fila, ?array $columna): mixed
    {
        return PrecioImportColumnasSupport::valorCeldaFila($fila, $columna);
    }

    public static function normalizarTextoCelda(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return trim(TextoUtf8Support::normalizar((string) $valor));
    }

    public static function normalizarCodigoCuenta(mixed $valor): string
    {
        $texto = self::normalizarTextoCelda($valor);
        if ($texto === '') {
            return '';
        }

        // Excel a veces entrega 111010001 como float 111010001.0
        if (is_numeric($valor) && ! str_contains($texto, 'E') && ! str_contains($texto, 'e')) {
            $float = (float) $valor;
            if (abs($float - round($float)) < 0.0000001) {
                return (string) (int) round($float);
            }
        }

        return $texto;
    }

    public static function normalizarImporte(mixed $valor): ?float
    {
        return PrecioImportColumnasSupport::normalizarValorPrecio($valor);
    }

    public static function formatearImporte(?float $importe): string
    {
        if ($importe === null) {
            return '';
        }

        return number_format($importe, 2, ',', '.');
    }
}
