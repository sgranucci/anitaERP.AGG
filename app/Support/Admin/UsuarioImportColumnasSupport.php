<?php

namespace App\Support\Admin;

use App\Imports\Admin\UsuarioImportLecturaCruda;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Nombres de columnas del Excel de importación masiva de usuarios.
 */
final class UsuarioImportColumnasSupport
{
    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    public const COL_USUARIO_DEFAULT = 'usuario';

    public const COL_NOMBRE_DEFAULT = 'nombre';

    public const COL_EMAIL_DEFAULT = 'email';

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_USUARIO = [
        'usuario',
        'login',
        'user',
        'username',
        'codigo_usuario',
        'usuario_login',
        'cuenta',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_NOMBRE = [
        'nombre',
        'nombre_completo',
        'apenom',
        'apellido_nombre',
        'nombre_y_apellido',
        'nombreyapellido',
        'razon_social',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_EMAIL = [
        'email',
        'e_mail',
        'mail',
        'correo',
        'correo_electronico',
        'email_usuario',
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

        $hoja = Excel::toArray(new UsuarioImportLecturaCruda(), $archivo)[$hojaIndice] ?? [];
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

        $aliasTodos = array_merge(
            self::ALIAS_ENCABEZADO_USUARIO,
            self::ALIAS_ENCABEZADO_NOMBRE,
            self::ALIAS_ENCABEZADO_EMAIL
        );

        foreach ($celdas as $celda) {
            if (in_array($celda, $aliasTodos, true)) {
                return true;
            }
            foreach ($aliasTodos as $alias) {
                if ($alias !== '' && (str_contains($celda, $alias) || str_contains($alias, $celda))) {
                    if (strlen($celda) >= 4 || strlen($alias) >= 4) {
                        return true;
                    }
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

        return trim((string) $valor);
    }

    public static function normalizarEmail(mixed $valor): string
    {
        return mb_strtolower(self::normalizarTextoCelda($valor));
    }
}
