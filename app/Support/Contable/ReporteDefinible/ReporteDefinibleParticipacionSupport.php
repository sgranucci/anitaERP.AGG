<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContableParticipacion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * % participación por empresa en consolidación de un informe.
 */
class ReporteDefinibleParticipacionSupport
{
    /**
     * @return array<int, float> empresa_id => factor 0..1 (default vacío = 100% implícito)
     */
    public function mapaFactores(int $reporteId, ?string $fechaRef = null): array
    {
        $fecha = $fechaRef && strlen($fechaRef) >= 10 ? substr($fechaRef, 0, 10) : null;
        $q = ReporteContableParticipacion::query()
            ->where('reporte_contable_id', $reporteId);

        /** @var array<int, float> $out */
        $out = [];
        foreach ($q->get() as $row) {
            if ($fecha !== null) {
                if ($row->vigente_desde && $fecha < $row->vigente_desde->format('Y-m-d')) {
                    continue;
                }
                if ($row->vigente_hasta && $fecha > $row->vigente_hasta->format('Y-m-d')) {
                    continue;
                }
            }
            $pct = (float) $row->porcentaje;
            if ($pct < 0) {
                $pct = 0;
            }
            $out[(int) $row->empresa_id] = $pct / 100.0;
        }

        return $out;
    }

    public function tienePonderacion(int $reporteId, ?string $fechaRef = null): bool
    {
        $mapa = $this->mapaFactores($reporteId, $fechaRef);
        if ($mapa === []) {
            return false;
        }
        foreach ($mapa as $f) {
            if (abs($f - 1.0) > 1e-9) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{codigo: int, ccosto: int, monto: float, fecha: string, empresa_id?: int}>  $movimientos
     * @param  array<int, float>  $factores
     * @return list<array{codigo: int, ccosto: int, monto: float, fecha: string, empresa_id?: int}>
     */
    public function aplicarAMovimientos(array $movimientos, array $factores): array
    {
        if ($factores === []) {
            return $movimientos;
        }
        $out = [];
        foreach ($movimientos as $mov) {
            $emp = (int) ($mov['empresa_id'] ?? 0);
            $factor = $emp > 0 && array_key_exists($emp, $factores) ? $factores[$emp] : 1.0;
            if (abs($factor) < 1e-12) {
                continue;
            }
            $mov['monto'] = round((float) $mov['monto'] * $factor, 4);
            $out[] = $mov;
        }

        return $out;
    }

    /**
     * @return Collection<int, ReporteContableParticipacion>
     */
    public function listar(int $reporteId): Collection
    {
        return ReporteContableParticipacion::query()
            ->where('reporte_contable_id', $reporteId)
            ->orderBy('empresa_id')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payloadUi(int $reporteId): array
    {
        $out = [];
        foreach ($this->listar($reporteId) as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'empresa_id' => (int) $r->empresa_id,
                'porcentaje' => (float) $r->porcentaje,
                'vigente_desde' => optional($r->vigente_desde)->format('Y-m-d'),
                'vigente_hasta' => optional($r->vigente_hasta)->format('Y-m-d'),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(int $reporteId, array $data): ReporteContableParticipacion
    {
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $pct = (float) ($data['porcentaje'] ?? 100);
        if ($empresaId <= 0) {
            throw ValidationException::withMessages(['empresa_id' => 'Empresa obligatoria.']);
        }
        if ($pct < 0 || $pct > 100) {
            throw ValidationException::withMessages(['porcentaje' => 'El % debe estar entre 0 y 100.']);
        }

        $row = ReporteContableParticipacion::query()->updateOrCreate(
            [
                'reporte_contable_id' => $reporteId,
                'empresa_id' => $empresaId,
            ],
            [
                'porcentaje' => $pct,
                'vigente_desde' => ($data['vigente_desde'] ?? null) ?: null,
                'vigente_hasta' => ($data['vigente_hasta'] ?? null) ?: null,
            ]
        );

        return $row;
    }

    public function eliminar(int $reporteId, int $id): void
    {
        ReporteContableParticipacion::query()
            ->where('reporte_contable_id', $reporteId)
            ->whereKey($id)
            ->delete();
    }
}
