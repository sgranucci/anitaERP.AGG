<?php

namespace App\Exports\Contable;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\EfeMensualReporteService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EfeMensualExport
{
    private const PLANTILLA_RELATIVA = 'templates/contable/efe_plantilla.xlsx';

    private const HOJA_DATOS = 'Datos';

    private const HOJA_INFORME_CONCEPTOS = 'Informe de Conceptos';

    private const HOJA_BIYEMAS = 'Biyemas';

    private const HOJA_SUMARIAS = 'Sumarias';

    private const HOJA_POS_FIN = 'pos fin Biy';

    private const FILA_INICIO_DATOS = 7;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, mixed>|null */
    private ?array $resultado = null;

    public function __construct(
        private readonly EfeMensualReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros, ?array $resultado = null): self
    {
        $this->filtros = $filtros;
        $this->resultado = $resultado;

        return $this;
    }

    public function download(string $nombreArchivo): StreamedResponse
    {
        $resultado = $this->resultado ?? $this->reporteService->generarDesdeFiltros($this->filtros);
        $spreadsheet = $this->cargarPlantilla();
        $this->poblarInformeConceptos($spreadsheet, $resultado['conceptos_informe'] ?? []);
        $this->poblarDatos($spreadsheet, $resultado['filas_datos'] ?? []);
        $this->poblarSumarias($spreadsheet, $resultado['sumarias'] ?? []);
        $this->poblarPosicionFinanciera($spreadsheet, $resultado['posicion_financiera'] ?? []);
        $this->renombrarHojaEmpresa($spreadsheet, $resultado);
        $this->actualizarCabeceraBiyemas($spreadsheet, $resultado);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function cargarPlantilla(): Spreadsheet
    {
        $path = storage_path(self::PLANTILLA_RELATIVA);
        if (! is_file($path)) {
            throw new \RuntimeException('No se encontró la plantilla EFE en '.$path);
        }

        return IOFactory::load($path);
    }

    /**
     * @param  list<array{id: int, nombre: string}>  $conceptos
     */
    private function poblarInformeConceptos(Spreadsheet $spreadsheet, array $conceptos): void
    {
        $sheet = $spreadsheet->getSheetByName(self::HOJA_INFORME_CONCEPTOS);
        if ($sheet === null) {
            return;
        }

        $fila = 5;
        foreach ($conceptos as $concepto) {
            $sheet->setCellValue('A'.$fila, (int) ($concepto['id'] ?? 0));
            $sheet->setCellValue('B'.$fila, ' '.str_pad(mb_strtoupper((string) ($concepto['nombre'] ?? '')), 30, ' ', STR_PAD_RIGHT).' ');
            $fila++;
        }

        while ($sheet->getCell('A'.$fila)->getValue() !== null || $sheet->getCell('B'.$fila)->getValue() !== null) {
            $sheet->setCellValue('A'.$fila, null);
            $sheet->setCellValue('B'.$fila, null);
            $fila++;
            if ($fila > 500) {
                break;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function poblarDatos(Spreadsheet $spreadsheet, array $filas): void
    {
        $sheet = $spreadsheet->getSheetByName(self::HOJA_DATOS);
        if ($sheet === null) {
            return;
        }

        $ultimaUsada = $sheet->getHighestDataRow();
        for ($r = self::FILA_INICIO_DATOS; $r <= max($ultimaUsada, self::FILA_INICIO_DATOS); $r++) {
            foreach (range('A', 'S') as $col) {
                $sheet->setCellValue($col.$r, null);
            }
        }

        $fila = self::FILA_INICIO_DATOS;
        foreach ($filas as $row) {
            $sheet->setCellValue('A'.$fila, '=IF(LEFT(C'.$fila.',5)="conce",C'.$fila.',A'.$fila.')');
            $sheet->setCellValue('B'.$fila, (string) ($row['clasificacion_efe'] ?? ''));
            $sheet->setCellValue('C'.$fila, (string) ($row['cuenta_codigo'] ?? ''));
            $sheet->setCellValue('D'.$fila, (string) ($row['cuenta_nombre'] ?? ''));
            if (! empty($row['fecha'])) {
                $sheet->setCellValue('E'.$fila, ExcelDate::PHPToExcel(
                    Carbon::createFromFormat('Ymd', str_pad((string) $row['fecha'], 8, '0', STR_PAD_LEFT))->startOfDay()
                ));
            }
            $sheet->setCellValue('F'.$fila, (int) ($row['nro_asiento'] ?? 0));
            $sheet->setCellValue('G'.$fila, (string) ($row['tipo_comp'] ?? ''));
            $sheet->setCellValue('H'.$fila, (string) ($row['comprobante'] ?? ''));
            $sheet->setCellValue('I'.$fila, (string) ($row['cheque'] ?? ''));
            $sheet->setCellValue('J'.$fila, (int) ($row['nro_oc'] ?? 0));
            $sheet->setCellValue('K'.$fila, (string) ($row['descripcion'] ?? ''));
            $sheet->setCellValue('L'.$fila, (string) ($row['moneda_abrev'] ?? ''));
            $sheet->setCellValue('M'.$fila, (float) ($row['cotizacion'] ?? 0));
            if (($row['mon_referencia'] ?? null) !== null) {
                $sheet->setCellValue('N'.$fila, (float) $row['mon_referencia']);
            }
            if (($row['pagos'] ?? null) !== null) {
                $sheet->setCellValue('O'.$fila, (float) $row['pagos']);
            }
            if (($row['cobros'] ?? null) !== null) {
                $sheet->setCellValue('P'.$fila, (float) $row['cobros']);
            }
            $sheet->setCellValue('S'.$fila, (int) ($row['empresa_id'] ?? 0));
            $fila++;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function poblarSumarias(Spreadsheet $spreadsheet, array $filas): void
    {
        $sheet = $spreadsheet->getSheetByName(self::HOJA_SUMARIAS);
        if ($sheet === null) {
            return;
        }

        $fila = 2;
        foreach ($filas as $row) {
            $sheet->setCellValue('A'.$fila, (string) ($row['cuenta_codigo'] ?? ''));
            $sheet->setCellValue('B'.$fila, (string) ($row['cuenta_nombre'] ?? ''));
            $sheet->setCellValue('C'.$fila, (float) ($row['saldo_ejer'] ?? 0));
            $sheet->setCellValue('D'.$fila, (float) ($row['ajuste'] ?? 0));
            $sheet->setCellValue('E'.$fila, (float) ($row['saldo_ajustado'] ?? 0));
            $sheet->setCellValue('F'.$fila, (float) ($row['saldo_mes_anterior'] ?? 0));
            $sheet->setCellValue('G'.$fila, (float) ($row['saldo_mes_anterior'] ?? 0));
            $fila++;
        }
    }

    /**
     * @param  array<string, mixed>  $posicionFinanciera
     */
    private function poblarPosicionFinanciera(Spreadsheet $spreadsheet, array $posicionFinanciera): void
    {
        $sheet = $spreadsheet->getSheetByName(self::HOJA_POS_FIN);
        if ($sheet === null) {
            return;
        }

        $totales = $posicionFinanciera['totales_por_etiqueta'] ?? [];
        if ($totales === []) {
            return;
        }

        $ultima = min($sheet->getHighestRow(), 250);
        for ($fila = 1; $fila <= $ultima; $fila++) {
            $etiqueta = trim((string) $sheet->getCell('A'.$fila)->getValue());
            if ($etiqueta === '') {
                continue;
            }

            $valor = $this->resolverTotalPosFin($totales, $etiqueta);
            if ($valor !== null) {
                $sheet->setCellValue('B'.$fila, $valor);
            }
        }
    }

    /**
     * @param  array<string, float>  $totales
     */
    private function resolverTotalPosFin(array $totales, string $etiqueta): ?float
    {
        if (isset($totales[$etiqueta])) {
            return (float) $totales[$etiqueta];
        }

        $normalizada = preg_replace('/\s+/', ' ', mb_strtoupper(trim($etiqueta))) ?? '';
        foreach ($totales as $clave => $valor) {
            $claveNorm = preg_replace('/\s+/', ' ', mb_strtoupper(trim($clave))) ?? '';
            if ($claveNorm === $normalizada) {
                return (float) $valor;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function renombrarHojaEmpresa(Spreadsheet $spreadsheet, array $resultado): void
    {
        $sheet = $spreadsheet->getSheetByName(self::HOJA_BIYEMAS);
        if ($sheet === null) {
            return;
        }

        $empresaId = (int) ($resultado['parametros']['empresa_id'] ?? 0);
        $empresa = $empresaId > 0 ? $this->empresaRepository->find($empresaId) : null;
        if ($empresa === null) {
            return;
        }

        $titulo = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', (string) $empresa->nombre) ?? 'Empresa';
        $titulo = trim(mb_substr($titulo, 0, 31));
        if ($titulo === '') {
            return;
        }

        $sheet->setTitle($titulo);
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function actualizarCabeceraBiyemas(Spreadsheet $spreadsheet, array $resultado): void
    {
        $empresaId = (int) ($resultado['parametros']['empresa_id'] ?? 0);
        $empresa = $empresaId > 0 ? $this->empresaRepository->find($empresaId) : null;
        $tituloHoja = $empresa !== null
            ? mb_substr(trim(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', (string) $empresa->nombre) ?? ''), 0, 31)
            : self::HOJA_BIYEMAS;

        $sheet = $spreadsheet->getSheetByName($tituloHoja);
        if ($sheet === null) {
            $sheet = $spreadsheet->getSheetByName(self::HOJA_BIYEMAS);
        }
        if ($sheet === null) {
            return;
        }

        if ($empresa !== null) {
            $sheet->setCellValue('B4', mb_strtoupper((string) $empresa->nombre));
        }

        $mes = (int) ($resultado['parametros']['mes'] ?? 0);
        $anio = (int) ($resultado['parametros']['anio'] ?? 0);
        if ($mes > 0 && $anio > 0) {
            $cierre = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();
            $apertura = $cierre->copy()->subMonth()->endOfMonth();
            $sheet->setCellValue('D8', ExcelDate::PHPToExcel($cierre));
            $sheet->setCellValue('I8', ExcelDate::PHPToExcel($apertura));
        }
    }
}
