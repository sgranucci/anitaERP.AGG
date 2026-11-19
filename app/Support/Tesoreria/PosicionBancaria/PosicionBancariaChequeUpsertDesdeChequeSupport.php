<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Models\Caja\Cheque;
use App\Models\Tesoreria\PosicionBancariaCheque;
use App\Support\Finanzas\FinanzaPosicionHojaSupport;
use Carbon\Carbon;

/**
 * Mantiene posicion_bancaria_cheque al día con cheques propios emitidos (Macro).
 * Alta/actualización al grabar; baja si está anulado.
 */
final class PosicionBancariaChequeUpsertDesdeChequeSupport
{
    public const ORIGEN_CHEQUE_ERP = 'cheque_erp';

    public function sincronizarSiAplica(Cheque $cheque): void
    {
        if (strtoupper((string) $cheque->origen) !== 'E') {
            return;
        }
        $numero = $this->numeroCanonico((string) $cheque->numerocheque);
        if ($numero === '') {
            return;
        }
        $empresaId = (int) $cheque->empresa_id;
        $cuentacajaId = (int) ($cheque->cuentacaja_id ?? 0);
        if ($empresaId <= 0 || $cuentacajaId <= 0) {
            return;
        }

        $cuenta = $cheque->relationLoaded('cuentacajas')
            ? $cheque->cuentacajas
            : $cheque->cuentacajas()->first();
        $hoja = FinanzaPosicionHojaSupport::hojaDesdeCuenta($cuenta);
        if ($hoja !== 'Macro') {
            return;
        }

        $estado = (string) ($cheque->estado ?? '');
        $anulado = strtoupper(trim($estado)) === 'A';

        $existing = PosicionBancariaCheque::query()
            ->where('empresa_id', $empresaId)
            ->where('banco_canonico', PosicionBancariaChequeAgingSupport::BANCO_MACRO)
            ->where('numero_cheque', $numero)
            ->orderByDesc('activo')
            ->orderByDesc('id')
            ->first();

        if ($anulado) {
            if ($existing && $existing->activo) {
                $existing->activo = false;
                $existing->en_portfolio_posicion = false;
                $existing->estado = 'A';
                $json = is_array($existing->origen_json) ? $existing->origen_json : [];
                $json['baja_desde_cheque_at'] = now()->toIso8601String();
                $json['cheque_id'] = $cheque->id;
                $existing->origen_json = $json;
                $existing->save();
            }

            return;
        }

        $entregado = trim((string) ($cheque->anombrede ?? ''));
        if ($entregado === '' && $cheque->relationLoaded('proveedores') && $cheque->proveedores) {
            $entregado = trim((string) ($cheque->proveedores->nombre ?? ''));
        } elseif ($entregado === '') {
            $proveedor = $cheque->proveedores()->first();
            $entregado = trim((string) ($proveedor->nombre ?? ''));
        }

        $payload = [
            'cuentacaja_id' => $cuentacajaId,
            'tip' => 'CHP',
            'fecha_emision' => $cheque->fechaemision,
            'fecha_cheque' => $cheque->fechapago,
            'fecha_entrega' => $cheque->fecha_entrega,
            'importe' => abs((float) $cheque->monto),
            'estado' => ' ',
            'entregado_a' => mb_substr($entregado, 0, 120),
            'activo' => true,
            'en_portfolio_posicion' => true,
            'origen' => self::ORIGEN_CHEQUE_ERP,
            'origen_json' => [
                'cheque_id' => $cheque->id,
                'pagoproveedor_id' => $cheque->pagoproveedor_id,
                'sync_at' => now()->toIso8601String(),
            ],
            'nro_op' => $cheque->pagoproveedor_id ? (string) $cheque->pagoproveedor_id : '',
        ];

        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return;
        }

