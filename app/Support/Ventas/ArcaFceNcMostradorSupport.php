<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Configuracion\ParametroSistemaSupport;

/**
 * NC/ND FCE en facturación mostrador (no POS gastronomía/estacionamiento).
 *
 * ARCA (MTXCA):
 * - Origen FCE → siempre NCE/NDE (203/208/…): un CbteAsoc FCE + CUIT (209/210)
 *   + opcional 22. El tope MiPyME (obs. 151/152) aplica a emitir FCE, no a la NC.
 * - NC 003/008 con origen FAC: CbteAsoc FAC sin CUIT (204). Periodo solo si no hay puntual.
 * - NC 003 no puede asociar FCE (obs. 213) ni alcanzar con solo período si emisor/receptor
 *   son candidatos FCE.
 */
final class ArcaFceNcMostradorSupport
{
    public const MARCA_ANULACION_LEYENDA = 'FCE22';

    /** Remitos de terceros: único caso donde CUIT en asoc aplica fuera de NCE (obs. 204). */
    private const TIPOS_ASOC_CUIT_REMITO = [88, 990, 991];

    /** @var list<int> */
    public const TIPOS_FACTURA_FCE = [201, 206, 211];

    /** @var list<int> */
    public const TIPOS_NC_ND_FCE = [202, 203, 207, 208, 212, 213];

    /**
     * Tope MiPyME para emitir FCE (FAC→FCE). No decide NCE vs NC sobre una FCE.
     */
    public static function correspondeEmitirNcNdFce(float $totalComprobante): bool
    {
        $tope = ParametroSistemaSupport::limiteFce();

        return $tope > 0.0 && $totalComprobante >= $tope;
    }

    public static function esTipoFacturaFce(int $tipoAfip): bool
    {
        return in_array($tipoAfip, self::TIPOS_FACTURA_FCE, true);
    }

    public static function esTipoNcNdFce(int $tipoAfip): bool
    {
        return in_array($tipoAfip, self::TIPOS_NC_ND_FCE, true);
    }

    /**
     * Arma CbteAsoc para ARCA según el tipo de NC/ND que se autoriza.
     *
     * @param  list<array{tipo:int, ptovta:int, nro:int}>  $asocs
     * @return list<array{tipo:int, ptovta:int, nro:int, cuit?:string, cbtefch?:string}>
     */
    public static function asociadosParaArca(
        array $asocs,
        int $codigoTipoNcNd,
        ?object $empresa = null,
        ?object $facturaOrigen = null
    ): array {
        $emiteNcNdFce = self::esTipoNcNdFce($codigoTipoNcNd);
        $out = [];
        foreach ($asocs as $asoc) {
            if (! is_array($asoc)) {
                continue;
            }
            $tipoAsoc = (int) ($asoc['tipo'] ?? 0);
            // NC/ND FE (003/008/…): FCE como asociado → ARCA 213.
            if (! $emiteNcNdFce && self::esTipoFacturaFce($tipoAsoc)) {
                continue;
            }
            $out[] = self::enriquecerAsociadoConEmisor(
                $asoc,
                $empresa,
                $facturaOrigen,
                $emiteNcNdFce
            );
        }

        return $out;
    }

    /**
     * @param  array{tipo:int, ptovta:int, nro:int}  $asoc
     * @return array{tipo:int, ptovta:int, nro:int, cuit?:string, cbtefch?:string}
     */
    public static function enriquecerAsociadoConEmisor(
        array $asoc,
        ?object $empresa,
        ?object $facturaOrigen,
        bool $emiteNcNdFce = false
    ): array {
        $tipoAsoc = (int) ($asoc['tipo'] ?? 0);
        // 204: CUIT solo remito 88/990; 209: NCE/NDE exige CUIT del asociado.
        $incluirCuit = $emiteNcNdFce || in_array($tipoAsoc, self::TIPOS_ASOC_CUIT_REMITO, true);
        if ($incluirCuit) {
            $cuit = preg_replace('/\D+/', '', (string) ($empresa->nroinscripcion ?? '')) ?? '';
            if ($cuit !== '') {
                $asoc['cuit'] = $cuit;
            }
        } else {
            unset($asoc['cuit']);
        }

        $fecha = $facturaOrigen->fecha ?? null;
        if ($fecha) {
            $asoc['cbtefch'] = date('Ymd', strtotime((string) $fecha));
        }

        return $asoc;
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
}
