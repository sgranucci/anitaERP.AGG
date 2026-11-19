<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Models\Tesoreria\PosicionBancariaCheque;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Alinea el padrón Macro de posicion_bancaria_cheque a la cartera operativa
 * (Excel Posición de Lucas): altas/actualizaciones + baja de todo Macro activo
 * que no esté en esa cartera (cpromae / florencia / excel viejo).
 *
 * Solo banco MACRO. ITAU/BIND no se tocan.
 */
final class PosicionBancariaChequeMacroAlinearSupport
{
    /** @var array<string, int> */
    private const HOJA_EMPRESA = [
        'Cheques BSA' => 1,
        'Cheques KSA' => 2,
        'Cheques RSA' => 3,
    ];

    /**
     * @return array{
     *   dry_run: bool,
     *   abiertos_excel: int,
     *   importe_excel: float,
     *   a_insertar: int,
     *   a_actualizar: int,
     *   a_desactivar: int,
     *   importe_desactivar: float,
     *   por_empresa: array<int, array{abiertos:int,importe:float,insertar:int,actualizar:int,desactivar:int}>,
     *   muestras_insertar: list<array<string,mixed>>,
     *   muestras_desactivar: list<array<string,mixed>>,
     *   aging_antes: array<string,mixed>,
     *   aging_despues_sim: array<string,mixed>
     * }
     */
    public function alinear(string $rutaExcel, Carbon $fechaPosicion, bool $ejecutar = false): array
    {
        if (! is_readable($rutaExcel)) {
            throw new RuntimeException('No se puede leer el Excel: '.$rutaExcel);
        }

        $abiertos = $this->leerMacroAbiertosDesdeExcel($rutaExcel);
        $aging = new PosicionBancariaChequeAgingSupport();

        $activosMacro = PosicionBancariaCheque::query()
            ->where('activo', true)
            ->where('banco_canonico', PosicionBancariaChequeAgingSupport::BANCO_MACRO)
            ->get();

        /** @var array<string, list<\App\Models\Tesoreria\PosicionBancariaCheque>> $porClaveActivo */
        $porClaveActivo = [];
        foreach ($activosMacro as $row) {
            $clave = $this->clave((int) $row->empresa_id, (string) $row->numero_cheque);
            $porClaveActivo[$clave][] = $row;
        }

        $aInsertar = [];
        $aActualizar = [];
        foreach ($abiertos as $clave => $item) {
            if (isset($porClaveActivo[$clave])) {
                $aActualizar[$clave] = $item;
            } else {
                $aInsertar[$clave] = $item;
            }
        }

        // Todas las filas activas cuyo número no está en la cartera (incluye duplicados UK viejos).
        // No bajar emitidos vivos del ERP (origen cheque_erp): el Excel de Lucas puede ir atrasado
        // respecto de OPP del día; esos los mantiene UpsertDesdeCheque / observer.
        $origenChequeErp = PosicionBancariaChequeUpsertDesdeChequeSupport::ORIGEN_CHEQUE_ERP;
        $aDesactivar = [];
        $retenidosChequeErp = [];
        foreach ($porClaveActivo as $clave => $rows) {
            if (isset($abiertos[$clave])) {
                // Duplicados del mismo número: dejar una sola activa (la de mayor id) vía upsert;
                // el resto se apaga.
                if (count($rows) > 1) {
                    usort($rows, fn ($a, $b) => $b->id <=> $a->id);
                    foreach (array_slice($rows, 1) as $dup) {
                        $aDesactivar[$dup->id] = $dup;
                    }
                }
                continue;
            }
            foreach ($rows as $row) {
                if ((string) $row->origen === $origenChequeErp) {
                    $retenidosChequeErp[$row->id] = $row;
                    continue;
                }
                $aDesactivar[$row->id] = $row;
            }
        }

        $porEmpresa = [];
        foreach ([1, 2, 3] as $empId) {
            $porEmpresa[$empId] = [
                'abiertos' => 0,
                'importe' => 0.0,
                'insertar' => 0,
                'actualizar' => 0,
                'desactivar' => 0,
            ];
        }
        foreach ($abiertos as $item) {
            $e = (int) $item['empresa_id'];
            $porEmpresa[$e]['abiertos']++;
            $porEmpresa[$e]['importe'] = round($porEmpresa[$e]['importe'] + (float) $item['importe'], 2);
        }
        foreach ($aInsertar as $item) {
            $porEmpresa[(int) $item['empresa_id']]['insertar']++;
        }
        foreach ($aActualizar as $item) {
            $porEmpresa[(int) $item['empresa_id']]['actualizar']++;
        }
        foreach ($aDesactivar as $row) {
            $porEmpresa[(int) $row->empresa_id]['desactivar']++;
        }

        $importeExcel = round(array_sum(array_map(fn ($i) => (float) $i['importe'], $abiertos)), 2);
        $importeDesactivar = round($aDesactivar === [] ? 0.0 : array_sum(array_map(
            fn ($r) => (float) $r->importe,
            $aDesactivar
        )), 2);

        $agingAntes = $aging->resumir(
            $fechaPosicion,
            $activosMacro,
            null,
            false
        );

        $coleccionSim = collect();
        foreach ($abiertos as $item) {
            $coleccionSim->push(new PosicionBancariaCheque([
                'empresa_id' => $item['empresa_id'],
                'cuentacaja_id' => $item['cuentacaja_id'],
                'banco_canonico' => PosicionBancariaChequeAgingSupport::BANCO_MACRO,
                'tip' => 'CHP',
                'numero_cheque' => $item['numero_cheque'],
                'fecha_emision' => $item['fecha_emision'],
                'fecha_cheque' => $item['fecha_cheque'],
                'importe' => $item['importe'],
                'estado' => ' ',
                'activo' => true,
                'en_portfolio_posicion' => true,
            ]));
        }
        foreach ($retenidosChequeErp as $row) {
            $coleccionSim->push($row);
        }
        $agingDespues = $aging->resumir($fechaPosicion, $coleccionSim, null, false);

        $resultado = [
            'dry_run' => ! $ejecutar,
            'abiertos_excel' => count($abiertos),
            'importe_excel' => $importeExcel,
            'a_insertar' => count($aInsertar),
            'a_actualizar' => count($aActualizar),
            'a_desactivar' => count($aDesactivar),
            'importe_desactivar' => $importeDesactivar,
            'por_empresa' => $porEmpresa,
            'muestras_insertar' => $this->muestrasInsertar($aInsertar, 12),
            'muestras_desactivar' => $this->muestrasDesactivar($aDesactivar, 12),
            'aging_antes' => $agingAntes,
            'aging_despues_sim' => $agingDespues,
        ];

        if (! $ejecutar) {
            return $resultado;
        }

        DB::transaction(function () use ($abiertos, $aDesactivar): void {
            foreach ($abiertos as $item) {
                $this->upsertAbierto($item);
            }
            foreach ($aDesactivar as $row) {
                $row->activo = false;
                $row->en_portfolio_posicion = false;
                $json = is_array($row->origen_json) ? $row->origen_json : [];
                $json['macro_alineado_baja_at'] = now()->toIso8601String();
                $row->origen_json = $json;
                $row->save();
            }
        });

        $resultado['dry_run'] = false;

        return $resultado;
    }

