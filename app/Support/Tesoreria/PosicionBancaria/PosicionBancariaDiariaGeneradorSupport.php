<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
        int $diasProyectados = 5,
        int $saltoDias = 1,
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
        $this->presentarSaldos($wb);
        $this->aplicarResumenMeta($wb, $fecha, $usd, $eur);

        $statsCheques = $this->chequesExport->volcarEnSpreadsheet($wb, $fecha);
        $statsProy = $this->proyeccion->volcarEnSpreadsheet($wb, $fecha, $diasProyectados, $saltoDias);
        $this->presentarResumen($wb);
        $this->ordenarHojas($wb);

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

        $writer = new Xlsx($wb);
        // Sin precalcular: el motor deja en 0 el saldo_dia y Excel no lo vuelve a calcular.
        $writer->setPreCalculateFormulas(false);
        $writer->save($rutaSalida);

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
            $cell->setValue(ExcelDate::PHPToExcel($fecha));
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
        $wr->setCellValue('E1', ExcelDate::PHPToExcel($fecha));
        $wr->getStyle('E1')->getNumberFormat()->setFormatCode('DD/MM/YYYY');
        $wr->setCellValue('B41', $usd);
        $wr->setCellValue('B42', $eur);
    }

    private function presentarSaldos(Spreadsheet $wb): void
    {
        if (! $wb->sheetNameExists('Saldos')) {
            return;
        }
        $ws = $wb->getSheetByName('Saldos');
        $this->insertarEncabezadosSaldos($ws);

        $lastCol = Coordinate::columnIndexFromString($ws->getHighestColumn(1));
        $lastLetter = Coordinate::stringFromColumnIndex($lastCol);
        $maxRow = (int) $ws->getHighestDataRow();

        $ws->setCellValue('F1', 'Concepto');
        $ws->setCellValue('G1', 'Saldo del día');
        $ws->getStyle('A1:'.$lastLetter.'1')->getFont()->setBold(true)->getColor()->setRGB('17202A');
        $ws->getStyle('A1:'.$lastLetter.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
        $ws->getStyle('A1:'.$lastLetter.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension(1)->setRowHeight(22);

        for ($c = 8; $c <= $lastCol; $c++) {
            $col = Coordinate::stringFromColumnIndex($c);
            $ws->getStyle($col.'1')->getNumberFormat()->setFormatCode('d-mmm');
            $ws->getColumnDimension($col)->setWidth(16);
        }
        $ws->getColumnDimension('F')->setWidth(32);
        $ws->getColumnDimension('G')->setWidth(20);

        for ($r = 2; $r <= $maxRow; $r++) {
            $concepto = trim((string) $ws->getCell("F{$r}")->getValue());
            $soc = trim((string) $ws->getCell("B{$r}")->getValue());
            $banco = trim((string) $ws->getCell("C{$r}")->getValue());
            if ($soc === '' && $banco === '' && $concepto !== '') {
                continue;
            }
            $moneda = strtoupper(trim((string) $ws->getCell("D{$r}")->getValue()));
            $formato = match ($moneda) {
                'USD' => '"U$D" #,##0.00',
                'EUR' => '"€" #,##0.00',
                default => '"$" #,##0.00',
            };
            $ws->getStyle("G{$r}:{$lastLetter}{$r}")->getNumberFormat()->setFormatCode($formato);
            if (preg_match('/tr[aá]nsito/iu', $concepto) === 1 || stripos($concepto, 'transito') !== false) {
                $ws->getStyle("F{$r}:{$lastLetter}{$r}")->getFont()->getColor()->setRGB('C0392B');
            }
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $ws->getColumnDimension($col)->setVisible(false);
        }
        $ws->freezePane('H2');
        $ws->setAutoFilter('F1:'.$lastLetter.$maxRow);
        $ws->getSheetView()->setZoomScale(110);
    }

    private function insertarEncabezadosSaldos(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $nombresSoc = [
            'BIY' => 'BIYEMAS',
            'KAN' => 'KANDIKO',
            'REB' => 'REBISCO',
            'UT' => 'BIYEMAS - SKILL ON NET UT',
        ];
        $nombresBanco = [
            'MACRO' => 'BANCO MACRO',
            'ITAU' => 'BANCO ITAU',
            'FRANCES' => 'BANCO FRANCES',
            'BAPRO' => 'BANCO PROVINCIA',
            'MACO' => 'MACO',
            'BNA' => 'BNA',
            'BIBANK' => 'Bi Bank',
            'BIND' => 'Banco Industrial',
            'TESORERIA' => 'TESORERIA',
            'INVERSIONES' => 'INVERSIONES CORTO PLAZO',
            'CIUDAD' => 'BANCO CIUDAD',
            'MP' => 'MERCADO PAGO',
            'TOTAL' => 'TOTAL',
        ];

        $max = (int) $ws->getHighestDataRow();
        $filas = [];
        for ($r = 2; $r <= $max; $r++) {
            $soc = trim((string) $ws->getCell("B{$r}")->getValue());
            $banco = trim((string) $ws->getCell("C{$r}")->getValue());
            if ($soc === '' || $soc === 'TOT_BINGOS') {
                continue;
            }
            $filas[] = [$r, $soc, $banco];
        }

        $inserts = [];
        $socAnterior = null;
        $bancoAnterior = null;
        foreach ($filas as [$r, $soc, $banco]) {
            if ($soc !== $socAnterior) {
                $inserts[] = [$r, 'soc', $nombresSoc[$soc] ?? $soc];
                $inserts[] = [$r, 'banco', $nombresBanco[$banco] ?? $banco];
            } elseif ($banco !== $bancoAnterior) {
                $inserts[] = [$r, 'banco', $nombresBanco[$banco] ?? $banco];
            }
            $socAnterior = $soc;
            $bancoAnterior = $banco;
        }

        usort($inserts, static function (array $a, array $b): int {
            if ($a[0] !== $b[0]) {
                return $b[0] <=> $a[0];
            }

            return $a[1] === 'banco' ? -1 : 1;
        });

        $lastLetter = $ws->getHighestColumn(1);
        foreach ($inserts as [$fila, $tipo, $titulo]) {
            $ws->insertNewRowBefore($fila, 1);
            $ws->setCellValue("F{$fila}", $titulo);
            $rango = "A{$fila}:{$lastLetter}{$fila}";
            $ws->getStyle($rango)->getFont()->setBold(true);
            if ($tipo === 'soc') {
                $ws->getStyle($rango)->getFont()->setSize(13)->getColor()->setRGB('FFFFFF');
                $ws->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B4F72');
            } else {
                $ws->getStyle($rango)->getFont()->getColor()->setRGB('17202A');
                $ws->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D5D8DC');
            }
        }
    }

    private function presentarResumen(Spreadsheet $wb): void
    {
        if (! $wb->sheetNameExists('Resumen')) {
            return;
        }
        $wr = $wb->getSheetByName('Resumen');
        $wr->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('17324D');
        foreach ([3, 39, 63, 76] as $fila) {
            $wr->getStyle("A{$fila}:G{$fila}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $wr->getStyle("A{$fila}:G{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('17324D');
        }
        foreach ([5, 19, 31, 44, 65, 77] as $fila) {
            $wr->getStyle("A{$fila}:G{$fila}")->getFont()->setBold(true)->getColor()->setRGB('17202A');
            $wr->getStyle("A{$fila}:G{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
        }
        foreach ([17, 29, 37, 61, 74, 80, 84, 88] as $fila) {
            $wr->getStyle("A{$fila}:E{$fila}")->getFont()->setBold(true);
            $wr->getStyle("A{$fila}:E{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF2F8');
        }
        foreach (['B6:E17', 'B20:E29', 'B32:E37', 'B45:E61', 'B66:E74', 'B78:E80', 'B82:E84', 'B86:E88'] as $rango) {
            $wr->getStyle($rango)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D5D8DC');
            $wr->getStyle($rango)->getNumberFormat()->setFormatCode('#,##0.00');
            $wr->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        if ($wr->getCell('G76')->getValue() !== null && $wr->getCell('G76')->getValue() !== '') {
            $valor = $wr->getCell('G76')->getValue();
            if (is_string($valor)) {
                try {
                    $wr->setCellValue('G76', ExcelDate::PHPToExcel(Carbon::parse($valor)));
                } catch (\Throwable) {
                    // deja el valor si no es una fecha
                }
            }
            $wr->getStyle('G76')->getNumberFormat()->setFormatCode('DD/MM/YYYY');
        }
        $wr->getColumnDimension('A')->setWidth(52);
        foreach (['B', 'C', 'D', 'E'] as $col) {
            $wr->getColumnDimension($col)->setWidth(16);
        }
        $wr->freezePane('A3');
    }

    private function ordenarHojas(Spreadsheet $wb): void
    {
        $orden = [
            'Saldos',
            'Resumen',
            'Macro',
            'Macro (BMA)',
            'BAPRO',
            'Bi Bank',
            'Bind',
            'Resumen descubierto',
            'Cheques BSA',
            'Cheques KSA',
            'Cheques RSA',
            'ResumenCheques',
            '_MapaCodigos',
            '_Instrucciones',
        ];
        $indice = 0;
        foreach ($orden as $nombre) {
            if (! $wb->sheetNameExists($nombre)) {
                continue;
            }
            $wb->setIndexByName($nombre, $indice);
            $indice++;
        }
        if ($wb->sheetNameExists('Resumen')) {
            $wb->setActiveSheetIndex($wb->getIndex($wb->getSheetByName('Resumen')));
        }
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
