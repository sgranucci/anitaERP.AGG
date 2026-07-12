<?php

namespace App\Support\Contable;

use App\Support\Contable\Efe\EfeAnitaBridgeReader;

/**
 * Catálogo concbingo (Anita) para tipos, porcentajes y cuentas de canones.
 */
final class CierreRendicionBingoConcbingoSupport
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $cachePorConcepto = null;

    /**
     * @return array<int, array{
     *   concepto: int,
     *   desc: string,
     *   tipo_conc: string,
     *   porcentaje: float,
     *   cta_contable_codigo: int,
     *   contrapartida_codigo: int,
     *   cuenta_debe_id: int,
     *   cuenta_haber_id: int
     * }>
     */
    public static function indicePorConcepto(int $empresaId): array
    {
        if (self::$cachePorConcepto !== null) {
            return self::$cachePorConcepto;
        }

        $reader = new EfeAnitaBridgeReader;
        $filas = $reader->listarConcbingoExtendido();
        $indice = [];

        foreach ($filas as $row) {
            $concepto = (int) ($row->concb_concepto ?? 0);
            if ($concepto <= 0) {
                continue;
            }

            $ctaCodigo = (int) ($row->concb_cta_contable ?? 0);
            $contraCodigo = (int) ($row->concb_contrapartida ?? 0);

            $indice[$concepto] = [
                'concepto' => $concepto,
                'desc' => trim((string) ($row->concb_desc ?? '')),
                'tipo_conc' => trim((string) ($row->concb_tipo_conc ?? '')),
                'porcentaje' => round((float) ($row->concb_porcentaje ?? 0), 4),
                'cta_contable_codigo' => $ctaCodigo,
                'contrapartida_codigo' => $contraCodigo,
                'cuenta_debe_id' => CierreRendicionBingoConfigSupport::resolverCuentacontableIdPorCodigo($empresaId, $ctaCodigo),
                'cuenta_haber_id' => CierreRendicionBingoConfigSupport::resolverCuentacontableIdPorCodigo($empresaId, $contraCodigo),
            ];
        }

        self::$cachePorConcepto = $indice;

        return $indice;
    }

    public static function limpiarCache(): void
    {
        self::$cachePorConcepto = null;
    }
}
