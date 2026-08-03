<?php

declare(strict_types=1);

namespace App\Support\Caja\Remesa;

/**
 * Constantes de negocio del módulo Remesas.
 */
final class RemesaSupport
{
    public const USO_DESTINO = 'Remesas destino';

    /** Haber / origen de remesa externa: cajas efectivo por moneda de la empresa. */
    public const USO_ORIGEN_EXTERNA = 'Remesas origen (efectivo)';

    /** Haber / origen de remesa interna: TES (tesorería). */
    public const USO_ORIGEN_INTERNA = 'Remesas origen interna (TES)';

    /** @deprecated Usar USO_ORIGEN_EXTERNA */
    public const USO_ORIGEN = self::USO_ORIGEN_EXTERNA;

    public const TIPO_INTERNA = 'I';

    public const TIPO_EXTERNA = 'M';

    public const ESTADO_CONFIRMADA = 'confirmada';

    /** Deshace asiento/caja; disponible para tesorería. */
    public const ESTADO_REVERTIDA = 'revertida';

    /** Cierre definitivo; solo administrador. */
    public const ESTADO_ANULADA = 'anulada';

    public const LADO_DESTINO = 'destino';

    public const LADO_ORIGEN = 'origen';

    /** tipotransaccion_caja.abreviatura */
    public const ABREV_REM = 'REM';

    public const ABREV_RMI = 'RMI';

    /** tipoasiento.abreviatura (asientos contables de remesa externa). */
    public const ABREV_TIPOASIENTO = 'REM';

    /**
     * Códigos Anita (tesmae, a-remebill.c carga_cuenta) → uso destino.
     * En ERP cuentacaja.codigo van sin padding (ver normalizarCodigoErp).
     */
    public const CODIGOS_DESTINO = [
        '00000120', '00000121', '00000122', '00000123', '00000125', '00000127', '00000128',
        '00000130', '00000131', '00000136', '00001001', '00001004', '00001115',
        '00000220', '00000221', '00000222', '00000223', '00000225', '00000226', '00000227',
        '00000230', '00000232', '00000236', '00001002', '00002115',
        '00000320', '00000321', '00000322', '00000323', '00000325', '00000326', '00000327',
        '00000330', '00000333', '00000336', '00001003', '00003115',
    ];

    /** TES (origen remesa interna). */
    public const CODIGOS_ORIGEN_INTERNA = [
        '00000TES',
    ];

    /**
     * Cajas efectivo por empresa/moneda (origen remesa externa / Haber asiento).
     * 100/200/300 pesos, 110/210/310 dólar, 129/219/319 euro, 11105033 cripto compartida.
     */
    public const CODIGOS_ORIGEN_EXTERNA = [
        '00000100', '00000110', '00000129',
        '00000200', '00000210', '00000219',
        '00000300', '00000310', '00000319',
        '11105033',
    ];

    /** @deprecated Usar CODIGOS_ORIGEN_INTERNA */
    public const CODIGOS_ORIGEN = self::CODIGOS_ORIGEN_INTERNA;

    public static function usoOrigenParaTipo(string $tipo): string
    {
        return strtoupper(trim($tipo)) === self::TIPO_INTERNA
            ? self::USO_ORIGEN_INTERNA
            : self::USO_ORIGEN_EXTERNA;
    }

    /**
     * Secciones de la pantalla Configurar remesas (preview + pivot).
     *
     * @return list<array{
     *   clave: string,
     *   nombre: string,
     *   titulo: string,
     *   descripcion: string,
     *   genera_asiento: bool
     * }>
     */
    public static function usosConfiguracion(): array
    {
        return [
            [
                'clave' => 'destino',
                'nombre' => self::USO_DESTINO,
                'titulo' => 'Destino (bancos)',
                'descripcion' => 'Cuentas destino de la remesa. En remesa externa forman el Debe del asiento REM (uno por moneda).',
                'genera_asiento' => true,
            ],
            [
                'clave' => 'origen_externa',
                'nombre' => self::USO_ORIGEN_EXTERNA,
                'titulo' => 'Origen externa (efectivo)',
                'descripcion' => 'Cajas efectivo por moneda de la empresa (y cripto compartida). Haber del asiento REM externo.',
                'genera_asiento' => true,
            ],
            [
                'clave' => 'origen_interna',
                'nombre' => self::USO_ORIGEN_INTERNA,
                'titulo' => 'Origen interna (TES)',
                'descripcion' => 'Tesorería (TES). Solo remesa interna: movimiento de caja RMI, sin asiento contable.',
                'genera_asiento' => false,
            ],
        ];
    }

    /**
     * @return array{clave: string, nombre: string, titulo: string, descripcion: string, genera_asiento: bool}|null
     */
    public static function usoConfiguracionPorClave(string $clave): ?array
    {
        $clave = trim($clave);
        foreach (self::usosConfiguracion() as $meta) {
            if ($meta['clave'] === $clave) {
                return $meta;
            }
        }

        return null;
    }

    /**
     * Anita 8 chars → código ERP en cuentacaja (00000120→120, 00000TES→TES).
     */
    public static function normalizarCodigoErp(string $codigoAnita): string
    {
        $codigo = strtoupper(trim($codigoAnita));
        if ($codigo === '' || preg_match('/^0+$/', $codigo)) {
            return '';
        }
        if (str_ends_with($codigo, 'TES') || $codigo === 'TES') {
            return 'TES';
        }
        // Códigos largos (ej. cripto 11105033): no recortar ceros internos.
        if (strlen($codigo) > 8) {
            return ltrim($codigo, '0') !== '' ? ltrim($codigo, '0') : $codigo;
        }

        return ltrim($codigo, '0');
    }

    /**
     * @param  list<string>  $codigosAnita
     * @return list<string>
     */
    public static function codigosErpDesdeAnita(array $codigosAnita): array
    {
        $out = [];
        foreach ($codigosAnita as $codigo) {
            $erp = self::normalizarCodigoErp((string) $codigo);
            if ($erp !== '') {
                $out[$erp] = $erp;
            }
        }

        return array_values($out);
    }

    /**
     * @return list<array{valor: string, nombre: string}>
     */
    public static function enumTipo(): array
    {
        return [
            ['valor' => self::TIPO_INTERNA, 'nombre' => 'Interna'],
            ['valor' => self::TIPO_EXTERNA, 'nombre' => 'Externa'],
        ];
    }

    /**
     * @return list<array{valor: string, nombre: string}>
     */
    public static function enumEstado(): array
    {
        return [
            ['valor' => self::ESTADO_CONFIRMADA, 'nombre' => 'Confirmada'],
            ['valor' => self::ESTADO_REVERTIDA, 'nombre' => 'Revertida'],
            ['valor' => self::ESTADO_ANULADA, 'nombre' => 'Anulada'],
        ];
    }
}
