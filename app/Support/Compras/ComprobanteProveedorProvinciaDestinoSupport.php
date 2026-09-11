<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Configuracion\Provincia;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;

/**
 * Destino de la mercadería / servicio en la factura de proveedor.
 *
 * Alimenta com_provincia_ibr (código Anita) y el recorte de base IIBB ARBA en el pago.
 * Default: Buenos Aires (id 2, código Anita 2, jurisdicción AFIP 902).
 */
final class ComprobanteProveedorProvinciaDestinoSupport
{
    public const DEFAULT_PROVINCIA_ID = 2;

    public const DEFAULT_CODIGO_ANITA = 2;

    public const JURISDICCION_AFIP = 902;

    public static function idDesdeRequest(mixed $raw): int
    {
        $id = (int) $raw;

        return $id > 0 ? $id : self::DEFAULT_PROVINCIA_ID;
    }

    public static function codigoAnita(?Provincia $provincia): int
    {
        if ($provincia !== null && filled($provincia->codigo)) {
            return (int) $provincia->codigo;
        }

        return self::DEFAULT_CODIGO_ANITA;
    }

    public static function esDestinoBuenosAires(?Comprobante_Proveedor $comprobante): bool
    {
        if ($comprobante === null) {
            return false;
        }

        $id = (int) ($comprobante->provincia_destino_id ?? 0);
        if ($id <= 0) {
            return true;
        }

        $provincia = $comprobante->relationLoaded('provinciaDestino')
            ? $comprobante->provinciaDestino
            : $comprobante->provinciaDestino()->first();

        if ($provincia === null) {
            return $id === self::DEFAULT_PROVINCIA_ID;
        }

        return IngresosBrutosProvinciaAnitaSupport::esBuenosAires($provincia)
            || (int) $provincia->id === self::DEFAULT_PROVINCIA_ID
            || (int) ($provincia->codigo ?? 0) === self::DEFAULT_CODIGO_ANITA
            || (int) ($provincia->jurisdiccion ?? 0) === self::JURISDICCION_AFIP;
    }

    /**
     * @return array{id:int, codigo:string, nombre:string, jurisdiccion:string}
     */
    public static function defaultParaFormulario(): array
    {
        $p = Provincia::query()->find(self::DEFAULT_PROVINCIA_ID);

        return [
            'id' => self::DEFAULT_PROVINCIA_ID,
            'codigo' => (string) ($p->codigo ?? self::DEFAULT_CODIGO_ANITA),
            'nombre' => (string) ($p->nombre ?? 'Buenos Aires'),
            'jurisdiccion' => (string) ($p->jurisdiccion ?? self::JURISDICCION_AFIP),
        ];
    }
}
