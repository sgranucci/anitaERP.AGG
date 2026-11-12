<?php

namespace App\Services\Caja\Flash;

use App\Models\Caja\Flash\FlashCrown;
use App\Support\Caja\Flash\FlashCrownCalculoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class FlashCrownService
{
    /**
     * Calcula online y opcionalmente persiste (respeta overrides y no pisa validado salvo forzar).
     *
     * @param  array<string, mixed>  $overrides
     */
    public function sincronizar(
        int $salaId,
        string $fechaYmd,
        array $overrides = [],
        bool $persistir = true,
        bool $forzar = false,
        bool $reemplazarOverrides = false,
    ): FlashCrown {
        $fecha = Carbon::parse($fechaYmd)->toDateString();
        $existente = FlashCrown::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fecha)
            ->first();

        if ($existente && $existente->estaValidado() && ! $forzar) {
            return $existente;
        }

        $calculado = FlashCrownCalculoSupport::deSalaFecha($salaId, $fecha);
        // Sin jornada gastro/hotel (salas que solo traen resultado Anita): no pisar AyB/Hotel ya cargados.
        $fuentes = $calculado['detalle']['fuentes'] ?? [];
        if ($existente && ! in_array('gastro_hotel', $fuentes, true)) {
            if (abs((float) ($calculado['ayb'] ?? 0)) < 0.001) {
                $calculado['ayb'] = round((float) $existente->ayb, 2);
            }
            if (abs((float) ($calculado['hotel'] ?? 0)) < 0.001) {
                $calculado['hotel'] = round((float) $existente->hotel, 2);
            }
        }
        $overridesPrevios = is_array($existente?->overrides_json) ? $existente->overrides_json : [];
        $overridesMerged = $reemplazarOverrides
            ? $overrides
            : array_merge($overridesPrevios, $overrides);
        if ($overridesMerged !== []) {
            $calculado = FlashCrownCalculoSupport::aplicarOverrides($calculado, $overridesMerged);
        }

        $payload = $this->payloadDesdeCalculo($calculado, $overridesMerged);

        if (! $persistir) {
            $tmp = new FlashCrown($payload);
            $tmp->id = $existente->id ?? 0;

            return $tmp;
        }

        return DB::transaction(function () use ($existente, $payload, $salaId, $fecha) {
            if ($existente) {
                $payload['actualizousuario_id'] = Auth::id();
                $existente->fill($payload);
                $existente->save();

                return $existente->fresh(['sala', 'empresa']);
            }

            $payload['sala_id'] = $salaId;
            $payload['fecha'] = $fecha;
            $payload['creousuario_id'] = Auth::id();

            return FlashCrown::query()->create($payload)->fresh(['sala', 'empresa']);
        });
    }

    /**
     * @param  array<string, mixed>  $calculado
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function payloadDesdeCalculo(array $calculado, array $overrides = []): array
    {
        $payload = [];
        foreach (FlashCrown::camposNumericos() as $campo) {
            $payload[$campo] = $calculado[$campo] ?? 0;
        }
        $payload['empresa_id'] = (int) ($calculado['empresa_id'] ?? 0);
        $payload['comentario'] = $calculado['comentario'] ?? null;
        $payload['overrides_json'] = $overrides === [] ? null : $overrides;
        $payload['detalle_json'] = [
            'avisos' => $calculado['avisos'] ?? [],
            'detalle' => $calculado['detalle'] ?? [],
            'metricas' => FlashCrownCalculoSupport::metricasDerivadas($calculado),
        ];
        $payload['origen'] = $overrides === []
            ? FlashCrown::ORIGEN_AUTO
            : FlashCrown::ORIGEN_MIXTO;
        $payload['calculado_en'] = now();

        return $payload;
    }

    public function validar(FlashCrown $flash, int $usuarioId): FlashCrown
    {
        $flash->validado = true;
        $flash->validado_en = now();
        $flash->validado_usuario_id = $usuarioId;
        $flash->save();

        return $flash->fresh();
    }

    public function quitarValidacion(FlashCrown $flash): FlashCrown
    {
        $flash->validado = false;
        $flash->validado_en = null;
        $flash->validado_usuario_id = null;
        $flash->save();

        return $flash->fresh();
    }

    /**
     * Consolida varias salas para una fecha (suma).
     *
     * @param  list<FlashCrown>  $filas
     * @return array<string, float|int>
     */
    public function consolidar(array $filas): array
    {
        $tot = [];
        $globales = array_flip(FlashCrown::camposOnlineGlobal());
        foreach (FlashCrown::camposNumericos() as $campo) {
            $tot[$campo] = 0;
        }
        foreach ($filas as $fila) {
            foreach (FlashCrown::camposNumericos() as $campo) {
                $valor = (float) ($fila->{$campo} ?? 0);
                if (isset($globales[$campo])) {
                    // Misma cifra en todas las salas: no sumar (tomar el de mayor abs).
                    if (abs($valor) > abs((float) $tot[$campo])) {
                        $tot[$campo] = round($valor, 2);
                    }
                } else {
                    $tot[$campo] = round((float) $tot[$campo] + $valor, 2);
                }
            }
        }
        $tot['att'] = (int) round($tot['att']);
        $tot['cant_mesas'] = (int) round($tot['cant_mesas']);
        $tot['cant_slots'] = (int) round($tot['cant_slots']);
        $tot['cant_rul_el'] = (int) round($tot['cant_rul_el']);
        $tot['ol_att'] = (int) round($tot['ol_att'] ?? 0);
        $tot['bs_att'] = (int) round($tot['bs_att'] ?? 0);
        $tot['ad_att'] = (int) round($tot['ad_att'] ?? 0);

        return $tot;
    }
}