    /**
     * @return array<string, array{
     *   empresa_id:int,
     *   cuentacaja_id:?int,
     *   numero_cheque:string,
     *   fecha_emision:?string,
     *   fecha_cheque:?string,
     *   importe:float,
     *   entregado_a:string
     * }>
     */
    public function leerMacroAbiertosDesdeExcel(string $rutaExcel): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setLoadSheetsOnly(array_keys(self::HOJA_EMPRESA));
        $reader->setReadFilter(new PosicionBancariaChequeExcelReadFilter());
        $wb = $reader->load($rutaExcel);

        $out = [];
        foreach (self::HOJA_EMPRESA as $hoja => $empresaId) {
            if (! $wb->sheetNameExists($hoja)) {
                continue;
            }
            $ws = $wb->getSheetByName($hoja);
            $max = (int) $ws->getHighestDataRow();
            for ($r = 1; $r <= $max; $r++) {
                $tip = strtoupper(trim((string) $ws->getCell('A'.$r)->getValue()));
                if ($tip !== '' && $tip !== 'CHP') {
                    continue;
                }
                $banco = PosicionBancariaChequeAgingSupport::normalizarBanco(
                    (string) $ws->getCell('H'.$r)->getValue()
                );
                if ($banco !== PosicionBancariaChequeAgingSupport::BANCO_MACRO) {
                    continue;
                }
                $nro = ltrim((string) preg_replace('/\D/', '', (string) $ws->getCell('B'.$r)->getValue()), '0');
                if ($nro === '' || $nro === '0') {
                    continue;
                }
                // Col G = créditos (stock abierto). Débitos en F = bajas, se ignoran.
                $credito = round((float) $ws->getCell('G'.$r)->getCalculatedValue(), 2);
                if ($credito < 0.005) {
                    continue;
                }
                $fechaEmision = $this->celdaFecha($ws->getCell('C'.$r)->getValue());
                $fechaCheque = $this->celdaFecha($ws->getCell('D'.$r)->getValue());
                $entregado = trim((string) $ws->getCell('E'.$r)->getValue());
                $cuentacajaId = PosicionBancariaChequeAgingSupport::resolverCuentacajaId(
                    $empresaId,
                    PosicionBancariaChequeAgingSupport::BANCO_MACRO
                );
                $clave = $this->clave($empresaId, $nro);
                // Si el Excel repite el número, gana el de mayor crédito (último operativo).
                if (isset($out[$clave]) && (float) $out[$clave]['importe'] >= $credito) {
                    continue;
                }
                $out[$clave] = [
                    'empresa_id' => $empresaId,
                    'cuentacaja_id' => $cuentacajaId,
                    'numero_cheque' => $nro,
                    'fecha_emision' => $fechaEmision,
                    'fecha_cheque' => $fechaCheque,
                    'importe' => $credito,
                    'entregado_a' => mb_substr($entregado, 0, 120),
                ];
            }
        }
        unset($wb);

