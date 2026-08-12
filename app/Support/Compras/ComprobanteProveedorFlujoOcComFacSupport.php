<?php

namespace App\Support\Compras;

use App\Models\Compras\Configuracion_ComprobanteProveedor;
use App\Models\Compras\Ordencompra;

/**
 * Política de flujo OC → COM → factura por empresa (configuración comprobante proveedor).
 */
final class ComprobanteProveedorFlujoOcComFacSupport
{
    /**
     * Empresa con flujo estricto (estilo Biyemas): COM obligatoria salvo OC anticipada sin COM aún.
     */
    public static function exigeFlujo(int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return false;
        }

        return (bool) Configuracion_ComprobanteProveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('exige_flujo_oc_com_fac', true)
            ->value('exige_flujo_oc_com_fac');
    }

    public static function esOcAnticipada(?Ordencompra $oc): bool
    {
        if (! $oc) {
            return false;
        }

        $t = strtoupper(trim((string) ($oc->tratamiento ?? '')));

        return $t === 'ANTICIPADA' || $t === '2' || $t === 'S';
    }

    /**
     * Con flujo estricto: si hay COM disponible, debe facturarse contra COM
     * (también en OC anticipada que ya recibió).
     * Sin COM: solo se admite factura anticipada (ASIGNA_OC) si la OC es anticipada.
     *
     * @return array{
     *     exige_flujo: bool,
     *     es_anticipada: bool,
     *     tiene_com: bool,
     *     debe_asignar_com: bool,
     *     permite_factura_anticipada: bool,
     *     bloquea_sin_com: bool
     * }
     */
    public static function resolverPolitica(?Ordencompra $oc, bool $tieneComDisponibles): array
    {
        $empresaId = (int) ($oc->empresa_id ?? 0);
        $exige = $oc ? self::exigeFlujo($empresaId) : false;
        $anticipada = self::esOcAnticipada($oc);

        $debeAsignarCom = $tieneComDisponibles;
        if ($exige && ! $tieneComDisponibles && ! $anticipada) {
            // Sin COM y no anticipada: no se puede cargar factura en flujo estricto.
            $bloqueaSinCom = true;
        } else {
            $bloqueaSinCom = false;
        }

        $permiteAnticipada = $exige && $anticipada && ! $tieneComDisponibles;

        if ($exige && ! $tieneComDisponibles && $anticipada) {
            $debeAsignarCom = false;
        } elseif ($exige && $tieneComDisponibles) {
            $debeAsignarCom = true;
        } elseif (! $exige) {
            // Optativo: solo obliga COM si ya hay recepciones disponibles en el legajo.
            $debeAsignarCom = $tieneComDisponibles;
        }

        return [
            'exige_flujo' => $exige,
            'es_anticipada' => $anticipada,
            'tiene_com' => $tieneComDisponibles,
            'debe_asignar_com' => $debeAsignarCom,
            'permite_factura_anticipada' => $permiteAnticipada,
            'bloquea_sin_com' => $bloqueaSinCom,
        ];
    }

    public static function modoCargaSugerido(array $politica, ?string $modoActual = null): string
    {
        if ($politica['debe_asignar_com'] ?? false) {
            return ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;
        }

        if ($politica['permite_factura_anticipada'] ?? false) {
            return ComprobanteProveedorModoCarga::ASIGNA_OC;
        }

        $modo = (string) ($modoActual ?? '');
        if (in_array($modo, ComprobanteProveedorModoCarga::todos(), true)) {
            return $modo;
        }

        return ComprobanteProveedorModoCarga::SIN_RECEPCION;
    }
}
