<?php

namespace App\Services\Uif;

use App\Support\Uif\UifWigosConciliacionEmpresaSupport;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee planillas Excel Wigos (Titos / PM) con el layout del informe global UIF.
 */
final class UifWigosExcelReader
{
    /**
     * Lee un archivo exportado suelto (solapa Sheet1) detectando columnas por contenido.
     *
     * @return array{filas: list<array<string, mixed>>, hojas: list<string>}
     */
    public function leerArchivoSoloTitos(string $ruta): array
    {
        return $this->leerArchivoPorTipo($ruta, 'titos');
    }

    /**
     * @return array{filas: list<array<string, mixed>>, hojas: list<string>}
     */
    public function leerArchivoSoloPm(string $ruta): array
    {
        return $this->leerArchivoPorTipo($ruta, 'pm');
    }

    /**
     * @return array{filas: list<array<string, mixed>>, hojas: list<string>}
     */
    private function leerArchivoPorTipo(string $ruta, string $tipo): array
    {
        $spreadsheet = IOFactory::load($ruta);
        $filas = [];
        $hojas = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $leidas = $tipo === 'titos' ? $this->leerTitos($sheet) : $this->leerPm($sheet);
            if ($leidas === []) {
                continue;
            }
            $filas = array_merge($filas, $leidas);
            $hojas[] = $sheet->getTitle();
        }

        $spreadsheet->disconnectWorksheets();

