<?php

namespace App\Exports\Compras;

use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Compras\ProveedorCuentacorrientePreferenciasUsuario;
use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProveedorCuentacorrienteListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA_DEUDA = 'J';

    private const COL_ULTIMA_CC = 'J';

    private Proveedor_CuentacorrienteRepositoryInterface $proveedorCuentacorrienteRepository;

    private string $busqueda = '';

    private int $proveedorId = 0;

    private string $modoVista = ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE;

    private string $nombreProveedor = '';

    private string $codigoProveedor = '';

    /** @var list<array{moneda_id: int, abreviatura: string, saldo_cc: float, deuda: float}> */
    private array $saldosPorMoneda = [];

    private ?int $monedaId = null;

    private string $expresion = CuentacorrienteSaldosPorMoneda::EXPRESION_ORIGEN;

    /** @var array{saldo_cc?: float, deuda?: float, abreviatura?: string} */
    private array $equivalentePesos = [];

    private bool $esCsv = false;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 4;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(Proveedor_CuentacorrienteRepositoryInterface $proveedorCuentacorrienteRepository)
    {
        $this->proveedorCuentacorrienteRepository = $proveedorCuentacorrienteRepository;
    }

    public function view(): View
    {
        if ($this->modoVista === ProveedorCuentacorrientePreferenciasUsuario::MODO_DEUDA) {
            $filas = $this->proveedorCuentacorrienteRepository->listarDeudaProveedor($this->busqueda, $this->proveedorId, false, $this->filtros);
        } else {
            $filas = $this->proveedorCuentacorrienteRepository->listarCuentaCorriente($this->busqueda, $this->proveedorId, false, $this->filtros);
        }

        self::enriquecerNombreEmpresa($filas);

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        $modoDeuda = $this->modoVista === ProveedorCuentacorrientePreferenciasUsuario::MODO_DEUDA;
        $titulo = $modoDeuda
            ? 'Deuda de proveedores (facturas impagas)'
            : 'Cuenta corriente de proveedores';
        $subtitulo = 'Proveedor: '.trim(($this->codigoProveedor !== '' ? $this->codigoProveedor.' — ' : '').$this->nombreProveedor);

        return view('exports.compras.cuentacorrienteproveedorindex', [
            'filas' => $filas,
            'modoVista' => $this->modoVista,
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'saldosPorMoneda' => $this->saldosPorMoneda,
            'monedaId' => $this->monedaId,
            'expresion' => $this->expresion,
            'equivalentePesos' => $this->equivalentePesos,
            'mostrarSaldoCorrido' => CuentacorrienteSaldosPorMoneda::mostrarSaldoCorrido($this->monedaId, $this->saldosPorMoneda),
            'totalFilas' => $filas->count(),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'formatoNumeroExcel' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        // Debe/Haber/Saldo (G–I) con máscara neutra: sumables y adaptables a la región.
        $codigo = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'G' => $codigo,
            'H' => $codigo,
            'I' => $codigo,
            'J' => $codigo,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 11,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 18,
            'C' => 12,
            'D' => 12,
            'E' => 32,
            'F' => CuentacorrienteSaldosPorMoneda::esExpresionPesos($this->expresion) ? 22 : 8,
            'G' => 14,
            'H' => 14,
            'I' => 14,
            'J' => 16,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    $saltoXp = 160;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }

                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setDescription('Logo empresa');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp + $idx * $saltoXp);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaTit = $this->filaInicioMeta;
                $filaFinMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($fila = $filaTit; $fila <= $filaFinMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$this->colUltima().$fila);
                }

                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.$this->colUltima().$filaTit)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A'.($filaTit + 1).':'.$this->colUltima().$filaFinMeta)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'name' => 'Arial',
                        'color' => ['rgb' => '444444'],
                    ],
                    'alignment' => [
                        'wrapText' => true,
                        'vertical' => Alignment::VERTICAL_TOP,
                    ],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $primera = $this->filaPrimeraDatosExcel;
                $sheet->getStyle('E'.$primera.':E'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);

                $sheet->getStyle('G'.$primera.':'.$this->colUltima().$sheet->getHighestRow())
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    private function colUltima(): string
    {
        return $this->modoVista === ProveedorCuentacorrientePreferenciasUsuario::MODO_DEUDA
            ? self::COL_ULTIMA_DEUDA
            : self::COL_ULTIMA_CC;
    }

    public function title(): string
    {
        return $this->modoVista === ProveedorCuentacorrientePreferenciasUsuario::MODO_DEUDA
            ? 'Deuda proveedor'
            : 'Cuenta corriente';
    }

    public function parametros(
        string $busqueda,
        int $proveedorId,
        string $modoVista,
        string $nombreProveedor,
        array $saldosPorMoneda = [],
        ?int $monedaId = null,
        bool $esCsv = false,
        array $filtros = [],
        string $expresion = CuentacorrienteSaldosPorMoneda::EXPRESION_ORIGEN,
        array $equivalentePesos = [],
        string $codigoProveedor = '',
    ): self {
        $this->busqueda = $busqueda;
        $this->proveedorId = $proveedorId;
        $this->modoVista = ProveedorCuentacorrientePreferenciasUsuario::resolverModoVista($modoVista);
        $this->nombreProveedor = $nombreProveedor;
        $this->codigoProveedor = $codigoProveedor;
        $this->saldosPorMoneda = $saldosPorMoneda;
        $this->monedaId = $monedaId;
        $this->esCsv = $esCsv;
        $this->filtros = $filtros;
        $this->expresion = CuentacorrienteSaldosPorMoneda::resolverExpresion($expresion);
        $this->equivalentePesos = $equivalentePesos;

        return $this;
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Compras\Proveedor_Cuentacorriente>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Compras\Proveedor_Cuentacorriente>  $filas
     */
    private static function enriquecerNombreEmpresa($filas): void
    {
        foreach ($filas as $row) {
            $row->nombreempresa = $row->empresas->nombre ?? '';
        }
    }
}
