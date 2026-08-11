<?php

namespace App\Support\Stock\Surmar;

/**
 * Bridge HTTP Anita Surmar para trazabilidad.
 * Textos/char con posible "|" van al final del SELECT (regla bridge CSV).
 * Lecturas: una sola por tabla (sin particionar por mes).
 */
final class TrazabilidadSurmarAnitaBridgeSupport
{
    /**
     * @return array{sistema: string, path_sistema: string}
     */
    public static function parametrosCompras(): array
    {
        return [
            'sistema' => self::sistemaCompras(),
            'path_sistema' => self::pathSistema(),
        ];
    }

    /**
     * @return array{sistema: string, path_sistema: string}
     */
    public static function parametrosVentas(): array
    {
        return [
            'sistema' => self::sistemaVentas(),
            'path_sistema' => self::pathSistema(),
        ];
    }

    public static function pathSistema(): string
    {
        $path = rtrim((string) config('trazabilidad_anita_surmar.path_sistema', '/usr2/surmar'), '/');

        return $path !== '' ? $path : '/usr2/surmar';
    }

    public static function sistemaCompras(): string
    {
        $s = trim((string) config('trazabilidad_anita_surmar.sistema_compras', 'compras'));

        return $s !== '' ? $s : 'compras';
    }

    public static function sistemaVentas(): string
    {
        $s = trim((string) config('trazabilidad_anita_surmar.sistema_ventas', 'ventas'));

        return $s !== '' ? $s : 'ventas';
    }

    public static function fechaDesde(): int
    {
        return (int) config('trazabilidad_anita_surmar.fecha_desde', 20260101);
    }

    public static function fechaHasta(): ?int
    {
        $h = (int) config('trazabilidad_anita_surmar.fecha_hasta', 0);

        return $h > 0 ? $h : null;
    }

    public static function whereFecha(string $campoFecha): string
    {
        $desde = self::fechaDesde();
        $where = ' WHERE '.$campoFecha.' >= '.(int) $desde;
        $hasta = self::fechaHasta();
        if ($hasta !== null) {
            $where .= ' AND '.$campoFecha.' <= '.(int) $hasta;
        }

        return $where;
    }

    /** Campos t_comp: descripción al final. */
    public static function camposTcomp(): string
    {
        return 'tcomp_clave,tcomp_oper,tcomp_oper_stk,tcomp_tipo_comp,tcomp_tipo_oper,tcomp_estado,tcomp_genera_asi,tcomp_desc';
    }

    /** Campos recepaper: desc/cert al final. */
    public static function camposRecepaper(): string
    {
        return implode(',', [
            'recap_proveedor',
            'recap_tipo',
            'recap_letra',
            'recap_sucursal',
            'recap_nro',
            'recap_orden',
            'recap_articulo',
            'recap_nro_interno',
            'recap_nro_apertura',
            'recap_cant_pieza',
            'recap_peso_bruto',
            'recap_peso_neto',
            'recap_cod_umd',
            'recap_fecha_vto',
            'recap_fecha_emi',
            'recap_hora_emi',
            'recap_nro_establ',
            'recap_certificado',
            'recap_desc',
        ]);
    }

    /** Campos stkmov: usuario/terminal/cert al final. */
    public static function camposStkmov(): string
    {
        return implode(',', [
            'stkv_articulo',
            'stkv_fecha',
            'stkv_tipo',
            'stkv_letra',
            'stkv_sucursal',
            'stkv_nro',
            'stkv_ref_tipo',
            'stkv_ref_sucursal',
            'stkv_ref_nro',
            'stkv_deposito',
            'stkv_cantidad',
            'stkv_precio',
            'stkv_nro_orden',
            'stkv_cli_pro',
            'stkv_cod_umd',
            'stkv_cant_unidad',
            'stkv_tropa',
            'stkv_usuario',
            'stkv_terminal',
            'stkv_certificado',
        ]);
    }

    /** Campos stkvaper: desc/hora al final. */
    public static function camposStkvaper(): string
    {
        return implode(',', [
            'stkvap_tipo',
            'stkvap_letra',
            'stkvap_sucursal',
            'stkvap_nro',
            'stkvap_orden',
            'stkvap_articulo',
            'stkvap_nro_interno',
            'stkvap_nro_aper',
            'stkvap_cant_pieza',
            'stkvap_peso_bruto',
            'stkvap_peso_neto',
            'stkvap_fecha_emi',
            'stkvp_hora_emi',
            'stkvap_desc',
        ]);
    }

    /** Campos apcom: desc/cert/hora al final. */
    public static function camposApcom(): string
    {
        return implode(',', [
            'apcom_tipo',
            'apcom_letra',
            'apcom_sucursal',
            'apcom_nro',
            'apcom_orden',
            'apcom_articulo',
            'apcom_nro_interno',
            'apcom_nro_apertura',
            'apcom_cant_pieza',
            'apcom_peso_bruto',
            'apcom_peso_neto',
            'apcom_fecha_emi',
            'apcom_umd',
            'apcom_fecha_vto',
            'apcom_hora_emision',
            'apcom_certificado',
            'apcom_desc',
        ]);
    }

    /**
     * Mapea tcomp_oper_stk → operacion/signo ERP.
     * null = no es tipo de stock operativo (omitir upsert).
     *
     * @return array{operacion: string, signo: string}|null
     */
    public static function operacionDesdeOperStk(int|string $operStk): ?array
    {
        $op = (int) trim((string) $operStk);

        return match ($op) {
            3, 6, 8 => ['operacion' => 'S', 'signo' => 'R'],
            5, 7 => ['operacion' => 'E', 'signo' => 'S'],
            4 => ['operacion' => 'T', 'signo' => 'S'],
            default => null,
        };
    }

    /** Signo numérico para firmar cantidad histórica (±1). */
    public static function signoNumerico(string $abreviatura, int $tipotransaccionSignoDb): int
    {
        $abrev = strtoupper(trim($abreviatura));
        // TRS/TRE/TRA: TRS resta, TRE suma; TRA genérico no se usa en stkmov.
        if ($abrev === 'TRS') {
            return -1;
        }
        if ($abrev === 'TRE') {
            return 1;
        }

        return $tipotransaccionSignoDb >= 0 ? 1 : -1;
    }

    public static function rolDesdeTipoStkmov(string $tipo): string
    {
        $t = strtoupper(trim($tipo));

        return match ($t) {
            'COM', 'TRE', 'AP', 'AJU' => 'ENTRADA',
            'REM', 'TRS', 'DES', 'FAC', 'DEP', 'SCO', 'SVT', 'SDP', 'SRO', 'SAJ' => 'SALIDA',
            'TRA' => 'TRANSFERENCIA',
            default => 'SALIDA',
        };
    }

    public static function claveMovimiento(string $tipo, string $letra, int $sucursal, int $nro): string
    {
        $prefix = (string) config('trazabilidad_anita_surmar.leyenda_prefix', 'ANITA_SURMAR');

        return $prefix.'|'.strtoupper(trim($tipo)).'|'.trim($letra).'|'.$sucursal.'|'.$nro;
    }

    public static function conceptoLinea(string $tipo, string $letra, int $sucursal, int $nro, int $orden): string
    {
        return self::claveMovimiento($tipo, $letra, $sucursal, $nro).'|'.$orden;
    }
}
