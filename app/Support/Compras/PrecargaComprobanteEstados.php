<?php

namespace App\Support\Compras;

/**
 * Estados de precarga de comprobante de proveedor.
 */
final class PrecargaComprobanteEstados
{
    public const PENDIENTE = 'PENDIENTE';

    /** Ya tiene comprobante generado / asignado (no figura en el index por defecto). */
    public const GENERADA = 'GENERADA';

    /** Factura ya cargada en Anita (nativo u otro origen); no se genera comprobante ERP. */
    public const CARGADA_ANITA = 'CARGADA_ANITA';

    /**
     * Mercadería aún no entregada: Compras la retiene para no bloquear el resto del legajo.
     * No exige COM, no figura como pendiente de carga en CxP ni impide enviar a Pagos.
     */
    public const PENDIENTE_ENTREGA = 'PENDIENTE_ENTREGA';

    /** Descartada: no debe cargarse (duplicada, error de scan). No es un estado ofrecido en el ABM. */
    public const ANULADA = 'ANULADA';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PENDIENTE,
            self::GENERADA,
            self::CARGADA_ANITA,
        ];
    }

    public static function etiqueta(string $estado): string
    {
        return match ($estado) {
            self::PENDIENTE => 'Pendientes',
            self::GENERADA => 'Generadas',
            self::CARGADA_ANITA => 'Ya cargadas en Anita',
            self::PENDIENTE_ENTREGA => 'Pendiente de entrega',
            default => $estado,
        };
    }

    public static function etiquetaRegistro(string $estado): string
    {
        return match ($estado) {
            self::PENDIENTE => 'PENDIENTE',
            self::GENERADA => 'GENERADA',
            self::CARGADA_ANITA => 'Ya cargada en Anita',
            self::PENDIENTE_ENTREGA => 'Pendiente de entrega',
            default => $estado,
        };
    }

    public static function puedeGenerarComprobante(string $estado): bool
    {
        return $estado === self::PENDIENTE;
    }

    public static function puedeMarcarCargadaAnita(string $estado): bool
    {
        return $estado === self::PENDIENTE;
    }

    /** Ya está en Anita; no debe figurar como pendiente de carga en CxP / bandeja. */
    public static function esCargadaAnita(?string $estado): bool
    {
        return strtoupper(trim((string) $estado)) === self::CARGADA_ANITA;
    }

    public static function esPendienteEntrega(?string $estado): bool
    {
        return strtoupper(trim((string) $estado)) === self::PENDIENTE_ENTREGA;
    }

    /**
     * Puede pasar a “pendiente de entrega” (retenida hasta que llegue la mercadería).
     */
    public static function puedeMarcarPendienteEntrega(?string $estado): bool
    {
        $e = strtoupper(trim((string) $estado));

        return $e === '' || $e === self::PENDIENTE || $e === self::GENERADA;
    }

    /**
     * Precarga con PDF usable para “Listo para cargar” / pendientes de CxP.
     * Excluye anuladas, cargadas en Anita y retenidas por falta de entrega.
     */
    public static function pendienteCargaEnCxp(?string $estado): bool
    {
        $e = strtoupper(trim((string) $estado));

        return $e !== self::ANULADA
            && $e !== self::CARGADA_ANITA
            && $e !== self::PENDIENTE_ENTREGA;
    }
}
