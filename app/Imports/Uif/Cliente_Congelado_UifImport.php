<?php

namespace App\Imports\Uif;

use App\Repositories\Uif\Cliente_Congelado_UifRepositoryInterface;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class Cliente_Congelado_UifImport implements OnEachRow, WithStartRow
{
    private $cliente_congelado_uifRepository;

    public function __construct(Cliente_Congelado_UifRepositoryInterface $cliente_congelado_uifrepository)
    {
        $this->cliente_congelado_uifRepository = $cliente_congelado_uifrepository;
    }

    public function startRow(): int
    {
        return 4;
    }

    public function onRow(Row $row): void
    {
        $row = $row->toArray();

        $numerodocumento = preg_replace('/\D/', '', trim((string) ($row[0] ?? '')));
        if ($numerodocumento === '') {
            return;
        }

        $partesNombre = [];
        for ($i = 1; $i <= 5; $i++) {
            $parte = trim((string) ($row[$i] ?? ''));
            if ($parte !== '') {
                $partesNombre[] = $parte;
            }
        }

        $nombre = trim(implode(' ', $partesNombre));
        if ($nombre === '') {
            return;
        }

        $arrayClienteCongelado_Uif = [
            'nombre' => $nombre,
            'numerodocumento' => $numerodocumento,
            'resolucion' => trim((string) ($row[6] ?? '')),
            'fechacaducidad' => $this->parseFecha($row[7] ?? null),
        ];

        $cliente_congelado_uif = $this->cliente_congelado_uifRepository
            ->buscaCliente_Congelado_Uif($numerodocumento);

        if ($cliente_congelado_uif) {
            $this->cliente_congelado_uifRepository->update($arrayClienteCongelado_Uif, $cliente_congelado_uif->id);
        } else {
            $this->cliente_congelado_uifRepository->create($arrayClienteCongelado_Uif);
        }
    }

    private function parseFecha($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
        }

        foreach (['n/j/Y', 'm/d/Y', 'd/m/Y', 'Y-m-d'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $value);
                if ($fecha !== false) {
                    return $fecha->format('Y-m-d');
                }
            } catch (\Exception $exception) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $exception) {
            return null;
        }
    }
}
