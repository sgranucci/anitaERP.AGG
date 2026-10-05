<?php

namespace App\Exports\Caja;

use App\Models\Caja\RendicionMaquina;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaComprobanteDatos;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class RendicionMaquinaComprobanteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithTitle
{
    private const COL_ULTIMA = 'C';

    /** @var list<array{tipo: string, celdas: list<mixed>, destacar?: bool}> */
    private array $filas = [];

    private bool $hayLogo = false;

    private int $filaTitulo = 1;

    /** @var list<int> */
    private array $filasSeccion = [];

    /** @var list<int> */
    private array $filasCabecera = [];

    /** @var list<int> */
    private array $filasDestacadas = [];

    private ?string $rutaLogo = null;

    public function __construct(private RendicionMaquina $rendicion)
    {
        $this->armarFilas();
    }

    public function view(): View
    {
        return view('exports.caja.rendicion_maquina_comprobante', [
            'filas' => $this->filas,
            'formatoNumero' => ExcelFormatoNumero::preferenciaGlobal(),
        ]);
    }

    public function title(): string
    {
        $codigo = trim((string) ($this->rendicion->codigo ?? ''));
        $titulo = $codigo !== '' ? 'Rend. '.$codigo : 'Rendición máquinas';

        return mb_substr($titulo, 0, 31);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 42,
            'C' => 18,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $col = self::COL_ULTIMA;

                if ($this->hayLogo && $this->rutaLogo !== null && is_file($this->rutaLogo)) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $drawing = new Drawing();
                    $drawing->setPath($this->rutaLogo);
                    $drawing->setHeight(48);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(5);
                    $drawing->setWorksheet($sheet);
                }

                $sheet->mergeCells('A'.$this->filaTitulo.':'.$col.$this->filaTitulo);
                $sheet->getStyle('A'.$this->filaTitulo)->getFont()->setName('Arial')->setSize(14)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaTitulo)->setRowHeight(22);

                $subtitulo = $this->filaTitulo + 1;
                $sheet->mergeCells('A'.$subtitulo.':'.$col.$subtitulo);
                $sheet->getStyle('A'.$subtitulo)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');

                foreach ($this->filasSeccion as $fila) {
                    $sheet->mergeCells('A'.$fila.':'.$col.$fila);
                    $sheet->getStyle('A'.$fila.':'.$col.$fila)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('85C1E9');
                    $sheet->getStyle('A'.$fila)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
                }

                foreach ($this->filasCabecera as $fila) {
                    $rango = 'A'.$fila.':'.$col.$fila;
                    $sheet->getStyle($rango)->getFont()->setName('Arial')->setBold(true)->getColor()->setRGB('17202A');
                    $sheet->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D6EAF8');
                }

                foreach ($this->filasDestacadas as $fila) {
                    $sheet->getStyle('A'.$fila.':'.$col.$fila)->getFont()->setBold(true);
                    $sheet->getStyle('A'.$fila.':'.$col.$fila)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('E8F4FC');
                }

                $ultima = count($this->filas);
                if ($ultima > 0) {
                    $sheet->getStyle('C1:C'.$ultima)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            },
        ];
    }

    private function armarFilas(): void
    {
        $datos = RendicionMaquinaComprobanteDatos::armar($this->rendicion);
        $formato = ExcelFormatoNumero::preferenciaGlobal();
        $num = static function (float $valor) use ($formato): string {
            if (ExcelFormatoNumero::esAuto($formato)) {
                return number_format($valor, 2, '.', '');
            }

            return ExcelFormatoNumero::formatearTexto($valor, $formato, 2);
        };

        $this->rutaLogo = EmpresaLogoArchivo::rutaLogoEmpresa($datos['empresa'] !== '' ? $datos['empresa'] : null)
            ?? EmpresaLogoArchivo::rutaLogoEmpresa((string) config('app.empresa'));
        $this->hayLogo = $this->rutaLogo !== null && is_file($this->rutaLogo);

        $fila = 1;
        if ($this->hayLogo) {
            $this->filas[] = ['tipo' => 'logo', 'celdas' => ['']];
            $fila++;
        }

        $this->filaTitulo = $fila;
        $this->filas[] = ['tipo' => 'titulo', 'celdas' => ['Rendición de máquinas']];
        $fila++;

        $nro = '';
        if ($datos['nro_oper_anita']) {
            $etiquetaNro = config('rendicion_maquina_anita.sincronizar') ? 'Nro. Anita' : 'Nro.';
            $nro = ' · '.$etiquetaNro.': '.$datos['nro_oper_anita'];
        }
        $this->filas[] = [
            'tipo' => 'subtitulo',
            'celdas' => ['Código: '.$datos['codigo'].$nro.' · Generado '.now()->format('d/m/Y H:i')],
        ];
        $fila++;

        foreach ([
            'Empresa' => $datos['empresa'],
            'Fecha' => $datos['fecha'],
            'Turno' => trim($datos['turno'].($datos['turno_codigo'] !== '' ? ' ('.$datos['turno_codigo'].')' : '')),
            'Estado' => $datos['estado'],
            'Supervisor' => $datos['supervisor'],
            'Cajero' => $datos['cajero'],
            'Auxiliar' => $datos['auxiliar'],
            'Registró' => $datos['registro'],
        ] as $etiqueta => $valor) {
            $this->filas[] = ['tipo' => 'meta', 'celdas' => [$etiqueta, $valor, '']];
            $fila++;
        }

        $this->filasSeccion[] = $fila;
        $this->filas[] = ['tipo' => 'seccion', 'celdas' => ['Totales de cierre']];
        $fila++;
        foreach ($datos['totales'] as $total) {
            if (! empty($total['destacar'])) {
                $this->filasDestacadas[] = $fila;
            }
            $this->filas[] = [
                'tipo' => 'importe',
                'celdas' => [$total['etiqueta'], '', $num((float) $total['valor'])],
                'destacar' => ! empty($total['destacar']),
            ];
            $fila++;
        }

        $this->filasSeccion[] = $fila;
        $this->filas[] = ['tipo' => 'seccion', 'celdas' => ['Datos principales']];
        $fila++;
        $this->filasCabecera[] = $fila;
        $this->filas[] = ['tipo' => 'cabecera', 'celdas' => ['Concepto', '', 'Importe']];
        $fila++;
        if ($datos['principales'] === []) {
            $this->filas[] = ['tipo' => 'vacio', 'celdas' => ['Sin datos', '', '']];
            $fila++;
        }
        foreach ($datos['principales'] as $filaDato) {
            $this->filas[] = [
                'tipo' => 'importe',
                'celdas' => [$filaDato['etiqueta'], '', $num((float) $filaDato['valor'])],
            ];
            $fila++;
        }

        $this->filasSeccion[] = $fila;
        $this->filas[] = ['tipo' => 'seccion', 'celdas' => ['Valores (cuentas de caja)']];
        $fila++;
        $this->filasCabecera[] = $fila;
        $this->filas[] = ['tipo' => 'cabecera', 'celdas' => ['Código', 'Cuenta', 'Monto']];
        $fila++;
        if ($datos['valores'] === []) {
            $this->filas[] = ['tipo' => 'vacio', 'celdas' => ['Sin valores cargados.', '', '']];
            $fila++;
        }
        foreach ($datos['valores'] as $valor) {
            $this->filas[] = [
                'tipo' => 'importe',
                'celdas' => [$valor['codigo'], $valor['cuenta'], $num((float) $valor['monto'])],
            ];
            $fila++;
        }
        $this->filasDestacadas[] = $fila;
        $this->filas[] = ['tipo' => 'importe', 'celdas' => ['Total valores', '', $num((float) $datos['total_valores'])], 'destacar' => true];
        $fila++;

        $this->filasSeccion[] = $fila;
        $this->filas[] = ['tipo' => 'seccion', 'celdas' => ['Apertura de gastos']];
        $fila++;
        $this->filasCabecera[] = $fila;
        $this->filas[] = ['tipo' => 'cabecera', 'celdas' => ['Código', 'Concepto', 'Monto']];
        $fila++;
        if ($datos['gastos'] === []) {
            $this->filas[] = ['tipo' => 'vacio', 'celdas' => ['Sin gastos.', '', '']];
            $fila++;
        }
        foreach ($datos['gastos'] as $gasto) {
            $this->filas[] = [
                'tipo' => 'importe',
                'celdas' => [$gasto['codigo'], $gasto['concepto'], $num((float) $gasto['monto'])],
            ];
            $fila++;
        }
        $this->filasDestacadas[] = $fila;
        $this->filas[] = ['tipo' => 'importe', 'celdas' => ['Total gastos', '', $num((float) $datos['total_gastos'])], 'destacar' => true];
        $fila++;

        if ($datos['observacion'] !== '') {
            $this->filasSeccion[] = $fila;
            $this->filas[] = ['tipo' => 'seccion', 'celdas' => ['Observación']];
            $fila++;
            $this->filas[] = ['tipo' => 'texto', 'celdas' => [$datos['observacion']]];
        }
    }
}
