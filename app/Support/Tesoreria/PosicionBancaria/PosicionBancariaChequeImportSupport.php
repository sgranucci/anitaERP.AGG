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
            for ($r = 43; $r <= $max; $r++) {
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
                $importe = abs((float) $ws->getCell('G'.$r)->getCalculatedValue());
                if ($importe < 0.005 && $fechaCheque === null) {
                    $stats['skipped']++;
                    continue;
                }

                $cuentacajaId = PosicionBancariaChequeAgingSupport::resolverCuentacajaId($empresaId, $banco);
                $key = $empresaId.'|'.$banco.'|'.$nro;
                $vistos[$key] = true;

                $payload = [
                    'cuentacaja_id' => $cuentacajaId,
                    'tip' => $tip !== '' ? strtoupper($tip) : 'CHP',
                    'fecha_emision' => $fechaEmision,
                    'fecha_cheque' => $fechaCheque,
                    'importe' => $importe,
                    'estado' => ' ',
                    'entregado_a' => mb_substr($entregado, 0, 120),
                    'activo' => true,
                    'en_portfolio_posicion' => true,
                    'origen' => 'excel_posicion',
                    'origen_json' => [
                        'hoja' => $hoja,
                        'fila' => $r,
                        'banco_raw' => (string) $bancoRaw,
                        'importado_at' => now()->toIso8601String(),
                    ],
                ];

                $existing = PosicionBancariaCheque::query()
                    ->where('empresa_id', $empresaId)
                    ->where('numero_cheque', $nro)
                    ->when(
                        $cuentacajaId !== null,
                        fn ($q) => $q->where('cuentacaja_id', $cuentacajaId),
                        fn ($q) => $q->where('banco_canonico', $banco),
                    )
                    ->first();

                if ($existing) {
                    $existing->fill($payload);
                    $existing->save();
                    $stats['updated']++;
                } else {
                    PosicionBancariaCheque::query()->create(array_merge($payload, [
                        'empresa_id' => $empresaId,
                        'banco_canonico' => $banco,
                        'numero_cheque' => $nro,
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
