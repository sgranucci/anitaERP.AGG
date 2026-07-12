<?php

namespace App\Support\Contable\Efe;

use App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader;
use Carbon\Carbon;

/**
 * Anticipos gaming (114040-001) con mayor concepto 0 que Anita muestra en concepto 12.
 */
class EfeDatosGamingSuppliesSupport
{
    public const CONCEPTO_GAMING_SUPPLIES = 12;

    private const CUENTA_ANTICIPO_GAMING = 114040001;

    /** @var array<string, array<string, mixed>> */
    private array $auxpagPorRec = [];

    public function __construct(
        private readonly MayorConceptoAnitaBridgeReader $bridgeReader,
        private readonly EfeClasificacionConceptoSupport $clasificacionSupport,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @param  array<int, string>  $nombresConcepto
     * @return list<array<string, mixed>>
     */
    public function aplicar(array $filas, array $filtros, array $nombresConcepto): array
    {
        if ($filas === []) {
            return $filas;
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        if ($empresaId <= 0 || $mes <= 0 || $anio <= 0) {
            return $filas;
        }

        $inicio = Carbon::createFromDate($anio, $mes, 1);
        $bridge = $this->bridgeReader->cargarPeriodo(
            $empresaId,
            (int) $inicio->format('Ymd'),
            (int) $inicio->copy()->endOfMonth()->format('Ymd'),
        );
        $this->auxpagPorRec = $this->indexarAuxpagPorRec($bridge['auxpag'] ?? []);

        $nombreConcepto = $nombresConcepto[self::CONCEPTO_GAMING_SUPPLIES] ?? 'GAMING SUPPLIES';
        $clasificacion = $this->clasificacionSupport->formatearClave(
            self::CONCEPTO_GAMING_SUPPLIES,
            $nombreConcepto,
        );

        foreach ($filas as $indice => $fila) {
            if ((int) ($fila['cuenta'] ?? 0) !== self::CUENTA_ANTICIPO_GAMING) {
                continue;
            }

            if ((int) ($fila['concepto_id'] ?? 0) !== 0) {
                continue;
            }

            if (strtoupper(trim((string) ($fila['tipo_comp'] ?? ''))) !== 'OPP') {
                continue;
            }

            $rec = $this->extraerRecComprobante((string) ($fila['comprobante'] ?? ''));
            if ($rec === '' || ! $this->recEsGamingSupplies12($rec, (float) ($fila['pagos'] ?? 0))) {
                continue;
            }

            $filas[$indice]['concepto_id'] = self::CONCEPTO_GAMING_SUPPLIES;
            $filas[$indice]['concepto_nombre'] = $nombreConcepto;
            $filas[$indice]['clasificacion_efe'] = $clasificacion;
        }

        return $filas;
    }

    /**
     * @param  list<object>  $auxpag
     * @return array<string, array<string, mixed>>
     */
    private function indexarAuxpagPorRec(array $auxpag): array
    {
        /** @var array<string, array<string, mixed>> */
        $porRec = [];

        foreach ($auxpag as $aplicacion) {
            $rec = trim((string) ($aplicacion->axp_rec ?? ''));
            if ($rec === '') {
                continue;
            }

            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            $porRec[$rec]['tipos'][$tipoAp] = [
                'concepto' => (int) ($aplicacion->axp_concepto ?? 0),
                'monto' => round((float) ($aplicacion->axp_monto_ap ?? 0), 2),
            ];
        }

        return $porRec;
    }

    private function recEsGamingSupplies12(string $rec, float $pagos): bool
    {
        $tipos = $this->auxpagPorRec[$rec]['tipos'] ?? [];
        if ($tipos === []) {
            return false;
        }

        $pagos = round($pagos, 2);
        $tieneFis = isset($tipos['FIS']);
        $tieneTmb = isset($tipos['TMB']);
        $tieneFnb = isset($tipos['FNB']);
        $tieneChp = isset($tipos['CHP']);
        $tieneFns = isset($tipos['FNS']);

        if ($tieneTmb && $tieneFnb && ! $tieneFis) {
            return abs((float) ($tipos['TMB']['monto'] ?? 0) - $pagos) < 0.02;
        }

        if ($tieneChp && $tieneFns && ! $tieneFis && ! $tieneTmb) {
            $fnsConc = (int) ($tipos['FNS']['concepto'] ?? 0);
            if ($fnsConc !== 24) {
                return false;
            }

            return abs((float) ($tipos['CHP']['monto'] ?? 0) - $pagos) < 0.02;
        }

        return false;
    }

    private function extraerRecComprobante(string $comprobante): string
    {
        if (preg_match('/-(\d+)\s*$/', trim($comprobante), $matches)) {
            return $matches[1];
        }

        if (preg_match('/#(\d+)/', $comprobante, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
