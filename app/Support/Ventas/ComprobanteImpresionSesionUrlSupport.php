<?php

namespace App\Support\Ventas;

/**
 * URL de sesión de impresión según documentos operativos disponibles.
 * Si no hay factura visible (reparto 101 / Villafranca oculta) sigue con remito + pedido.
 */
final class ComprobanteImpresionSesionUrlSupport
{
    public static function postFacturacion(?int $ventaId, ?int $remitoId, ?int $pedidoId, string $retornoPath = '', bool $autoEnviar = true, bool $planConEnvios = false): ?string
    {
        $ventaId = (int) $ventaId;
        $remitoId = (int) $remitoId;
        $pedidoId = (int) $pedidoId;

        // auto: misma ruta que "Imprimir" del listado (despacha sin paso manual).
        // Sin auto (elegir): abre la sesión para destildar copias (p. ej. Envío) y ejecutar a mano.
        $flags = $autoEnviar
            ? ['auto' => 1, 'enviar_impresora' => 1]
            : ['elegir' => 1, 'enviar_impresora' => 1];

        $url = null;
        if ($ventaId > 0 && PedidoFacturaAnitaArchivosSupport::esVentaIdVisible($ventaId)) {
            $url = route('sesion_impresion_factura', ['id' => $ventaId] + $flags);
        } elseif ($remitoId > 0) {
            $url = route('sesion_impresion_remito', [
                'id' => $remitoId,
                'pack' => 1,
            ] + $flags);
        } elseif ($pedidoId > 0) {
            $url = route('sesion_impresion_pedido', [
                'id' => $pedidoId,
                'pack' => 1,
            ] + $flags);
        }

        if ($url === null) {
            return null;
        }

        if ($planConEnvios) {
            $sep = str_contains($url, '?') ? '&' : '?';
            $url .= $sep.'con_envios=1';
        }

        return self::anexarRetorno($url, $retornoPath);
    }

    /**
     * URLs de sesión tras facturar un lote de pedidos (reparto El Bierzo).
     * Respeta los tildes del programa de la primera factura del lote.
     *
     * @param  list<int>  $ventaIds
     * @return array{completa: ?string, elegir: ?string}
     */
    public static function postFacturacionReparto(array $ventaIds, int $transporteId, string $retornoPath = ''): array
    {
        $ventaIds = array_values(array_unique(array_filter(array_map('intval', $ventaIds))));
        $vacio = ['completa' => null, 'elegir' => null];
        if ($ventaIds === [] || $transporteId <= 0) {
            return $vacio;
        }

        $ventaId = $ventaIds[0];
        if (! ComprobanteImpresionResolverSupport::dispararProcesoImpresionAlFacturar($ventaId, 0, 0)) {
            return $vacio;
        }

        $base = [
            'transporteId' => $transporteId,
            'venta_ids' => implode(',', $ventaIds),
        ];
        $auto = ComprobanteImpresionResolverSupport::enviarAutomaticoAlFacturar($ventaId, 0, 0);
        if ($auto) {
            $url = route('sesion_impresion_reparto_pedidos', $base + [
                'pack_completo' => 1,
                'auto' => 1,
                'enviar_impresora' => 1,
            ]);

            return [
                'completa' => self::anexarRetorno($url, $retornoPath),
                'elegir' => null,
            ];
        }

        $url = route('sesion_impresion_reparto_pedidos', $base + [
            'pack_completo' => 1,
            'elegir' => 1,
        ]);

        return [
            'completa' => null,
            'elegir' => self::anexarRetorno($url, $retornoPath),
        ];
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
