<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

use App\Support\Compras\OrdencompraEstados;

/**
 * Códigos penmp_estado Anita (char(1)) — ver #define en legacy compras.
 */
final class OrdencompraAnitaEstadosSupport
{
    public const PENTREGAR = '0';

    public const ENTRPARC = '1';

    public const ENTREGADO = '2';

    public const FACTURADO = '3';

    public const SUSPENDIDO = '4';

    public const CERRADA = 'C';

    public const CERRADA_CDIF = 'D';

    public const A_AUTORIZAR = 'A';

    /** ERP → Anita al grabar / actualizar pendmaep. */
    public static function desdeEstadoErp(string $estadoErp): string
    {
        return match (strtoupper(trim($estadoErp))) {
            OrdencompraEstados::APROBADA => self::A_AUTORIZAR,
            OrdencompraEstados::CUMPLIDA => self::ENTREGADO,
            OrdencompraEstados::SUSPENDIDA => self::SUSPENDIDO,
            OrdencompraEstados::CERRADA => self::CERRADA,
            default => self::PENTREGAR,
        };
    }

    /** Anita → ERP (importación). */
    public static function haciaEstadoErp(mixed $codigoAnita): string
    {
        return match (strtoupper(trim((string) $codigoAnita))) {
            self::A_AUTORIZAR => OrdencompraEstados::APROBADA,
            self::ENTRPARC => OrdencompraEstados::PENDIENTE,
            self::ENTREGADO, self::FACTURADO => OrdencompraEstados::CUMPLIDA,
            self::SUSPENDIDO => OrdencompraEstados::SUSPENDIDA,
            self::CERRADA, self::CERRADA_CDIF => OrdencompraEstados::CERRADA,
            default => OrdencompraEstados::PENDIENTE,
        };
    }

    /**
     * Estado cabecera OC tras recepción según cantidades en pendmovp.
     */
    public static function desdeRecepcionPendmovp(bool $algunaActividad, bool $todasCompletas): string
    {
        if ($todasCompletas) {
            return self::ENTREGADO;
        }

        if ($algunaActividad) {
            return self::ENTRPARC;
        }

        return self::PENTREGAR;
    }

    /**
     * Importación Anita → ERP: la cabecera (penmp_estado) puede quedar ENTREGADO/FACTURADO
     * aunque pendmovp tenga líneas sin penvp_cantentr; en ese caso la OC debe quedar APROBADA.
     *
     * @param  list<object>  $lineasPendmovp
     */
    public static function haciaEstadoErpImportacion(mixed $codigoAnitaCabecera, array $lineasPendmovp): string
    {
        $estadoCabecera = self::haciaEstadoErp($codigoAnitaCabecera);

        if (in_array($estadoCabecera, [OrdencompraEstados::SUSPENDIDA, OrdencompraEstados::CERRADA], true)) {
            return $estadoCabecera;
        }

        if ($lineasPendmovp === []) {
            return $estadoCabecera === OrdencompraEstados::CUMPLIDA
                ? OrdencompraEstados::APROBADA
                : ($estadoCabecera === OrdencompraEstados::PENDIENTE ? OrdencompraEstados::APROBADA : $estadoCabecera);
        }

        if (self::todasLineasPendmovpCompletas($lineasPendmovp)) {
            return OrdencompraEstados::CUMPLIDA;
        }

        return OrdencompraEstados::APROBADA;
    }

    /**
     * @param  list<object>  $lineasPendmovp
     */
    public static function todasLineasPendmovpCompletas(array $lineasPendmovp): bool
    {
        foreach ($lineasPendmovp as $row) {
            $cantOc = (float) ($row->penvp_cantidad ?? 0);
            $cantEntr = (float) ($row->penvp_cantentr ?? 0);
            $partida = (int) ($row->penvp_partida ?? 0);
            $cerrada = $partida === -1;
            $completa = $cerrada || ($cantOc > 0.000001 && $cantEntr + 0.000001 >= $cantOc);

            if (! $completa) {
                return false;
            }
        }

        return true;
    }
}
