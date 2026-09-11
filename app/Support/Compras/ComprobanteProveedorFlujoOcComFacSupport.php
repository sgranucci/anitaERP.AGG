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
        static $cache = [];
        if (array_key_exists($empresaId, $cache)) {
            return $cache[$empresaId];
        }

        return $cache[$empresaId] = (bool) Configuracion_ComprobanteProveedor::query()
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
     * Con flujo estricto:
     * - OC no anticipada + COM → factura contra COM.
     * - OC anticipada sin COM → factura anticipada (ASIGNA_OC); puede haber varias.
     * - OC anticipada con COM → Anita (a-compprov.c) pregunta S/N si aplicar a recepciones
     *   o seguir anticipada; acá no forzamos: el operador elige el modo.
     *
     * Contrato vigente: sobrescribe la política (ruta con/sin recepción del contrato).
     * NC / ND / REC no exigen COM aunque el contrato o el flujo de la empresa sí lo pidan para facturas.
     *
     * @return array{
     *     exige_flujo: bool,
     *     es_anticipada: bool,
     *     tiene_com: bool,
     *     debe_asignar_com: bool,
     *     permite_factura_anticipada: bool,
     *     anticipada_elige_modo: bool,
     *     bloquea_sin_com: bool,
     *     sin_com_por_tipo: bool,
     *     tipo_documento: string|null,
     *     contrato_es: bool,
     *     contrato_vigente: bool,
     *     contrato_requiere_recepcion: bool|null,
     *     contrato_imputacion: string|null,
     *     contrato_cuentacontable_id: int,
     *     contrato_fuera_de_vigencia: bool
     * }
     */
    public static function resolverPolitica(
        ?Ordencompra $oc,
        bool $tieneComDisponibles,
        ?string $fechaYmd = null,
        ?string $tipoDocumento = null,
    ): array
    {
        $empresaId = (int) ($oc->empresa_id ?? 0);
        $exige = $oc ? self::exigeFlujo($empresaId) : false;
        $anticipada = self::esOcAnticipada($oc);

        $bloqueaSinCom = false;
        $permiteAnticipada = false;
        $anticipadaEligeModo = false;
        $debeAsignarCom = $tieneComDisponibles;

        if ($exige) {
            if ($anticipada && ! $tieneComDisponibles) {
                $permiteAnticipada = true;
                $debeAsignarCom = false;
            } elseif ($anticipada && $tieneComDisponibles) {
                // a-compprov.c: pregunta si aplicar a OC anticipada con recepciones.
                $anticipadaEligeModo = true;
                $debeAsignarCom = false;
            } elseif (! $tieneComDisponibles) {
                $bloqueaSinCom = true;
                $debeAsignarCom = false;
            } else {
                $debeAsignarCom = true;
            }
        } else {
            $debeAsignarCom = $tieneComDisponibles;
        }

        $contrato = OrdencompraContratoRutaFacturaSupport::resolver($oc, $fechaYmd);

        if ($contrato['aplica']) {
            if ($contrato['requiere_recepcion']) {
                $debeAsignarCom = true;
                $bloqueaSinCom = ! $tieneComDisponibles;
                $permiteAnticipada = false;
                $anticipadaEligeModo = false;
            } else {
                $debeAsignarCom = false;
                $bloqueaSinCom = false;
                $permiteAnticipada = false;
                $anticipadaEligeModo = false;
            }
        }

        $sinComPorTipo = $tipoDocumento !== null
            && $tipoDocumento !== ''
            && ! OrdencompraLegajoDocumentoTipoSupport::exigeCom($tipoDocumento);
        if ($sinComPorTipo) {
            $debeAsignarCom = false;
            $bloqueaSinCom = false;
            $permiteAnticipada = false;
            $anticipadaEligeModo = false;
        }

        return [
            'exige_flujo' => $exige,
            'es_anticipada' => $anticipada,
            'tiene_com' => $tieneComDisponibles,
            'debe_asignar_com' => $debeAsignarCom,
            'permite_factura_anticipada' => $permiteAnticipada,
            'anticipada_elige_modo' => $anticipadaEligeModo,
            'bloquea_sin_com' => $bloqueaSinCom,
            'sin_com_por_tipo' => $sinComPorTipo,
            'tipo_documento' => $tipoDocumento,
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
        if ($politica['sin_com_por_tipo'] ?? false) {
            return ComprobanteProveedorModoCarga::SIN_RECEPCION;
        }

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

        // Anticipada con COM: Anita pregunta; sugerimos COM y dejamos el modo editable.
        if (($politica['anticipada_elige_modo'] ?? false)
            || (($politica['es_anticipada'] ?? false) && ($politica['tiene_com'] ?? false))) {
            $modo = (string) ($modoActual ?? '');
            if (in_array($modo, [
                ComprobanteProveedorModoCarga::ASIGNA_RECEPCION,
                ComprobanteProveedorModoCarga::ASIGNA_OC,
            ], true)) {
                return $modo;
            }

            return ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;
        }

        $modo = (string) ($modoActual ?? '');
        if (in_array($modo, ComprobanteProveedorModoCarga::todos(), true)) {
            return $modo;
        }

        return ComprobanteProveedorModoCarga::SIN_RECEPCION;
    }

    /**
     * @param  array<string, mixed>  $politica
     */
    public static function mensajeBloqueaSinCom(array $politica): string
    {
        if (! empty($politica['contrato_vigente']) && ($politica['contrato_requiere_recepcion'] ?? false)) {
            return 'El contrato vigente de esta OC exige recepción COM obligatoria. '
                .'Confirme una COM con provisión antes de cargar la factura.';
        }

        return 'Esta empresa exige el flujo OC → COM → factura: no hay recepción COM disponible '
            .'y la orden no es anticipada. Confirme una COM con provisión o marque la OC como anticipada.';
    }

    /**
     * @param  array<string, mixed>  $politica
     */
    public static function mensajeDebeAsignarCom(array $politica): string
    {
        if (! empty($politica['contrato_vigente']) && ($politica['contrato_requiere_recepcion'] ?? false)) {
            return 'El contrato vigente exige factura contra recepción (COM). Asigne una COM antes de guardar.';
        }

        return 'Hay recepción COM disponible en el legajo: debe asignarla a la factura. No se puede guardar sin COM.';
    }
}
