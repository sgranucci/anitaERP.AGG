<?php

namespace App\Support\Ventas;

use Illuminate\Support\Str;

final class ComprobanteImpresionNasPathSupport
{
    public static function raizFormulario(string $formulario): string
    {
        return match ($formulario) {
            ComprobanteImpresionFormulario::REMITO => (string) config('impresion_comprobante.nas_remitos'),
            ComprobanteImpresionFormulario::PEDIDO => (string) config('impresion_comprobante.nas_pedidos'),
            default => (string) config('impresion_comprobante.nas_facturas'),
        };
    }

    public static function destino(string $formulario, string $fechaYmd, string $codigoComprobante): string
    {
        $periodo = substr(str_replace('-', '', $fechaYmd), 0, 6);
        if (strlen($periodo) === 6) {
            $periodo = substr($periodo, 0, 4).'-'.substr($periodo, 4, 2);
        } else {
            $periodo = date('Y-m');
        }

        $nombre = self::nombreArchivo($codigoComprobante);

        return rtrim(self::raizFormulario($formulario), '/').'/'.$periodo.'/'.$nombre;
    }

    public static function nombreArchivo(string $codigo): string
    {
        $limpio = trim(preg_replace('/\s+/', '-', $codigo) ?? '');
        $limpio = preg_replace('/[^A-Za-z0-9._-]+/', '_', $limpio) ?? 'comprobante';

        return Str::limit($limpio, 120, '').'.pdf';
    }

    public static function nasMontado(): bool
    {
        $raiz = (string) config('impresion_comprobante.nas_raiz', '/NAS');
        if (! is_dir($raiz)) {
            return false;
        }

        $mounts = @file_get_contents('/proc/mounts');
        if (! is_string($mounts) || $mounts === '') {
            return false;
        }

        foreach (explode("\n", $mounts) as $linea) {
            $partes = preg_split('/\s+/', trim($linea));
            if (! is_array($partes) || count($partes) < 3) {
                continue;
            }
            if ($partes[1] === $raiz && str_contains($partes[2], 'nfs')) {
                return true;
            }
        }

        return false;
    }

    public static function esSalidaArchivo(?string $comando): bool
    {
        if ($comando === null || $comando === '') {
            return false;
        }

        return str_contains($comando, 'archivar-comprobante-nas');
    }
}
