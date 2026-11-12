<?php

namespace App\Services\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Tesoreria\PosicionBancariaCheque;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaComparacionChequesFlorenciaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aplica la leyenda de Florencia sobre posicion_bancaria_cheque:
 * amarillo → upsert activo; rojo → desactivar.
 */
class ConciliacionBancariaAplicarComparacionChequesService
{
    /**
     * @return array<string, mixed>
     */
    public function aplicar(
        string $ruta,
        int $empresaId,
        int $cuentacajaId,
        bool $aplicar = false,
    ): array {
        $cuenta = Cuentacaja::query()->find($cuentacajaId);
        if (! $cuenta || ! $cuenta->perteneceAEmpresa($empresaId)) {
            throw new RuntimeException('Cuenta de caja inválida para la empresa.');
        }

        $cmp = ConciliacionBancariaComparacionChequesFlorenciaSupport::leer($ruta);
        $amarillos = $cmp['amarillos'];
        $rojos = $cmp['rojos'];

        $alta = 0;
        $reactiva = 0;
        $actualiza = 0;
        $baja = 0;
        $rojosSinStock = 0;

        $detalleAltas = [];
        $detalleBajas = [];

        DB::transaction(function () use (
            $aplicar,
            $empresaId,
            $cuentacajaId,
            $amarillos,
            $rojos,
            &$alta,
            &$reactiva,
            &$actualiza,
            &$baja,
            &$rojosSinStock,
            &$detalleAltas,
            &$detalleBajas,
        ): void {
            foreach ($amarillos as $fila) {
                $numero = (string) $fila['numero'];
                $fechaCheque = $fila['fecha_cheque'] ?? null;
                $q = PosicionBancariaCheque::query()
                    ->where('empresa_id', $empresaId)
                    ->where('cuentacaja_id', $cuentacajaId)
                    ->where('numero_cheque', $numero);
                if ($fechaCheque) {
                    $q->whereDate('fecha_cheque', $fechaCheque);
                }
                $existentes = $q->get();
                $payload = [
                    'tip' => (string) ($fila['tip'] ?: 'CHP'),
                    'fecha_emision' => $fila['fecha_emision'] ?? null,
                    'fecha_cheque' => $fechaCheque,
                    'importe' => abs((float) $fila['importe']),
                    'entregado_a' => mb_substr((string) ($fila['detalle'] ?? ''), 0, 120),
                    'activo' => true,
                    'en_portfolio_posicion' => true,
                    'origen' => 'florencia_comparacion',
                    'origen_json' => [
                        'fuente' => 'comparacion_cheques_anita_erp',
                        'color' => 'amarillo',
                        'aplicado_at' => now()->toIso8601String(),
                    ],
                ];

                if ($existentes->isEmpty()) {
                    if ($aplicar) {
                        PosicionBancariaCheque::query()->create(array_merge($payload, [
                            'empresa_id' => $empresaId,
                            'cuentacaja_id' => $cuentacajaId,
                            'banco_canonico' => 'MACRO',
                            'numero_cheque' => $numero,
                            'estado' => ' ',
                            'estado_banco' => '',
                            'proveedor_codigo' => '',
                            'nro_op' => $payload['tip'] === 'OPP' ? $numero : '',
                        ]));
                    }
                    $alta++;
                    $detalleAltas[] = $numero;
                    continue;
                }

                foreach ($existentes as $row) {
                    $eraInactivo = ! $row->activo;
                    if ($aplicar) {
                        $row->fill($payload);
                        if ($payload['tip'] === 'OPP' && trim((string) $row->nro_op) === '') {
                            $row->nro_op = $numero;
                        }
                        $row->save();
                    }
                    if ($eraInactivo) {
                        $reactiva++;
                        $detalleAltas[] = $numero;
                    } else {
                        $actualiza++;
                    }
                }
            }

            $numsRojos = [];
            foreach ($rojos as $fila) {
                $n = (string) $fila['numero'];
                if ($n !== '') {
                    $numsRojos[$n] = true;
                }
            }
            if ($numsRojos !== []) {
                $rows = PosicionBancariaCheque::query()
                    ->where('empresa_id', $empresaId)
                    ->where('cuentacaja_id', $cuentacajaId)
                    ->whereIn('numero_cheque', array_keys($numsRojos))
                    ->get();
                $vistos = [];
                foreach ($rows as $row) {
                    $vistos[(string) $row->numero_cheque] = true;
                    if (! $row->activo && ! $row->en_portfolio_posicion) {
                        continue;
                    }
                    if ($aplicar) {
                        $row->activo = false;
                        $row->en_portfolio_posicion = false;
                        $json = is_array($row->origen_json) ? $row->origen_json : [];
                        $json['baja_florencia'] = [
                            'color' => 'rojo',
                            'aplicado_at' => now()->toIso8601String(),
                        ];
                        $row->origen_json = $json;
                        $row->save();
                    }
                    $baja++;
                    $detalleBajas[] = (string) $row->numero_cheque;
                }
                foreach (array_keys($numsRojos) as $n) {
                    if (! isset($vistos[$n])) {
                        $rojosSinStock++;
                    }
                }
            }
        });

        return [
            'ok' => true,
            'aplicar' => $aplicar,
            'archivo' => $cmp['archivo'],
            'codigo' => (string) $cuenta->codigo,
            'cuentacaja_id' => $cuentacajaId,
            'empresa_id' => $empresaId,
            'amarillos_n' => count($amarillos),
            'rojos_n' => count($rojos),
            'verdes_anita_n' => count($cmp['verdes_anita']),
            'verdes_erp_n' => count($cmp['verdes_erp']),
            'altas' => $alta,
            'reactivados' => $reactiva,
            'actualizados' => $actualiza,
            'bajas' => $baja,
            'rojos_sin_stock' => $rojosSinStock,
            'altas_numeros' => array_values(array_unique($detalleAltas)),
            'bajas_numeros' => array_values(array_unique($detalleBajas)),
        ];
    }
}
