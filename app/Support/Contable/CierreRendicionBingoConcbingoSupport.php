<?php

namespace App\Support\Contable;

use App\Models\Caja\Bingo\BingoConceptoRendicion;

/**
 * Catálogo de conceptos del cierre bingo desde anitaERP (bingo_concepto_rendicion).
 * Sustituye la lectura live de concbingo vía bridge Anita (bingo vive en ERP desde ago/2026).
 */
final class CierreRendicionBingoConcbingoSupport
{
    /** @var array<int, array<int, array<string, mixed>>> */
    private static array $cachePorEmpresa = [];

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
        if (isset(self::$cachePorEmpresa[$empresaId])) {
            return self::$cachePorEmpresa[$empresaId];
        }

        $indice = [];

        $conceptos = BingoConceptoRendicion::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', BingoConceptoRendicion::ESTADO_ACTIVO)
            ->whereNotNull('codigo_anita')
            ->where('codigo_anita', '>', 0)
            ->orderBy('codigo_anita')
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'codigo', 'codigo_anita', 'detalle', 'porcentaje']);

        foreach ($conceptos as $concepto) {
            $codigoErp = strtoupper(trim((string) ($concepto->codigo ?? '')));
            $tipo = CierreRendicionBingoConceptoTipos::tipoPorCodigoErp($codigoErp);
            if ($tipo === null) {
                continue;
            }

            $codigoAnita = (int) $concepto->codigo_anita;
            if ($codigoAnita <= 0 || isset($indice[$codigoAnita])) {
                continue;
            }

            $indice[$codigoAnita] = self::filaCatalogo(
                $empresaId,
                $codigoAnita,
                trim((string) ($concepto->detalle ?? '')) !== ''
                    ? trim((string) $concepto->detalle)
                    : $codigoErp,
                $tipo,
                round((float) ($concepto->porcentaje ?? 0), 4),
                0,
                0,
            );
        }

        foreach (CierreRendicionBingoConceptoTipos::extrasCatalogoReporte() as $extra) {
            $codigoAnita = (int) ($extra['concepto'] ?? 0);
            if ($codigoAnita <= 0 || isset($indice[$codigoAnita])) {
                continue;
            }
            $indice[$codigoAnita] = self::filaCatalogo(
                $empresaId,
                $codigoAnita,
                (string) ($extra['desc'] ?? ('Concepto '.$codigoAnita)),
                (string) ($extra['tipo_conc'] ?? ''),
                round((float) ($extra['porcentaje'] ?? 0), 4),
                (int) ($extra['cta_contable_codigo'] ?? 0),
                (int) ($extra['contrapartida_codigo'] ?? 0),
            );
        }

        ksort($indice, SORT_NUMERIC);
        self::$cachePorEmpresa[$empresaId] = $indice;

        return $indice;
    }

    /**
     * @return array{
     *   concepto: int,
     *   desc: string,
     *   tipo_conc: string,
     *   porcentaje: float,
     *   cta_contable_codigo: int,
     *   contrapartida_codigo: int,
     *   cuenta_debe_id: int,
     *   cuenta_haber_id: int
     * }
     */
    private static function filaCatalogo(
        int $empresaId,
        int $concepto,
        string $desc,
        string $tipo,
        float $porcentaje,
        int $ctaCodigo,
        int $contraCodigo,
    ): array {
        return [
            'concepto' => $concepto,
            'desc' => $desc,
            'tipo_conc' => $tipo,
            'porcentaje' => $porcentaje,
            'cta_contable_codigo' => $ctaCodigo,
            'contrapartida_codigo' => $contraCodigo,
            'cuenta_debe_id' => CierreRendicionBingoConfigSupport::resolverCuentacontableIdPorCodigo($empresaId, $ctaCodigo),
            'cuenta_haber_id' => CierreRendicionBingoConfigSupport::resolverCuentacontableIdPorCodigo($empresaId, $contraCodigo),
        ];
    }

    public static function limpiarCache(): void
    {
        self::$cachePorEmpresa = [];
    }
}
