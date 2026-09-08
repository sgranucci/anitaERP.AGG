<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Models\Caja\Cuentacaja;
use App\Models\Tesoreria\PosicionBancariaCheque;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaCpromaeBridgeReader;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaPendientesCpromaeSupport;
use Illuminate\Support\Facades\DB;

/**
 * Sync batch Anita cpromae → posicion_bancaria_cheque (sin uso online en requests).
 */
final class PosicionBancariaChequeCpromaeSyncSupport
{
    public function __construct(
        private readonly ConciliacionBancariaCpromaeBridgeReader $bridge = new ConciliacionBancariaCpromaeBridgeReader(),
        private readonly ConciliacionBancariaPendientesCpromaeSupport $mapper = new ConciliacionBancariaPendientesCpromaeSupport(),
    ) {
    }

    /**
     * @param  list<int>|null  $empresaIds
     * @return array{cuentas:int,leidos:int,anio:int,inserted:int,updated:int,skipped:int,errores:list<string>}
     */
    public function syncAnio(int $anio, ?array $empresaIds = null, ?callable $onCuenta = null): array
    {
        $empresaIds ??= [1, 2, 3];
        $desde = $anio * 10000 + 101;
        $hasta = $anio * 10000 + 1231;

        $cuentas = Cuentacaja::query()
            ->with('bancos')
            ->whereIn('empresa_id', $empresaIds)
            ->where(function ($q) {
                $q->whereIn('banco_id', [54, 56, 58]) // MACRO / ITAU / INDUSTRIAL
                    ->orWhere('nombre', 'like', '%MACRO%')
                    ->orWhere('nombre', 'like', '%ITAU%')
                    ->orWhere('nombre', 'like', '%INDUSTRIAL%')
                    ->orWhere('nombre', 'like', '%BIND%')
                    ->orWhere('nombre', 'like', 'BCO.%')
                    ->orWhere('nombre', 'like', 'BANCO %');
            })
            ->orderBy('empresa_id')
            ->orderBy('codigo')
            ->get();

        // Asegurar cuentas canónicas de posición aunque no matcheen el filtro.
        $canonIds = [];
        foreach (PosicionBancariaChequeAgingSupport::CUENTAS_CANONICAS as $empId => $map) {
            if (! in_array((int) $empId, $empresaIds, true)) {
                continue;
            }
            foreach ($map as $codigo) {
                $canonIds[] = [$empId, (string) $codigo];
            }
        }
        foreach ($canonIds as [$empId, $codigo]) {
            if ($cuentas->first(fn ($c) => (int) $c->empresa_id === (int) $empId && (string) $c->codigo === $codigo)) {
                continue;
            }
            $extra = Cuentacaja::query()->with('bancos')
                ->where('empresa_id', $empId)->where('codigo', $codigo)->first();
            if ($extra) {
                $cuentas->push($extra);
            }
        }

        $stats = [
            'cuentas' => 0,
            'leidos' => 0,
            'anio' => $anio,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errores' => [],
        ];

        foreach ($cuentas as $cc) {
            $codigo = trim((string) $cc->codigo);
            if ($codigo === '') {
                continue;
            }
            $stats['cuentas']++;
            try {
                $rows = $this->bridge->listarPorCuenta($codigo, (int) $cc->empresa_id);
            } catch (\Throwable $e) {
                $stats['errores'][] = "cta {$codigo} emp {$cc->empresa_id}: ".$e->getMessage();
                if ($onCuenta) {
                    $onCuenta($cc, 0, 0, $e->getMessage());
                }
                continue;
            }

            $bancoCanonico = $this->resolverBancoCanonico($cc);
            $enAnio = 0;
            $upserts = 0;

            foreach ($rows as $raw) {
                $stats['leidos']++;
                $fc = (int) preg_replace('/\D/', '', (string) ($raw->cpro_fecha_cheque ?? ''));
                $fe = (int) preg_replace('/\D/', '', (string) ($raw->cpro_fecha_emision ?? ''));
                $inAnio = ($fc >= $desde && $fc <= $hasta) || ($fe >= $desde && $fe <= $hasta);
                if (! $inAnio) {
                    $stats['skipped']++;
                    continue;
                }
                $enAnio++;

                $mapped = $this->mapper->mapearFila($raw);
                if ($mapped === null) {
                    $stats['skipped']++;
                    continue;
                }

                $estado = (string) ($mapped['estado'] ?? '');
                $activo = ! in_array(strtoupper(trim($estado)), ['A', '*'], true);

                $payload = [
                    'banco_canonico' => $bancoCanonico,
                    'tip' => $mapped['tip'] ?? 'CHP',
                    'fecha_emision' => $mapped['fecha_emision'],
                    'fecha_cheque' => $mapped['fecha_cheque'],
                    'fecha_entrega' => $mapped['fecha_entrega'],
                    'importe' => abs((float) $mapped['importe']),
                    'estado' => $estado === '' ? ' ' : $estado,
                    'estado_banco' => $mapped['estado_banco'] ?? null,
                    'entregado_a' => mb_substr((string) ($mapped['entregado_a'] ?? ''), 0, 120),
                    'proveedor_codigo' => $mapped['proveedor_codigo'] ?? null,
                    'nro_op' => $mapped['nro_op'] ?? null,
                    'activo' => $activo,
                    // No tocar en_portfolio_posicion: lo marca el import Excel / tesorería.
                    'origen' => 'cpromae_'.$anio,
                    'origen_json' => array_merge(
                        is_array($mapped['origen_json'] ?? null) ? $mapped['origen_json'] : [],
                        [
                            'sync_anio' => $anio,
                            'para_dep' => $mapped['para_dep'] ?? null,
                            'synced_at' => now()->toIso8601String(),
                        ],
                    ),
                    'updated_at' => now(),
                ];

                $existing = PosicionBancariaCheque::query()
                    ->where('empresa_id', (int) $cc->empresa_id)
                    ->where('cuentacaja_id', (int) $cc->id)
                    ->where('numero_cheque', $mapped['numero_cheque'])
                    ->first();

                if ($existing) {
                    // Preservar flag de portfolio y origen excel si ya estaba.
                    unset($payload['origen']);
                    if ($existing->origen === 'excel_posicion' || $existing->en_portfolio_posicion) {
                        // mantener origen excel_posicion
                    } else {
                        $payload['origen'] = 'cpromae_'.$anio;
                    }
                    $existing->fill($payload);
                    $existing->save();
                    $stats['updated']++;
                } else {
                    PosicionBancariaCheque::query()->create(array_merge($payload, [
                        'empresa_id' => (int) $cc->empresa_id,
                        'cuentacaja_id' => (int) $cc->id,
                        'numero_cheque' => $mapped['numero_cheque'],
                        'en_portfolio_posicion' => false,
                        'created_at' => now(),
                    ]));
                    $stats['inserted']++;
                }
                $upserts++;
            }

            if ($onCuenta) {
                $onCuenta($cc, count($rows), $enAnio, null);
            }
        }

        return $stats;
    }

    private function resolverBancoCanonico(Cuentacaja $cc): string
    {
        $fromMap = null;
        $maps = PosicionBancariaChequeAgingSupport::CUENTAS_CANONICAS[(int) $cc->empresa_id] ?? [];
        foreach ($maps as $banco => $codigo) {
            if ((string) $cc->codigo === (string) $codigo) {
                return $banco;
            }
        }

        $nombre = strtoupper(trim((string) ($cc->nombre ?? '')));
        $bancoNombre = strtoupper(trim((string) ($cc->bancos->nombre ?? '')));
        $blob = $nombre.' '.$bancoNombre;

        $norm = PosicionBancariaChequeAgingSupport::normalizarBanco($blob);
        if ($norm !== null) {
            return $norm;
        }

        return 'CTA'.preg_replace('/\W+/', '', (string) $cc->codigo);
    }
}
