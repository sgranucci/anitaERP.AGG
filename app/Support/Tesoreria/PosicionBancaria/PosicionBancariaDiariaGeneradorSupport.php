<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Orquesta la generación del Excel de Posición bancaria diaria
 * (Saldos desde Interbanking + Resumen + Cheques portfolio + Disponible HOY).
 */
final class PosicionBancariaDiariaGeneradorSupport
{
    public function __construct(
        private readonly PosicionBancariaSaldosInterbankingSupport $saldosIb = new PosicionBancariaSaldosInterbankingSupport(),
        private readonly PosicionBancariaChequePlantillaExportSupport $chequesExport = new PosicionBancariaChequePlantillaExportSupport(),
        private readonly PosicionBancariaProyeccionSupport $proyeccion = new PosicionBancariaProyeccionSupport(),
    ) {
    }

    /**
     * @return array{
     *   path: string,
     *   fecha: string,
     *   fecha_saldos: string,
     *   cotizacion_usd: float,
     *   cotizacion_eur: float,
     *   saldos_codigos: int,
     *   saldos_sin_mapear: int,
     *   cheques_filas: int,
     *   proyeccion_hojas: int,
     *   advertencias: list<string>
     * }
     */
    public function generar(
        Carbon $fecha,
        ?string $rutaSalida = null,
        ?float $cotizacionUsd = null,
        ?float $cotizacionEur = null,
        ?string $plantillaBase = null,
    ): array {
        $advertencias = [];
        $plantillaBase ??= base_path('docs/tesoreria/posicion-bancaria-diaria/Posicion_Bancos_plantilla.xlsx');
        if (! is_file($plantillaBase)) {
            throw new RuntimeException('No existe la plantilla base: '.$plantillaBase);
        }

        $saldos = $this->saldosIb->saldosPorCodigo($fecha);
        $fechaSaldos = Carbon::parse($saldos['fecha']);
        if ($fechaSaldos->toDateString() !== $fecha->toDateString()) {
            $advertencias[] = 'No hay saldos Interbanking para '.$fecha->toDateString()
                .'; se usó '.$fechaSaldos->toDateString().'.';
        }
        if ($saldos['sin_mapear'] !== []) {
            $advertencias[] = count($saldos['sin_mapear']).' cuentas IB sin mapear a código Saldos.';
        }

        [$usd, $eur] = $this->resolverCotizaciones($fecha, $cotizacionUsd, $cotizacionEur, $advertencias);

        $reader = IOFactory::createReader('Xlsx');
        $wb = $reader->load($plantillaBase);
        foreach ([
            'Cheques BSA', 'Cheques KSA', 'Cheques RSA', 'ResumenCheques',
            'Macro', 'Macro (BMA)', 'BAPRO', 'Bi Bank', 'Bind', 'Resumen descubierto',
        ] as $hoja) {
            if ($wb->sheetNameExists($hoja)) {
                $wb->removeSheetByIndex($wb->getIndex($wb->getSheetByName($hoja)));
            }
        }

        $this->aplicarSaldos($wb, $fechaSaldos, $saldos['por_codigo']);
        $this->aplicarResumenMeta($wb, $fecha, $usd, $eur);

        $statsCheques = $this->chequesExport->volcarEnSpreadsheet($wb, $fecha);
        $statsProy = $this->proyeccion->volcarEnSpreadsheet($wb, $fecha);

        // Preferir carpeta world/group-writable ya usada por exports (www-data del pool FPM).
        $rutaSalida ??= storage_path(
            'app/exports/posicion_bancaria/Posicion_Bancos_'.$fecha->format('Ymd').'_'.date('His').'.xlsx'
        );
        $dir = dirname($rutaSalida);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                $rutaSalida = sys_get_temp_dir()
                    .'/Posicion_Bancos_'.$fecha->format('Ymd').'_'.date('His').'.xlsx';
                $dir = dirname($rutaSalida);
            }
        }
        if (! is_writable($dir)) {
            $rutaSalida = sys_get_temp_dir()
                .'/Posicion_Bancos_'.$fecha->format('Ymd').'_'.date('His').'.xlsx';
        }

        (new Xlsx($wb))->save($rutaSalida);

        return [
            'path' => $rutaSalida,
            'fecha' => $fecha->toDateString(),
            'fecha_saldos' => $fechaSaldos->toDateString(),
            'cotizacion_usd' => $usd,
            'cotizacion_eur' => $eur,
            'saldos_codigos' => count($saldos['por_codigo']),
            'saldos_sin_mapear' => count($saldos['sin_mapear']),
            'cheques_filas' => (int) ($statsCheques['filas'] ?? 0),
            'proyeccion_hojas' => (int) ($statsProy['hojas'] ?? 0),
            'advertencias' => $advertencias,
            'detalle_saldos' => $saldos['detalle'],
            'sin_mapear' => $saldos['sin_mapear'],
        ];
    }

    /**
     * @param  array<string, float>  $porCodigo
     */
    private function aplicarSaldos(Spreadsheet $wb, Carbon $fecha, array $porCodigo): void
    {
        if (! $wb->sheetNameExists('Saldos')) {
            throw new RuntimeException('La plantilla no tiene hoja Saldos.');
        }
        $ws = $wb->getSheetByName('Saldos');
        $maxCol = Coordinate::columnIndexFromString($ws->getHighestColumn(1));
        $fechaCol = null;
        for ($c = 8; $c <= $maxCol; $c++) {
            $v = $ws->getCell(Coordinate::stringFromColumnIndex($c).'1')->getValue();
            if ($v instanceof \DateTimeInterface) {
                $d = Carbon::instance(\DateTimeImmutable::createFromInterface($v));
            } elseif (is_numeric($v)) {
                try {
                    $d = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v));
                } catch (\Throwable) {
                    continue;
                }
            } elseif (is_string($v) && $v !== '') {
                try {
                    $d = Carbon::parse($v);
                } catch (\Throwable) {
                    continue;
                }
            } else {
                continue;
            }
            if ($d->toDateString() === $fecha->toDateString()) {
                $fechaCol = $c;
                break;
            }
        }

        if ($fechaCol === null) {
            $fechaCol = $maxCol + 1;
            $cell = $ws->getCell(Coordinate::stringFromColumnIndex($fechaCol).'1');
            $cell->setValue($fecha->toDateString());
            $cell->getStyle()->getNumberFormat()->setFormatCode('DD/MM/YYYY');
            // Actualizar fórmulas saldo_dia (col G) al nuevo rango de fechas
            $lastLetter = Coordinate::stringFromColumnIndex($fechaCol);
            $maxRow = (int) $ws->getHighestDataRow();
            for ($r = 2; $r <= $maxRow; $r++) {
                $ws->setCellValue(
                    "G{$r}",
                    '=IFERROR(INDEX(H'.$r.':'.$lastLetter.$r.',MATCH(Resumen!$E$1,H$1:'.$lastLetter.'$1,0)),0)'
                );
            }
        }

        $colLetter = Coordinate::stringFromColumnIndex($fechaCol);
        $maxRow = (int) $ws->getHighestDataRow();
        for ($r = 2; $r <= $maxRow; $r++) {
            $codigo = trim((string) $ws->getCell("A{$r}")->getValue());
            if ($codigo === '' || ! isset($porCodigo[$codigo])) {
                continue;
            }
            // No pisar TRANSITO salvo que venga mapeado
            $ws->setCellValue("{$colLetter}{$r}", $porCodigo[$codigo]);
            $ws->getStyle("{$colLetter}{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    private function aplicarResumenMeta(Spreadsheet $wb, Carbon $fecha, float $usd, float $eur): void
    {
        if (! $wb->sheetNameExists('Resumen')) {
            return;
        }
        $wr = $wb->getSheetByName('Resumen');
        foreach ($wr->getMergeCells() as $range) {
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $m)) {
                $r1 = (int) $m[2];
                $r2 = (int) $m[4];
                if ($r2 >= 76 && $r1 <= 95) {
                    $wr->unmergeCells($range);
                }
            }
        }
        $wr->setCellValue('E1', $fecha->format('Y-m-d'));
        $wr->setCellValue('B41', $usd);
        $wr->setCellValue('B42', $eur);
    }

    /**
     * @param  list<string>  $advertencias
     * @return array{0: float, 1: float}
     */
    private function resolverCotizaciones(
        Carbon $fecha,
        ?float $usd,
        ?float $eur,
        array &$advertencias,
    ): array {
        if ($usd === null || $usd <= 0) {
            $usd = CotizacionTesoreriaConsultaSupport::ventaPorMonedaId($fecha, 2) ?? 0.0;
            if ($usd <= 0) {
                $usd = 1400.0;
                $advertencias[] = 'Sin cotización USD en tesorería; se usó placeholder 1400.';
            }
        }
        if ($eur === null || $eur <= 0) {
            $eur = CotizacionTesoreriaConsultaSupport::ventaPorMonedaId($fecha, 3) ?? 0.0;
            if ($eur <= 0) {
                $eur = 1500.0;
                $advertencias[] = 'Sin cotización EUR en tesorería; se usó placeholder 1500.';
            }
        }

        return [(float) $usd, (float) $eur];
    }
}
