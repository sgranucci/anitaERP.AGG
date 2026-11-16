<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Crown: el asiento de facturas de casino/gastronomía POS va por fecha de jornada.
 * La factura guarda fecha física y fechajornada; administración y ARCA usan la física.
 */
final class CrownFacturaAsientoFechaSupport
{
    public const ORIGEN_TRANSFERENCIA_CASINO = 'transferencia_casino';

    public const ORIGEN_GASTRONOMIA_POS = 'gastronomia_pos';

    public const ORIGEN_ADMINISTRACION = 'administracion';

    public const ORIGEN_ARCA = 'arca';

    /**
     * Fecha contable del asiento.
     */
    public static function resolver(string $fechaFisica, ?string $fechaJornada, bool $usarJornada): string
    {
        $fisica = trim($fechaFisica);
        if (! $usarJornada) {
            return $fisica;
        }

        $jornada = trim((string) $fechaJornada);
        if ($jornada === '') {
            return $fisica;
        }

        return $jornada;
    }

    /**
     * En Crown, POS gastronomía/estacionamiento/local usan jornada; administración no.
     *
     * @param  array<string, mixed>|null  $opcionesEmision
     */
    public static function usarJornadaEnEmisionPos(?array $opcionesEmision): bool
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return false;
        }

        $op = $opcionesEmision ?? [];

        return ! empty($op['omitir_movimiento_stock'])
            || ! empty($op['emision_pos_arca'])
            || ! empty($op['origen_estacionamiento'])
            || ! empty($op['origen_facturacion_local']);
    }

    /**
     * Transferencia casino/gastronomía Anita → ERP: asiento por jornada.
     * Importación ARCA / administración: fecha física.
     */
    public static function usarJornadaEnTransferencia(array $fila): bool
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return false;
        }

        if (array_key_exists('asiento_usa_jornada', $fila)) {
            return filter_var($fila['asiento_usa_jornada'], FILTER_VALIDATE_BOOLEAN);
        }

        $leyenda = (string) ($fila['leyenda'] ?? '');
        $obs = (string) ($fila['observacion'] ?? '');
        $texto = $leyenda.' '.$obs;
        if (stripos($texto, 'Importación ARCA') !== false || stripos($texto, 'Importacion ARCA') !== false) {
            return false;
        }

        // Default seguro para filas de TransferenciaVentaGastronomiaService.
        return true;
    }
}
