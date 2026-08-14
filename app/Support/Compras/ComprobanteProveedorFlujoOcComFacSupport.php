<?php

namespace App\Support\Compras;

use App\Models\Compras\Configuracion_ComprobanteProveedor;
use App\Models\Compras\Ordencompra;

/**
 * Política de flujo OC → COM → factura por empresa (configuración comprobante proveedor).
 *
 * Un contrato vigente en la OC manda sobre la política de la empresa: define si la
 * factura exige COM y, si no, el origen de la imputación contable del neto.
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
     * Contrato vigente: sobrescribe la política (ruta con/sin recepción del contrato).
     *
     * @return array{
     *     exige_flujo: bool,
     *     es_anticipada: bool,
     *     tiene_com: bool,
     *     debe_asignar_com: bool,
     *     permite_factura_anticipada: bool,
     *     bloquea_sin_com: bool,
     *     contrato_es: bool,
     *     contrato_vigente: bool,
     *     contrato_requiere_recepcion: bool|null,
     *     contrato_imputacion: string|null,
     *     contrato_cuentacontable_id: int,
     *     contrato_fuera_de_vigencia: bool
     * }
     */
    public static function resolverPolitica(?Ordencompra $oc, bool $tieneComDisponibles, ?string $fechaYmd = null): array
    {
        $empresaId = (int) ($oc->empresa_id ?? 0);
        $exige = $oc ? self::exigeFlujo($empresaId) : false;
        $anticipada = self::esOcAnticipada($oc);

        $debeAsignarCom = $tieneComDisponibles;
        if ($exige && ! $tieneComDisponibles && ! $anticipada) {
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
            $debeAsignarCom = $tieneComDisponibles;
        }

        $contrato = OrdencompraContratoRutaFacturaSupport::resolver($oc, $fechaYmd);

        if ($contrato['aplica']) {
            if ($contrato['requiere_recepcion']) {
                $debeAsignarCom = true;
                $bloqueaSinCom = ! $tieneComDisponibles;
                $permiteAnticipada = false;
            } else {
                $debeAsignarCom = false;
                $bloqueaSinCom = false;
                $permiteAnticipada = false;
            }
        }

        return [
            'exige_flujo' => $exige,
            'es_anticipada' => $anticipada,
            'tiene_com' => $tieneComDisponibles,
            'debe_asignar_com' => $debeAsignarCom,
            'permite_factura_anticipada' => $permiteAnticipada,
            'bloquea_sin_com' => $bloqueaSinCom,
            'contrato_es' => (bool) ($contrato['es_contrato'] ?? false),
            'contrato_vigente' => (bool) ($contrato['aplica'] ?? false),
            'contrato_requiere_recepcion' => $contrato['aplica'] ? (bool) $contrato['requiere_recepcion'] : null,
            'contrato_imputacion' => $contrato['aplica'] ? $contrato['imputacion'] : null,
            'contrato_cuentacontable_id' => $contrato['aplica'] ? (int) ($contrato['cuentacontable_id'] ?? 0) : 0,
            'contrato_fuera_de_vigencia' => (bool) ($contrato['es_contrato'] && ! $contrato['vigente']),
        ];
    }

    public static function modoCargaSugerido(array $politica, ?string $modoActual = null): string
    {
        if ($politica['contrato_vigente'] ?? false) {
            if ($politica['contrato_requiere_recepcion'] ?? false) {
                return ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;
            }

            return ComprobanteProveedorModoCarga::SIN_RECEPCION;
        }

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
