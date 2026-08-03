<?php

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Contable\CierreRendicionEstacionamientoListadoFiltros;
use App\Support\Contable\CierreRendicionEstacionamientoMediosCobroSupport;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CierreRendicionEstacionamientoListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private string $colUltima = 'K';

    /** @var list<array{cuentacaja_id: int, codigo: string, nombre: string, label: string, label_corto?: string}> */
    private array $columnasMedios = [];

    private string $subtituloFiltros = '';

    /**
     * @param  Collection<int, mixed>|null  $rendiciones
     * @param  list<array<string, mixed>>|null  $grupos
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        private ?Collection $rendiciones,
        private ?array $grupos,
        private bool $vistaPorTurno,
        private array $filtros = [],
        private bool $esCsv = false,
    ) {
        $fuente = $vistaPorTurno
            ? ($this->rendiciones ?? collect())
            : ($this->grupos ?? []);
        $this->columnasMedios = CierreRendicionEstacionamientoMediosCobroSupport::columnasDesdeFilasExport(
            $fuente,
            $vistaPorTurno,
        );
        $this->colUltima = $this->resolverColUltima();
        $this->subtituloFiltros = CierreRendicionEstacionamientoListadoFiltros::textoCabeceraExport($this->filtros);
    }

    public function view(): View
    {
        $paraLogos = $this->coleccionParaLogos();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($paraLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        // logo? + título + subtítulo filtros + cabeceras
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
        $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('contable.cierre_rendicion_estacionamiento.listado', [
            'rendiciones' => $this->rendiciones,
            'grupos' => $this->grupos,
            'vistaPorTurno' => $this->vistaPorTurno,
            'esExcel' => true,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'columnasMedios' => $this->columnasMedios,
            'subtituloFiltros' => $this->subtituloFiltros,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    /**
     * Montos con máscara neutra (#,##0.00): sumables y adaptables a la región de
     * la PC. Se resuelven dinámicamente porque las columnas de medios de cobro
     * varían por consulta.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $codigoMonto = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
        $formats = [];
        foreach ($this->indicesColumnasMonto() as $idxCol) {
            $letra = CierreRendicionEstacionamientoMediosCobroSupport::columnaExcel($idxCol);
            $formats[$letra] = $codigoMonto;
        }

        return $formats;
    }

    /**
     * Índices 0-based de columnas de dinero (2 decimales), excluyendo la columna
     * "Rend." (conteo entero) de la vista por PV.
     *
     * @return list<int>
     */
    private function indicesColumnasMonto(): array
    {
        $idxs = $this->indicesColumnasNumericas();
        if (! $this->vistaPorTurno) {
            $idxs = array_values(array_filter($idxs, static fn (int $i): bool => $i !== 3));
        }

        return $idxs;
    }

    /**
     * XLSX usa la preferencia global (auto adapta a la PC); CSV cae al respaldo
     * de config('export.csv_fallback').
     */
    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    public function columnWidths(): array
    {
        $headers = $this->headersParaAnchos();
        $anchosContenido = $this->anchosMaximosContenido();
        $widths = [];

        foreach ($headers as $i => $header) {
            $col = CierreRendicionEstacionamientoMediosCobroSupport::columnaExcel($i);
            $porHeader = self::anchoDesdeTexto($header, 10, 42);
            $porContenido = (float) ($anchosContenido[$i] ?? 0);
            $widths[$col] = max($porHeader, $porContenido);
        }

        return $widths;
    }

    /**
     * @return list<string>
     */
    private function headersParaAnchos(): array
    {
        if ($this->vistaPorTurno) {
            $headers = [
                'ID', 'Ticket', 'Fecha jornada', 'Empresa', 'Punto venta', 'Turno',
                'Fecha rend.', 'Estado', 'Asiento', 'Venta neta', 'NC', 'Venta total', 'Invitaciones',
            ];
            foreach ($this->columnasMedios as $medio) {
                $headers[] = (string) ($medio['label_descripcion'] ?? $medio['nombre'] ?? $medio['label'] ?? '');
            }
            $headers[] = 'Total cobrado';

            return $headers;
        }

        $headers = [
            'Fecha jornada', 'Empresa', 'Punto venta', 'Rend.',
            'Venta neta', 'NC', 'Venta total', 'Invitaciones',
        ];
        foreach ($this->columnasMedios as $medio) {
            $headers[] = (string) ($medio['label_descripcion'] ?? $medio['nombre'] ?? $medio['label'] ?? '');
        }
        $headers[] = 'Total cobrado';
        $headers[] = 'Estado';
        $headers[] = 'Asiento';

        return $headers;
    }

    /**
     * Anchos mínimos según el texto más largo de cada columna de datos.
     *
     * @return array<int, float>
     */
    private function anchosMaximosContenido(): array
    {
        $max = [];

        if ($this->vistaPorTurno) {
            foreach ($this->rendiciones ?? [] as $row) {
                $pv = $row->puntoventaCae;
                $pvLabel = $pv ? trim(($pv->codigo ?? '').' — '.($pv->nombre ?? '')) : '—';
                $estado = $row->tieneCierreContable()
                    ? ($row->esCierreContableLegacy() ? 'Cerrada (hist.)' : 'Cerrada')
                    : 'Pendiente';
                $valores = [
                    0 => (string) $row->id,
                    1 => (string) ($row->codigo ?? ''),
                    2 => '99/99/9999',
                    3 => (string) ($row->empresa?->nombre ?? ''),
                    4 => $pvLabel,
                    5 => \App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport::etiquetaTurno($row),
                    6 => '99/99/9999 99:99',
                    7 => $estado,
                    8 => (string) ($row->asiento?->numeroasiento ?? '—'),
                    9 => '999.999.999,99',
                    10 => '999.999.999,99',
                    11 => '999.999.999,99',
                    12 => '999.999.999,99',
                ];
                $medios = CierreRendicionEstacionamientoMediosCobroSupport::agregarDesdeRendiciones([$row]);
                $offset = 13;
                foreach ($this->columnasMedios as $idx => $medioCol) {
                    $valores[$offset + $idx] = '999.999.999,99';
                }
                $valores[$offset + count($this->columnasMedios)] = '999.999.999,99';
                $this->acumularAnchos($max, $valores);
            }

            return $max;
        }

        foreach ($this->grupos ?? [] as $grupo) {
            $estado = match ($grupo['estado_grupo'] ?? '') {
                \App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport::ESTADO_CERRADA => 'Cerrado',
                \App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport::ESTADO_LEGACY => \App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport::ETIQUETA_ESTADO_LEGACY,
                \App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport::ESTADO_PARCIAL => 'Parcial',
                default => 'Pendiente',
            };
            $asiento = (($grupo['asiento_ids_distintos'] ?? 0) > 1)
                ? 'Varios asientos'
                : (string) ($grupo['asiento_numero'] ?? '—');
            $valores = [
                0 => (string) ($grupo['fecha_dia_fmt'] ?? '99/99/9999'),
                1 => (string) ($grupo['empresa_nombre'] ?? ''),
                2 => (string) ($grupo['puntoventa_label'] ?? ''),
                3 => (string) ($grupo['cantidad_rendiciones'] ?? 0),
                4 => '999.999.999,99',
                5 => '999.999.999,99',
                6 => '999.999.999,99',
                7 => '999.999.999,99',
            ];
            $offset = 8;
            foreach ($this->columnasMedios as $idx => $_medioCol) {
                $valores[$offset + $idx] = '999.999.999,99';
            }
            $iTotal = $offset + count($this->columnasMedios);
            $valores[$iTotal] = '999.999.999,99';
            $valores[$iTotal + 1] = $estado;
            $valores[$iTotal + 2] = $asiento;
            $this->acumularAnchos($max, $valores);
        }

        return $max;
    }

    /**
     * @param  array<int, float>  $max
     * @param  array<int, string>  $valores
     */
    private function acumularAnchos(array &$max, array $valores): void
    {
        foreach ($valores as $i => $texto) {
            $ancho = self::anchoDesdeTexto((string) $texto, 8, 48);
            $max[$i] = max($max[$i] ?? 0, $ancho);
        }
    }

    private static function anchoDesdeTexto(string $texto, float $min, float $max): float
    {
        $len = mb_strlen(trim($texto));
        if ($len <= 0) {
            return $min;
        }
        // Arial ~ factor empírico para que el encabezado entre sin reajustar a mano.
        $ancho = round($len * 1.15 + 2.5, 1);

        return max($min, min($max, $ancho));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima;

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

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.$colUltima.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(26);
                $sheet->getStyle('A'.$filaTit.':'.$colUltima.$filaTit)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_NONE,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => false,
                    ],
                ]);

                $filaSub = $this->filaSubtituloExcel;
                $sheet->mergeCells('A'.$filaSub.':'.$colUltima.$filaSub);
                $sheet->getRowDimension($filaSub)->setRowHeight(48);
                $sheet->getStyle('A'.$filaSub.':'.$colUltima.$filaSub)->applyFromArray([
                    'font' => [
                        'bold' => false,
                        'size' => 9,
                        'name' => 'Arial',
                        'color' => ['rgb' => '444444'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_NONE,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $filaCab = $this->filaCabecerasExcel;
                $rangoCab = 'A'.$filaCab.':'.$colUltima.$filaCab;
                $sheet->getStyle($rangoCab)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                        'size' => 10,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => false,
                    ],
                ]);
                // Una sola línea visible por defecto
                $sheet->getRowDimension($filaCab)->setRowHeight(22);

                $filaDatosDesde = $this->filaPrimeraDatosExcel;
                $filaDatosHasta = max($filaDatosDesde, (int) $sheet->getHighestRow());
                foreach ($this->indicesColumnasNumericas() as $idxCol) {
                    $letra = CierreRendicionEstacionamientoMediosCobroSupport::columnaExcel($idxCol);
                    $sheet->getStyle($letra.$filaDatosDesde.':'.$letra.$filaDatosHasta)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    // Cabecera de columnas numéricas también a la derecha
                    $sheet->getStyle($letra.$filaCab)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    /**
     * Índices 0-based de columnas con montos / cantidades (alineación derecha).
     *
     * @return list<int>
     */
    private function indicesColumnasNumericas(): array
    {
        $nMedios = count($this->columnasMedios);
        if ($this->vistaPorTurno) {
            // Venta neta…Invitaciones (9..12) + medios + Total cobrado
            $idxs = [9, 10, 11, 12];
            for ($i = 0; $i < $nMedios; $i++) {
                $idxs[] = 13 + $i;
            }
            $idxs[] = 13 + $nMedios;

            return $idxs;
        }

        // Rend. (3) + Venta neta…Invit (4..7) + medios + Total cobrado
        $idxs = [3, 4, 5, 6, 7];
        for ($i = 0; $i < $nMedios; $i++) {
            $idxs[] = 8 + $i;
        }
        $idxs[] = 8 + $nMedios;

        return $idxs;
    }

    public function title(): string
    {
        return 'Cierre rend. estacionamiento';
    }

    private function cantidadColumnas(): int
    {
        if ($this->vistaPorTurno) {
            return 13 + count($this->columnasMedios) + 1;
        }

        return 8 + count($this->columnasMedios) + 1 + 2;
    }

    private function resolverColUltima(): string
    {
        return CierreRendicionEstacionamientoMediosCobroSupport::columnaExcel(
            max(0, $this->cantidadColumnas() - 1),
        );
    }

    private function coleccionParaLogos(): Collection
    {
        if ($this->vistaPorTurno) {
            $rows = $this->rendiciones ?? collect();
            foreach ($rows as $row) {
                $row->nombreempresa = $row->empresa->nombre ?? '';
            }

            return $rows instanceof Collection ? $rows : collect($rows);
        }

        return collect($this->grupos ?? [])->map(static function (array $g) {
            return (object) ['nombreempresa' => (string) ($g['empresa_nombre'] ?? '')];
        });
    }
}
