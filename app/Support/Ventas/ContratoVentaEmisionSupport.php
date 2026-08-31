<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Contrato_Venta_Periodo;
use App\Models\Ventas\Venta_Emision;
use App\Models\Ventas\Venta_Emision_Tag_Valor;
use App\Support\Database\EloquentAuditDeleteSupport;

/**
 * Persistencia de custom fields de tags y períodos facturados del abono.
 */
final class ContratoVentaEmisionSupport
{
    /**
     * @param  array<string, string>  $valores
     */
    public static function sincronizarTagValores(int $ventaEmisionId, array $valores): void
    {
        if ($ventaEmisionId <= 0) {
            return;
        }

        EloquentAuditDeleteSupport::each(
            Venta_Emision_Tag_Valor::query()->where('venta_emision_id', $ventaEmisionId)
        );

        foreach ($valores as $clave => $valor) {
            $claveN = ConceptoVentaPlantillaMotor::normalizarClave((string) $clave);
            if ($claveN === '') {
                continue;
            }
            Venta_Emision_Tag_Valor::query()->create([
                'venta_emision_id' => $ventaEmisionId,
                'clave' => $claveN,
                'valor' => mb_substr(trim((string) $valor), 0, 255),
            ]);
        }
    }

    public static function marcarPeriodoFacturado(
        int $contratoId,
        string $periodoDesde,
        string $periodoHasta,
        int $ventaId,
        ?int $ventaEmisionId = null,
    ): void {
        if ($contratoId <= 0 || $periodoDesde === '' || $periodoHasta === '') {
            return;
        }

        $existente = Contrato_Venta_Periodo::query()
            ->where('contrato_venta_id', $contratoId)
            ->where('periodo_desde', $periodoDesde)
            ->where('periodo_hasta', $periodoHasta)
            ->first();

        if ($existente) {
            $existente->update([
                'estado' => ContratoVentaSupport::PERIODO_FACTURADO,
                'venta_id' => $ventaId > 0 ? $ventaId : $existente->venta_id,
                'venta_emision_id' => $ventaEmisionId ?: $existente->venta_emision_id,
            ]);

            return;
        }

        Contrato_Venta_Periodo::query()->create([
            'contrato_venta_id' => $contratoId,
            'periodo_desde' => $periodoDesde,
            'periodo_hasta' => $periodoHasta,
            'estado' => ContratoVentaSupport::PERIODO_FACTURADO,
            'venta_id' => $ventaId > 0 ? $ventaId : null,
            'venta_emision_id' => $ventaEmisionId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $itemEmision
     */
    public static function persistirTrasCrearEmision(Venta_Emision $emision, array $itemEmision, int $ventaId): void
    {
        $valores = $itemEmision['tag_valores'] ?? [];
        if (is_string($valores)) {
            $decoded = json_decode($valores, true);
            $valores = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($valores)) {
            $valores = [];
        }

        /** @var array<string, string> $cast */
        $cast = [];
        foreach ($valores as $k => $v) {
            $cast[(string) $k] = (string) $v;
        }
        self::sincronizarTagValores((int) $emision->id, $cast);

        $contratoId = (int) ($itemEmision['contrato_venta_id'] ?? $emision->contrato_venta_id ?? 0);
        $desde = substr(trim((string) ($itemEmision['periodo_desde'] ?? '')), 0, 10);
        $hasta = substr(trim((string) ($itemEmision['periodo_hasta'] ?? '')), 0, 10);
        if ($contratoId > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            self::marcarPeriodoFacturado($contratoId, $desde, $hasta, $ventaId, (int) $emision->id);
        }
    }
}
