<?php

namespace App\Exports\Ventas;

use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Ventas\ClienteListadoColumnas;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClienteListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    private ClienteRepositoryInterface $clienteRepository;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    /** @var list<string> */
    private array $columnas = [];

    /** @var array<string, string> */
    private array $etiquetas = [];

    public function __construct(ClienteRepositoryInterface $clienteRepository)
    {
        $this->clienteRepository = $clienteRepository;
    }

    public function view(): View
    {
        $columnas = $this->columnas !== []
            ? ClienteListadoColumnas::normalizarVisibles($this->columnas)
            : ClienteListadoColumnas::defaultsVisibles();
        $columnasExport = array_values(array_filter(
            $columnas,
            static fn ($k) => ($meta = ClienteListadoColumnas::catalogoActivo()[$k] ?? null) && ! empty($meta['export'])
        ));
        if ($columnasExport === []) {
            $columnasExport = array_values(array_filter(
                ClienteListadoColumnas::defaultsVisibles(),
                static fn ($k) => ! empty(ClienteListadoColumnas::catalogoActivo()[$k]['export'])
            ));
        }

        $clientes = $this->clienteRepository->leeCliente($this->filtros, false);

        $etiquetas = $this->etiquetas !== []
            ? $this->etiquetas
            : ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
                ClienteListadoColumnas::RECURSO,
                ClienteListadoColumnas::catalogoActivo()
            );

        return view('exports.ventas.clienteindex', [
            'clientes' => $clientes,
            'columnasVisibles' => $columnasExport,
            'etiquetasColumnas' => $etiquetas,
        ]);
    }

    public function columnFormats(): array
    {
        $formats = ['A' => NumberFormat::FORMAT_TEXT];
        $letras = range('A', 'Z');
        $i = 0;
        foreach ($this->columnasEfectivasExport() as $key) {
            $letra = $letras[$i] ?? null;
            $i++;
            if ($letra === null) {
                continue;
            }
            if (in_array($key, ['id', 'codigo', 'numerodocumento', 'estado'], true)) {
                $formats[$letra] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
    }

    public function map($row): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        $widths = [];
        $letras = range('A', 'Z');
        $i = 0;
        foreach ($this->columnasEfectivasExport() as $key) {
            $letra = $letras[$i] ?? null;
            $i++;
            if ($letra === null) {
                continue;
            }
            $widths[$letra] = match ($key) {
                'id' => 8,
                'nombre', 'fantasia', 'domicilio' => 28,
                'vendedor', 'transporte' => 20,
                default => 16,
            };
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }

    public function title(): string
    {
        return 'Clientes';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  list<string>|null  $columnas
     * @param  array<string, string>|null  $etiquetas
     */
    public function parametros($filtros, ?array $columnas = null, ?array $etiquetas = null)
    {
        $this->filtros = $filtros;
        $this->columnas = is_array($columnas) ? $columnas : [];
        $this->etiquetas = is_array($etiquetas) ? $etiquetas : [];

        return $this;
    }

    /**
     * @return list<string>
     */
    private function columnasEfectivasExport(): array
    {
        $columnas = $this->columnas !== []
            ? ClienteListadoColumnas::normalizarVisibles($this->columnas)
            : ClienteListadoColumnas::defaultsVisibles();

        return array_values(array_filter(
            $columnas,
            static fn ($k) => ($meta = ClienteListadoColumnas::catalogoActivo()[$k] ?? null) && ! empty($meta['export'])
        ));
    }
}