        return $out;
    }

    /**
     * @param  array{
     *   empresa_id:int,
     *   cuentacaja_id:?int,
     *   numero_cheque:string,
     *   fecha_emision:?string,
     *   fecha_cheque:?string,
     *   importe:float,
     *   entregado_a:string
     * }  $item
     */
    private function upsertAbierto(array $item): void
    {
        $existing = PosicionBancariaCheque::query()
            ->where('empresa_id', $item['empresa_id'])
            ->where('banco_canonico', PosicionBancariaChequeAgingSupport::BANCO_MACRO)
            ->where('numero_cheque', $item['numero_cheque'])
            ->orderByDesc('activo')
            ->orderByDesc('id')
            ->first();

        $payload = [
            'cuentacaja_id' => $item['cuentacaja_id'],
            'tip' => 'CHP',
            'fecha_emision' => $item['fecha_emision'],
            'fecha_cheque' => $item['fecha_cheque'],
            'importe' => $item['importe'],
            'estado' => ' ',
            'entregado_a' => $item['entregado_a'],
            'activo' => true,
            'en_portfolio_posicion' => true,
            'origen' => 'excel_posicion',
            'origen_json' => [
                'macro_alineado_at' => now()->toIso8601String(),
                'fuente' => 'posicion_operativa_lucas',
            ],
        ];

        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return;
        }

        PosicionBancariaCheque::query()->create(array_merge($payload, [
            'empresa_id' => $item['empresa_id'],
            'banco_canonico' => PosicionBancariaChequeAgingSupport::BANCO_MACRO,
            'numero_cheque' => $item['numero_cheque'],
            'estado_banco' => '',
            'proveedor_codigo' => '',
            'nro_op' => '',
        ]));
    }

    private function clave(int $empresaId, string $numero): string
    {
        return $empresaId.'|'.ltrim($numero, '0');
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
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value))
                    ->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }
        $s = trim((string) $value);
        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, array<string,mixed>>  $items
     * @return list<array<string,mixed>>
     */
    private function muestrasInsertar(array $items, int $limit): array
    {
        $sorted = $items;
        uasort($sorted, fn ($a, $b) => (float) $b['importe'] <=> (float) $a['importe']);
        $out = [];
        foreach (array_slice($sorted, 0, $limit, true) as $item) {
            $out[] = [
                'empresa_id' => $item['empresa_id'],
                'numero' => $item['numero_cheque'],
                'vto' => $item['fecha_cheque'],
                'importe' => $item['importe'],
                'detalle' => $item['entregado_a'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, PosicionBancariaCheque>  $rows
     * @return list<array<string,mixed>>
     */
    private function muestrasDesactivar(array $rows, int $limit): array
    {
        $sorted = $rows;
        uasort($sorted, fn ($a, $b) => abs((float) $b->importe) <=> abs((float) $a->importe));
        $out = [];
        foreach (array_slice($sorted, 0, $limit, true) as $row) {
            $out[] = [
                'empresa_id' => (int) $row->empresa_id,
                'numero' => (string) $row->numero_cheque,
                'tip' => (string) $row->tip,
                'origen' => (string) $row->origen,
                'vto' => $row->fecha_cheque?->toDateString(),
                'importe' => (float) $row->importe,
                'detalle' => (string) $row->entregado_a,
            ];
        }

        return $out;
    }
}
