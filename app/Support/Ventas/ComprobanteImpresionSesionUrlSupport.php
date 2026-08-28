<?php

namespace App\Support\Ventas;

/**
 * URL de sesión de impresión según documentos operativos disponibles.
 * Si no hay factura visible (reparto 101 / Villafranca oculta) sigue con remito + pedido.
 */
final class ComprobanteImpresionSesionUrlSupport
{
    public static function postFacturacion(?int $ventaId, ?int $remitoId, ?int $pedidoId, string $retornoPath = ''): ?string
    {
        $ventaId = (int) $ventaId;
        $remitoId = (int) $remitoId;
        $pedidoId = (int) $pedidoId;

        $url = null;
        if ($ventaId > 0 && PedidoFacturaAnitaArchivosSupport::esVentaIdVisible($ventaId)) {
            $url = route('sesion_impresion_factura', ['id' => $ventaId, 'auto' => 1]);
        } elseif ($remitoId > 0) {
            $url = route('sesion_impresion_remito', ['id' => $remitoId, 'auto' => 1, 'pack' => 1]);
        } elseif ($pedidoId > 0) {
            $url = route('sesion_impresion_pedido', ['id' => $pedidoId, 'auto' => 1, 'pack' => 1]);
        }

        if ($url === null) {
            return null;
        }

        return self::anexarRetorno($url, $retornoPath);
    }

    /**
     * Path relativo seguro para volver al index (evita open redirect).
     */
    public static function sanitizarRetornoPath(string $retorno): string
    {
        $retorno = trim($retorno);
        if ($retorno === '' || str_contains($retorno, "\n") || str_contains($retorno, "\r")) {
            return '';
        }

        if (preg_match('#^https?://#i', $retorno) === 1) {
            $parts = parse_url($retorno);
            if (! is_array($parts) || empty($parts['path'])) {
                return '';
            }
            $retorno = $parts['path'];
            if (! empty($parts['query'])) {
                $retorno .= '?'.$parts['query'];
            }
        }

        if (! str_starts_with($retorno, '/') || str_starts_with($retorno, '//')) {
            return '';
        }

        return $retorno;
    }

    public static function anexarRetorno(string $url, string $retornoPath): string
    {
        $retornoPath = self::sanitizarRetornoPath($retornoPath);
        if ($retornoPath === '' || $url === '') {
            return $url;
        }

        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.'retorno='.rawurlencode($retornoPath);
    }
}
