<?php

namespace App\Exports\Caja\Flash;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class FlashCajaDesgloseWigosExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $desglose;

    private string $empresaNombre;

    /**
     * @param  array<string, mixed>  $desglose
     */
    public function __construct(array $desglose, string $empresaNombre = '')
    {
        $this->desglose = $desglose;
        $this->empresaNombre = $empresaNombre;
    }

    public function view(): View
    {
        return view('exports.caja.flash.desglose_wigos', [
            'desglose' => $this->desglose,
            'empresaNombre' => $this->empresaNombre,
            'labelsComponente' => self::labelsComponente(),
            'labelsTotal' => self::labelsTotal(),
        ]);
    }

    public function title(): string
    {
        return 'Desglose Wigos';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 36,
            'B' => 18,
            'C' => 18,
            'D' => 18,
            'E' => 18,
            'F' => 18,
            'G' => 16,
            'H' => 16,
            'I' => 16,
            'J' => 16,
            'K' => 16,
            'L' => 16,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A5');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setName('Arial');
                $sheet->getStyle('A2:A3')->getFont()->setBold(true)->setSize(10)->setName('Arial')->getColor()->setRGB('444444');

                $highestRow = (int) $sheet->getHighestRow();
                for ($row = 1; $row <= $highestRow; $row++) {
                    $valorA = trim((string) $sheet->getCell('A'.$row)->getValue());
                    $valorB = trim((string) $sheet->getCell('B'.$row)->getValue());
                    if ($valorA === '' || $valorB === '') {
                        continue;
                    }
                    // Filas de cabecera de tabla: primera celda "Concepto" / "Turno" / "Clave"
                    if (in_array($valorA, ['Concepto', 'Turno', 'Clave', 'Campo'], true)) {
                        $ultimaCol = $sheet->getHighestColumn($row);
                        $rango = 'A'.$row.':'.$ultimaCol.$row;
                        $sheet->getStyle($rango)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('85C1E9');
                        $sheet->getStyle($rango)->getFont()
                            ->setBold(true)
                            ->setName('Arial')
                            ->setSize(11)
                            ->getColor()->setRGB('17202A');
                        $sheet->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                // Montos a la derecha desde columna B
                $sheet->getStyle('B5:L'.$highestRow)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('B5:L'.$highestRow)
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labelsComponente(): array
    {
        return [
            'bill_slots' => 'Drop efectivo billetes slots (BRUTO)',
            'bill_rul' => 'Drop efectivo billetes ruletas (BRUTO)',
            'bill_poker' => 'Bill poker (BRUTO)',
            'ventas_caja' => 'Ventas caja (tickets, BRUTO)',
            'ventas_slots' => 'Ventas slots (tickets, BRUTO)',
            'ventas_ruletas' => 'Ventas ruletas (tickets, BRUTO)',
            'pagos_caja' => 'Pagos caja (tickets)',
            'pagos_slots' => 'Pagos slots (tickets)',
            'pagos_ruletas' => 'Pagos ruletas (tickets)',
            'monto_qr' => 'Monto QR (bruto)',
            'monto_neto_qr' => 'Monto neto QR',
            'impuesto_qr' => 'Impuesto QR',
            'pagos_manuales' => 'Pagos manuales',
            'tito_slots' => 'Tito slots',
            'tito_rul' => 'Tito ruletas',
            'tito_poker' => 'Tito poker',
            'coin_in_slots' => 'Coin in slots',
            'coin_in_rul' => 'Coin in ruletas',
            'coin_in_poker' => 'Coin in poker',
            'win_slots' => 'Win slots (on-line)',
            'win_rul' => 'Win ruletas (on-line)',
            'units_slots' => 'Units slots',
            'units_rul' => 'Units ruletas',
            'units_poker' => 'Units poker',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labelsTotal(): array
    {
        return [
            'slot_d' => 'Drop slots (slot_d)',
            'slot_r' => 'Win slots (slot_r)',
            'slot_coin_in' => 'Coin in slots',
            'win_ol_slot' => 'Win on-line slots',
            'soft_count' => 'Soft count / drop efectivo slots (BRUTO)',
            'hard_count' => 'Hard count slots',
            'cant_slots' => 'Cant. slots',
            'rul_d' => 'Drop ruletas (rul_d)',
            'rul_r' => 'Win ruletas (rul_r)',
            'rul_coin_in' => 'Coin in ruletas',
            'win_ol_rul' => 'Win on-line ruletas',
            'soft_rul' => 'Soft count ruletas (BRUTO)',
            'hard_rul' => 'Hard count ruletas',
            'cant_rul' => 'Cant. ruletas',
        ];
    }
}
