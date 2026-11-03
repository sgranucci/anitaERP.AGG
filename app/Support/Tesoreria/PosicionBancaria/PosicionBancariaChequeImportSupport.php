<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Models\Tesoreria\PosicionBancariaCheque;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Importa el portfolio de cheques de las hojas Cheques BSA/KSA/RSA
 * de la planilla Posición Bancos hacia posicion_bancaria_cheque.
 */
final class PosicionBancariaChequeImportSupport
{
    /** @var array<string, int> */
    private const HOJA_EMPRESA = [
        'Cheques BSA' => 1,
        'Cheques KSA' => 2,
        'Cheques RSA' => 3,
    ];

    /**
     * @return array{inserted:int,updated:int,skipped:int,errors:list<string>}
     */
    public function importarDesdeExcel(string $rutaExcel, bool $desactivarAusentes = false): array
    {
        if (! is_file($rutaExcel)) {
            throw new RuntimeException('No existe el Excel: '.$rutaExcel);
        }

        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $vistos = [];

        // Prefer PhpSpreadsheet en modo filtrado (solo A-K) — el Excel
        // Posición tiene ~600 columnas de aging y no cabe en memoria completo.
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setLoadSheetsOnly(array_keys(self::HOJA_EMPRESA));
        $reader->setReadFilter(new PosicionBancariaChequeExcelReadFilter());
        $wb = $reader->load($rutaExcel);

        foreach (self::HOJA_EMPRESA as $hoja => $empresaId) {
            if (! $wb->sheetNameExists($hoja)) {
                $stats['errors'][] = "Falta hoja {$hoja}";
                continue;
            }
            $ws = $wb->getSheetByName($hoja);
            $max = (int) $ws->getHighestDataRow();
            /** @var array<string, array<string, mixed>> $neto */
            $neto = [];
            for ($r = 1; $r <= $max; $r++) {
                $tip = trim((string) $ws->getCell('A'.$r)->getValue());
                $nroRaw = $ws->getCell('B'.$r)->getValue();
                $bancoRaw = $ws->getCell('H'.$r)->getValue();
                if ($tip === '' && ($nroRaw === null || $nroRaw === '') && ($bancoRaw === null || $bancoRaw === '')) {
                    continue;
                }
                if (strtoupper((string) $bancoRaw) === 'BANCO') {
                    $stats['skipped']++;
                    continue;
                }

                $banco = PosicionBancariaChequeAgingSupport::normalizarBanco((string) $bancoRaw);
                $nro = ltrim((string) preg_replace('/\D/', '', (string) $nroRaw), '0');
                if ($banco === null || $nro === '' || $nro === '0') {
                    $stats['skipped']++;
                    continue;
                }

                $fechaEmision = $this->celdaFecha($ws->getCell('C'.$r)->getValue());
                $fechaCheque = $this->celdaFecha($ws->getCell('D'.$r)->getValue());
                $entregado = trim((string) $ws->getCell('E'.$r)->getValue());
                $importe = round((float) $ws->getCell('G'.$r)->getCalculatedValue(), 2);
                if (abs($importe) < 0.005 && $fechaCheque === null) {
                    $stats['skipped']++;
                    continue;
                }

                $key = $empresaId.'|'.$banco.'|'.$nro;
                $fechaKey = $fechaCheque ?? '';
                if (! isset($neto[$key])) {
                    $neto[$key] = [
                        'banco' => $banco,
                        'nro' => $nro,
                        'banco_raw' => (string) $bancoRaw,
                        'fechas' => [],
                    ];
                }
                if (! isset($neto[$key]['fechas'][$fechaKey])) {
                    $neto[$key]['fechas'][$fechaKey] = [
                        'tip' => $tip !== '' ? strtoupper($tip) : 'CHP',
                        'fecha_emision' => $fechaEmision,
                        'fecha_cheque' => $fechaCheque,
                        'entregado' => $entregado,
                        'fila' => $r,
                        'importe' => 0.0,
                        'referencia' => 0.0,
                    ];
                }
                $neto[$key]['fechas'][$fechaKey]['importe'] = round(
                    $neto[$key]['fechas'][$fechaKey]['importe'] + $importe,
                    2
                );
                if (abs($importe) >= abs($neto[$key]['fechas'][$fechaKey]['referencia'])) {
                    $neto[$key]['fechas'][$fechaKey]['referencia'] = $importe;
                    $neto[$key]['fechas'][$fechaKey]['fecha_emision'] = $fechaEmision;
                    $neto[$key]['fechas'][$fechaKey]['entregado'] = $entregado;
                    $neto[$key]['fechas'][$fechaKey]['fila'] = $r;
                    $neto[$key]['fechas'][$fechaKey]['tip'] = $tip !== '' ? strtoupper($tip) : 'CHP';
                }
            }

            foreach ($neto as $key => $item) {
                $vigentes = [];
                foreach ($item['fechas'] as $fechaItem) {
                    if (abs((float) $fechaItem['importe']) < 0.005) {
                        $stats['skipped']++;
                        continue;
                    }
                    $vigentes[] = $fechaItem;
                }
                // Crédito y baja en la misma fecha se anulan. Si quedan en fechas
                // distintas, se guardan las dos: la baja resta en su tramo y el
                // crédito queda en el suyo, como en la planilla de finanzas.
                if ($vigentes === []) {
                    continue;
                }
                $vistos[$key] = true;
                $cuentacajaId = PosicionBancariaChequeAgingSupport::resolverCuentacajaId($empresaId, $item['banco']);
                $fechasGuardadas = [];
                foreach ($vigentes as $fechaItem) {
                    $fechasGuardadas[] = $fechaItem['fecha_cheque'];
                    $payload = [
                        'cuentacaja_id' => $cuentacajaId,
                        'tip' => $fechaItem['tip'],
                        'fecha_emision' => $fechaItem['fecha_emision'],
                        'fecha_cheque' => $fechaItem['fecha_cheque'],
                        'importe' => round((float) $fechaItem['importe'], 2),
                        'estado' => ' ',
                        'entregado_a' => mb_substr((string) $fechaItem['entregado'], 0, 120),
                        'activo' => true,
                        'en_portfolio_posicion' => true,
                        'origen' => 'excel_posicion',
                        'origen_json' => [
                            'hoja' => $hoja,
                            'fila' => $fechaItem['fila'],
                            'banco_raw' => $item['banco_raw'],
                            'importado_at' => now()->toIso8601String(),
                        ],
                    ];

                    $existing = PosicionBancariaCheque::query()
                        ->where('empresa_id', $empresaId)
                        ->where('numero_cheque', $item['nro'])
                        ->when(
                            $fechaItem['fecha_cheque'] !== null,
                            fn ($q) => $q->whereDate('fecha_cheque', $fechaItem['fecha_cheque']),
                            fn ($q) => $q->whereNull('fecha_cheque'),
                        )
                        ->when(
                            $cuentacajaId !== null,
                            fn ($q) => $q->where('cuentacaja_id', $cuentacajaId),
                            fn ($q) => $q->where('banco_canonico', $item['banco']),
                        )
                        ->first();

                    if ($existing) {
                        $existing->fill($payload);
                        $existing->save();
                        $stats['updated']++;
                    } else {
                        PosicionBancariaCheque::query()->create(array_merge($payload, [
                            'empresa_id' => $empresaId,
                            'banco_canonico' => $item['banco'],
                            'numero_cheque' => $item['nro'],
                        ]));
                        $stats['inserted']++;
                    }
                }

                $sobrantes = PosicionBancariaCheque::query()
                    ->where('origen', 'excel_posicion')
                    ->where('activo', true)
                    ->where('empresa_id', $empresaId)
                    ->where('numero_cheque', $item['nro'])
                    ->when(
                        $cuentacajaId !== null,
                        fn ($q) => $q->where('cuentacaja_id', $cuentacajaId),
                        fn ($q) => $q->where('banco_canonico', $item['banco']),
                    )
                    ->get();
                foreach ($sobrantes as $row) {
                    $fechaRow = $row->fecha_cheque?->toDateString();
                    if (! in_array($fechaRow, $fechasGuardadas, true)) {
                        $row->activo = false;
                        $row->save();
                        $stats['updated']++;
                    }
                }
            }
        }

        if ($desactivarAusentes && $vistos !== []) {
            PosicionBancariaCheque::query()
                ->where('origen', 'excel_posicion')
                ->where('activo', true)
                ->orderBy('id')
                ->chunkById(500, function ($chunk) use ($vistos, &$stats) {
                    foreach ($chunk as $row) {
                        $key = $row->empresa_id.'|'.$row->banco_canonico.'|'.$row->numero_cheque;
                        if (! isset($vistos[$key])) {
                            $row->activo = false;
                            $row->save();
                            $stats['updated']++;
                        }
                    }
                });
        }

        return $stats;
    }

    private function celdaFecha(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->toDateString();
        }
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