        PosicionBancariaCheque::query()->create(array_merge($payload, [
            'empresa_id' => $empresaId,
            'banco_canonico' => PosicionBancariaChequeAgingSupport::BANCO_MACRO,
            'numero_cheque' => $numero,
            'estado_banco' => '',
            'proveedor_codigo' => '',
        ]));
    }

    /**
     * Backfill acotado: emitidos Macro abiertos con fechaemision >= $desdeEmision.
     * No reabre el histórico (estado * viejo ≠ cartera viva; eso lo fija el Excel / align).
     *
     * @return array{
     *   dry_run: bool,
     *   desde_emision: string,
     *   revisados: int,
     *   a_insertar: int,
     *   a_reactivar: int,
     *   a_actualizar: int,
     *   ya_ok: int,
     *   omitidos_no_macro: int,
     *   importe_alta: float,
     *   por_cuenta: array<int, array{insertar:int,reactivar:int,actualizar:int,importe:float}>,
     *   muestras: list<array<string,mixed>>
     * }
     */
    public function sincronizarAbiertosMacro(bool $ejecutar = false, ?Carbon $desdeEmision = null): array
    {
        $desde = ($desdeEmision ?? Carbon::today())->toDateString();

        $cheques = Cheque::query()
            ->with(['cuentacajas', 'proveedores'])
            ->where('origen', 'E')
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', ' ')
                    ->orWhere('estado', '')
                    ->orWhere('estado', '*');
            })
            ->whereDate('fechaemision', '>=', $desde)
            ->whereNotNull('cuentacaja_id')
            ->orderBy('id')
            ->get();

        $porCuenta = [];
        $muestras = [];
        $aInsertar = 0;
        $aReactivar = 0;
        $aActualizar = 0;
        $yaOk = 0;
        $omitidos = 0;
        $importeAlta = 0.0;

        foreach ($cheques as $cheque) {
            $cuenta = $cheque->cuentacajas;
            $hoja = FinanzaPosicionHojaSupport::hojaDesdeCuenta($cuenta);
            if ($hoja !== 'Macro') {
                $omitidos++;
                continue;
            }

            $numero = $this->numeroCanonico((string) $cheque->numerocheque);
            if ($numero === '') {
                $omitidos++;
                continue;
            }

            $empresaId = (int) $cheque->empresa_id;
            $codigoCuenta = (int) ($cuenta->codigo ?? 0);
            if (! isset($porCuenta[$codigoCuenta])) {
                $porCuenta[$codigoCuenta] = [
                    'insertar' => 0,
                    'reactivar' => 0,
                    'actualizar' => 0,
                    'importe' => 0.0,
                ];
            }

            $importe = abs((float) $cheque->monto);
            $existing = PosicionBancariaCheque::query()
                ->where('empresa_id', $empresaId)
                ->where('banco_canonico', PosicionBancariaChequeAgingSupport::BANCO_MACRO)
                ->where('numero_cheque', $numero)
                ->orderByDesc('activo')
                ->orderByDesc('id')
                ->first();

            $accion = 'ok';
            if ($existing === null) {
                $accion = 'insertar';
                $aInsertar++;
                $porCuenta[$codigoCuenta]['insertar']++;
                $porCuenta[$codigoCuenta]['importe'] = round($porCuenta[$codigoCuenta]['importe'] + $importe, 2);
                $importeAlta = round($importeAlta + $importe, 2);
            } elseif (! $existing->activo) {
                $accion = 'reactivar';
                $aReactivar++;
                $porCuenta[$codigoCuenta]['reactivar']++;
                $porCuenta[$codigoCuenta]['importe'] = round($porCuenta[$codigoCuenta]['importe'] + $importe, 2);
                $importeAlta = round($importeAlta + $importe, 2);
            } elseif (abs((float) $existing->importe - $importe) > 0.009
                || (int) $existing->cuentacaja_id !== (int) $cheque->cuentacaja_id) {
                $accion = 'actualizar';
                $aActualizar++;
                $porCuenta[$codigoCuenta]['actualizar']++;
            } else {
                $yaOk++;
            }

            if ($accion !== 'ok' && count($muestras) < 20) {
                $muestras[] = [
                    'accion' => $accion,
                    'cheque_id' => $cheque->id,
                    'cuenta' => $codigoCuenta,
                    'numero' => $numero,
                    'importe' => $importe,
                    'detalle' => mb_substr(trim((string) ($cheque->anombrede ?? '')), 0, 40),
                ];
            }

            if ($ejecutar && $accion !== 'ok') {
                $this->sincronizarSiAplica($cheque);
            }
        }

        ksort($porCuenta);

        return [
            'dry_run' => ! $ejecutar,
            'desde_emision' => $desde,
            'revisados' => $cheques->count(),
            'a_insertar' => $aInsertar,
            'a_reactivar' => $aReactivar,
            'a_actualizar' => $aActualizar,
            'ya_ok' => $yaOk,
            'omitidos_no_macro' => $omitidos,
            'importe_alta' => $importeAlta,
            'por_cuenta' => $porCuenta,
            'muestras' => $muestras,
        ];
    }

    private function numeroCanonico(string $numero): string
    {
        $n = ltrim((string) preg_replace('/\D/', '', $numero), '0');

        return ($n === '' || $n === '0') ? '' : $n;
    }
}
