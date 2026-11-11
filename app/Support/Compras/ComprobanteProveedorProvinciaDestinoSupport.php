<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Configuracion\Provincia;
use App\Support\Compras\Retencion\RetencionIibbCrownMatrizSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;

/**
 * Destino de la mercadería / servicio en la factura de proveedor.
 *
 * Alimenta com_provincia_ibr (código Anita) y el recorte de base IIBB en el pago.
 *
 * Default:
 * - Crown: Río Negro (id 3, código Anita 3, AFIP 916) — flete del proveedor.
 * - Resto: Buenos Aires (id 2 en AGG = BA; no usar el id 2 de Crown = Neuquén).
 */
final class ComprobanteProveedorProvinciaDestinoSupport
{
    /** Default AGG / ARBA (Buenos Aires). En Crown el id 2 es Neuquén. */
    public const DEFAULT_PROVINCIA_ID = 2;

    public const DEFAULT_CODIGO_ANITA = 2;

    public const JURISDICCION_AFIP = 902;

    public const DEFAULT_PROVINCIA_ID_CROWN = RetencionIibbCrownMatrizSupport::PROVINCIA_LOCAL_ID;

    public const DEFAULT_CODIGO_ANITA_CROWN = 3;

    public const JURISDICCION_AFIP_CROWN = 916;

    public static function defaultProvinciaId(): int
    {
        return EntornoEmpresaSupport::esCrown()
            ? self::DEFAULT_PROVINCIA_ID_CROWN
            : self::DEFAULT_PROVINCIA_ID;
    }

    public static function defaultCodigoAnita(): int
    {
        return EntornoEmpresaSupport::esCrown()
            ? self::DEFAULT_CODIGO_ANITA_CROWN
            : self::DEFAULT_CODIGO_ANITA;
    }

    public static function defaultJurisdiccionAfip(): int
    {
        return EntornoEmpresaSupport::esCrown()
            ? self::JURISDICCION_AFIP_CROWN
            : self::JURISDICCION_AFIP;
    }

    public static function idDesdeRequest(mixed $raw): int
    {
        $id = (int) $raw;

        return $id > 0 ? $id : self::defaultProvinciaId();
    }

    public static function codigoAnita(?Provincia $provincia): int
    {
        if ($provincia !== null && filled($provincia->codigo)) {
            return (int) $provincia->codigo;
        }

        return self::defaultCodigoAnita();
    }

    /**
     * ¿El destino entra a la base de retención del agente local?
     * Crown: Río Negro. Resto: Buenos Aires (ARBA).
     */
    public static function esDestinoJurisdiccionAgente(?Comprobante_Proveedor $comprobante): bool
    {
        if (EntornoEmpresaSupport::esCrown()) {
            return self::esDestinoRioNegro($comprobante);
        }

        return self::esDestinoBuenosAires($comprobante);
    }

    public static function esDestinoBuenosAires(?Comprobante_Proveedor $comprobante): bool
    {
        if ($comprobante === null) {
            return false;
        }

        $id = (int) ($comprobante->provincia_destino_id ?? 0);
        if ($id <= 0) {
            return ! EntornoEmpresaSupport::esCrown();
        }

        $provincia = $comprobante->relationLoaded('provinciaDestino')
            ? $comprobante->provinciaDestino
            : $comprobante->provinciaDestino()->first();

        if ($provincia === null) {
            return $id === self::DEFAULT_PROVINCIA_ID
                && ! EntornoEmpresaSupport::esCrown();
        }

        return IngresosBrutosProvinciaAnitaSupport::esBuenosAires($provincia)
            || (int) ($provincia->jurisdiccion ?? 0) === self::JURISDICCION_AFIP;
    }

    public static function esDestinoRioNegro(?Comprobante_Proveedor $comprobante): bool
    {
        if ($comprobante === null) {
            return false;
        }

        $id = (int) ($comprobante->provincia_destino_id ?? 0);
        if ($id <= 0) {
            return EntornoEmpresaSupport::esCrown();
        }

        $provincia = $comprobante->relationLoaded('provinciaDestino')
            ? $comprobante->provinciaDestino
            : $comprobante->provinciaDestino()->first();

        if ($provincia === null) {
            return $id === self::DEFAULT_PROVINCIA_ID_CROWN;
        }

        return (int) $provincia->id === self::DEFAULT_PROVINCIA_ID_CROWN
            || (int) ($provincia->codigo ?? 0) === self::DEFAULT_CODIGO_ANITA_CROWN
            || (int) ($provincia->jurisdiccion ?? 0) === self::JURISDICCION_AFIP_CROWN;
    }

    /**
     * @return array{id:int, codigo:string, nombre:string, jurisdiccion:string}
     */
    public static function defaultParaFormulario(): array
    {
        $id = self::defaultProvinciaId();
        $p = Provincia::query()->find($id);
        $nombreDefault = EntornoEmpresaSupport::esCrown() ? 'Rio Negro' : 'Buenos Aires';

        return [
            'id' => $id,
            'codigo' => (string) ($p->codigo ?? self::defaultCodigoAnita()),
            'nombre' => (string) ($p->nombre ?? $nombreDefault),
            'jurisdiccion' => (string) ($p->jurisdiccion ?? self::defaultJurisdiccionAfip()),
        ];
    }
}
