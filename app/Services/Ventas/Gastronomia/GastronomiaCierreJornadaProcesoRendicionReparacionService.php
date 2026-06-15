<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPostCierreCaeaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Verifica y re-graba rendiciones post-cierre Waitry (CIERRE-WAITRY) cruzando ERP vs rendgastro.
 */
final class GastronomiaCierreJornadaProcesoRendicionReparacionService
{
    public function __construct(
        private readonly GastronomiaConciliacionPostCierreCaeaSupport $postCierreSupport,
        private readonly GastronomiaCierreJornadaProcesoRendicionAnitaService $rendicionAnitaService,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function verificarJornada(JornadaGastronomia $jornada, float $tolerancia = 0.02): array
    {
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $empresaId = (int) $jornada->empresa_id;
        $post = $this->postCierreSupport->totalesDia($empresaId, $fecha);
        $cabeceras = $this->rendgastroSupport->listarCabecerasPostCierrePorJornada($empresaId, (int) $jornada->id);

        $erp = round((float) ($post['ventas_erp'] ?? 0), 2);
        $rendg = null;
        if ($cabeceras !== []) {
            $suma = 0.0;
            foreach ($cabeceras as $cab) {
                $mapped = $this->importePostCierreCabecera($cab);
                $suma += (float) ($mapped['total'] ?? 0);
            }
            $rendg = round($suma, 2);
        }

        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->first();
        $rendSnap = is_array($snapshot?->payload['rendicion_proceso_anita'] ?? null)
            ? $snapshot->payload['rendicion_proceso_anita']
            : null;

        $estado = 'ok';
        if ($erp <= $tolerancia && ($rendg === null || $rendg <= $tolerancia)) {
            $estado = 'sin_actividad';
        } elseif ($cabeceras === []) {
            $estado = $erp > $tolerancia ? 'sin_rendgastro' : 'ok';
        } elseif ($rendg !== null && abs($erp - $rendg) > $tolerancia) {
            $estado = 'dif';
        }

        $snapshotValido = $rendSnap !== null
            && $this->rendicionAnitaService->rendicionPostCierreValidaEnAnita(
                $empresaId,
                (int) $jornada->id,
                $rendSnap,
            );

        return [
            'jornada_id' => (int) $jornada->id,
            'fecha_jornada' => $fecha,
            'empresa_id' => $empresaId,
            'ventas_erp_post_cierre' => $erp,
            'rendgastro_post_cierre' => $rendg,
            'cabeceras_waitry' => count($cabeceras),
            'snapshot_nro_oper' => $rendSnap['nro_oper'] ?? null,
            'snapshot_valido' => $snapshotValido,
            'estado' => $estado,
            'requiere_regrabado' => in_array($estado, ['sin_rendgastro', 'dif'], true) || ($erp > $tolerancia && ! $snapshotValido),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function regrabarJornada(JornadaGastronomia $jornada, bool $dryRun = false, float $tolerancia = 0.02): array
    {
        $verificacion = $this->verificarJornada($jornada, $tolerancia);
        if (! ($verificacion['requiere_regrabado'] ?? false)) {
            return array_merge($verificacion, [
                'accion' => 'ninguna',
                'mensaje' => 'Post-cierre OK en rendgastro.',
            ]);
        }

        if ($dryRun) {
            return array_merge($verificacion, [
                'accion' => 'simulado',
                'mensaje' => 'Se limpiaría snapshot inválido y se grabaría CIERRE-WAITRY con numeración dedicada.',
            ]);
        }

        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->firstOrFail();

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $rendSnap = is_array($payload['rendicion_proceso_anita'] ?? null)
            ? $payload['rendicion_proceso_anita']
            : null;

        if ($rendSnap !== null
            && ! $this->rendicionAnitaService->rendicionPostCierreValidaEnAnita(
                (int) $jornada->empresa_id,
                (int) $jornada->id,
                $rendSnap,
            )) {
            unset($payload['rendicion_proceso_anita']);
            DB::transaction(function () use ($snapshot, $payload) {
                $snapshot->payload = $payload;
                $snapshot->save();
            });
        }

        $resultado = $this->rendicionAnitaService->grabar((int) $jornada->id);
        $postVerificacion = $this->verificarJornada($jornada, $tolerancia);

        return array_merge($postVerificacion, [
            'accion' => 'regrabado',
            'mensaje' => (string) ($resultado['mensaje'] ?? 'Rendición re-grabada.'),
            'grabar' => $resultado,
        ]);
    }

    /**
     * @return array{total: float|null, total_x: float|null}
     */
    private function importePostCierreCabecera(object $cab): array
    {
        $x = round((float) ($cab->rendg_total_x ?? 0), 2);
        $z = round((float) ($cab->rendg_total_z ?? 0), 2);
        $total = $x > 0 ? $x : ($z > 0 ? $z : null);

        return ['total' => $total, 'total_x' => $x > 0 ? $x : null];
    }
}
