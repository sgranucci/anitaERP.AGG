<?php

namespace App\Exports\Caja\Flash;

use App\Support\Caja\Flash\FlashCrownInformeSupport;
use App\Support\Export\ExcelFormatoNumero;
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
            'formatoNumero' => ExcelFormatoNumero::AUTO,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $formato = FlashCrownInformeSupport::normalizarFormato($this->formato);
                $mascara = ExcelFormatoNumero::mascara(2);

                // Filas 1–2: título / generado. Luego thead de la tabla; datos debajo.
                // Forzar NÚMERO real + máscara de miles (Excel los muestra según región de la PC).
                if ($formato === FlashCrownInformeSupport::FORMATO_DAILY) {
                    // thead: grupos + columnas → datos desde fila 5. A=Day B=Fecha.
                    $sheet->freezePane('A5');
                    ExcelNumericCellsSupport::forzarRangoDatos($sheet, 5, 3, [], $mascara);
                } elseif ($formato === FlashCrownInformeSupport::FORMATO_GAMING) {
                    // 1 título, 2 generado, 3 thead, 4+ casino/datos (títulos de casino = texto, se ignoran).
                    $headerRow = 3;
                    $sheet->freezePane('A'.($headerRow + 1));
                    $lastCol = $sheet->getHighestColumn($headerRow);
                    $sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '17202A'],
                            'size' => 11,
                        ],
                    ]);
                    ExcelNumericCellsSupport::forzarRangoDatos(
                        $sheet,
                        $headerRow + 1,
                        2,
                        [],
                        $mascara,
                    );
                } else {
                    // Completo: thead fila 3, datos desde 4. A=Sala.
                    $sheet->freezePane('A4');
                    ExcelNumericCellsSupport::forzarRangoDatos($sheet, 4, 2, [], $mascara);
                }
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