        return ['filas' => $filas, 'hojas' => $hojas];
    }

    /**
     * @return array{
     *     titos: array<int, array<string, mixed>>,
     *     pm: array<int, array<string, mixed>>,
     *     hojas: array<int, string>
     * }
     */
    public function leerArchivo(string $ruta): array
    {
        $spreadsheet = IOFactory::load($ruta);
        $titos = [];
        $pm = [];
        $hojas = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $meta = $this->detectarHoja($sheet->getTitle());
            if ($meta === null) {
                continue;
            }

            $hojas[] = $sheet->getTitle();

            if ($meta['tipo'] === 'titos') {
                $titos = array_merge($titos, $this->leerTitos($sheet));
            } elseif ($meta['tipo'] === 'pm') {
                $pm = array_merge($pm, $this->leerPm($sheet));
            }
        }

        $spreadsheet->disconnectWorksheets();

        return [
            'titos' => $titos,
            'pm' => $pm,
            'hojas' => $hojas,
        ];
    }

    /**
     * @return array{codigo: string|null, tipo: string}|null
     */
    public function detectarHoja(string $nombreHoja): ?array
    {
        $upper = strtoupper(trim($nombreHoja));

        if (str_contains($upper, 'UNIFICADO') || str_contains($upper, 'OTROS') || str_contains($upper, 'BINGO')) {
            return null;
        }

        $codigo = UifWigosConciliacionEmpresaSupport::detectarCodigoDesdeNombreHoja($nombreHoja);

        if (str_contains($upper, 'PM') && str_contains($upper, 'WIGOS')) {
            return ['codigo' => $codigo, 'tipo' => 'pm'];
        }

        if ((str_contains($upper, 'TITO') || str_contains($upper, 'CAJAS')) && str_contains($upper, 'WIGOS')) {
            return ['codigo' => $codigo, 'tipo' => 'titos'];
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leerTitosDesdeHoja(Worksheet $sheet): array
    {
        return $this->leerTitos($sheet);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leerPmDesdeHoja(Worksheet $sheet): array
    {
        return $this->leerPm($sheet);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leerTitos(Worksheet $sheet): array
    {
        $headerRow = $this->buscarFilaEncabezado($sheet, ['número', 'numero']);
        if ($headerRow === null) {
            return [];
        }

        $mapa = $this->mapaColumnas($sheet, $headerRow, [
            'numero' => ['número', 'numero'],
            'secuencia' => ['secuencia'],
            'tipo' => ['tipo'],
            'promocion' => ['promoción', 'promocion'],
            'monto' => ['monto'],
            'estado' => ['estado'],
            'terminal' => ['terminal'],
            'cuenta' => ['cuenta'],
            'fecha_emision' => ['fecha emision', 'fecha emisión', 'fecha'],
            'terminal_caja' => [],
            'fecha_pago' => ['fecha pago'],
            'observaciones' => ['observaciones'],
        ]);

        $filas = [];
        $maxRow = $sheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $numero = $this->valorCelda($sheet, $row, $mapa['numero'] ?? null);
            if ($numero === '') {
                continue;
            }

            $monto = $this->parseMonto($this->valorCelda($sheet, $row, $mapa['monto'] ?? null));
            if ($monto === null) {
                continue;
            }

            $terminalCols = $this->indicesColumna($mapa, 'terminal');
            $fechaCols = $this->indicesColumna($mapa, 'fecha_emision');
            $fechaPagoCols = $this->indicesColumna($mapa, 'fecha_pago');

            $filas[] = [
                'numero' => $numero,
                'secuencia' => $this->parseEntero($this->valorCelda($sheet, $row, $mapa['secuencia'] ?? null)),
                'tipo' => $this->valorCelda($sheet, $row, $mapa['tipo'] ?? null),
                'promocion' => $this->valorCelda($sheet, $row, $mapa['promocion'] ?? null),
                'monto' => $monto,
                'estado' => $this->valorCelda($sheet, $row, $mapa['estado'] ?? null),
                'terminal' => $this->valorCelda($sheet, $row, $terminalCols[0] ?? null),
                'cuenta' => $this->valorCelda($sheet, $row, $mapa['cuenta'] ?? null),
                'fecha_emision' => $this->parseFecha($this->valorCeldaRaw($sheet, $row, $fechaCols[0] ?? null)),
                'terminal_caja' => $this->valorCelda($sheet, $row, $terminalCols[1] ?? ($mapa['terminal_caja'] ?? null)),
                'fecha_pago' => $this->parseFecha($this->valorCeldaRaw($sheet, $row, $fechaPagoCols[0] ?? null)),
                'observaciones' => $this->valorCelda($sheet, $row, $mapa['observaciones'] ?? null),
            ];
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leerPm(Worksheet $sheet): array
    {
        $headerRow = $this->buscarFilaEncabezado($sheet, ['proveedor', 'fecha']);
        if ($headerRow === null) {
            return [];
        }

        $mapa = $this->mapaColumnas($sheet, $headerRow, [
            'fecha' => ['fecha'],
            'proveedor' => ['proveedor'],
            'nombre' => ['nombre'],
            'id_planta' => ['id en planta', 'id_planta'],
            'monto_original' => ['monto original'],
            'monto_pagado' => ['monto pagado'],
            'tipo' => ['tipo'],
            'estado' => ['estado'],
            'observaciones' => ['observaciones'],
        ]);

        $filas = [];
        $maxRow = $sheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $fechaRaw = $this->valorCeldaRaw($sheet, $row, $mapa['fecha'] ?? null);
            $fecha = $this->parseFecha($fechaRaw);
            $montoPagado = $this->parseMonto($this->valorCelda($sheet, $row, $mapa['monto_pagado'] ?? null));
            $montoOriginal = $this->parseMonto($this->valorCelda($sheet, $row, $mapa['monto_original'] ?? null));

            if ($fecha === null && $montoPagado === null) {
                continue;
            }

            if ($montoPagado === null && $montoOriginal !== null) {
                $montoPagado = $montoOriginal;
            }

            if ($montoPagado === null) {
                continue;
            }

            $filas[] = [
                'fecha' => $fecha,
                'proveedor' => $this->valorCelda($sheet, $row, $mapa['proveedor'] ?? null),
                'nombre' => $this->valorCelda($sheet, $row, $mapa['nombre'] ?? null),
                'id_planta' => $this->valorCelda($sheet, $row, $mapa['id_planta'] ?? null),
                'monto_original' => $montoOriginal,
                'monto_pagado' => $montoPagado,
                'tipo' => $this->valorCelda($sheet, $row, $mapa['tipo'] ?? null),
                'estado' => $this->valorCelda($sheet, $row, $mapa['estado'] ?? null),
                'observaciones' => $this->valorCelda($sheet, $row, $mapa['observaciones'] ?? null),
            ];
        }

        return $filas;
    }

    /**
     * @param  list<string>  $marcadores
     */
    private function buscarFilaEncabezado(Worksheet $sheet, array $marcadores): ?int
    {
        $maxRow = min($sheet->getHighestDataRow(), 15);

        for ($row = 1; $row <= $maxRow; $row++) {
            $maxCol = min($sheet->getHighestDataColumn($row), 'M');
            $colIndex = 1;
            while ($colIndex <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol)) {
                $texto = strtolower(trim((string) $sheet->getCellByColumnAndRow($colIndex, $row)->getCalculatedValue()));
                foreach ($marcadores as $marcador) {
                    if ($texto !== '' && str_contains($texto, strtolower($marcador))) {
                        return $row;
                    }
                }
                $colIndex++;
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $definiciones
     * @return array<string, int|null>
     */
    private function mapaColumnas(Worksheet $sheet, int $headerRow, array $definiciones): array
    {
        $mapa = [];
        $maxCol = $sheet->getHighestDataColumn($headerRow);
        $colMax = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);
        $encabezados = [];

        for ($col = 1; $col <= $colMax; $col++) {
            $texto = strtolower(trim((string) $sheet->getCellByColumnAndRow($col, $headerRow)->getCalculatedValue()));
            if ($texto !== '') {
                $encabezados[$col] = $texto;
            }
        }

        foreach ($definiciones as $campo => $variantes) {
            $mapa[$campo] = null;
            foreach ($encabezados as $col => $texto) {
                if ($variantes === []) {
                    continue;
                }
                foreach ($variantes as $variante) {
                    if ($texto === strtolower($variante) || str_contains($texto, strtolower($variante))) {
                        $mapa[$campo] = $col;
                        break;
                    }
                }
                if ($mapa[$campo] !== null) {
                    break;
                }
            }
        }

        // Segunda columna "Terminal" / "Fecha" en titos
        if (isset($definiciones['terminal'])) {
            $found = 0;
            foreach ($encabezados as $col => $texto) {
                if (str_contains($texto, 'terminal')) {
                    $found++;
                    if ($found === 2) {
                        $mapa['terminal_caja'] = $col;
                        break;
                    }
                }
            }
        }

        if (isset($definiciones['fecha_emision'])) {
            $found = 0;
            foreach ($encabezados as $col => $texto) {
                if (str_contains($texto, 'fecha')) {
                    $found++;
                    if ($found === 2 && ! isset($mapa['fecha_pago'])) {
                        $mapa['fecha_pago'] = $col;
                    }
                }
            }
        }

        return $mapa;
    }

    /**
     * @param  array<string, int|null>  $mapa
     * @return list<int>
     */
    private function indicesColumna(array $mapa, string $campo): array
    {
        $indices = [];
        foreach ($mapa as $key => $col) {
            if (str_starts_with($key, $campo) && $col !== null) {
                $indices[] = $col;
            }
        }

        return array_values(array_unique($indices));
    }

    private function valorCelda(Worksheet $sheet, int $row, ?int $col): string
    {
        if ($col === null) {
            return '';
        }

        return trim((string) $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue());
    }

    private function valorCeldaRaw(Worksheet $sheet, int $row, ?int $col): mixed
    {
        if ($col === null) {
            return null;
        }

        return $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
    }

    private function parseMonto(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $limpio = preg_replace('/[^\d,.-]/', '', (string) $value);
        if ($limpio === '' || $limpio === null) {
            return null;
        }

        $limpio = str_replace('.', '', str_replace(',', '.', $limpio));

        return is_numeric($limpio) ? round((float) $limpio, 2) : null;
    }

    private function parseEntero(mixed $value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function parseFecha(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        $texto = trim((string) $value);
        if ($texto === '') {
            return null;
        }

        $formatos = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y',
            'Y-m-d',
        ];

        foreach ($formatos as $formato) {
            try {
                return Carbon::createFromFormat($formato, $texto);
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($texto);
        } catch (\Throwable) {
            return null;
        }
    }
}
