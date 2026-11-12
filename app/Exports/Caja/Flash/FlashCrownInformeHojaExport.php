<?php

namespace App\Exports\Caja\Flash;

use App\Support\Caja\Flash\FlashCrownInformeSupport;
use App\Support\Export\ExcelNumericCellsSupport;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class FlashCrownInformeHojaExport implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>|null  $seccion  Solo Daily: una sala / resumen por hoja.
     */
    public function __construct(
        private readonly array $informe,
        private readonly string $formato,
        private readonly string $titulo,
        private readonly string $nombreHoja,
        private readonly ?array $seccion = null,
    ) {}

    public function title(): string
    {
        return self::sanitizarNombreHoja($this->nombreHoja);
    }

    public function view(): View
    {
        $formato = FlashCrownInformeSupport::normalizarFormato($this->formato);

        return view('exports.caja.flash_crown_informe', [
            'informe' => $this->informe,
            'formato' => $formato,
            'titulo' => $this->titulo,
            'modo_excel' => true,
            'seccion' => $this->seccion,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A3');
                // Fila 1 título, 2 generado; datos desde 3. Col A = etiquetas.
                ExcelNumericCellsSupport::forzarRangoDatos(
                    $sheet,
                    3,
                    2,
                    [],
                    '#,##0.00',
                );
            },
        ];
    }

    public static function sanitizarNombreHoja(string $nombre): string
    {
        $nombre = trim(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', '', $nombre) ?? '');
        if ($nombre === '') {
            $nombre = 'Hoja';
        }

        return mb_substr($nombre, 0, 31);
    }

    public static function tituloInforme(array $informe, string $formato): string
    {
        $fecha = $informe['fecha'] ?? null;
        if ($fecha instanceof Carbon) {
            $fechaFmt = $fecha->format('d/m/Y');
        } else {
            $fechaFmt = $fecha ? Carbon::parse((string) $fecha)->format('d/m/Y') : '';
        }

        return match (FlashCrownInformeSupport::normalizarFormato($formato)) {
            FlashCrownInformeSupport::FORMATO_GAMING => 'Gaming Revenues '.$fechaFmt,
            FlashCrownInformeSupport::FORMATO_DAILY => 'Daily Flash Report '.$fechaFmt,
            default => 'Flash Crown '.$fechaFmt,
        };
    }
}
