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
                if (! isset($neto[$key])) {
                    $neto[$key] = [
                        'empresa_id' => $empresaId,
                        'banco' => $banco,
                        'nro' => $nro,
                        'banco_raw' => (string) $bancoRaw,
                        'tip' => $tip !== '' ? strtoupper($tip) : 'CHP',
                        'fecha_emision' => $fechaEmision,
                        'fecha_cheque' => $fechaCheque,
                        'entregado' => $entregado,
                        'fila' => $r,
                        'importe' => 0.0,
                        'fila_importe' => 0.0,
                    ];
                }
                $neto[$key]['importe'] = round($neto[$key]['importe'] + $importe, 2);
                // La fecha que vale es la del renglón que deja saldo (el crédito), no la baja.
                if ($importe > $neto[$key]['fila_importe']) {
                    $neto[$key]['fila_importe'] = $importe;
                    $neto[$key]['fecha_cheque'] = $fechaCheque;
                    $neto[$key]['fecha_emision'] = $fechaEmision;
                    $neto[$key]['entregado'] = $entregado;
                    $neto[$key]['fila'] = $r;
                    $neto[$key]['tip'] = $tip !== '' ? strtoupper($tip) : 'CHP';
                }
            }

            foreach ($neto as $key => $item) {
                // Un crédito y su baja (importe negativo) se compensan: el cheque no está vigente.
                if ($item['importe'] <= 0.005) {
                    $stats['skipped']++;
                    continue;
                }
                $vistos[$key] = true;
                $cuentacajaId = PosicionBancariaChequeAgingSupport::resolverCuentacajaId($empresaId, $item['banco']);
                $payload = [
                    'cuentacaja_id' => $cuentacajaId,
                    'tip' => $item['tip'],
                    'fecha_emision' => $item['fecha_emision'],
                    'fecha_cheque' => $item['fecha_cheque'],
                    'importe' => round((float) $item['importe'], 2),
                    'estado' => ' ',
                    'entregado_a' => mb_substr((string) $item['entregado'], 0, 120),
                    'activo' => true,
                    'en_portfolio_posicion' => true,
                    'origen' => 'excel_posicion',
                    'origen_json' => [
                        'hoja' => $hoja,
                        'fila' => $item['fila'],
                        'banco_raw' => $item['banco_raw'],
                        'importado_at' => now()->toIso8601String(),
                    ],
                ];

                $existing = PosicionBancariaCheque::query()
                    ->where('empresa_id', $empresaId)
                    ->where('numero_cheque', $item['nro'])
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
