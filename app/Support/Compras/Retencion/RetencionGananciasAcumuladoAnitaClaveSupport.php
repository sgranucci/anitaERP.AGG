<?php

namespace App\Support\Compras\Retencion;

use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;

/**
 * Clave Anita de retmov: tipo + letra + sucursal + nro + empresa.
 *
 * En ERP la letra de la OP no se usa (espacio). Anita sí la graba en retmov
 * (típico 'A'). Hay que leer y deduplicar con esa clave completa.
 */
final class RetencionGananciasAcumuladoAnitaClaveSupport
{
    /**
     * @return string tipo|letra|sucursal|nro|empresa
     */
    public static function clave(
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        int $empresa,
    ): string {
        return self::tipo($tipo).'|'
            .self::letra($letra).'|'
            .$sucursal.'|'
            .$nro.'|'
            .$empresa;
    }

    public static function tipo(string $tipo): string
    {
        $tipo = strtoupper(substr(trim($tipo), 0, 3));

        return $tipo !== '' ? $tipo : 'OPP';
    }

    /**
     * CHAR(1) Informix. Vacío → espacio (igual que ERP default).
     */
    public static function letra(string $letra): string
    {
        $letra = strtoupper(substr(trim($letra), 0, 1));

        return $letra !== '' ? $letra : ' ';
    }

    /**
     * Claves a marcar ocupadas desde un pago ERP.
     * Si la letra ERP es dummy (espacio), también ocupa la 'A' de Anita.
     *
     * @return list<string>
     */
    public static function clavesOcupacionDesdeErp(
        string $tipo,
        string $letraErp,
        int $sucursal,
        int $nro,
        int $empresa,
    ): array {
        if ($nro <= 0 || $empresa <= 0) {
            return [];
        }

        $letra = self::letra($letraErp);
        $out = [self::clave($tipo, $letra, $sucursal, $nro, $empresa)];
        if ($letra === ' ') {
            $out[] = self::clave($tipo, 'A', $sucursal, $nro, $empresa);
        }

        return array_values(array_unique($out));
    }

    public static function etiqueta(string $tipo, string $letra, int $sucursal, int $nro): string
    {
        $letra = self::letra($letra);

        return sprintf(
            '%s %s-%04d-%d',
            self::tipo($tipo),
            $letra === ' ' ? '·' : $letra,
            $sucursal,
            $nro
        );
    }

    /**
     * @param  array<string, mixed>  $fila  Fila retmov
     * @return array{
     *     clave: string,
     *     tipo: string,
     *     letra: string,
     *     sucursal: int,
     *     nro: int,
     *     empresa: int,
     *     fecha: string,
     *     neto: float,
     *     retenido: float,
     *     nro_certificado: int,
     *     codigo_ret: int
     * }|null
     */
    public static function parsearFilaRetmov(array $fila): ?array
    {
        $tipo = self::tipo((string) ($fila['retv_tipo'] ?? 'OPP'));
        $letra = self::letra((string) ($fila['retv_letra'] ?? 'A'));
        $sucursal = (int) ($fila['retv_sucursal'] ?? 0);
        $nro = (int) ($fila['retv_nro'] ?? 0);
        $empresa = (int) ($fila['retv_empresa'] ?? 0);
        $fecha = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($fila['retv_fecha'] ?? '');
        $signo = str_starts_with($tipo, 'AOP') ? -1.0 : 1.0;
        $retenido = round(abs((float) ($fila['retv_retencion'] ?? 0)) * $signo, 2);
        $gravado = (float) ($fila['retv_gravado'] ?? 0);
        $pagoActual = (float) ($fila['retv_pago_actual'] ?? 0);
        $netoAbs = $gravado > 0.0001 ? abs($gravado) : abs($pagoActual);
        $neto = round($netoAbs * $signo, 2);

        if ($nro <= 0 || $empresa <= 0 || $fecha === '' || abs($retenido) < 0.001) {
            return null;
        }

        return [
            'clave' => self::clave($tipo, $letra, $sucursal, $nro, $empresa),
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'nro' => $nro,
            'empresa' => $empresa,
            'fecha' => $fecha,
            'neto' => $neto,
            'retenido' => $retenido,
            'nro_certificado' => (int) ($fila['retv_nro_retencion'] ?? 0),
            'codigo_ret' => (int) ($fila['retv_codigo_ret'] ?? 0),
        ];
    }

    public static function codigoRetCoincide(int $codigoFila, string $codigoRegimenErp): bool
    {
        $codigoRegimenErp = trim($codigoRegimenErp);
        if ($codigoRegimenErp === '') {
            return true;
        }
        if ($codigoFila === (int) $codigoRegimenErp) {
            return true;
        }

        return strcasecmp(ltrim((string) $codigoFila, '0'), ltrim($codigoRegimenErp, '0')) === 0
            && ltrim($codigoRegimenErp, '0') !== '';
    }
}
