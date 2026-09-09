<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * NC/ND FCE en facturación mostrador (no POS gastronomía/estacionamiento).
 *
 * ARCA: NCE 203/208 + CbteAsoc a la FCE + dato adicional/opcional 22 (S/N anulación).
 */
final class ArcaFceNcMostradorSupport
{
    public const MARCA_ANULACION_LEYENDA = 'FCE22';

    /** @var list<int> */
    public const TIPOS_FACTURA_FCE = [201, 206, 211];

    /** @var list<int> */
    public const TIPOS_NC_ND_FCE = [202, 203, 207, 208, 212, 213];

    public static function esTipoFacturaFce(int $tipoAfip): bool
    {
        return in_array($tipoAfip, self::TIPOS_FACTURA_FCE, true);
    }

    public static function esTipoNcNdFce(int $tipoAfip): bool
    {
        return in_array($tipoAfip, self::TIPOS_NC_ND_FCE, true);
    }

    public static function facturaEsFce(?object $factura): bool
    {
        if (! is_object($factura)) {
            return false;
        }

        $asoc = self::parsearCodigoComprobante(trim((string) ($factura->codigo ?? '')));

        return $asoc !== null && self::esTipoFacturaFce((int) $asoc['tipo']);
    }

    /**
     * @return array{tipo:int, ptovta:int, nro:int}|null
     */
    public static function parsearCodigoComprobante(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '' || ! preg_match('/^([A-Z]{3})\s+([A-Z])-(\d+)-(\d+)$/i', $codigo, $m)) {
            return null;
        }

        $tipoAfip = ArcaCaeaAnitaTipoAfipSupport::tipoAfipDesdeAnita((string) $m[1], (string) $m[2]);
        if ($tipoAfip <= 0) {
            return null;
        }

        return [
            'tipo' => $tipoAfip,
            'ptovta' => (int) $m[3],
            'nro' => (int) $m[4],
        ];
    }

    /**
     * Normaliza anulación S/N para opcional 22.
     */
    public static function normalizarAnulacion(?string $valor): ?string
    {
        $v = strtoupper(trim((string) $valor));
        if ($v === 'S' || $v === 'N') {
            return $v;
        }

        return null;
    }

    public static function anexarMarcaAnulacionLeyenda(string $leyenda, string $anulacionSn): string
    {
        $leyenda = self::quitarMarcaAnulacionLeyenda($leyenda);
        $marca = '['.self::MARCA_ANULACION_LEYENDA.':'.$anulacionSn.']';

        return trim($leyenda) === '' ? $marca : rtrim($leyenda)."\n".$marca;
    }

    public static function quitarMarcaAnulacionLeyenda(string $leyenda): string
    {
        $limpia = preg_replace('/\s*\['.preg_quote(self::MARCA_ANULACION_LEYENDA, '/').':[SN]\]\s*/i', '', $leyenda) ?? $leyenda;

        return trim($limpia);
    }

    public static function leerAnulacionDesdeLeyenda(?string $leyenda): ?string
    {
        if ($leyenda === null || $leyenda === '') {
            return null;
        }
        if (! preg_match('/\['.preg_quote(self::MARCA_ANULACION_LEYENDA, '/').':([SN])\]/i', $leyenda, $m)) {
            return null;
        }

        return strtoupper($m[1]);
    }

    /**
     * @param  array{tipo:int, ptovta:int, nro:int}  $asoc
     * @return array{tipo:int, ptovta:int, nro:int, cuit?:string, cbtefch?:string}
     */
    public static function enriquecerAsociadoConEmisor(array $asoc, ?object $empresa, ?object $facturaOrigen): array
    {
        $cuit = preg_replace('/\D+/', '', (string) ($empresa->nroinscripcion ?? '')) ?? '';
        if ($cuit !== '') {
            $asoc['cuit'] = $cuit;
        }

        $fecha = $facturaOrigen->fecha ?? null;
        if ($fecha) {
            $asoc['cbtefch'] = date('Ymd', strtotime((string) $fecha));
        }

        return $asoc;
    }
}
