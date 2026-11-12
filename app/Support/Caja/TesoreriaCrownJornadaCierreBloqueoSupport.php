<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownCierre;
use App\Models\Caja\TesoreriaCrownCierreEvento;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Cajas que impiden cerrar la jornada: tienen comprobantes y el cierre vigente
 * no está en estado terminal (conforme / revisada / cierre de auditoría).
 */
final class TesoreriaCrownJornadaCierreBloqueoSupport
{
    /**
     * @return list<array{
     *     asignacion_id: int,
     *     cierre_id: int|null,
     *     caja: string,
     *     cajero: string,
     *     turno: string,
     *     tarea: string,
     *     comprobantes: int,
     *     estado: string|null,
     *     estado_txt: string,
     *     diferencia: float|null,
     *     accion: string,
     *     sanear_auditoria: bool,
     *     url_cierre: string|null
     * }>
     */
    public static function deJornada(TesoreriaCrownJornada $jornada): array
    {
        $out = [];
        foreach (TesoreriaCrownDiaCajeroSupport::filasDeJornada($jornada) as $fila) {
            $bloqueo = self::deDiaCajero($fila);
            if ($bloqueo !== null) {
                $out[] = $bloqueo;
            }
        }

        return $out;
    }

    /**
     * @param  object{
     *     cajero_crown_id: int,
     *     jornada_id: int,
     *     cajero: \App\Models\Caja\CajeroCrown,
     *     tipo_tarea: string,
     *     comprobantes: \Illuminate\Support\Collection,
     *     cierre: ?TesoreriaCrownCierre
     * }  $fila
     * @return array<string, mixed>|null
     */
    public static function deDiaCajero(object $fila): ?array
    {
        $comps = $fila->comprobantes->count();
        if ($comps === 0) {
            return null;
        }
        $cierre = $fila->cierre;
        if ($cierre !== null && TesoreriaCrownCierreSupport::terminal((string) $cierre->estado)) {
            return null;
        }
        $estado = $cierre !== null ? (string) $cierre->estado : null;
        $diferencia = $cierre !== null ? round((float) $cierre->diferencia, 2) : null;
        [$accion, $sanear] = self::accionSugerida($estado, $diferencia);

        return [
            'asignacion_id' => 0,
            'cajero_crown_id' => (int) $fila->cajero_crown_id,
            'jornada_id' => (int) $fila->jornada_id,
            'cierre_id' => $cierre !== null ? (int) $cierre->id : null,
            'caja' => '',
            'cajero' => (string) ($fila->cajero->nombre ?? ''),
            'turno' => '',
            'tarea' => (string) ($fila->tipo_tarea ?? $fila->cajero->tipo_tarea ?? ''),
            'comprobantes' => $comps,
            'estado' => $estado,
            'estado_txt' => $estado === null
                ? 'Sin cierre'
                : TesoreriaCrownCierreSupport::estado($estado),
            'diferencia' => $diferencia,
            'accion' => $accion,
            'sanear_auditoria' => $sanear,
            'url_cierre' => route('ver_tesoreria_crown_cierre', [
                'jornada' => $fila->jornada_id,
                'cajero' => $fila->cajero_crown_id,
            ]),
        ];
    }

    /**
     * @return array{
     *     asignacion_id: int,
     *     cierre_id: int|null,
     *     caja: string,
     *     cajero: string,
     *     turno: string,
     *     tarea: string,
     *     comprobantes: int,
     *     estado: string|null,
     *     estado_txt: string,
     *     diferencia: float|null,
     *     accion: string,
     *     sanear_auditoria: bool,
     *     url_cierre: string|null
     * }|null
     */
    public static function deAsignacion(TesoreriaCrownAsignacion $fila): ?array
    {
        $comps = (int) ($fila->comprobantes_count ?? $fila->comprobantes()->count());
        if ($comps === 0) {
            return null;
        }

        $cierre = $fila->cierre;
        if ($cierre !== null && TesoreriaCrownCierreSupport::terminal((string) $cierre->estado)) {
            return null;
        }

        $estado = $cierre !== null ? (string) $cierre->estado : null;
        $diferencia = $cierre !== null ? round((float) $cierre->diferencia, 2) : null;
        [$accion, $sanear] = self::accionSugerida($estado, $diferencia);

        return [
            'asignacion_id' => (int) $fila->id,
            'cierre_id' => $cierre !== null ? (int) $cierre->id : null,
            'caja' => (string) ($fila->deposito->codigo ?? ''),
            'cajero' => (string) ($fila->cajero->nombre ?? ''),
            'turno' => (string) $fila->turno,
            'tarea' => (string) $fila->tipo_tarea,
            'comprobantes' => $comps,
            'estado' => $estado,
            'estado_txt' => $estado === null
                ? 'Sin cierre'
                : TesoreriaCrownCierreSupport::estado($estado),
            'diferencia' => $diferencia,
            'accion' => $accion,
            'sanear_auditoria' => $sanear,
            'url_cierre' => route('ver_tesoreria_crown_cierre', [
                'jornada' => $fila->jornada_id,
                'cajero' => $fila->cajero_crown_id,
            ]),
        ];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private static function accionSugerida(?string $estado, ?float $diferencia): array
    {
        return match ($estado) {
            null => ['Abrí el cierre de la caja y dejalo conforme (o pasalo a auditoría).', false],
            TesoreriaCrownCierreSupport::AUDITORIA => [
                'Cerrar auditoría (firma de otra persona). Diferencia: '
                    .number_format((float) $diferencia, 2, ',', '.').'.',
                true,
            ],
            TesoreriaCrownCierreSupport::NO_CONFORME => ['Resolvé el no conforme (revisión / ajuste / anulación).', false],
            TesoreriaCrownCierreSupport::REVISION => ['Marcá el caso como revisada o mandalo a auditoría.', false],
            TesoreriaCrownCierreSupport::REABIERTA => ['La caja quedó reabierta: volvé a cerrarla.', false],
            default => ['Completá el circuito hasta un estado terminal (conforme, revisada o cierre de auditoría).', false],
        };
    }

    /**
     * @param  list<array{asignacion_id: int, cierre_id: int|null, sanear_auditoria: bool, caja?: string, diferencia?: float|null}>  $bloqueos
     * @return list<array{cierre_id: int, asignacion_id: int, caja: string, diferencia: float|null}>
     */
    public static function planCerrarAuditoria(array $bloqueos): array
    {
        $plan = [];
        foreach ($bloqueos as $b) {
            if (! ($b['sanear_auditoria'] ?? false) || empty($b['cierre_id'])) {
                continue;
            }
            $plan[] = [
                'cierre_id' => (int) $b['cierre_id'],
                'asignacion_id' => (int) $b['asignacion_id'],
                'caja' => (string) ($b['caja'] ?? ''),
                'diferencia' => isset($b['diferencia']) ? (float) $b['diferencia'] : null,
            ];
        }

        return $plan;
    }

    /**
     * Null = puede firmar. String = motivo de rechazo.
     */
    public static function motivoNoPuedeFirmarAuditoria(TesoreriaCrownCierre $cierre, Usuario $usuario): ?string
    {
        if ($cierre->estado !== TesoreriaCrownCierreSupport::AUDITORIA) {
            return 'Solo se cierra un caso que está en auditoría.';
        }
        $quienAudito = (int) $cierre->eventos()
            ->where('estado', TesoreriaCrownCierreSupport::AUDITORIA)
            ->latest('id')
            ->value('usuario_id');
        $involucrados = array_filter([$quienAudito, (int) $cierre->tesoreria_usuario_id]);
        if (in_array((int) $usuario->id, $involucrados, true)) {
            return 'El cierre de auditoría lo firma otra persona: no quien cerró la caja ni quien la pasó a auditoría.';
        }

        return null;
    }

    public static function cerrarAuditoria(TesoreriaCrownCierre $cierre, Usuario $usuario, string $detalle = ''): void
    {
        $motivo = self::motivoNoPuedeFirmarAuditoria($cierre, $usuario);
        if ($motivo !== null) {
            throw new \RuntimeException($motivo);
        }
        $detalle = trim($detalle);
        $cierre->estado = TesoreriaCrownCierreSupport::CIERRE_AUDITORIA;
        $cierre->auditor_usuario_id = $usuario->id;
        $cierre->auditor_at = now();
        if ($detalle !== '') {
            $obs = trim((string) ($cierre->observacion ?? ''));
            $cierre->observacion = $obs === '' ? $detalle : ($obs.' | '.$detalle);
        }
        $cierre->save();
        TesoreriaCrownCierreEvento::query()->create([
            'cierre_id' => $cierre->id,
            'estado' => TesoreriaCrownCierreSupport::CIERRE_AUDITORIA,
            'usuario_id' => $usuario->id,
            'detalle' => $detalle !== '' ? $detalle : null,
        ]);
    }

    /**
     * Cierra todas las auditorías de la jornada que el firmante pueda firmar.
     *
     * @return array{cerradas: list<string>, omitidas: list<string>}
     */
    public static function cerrarAuditoriasDeJornada(
        TesoreriaCrownJornada $jornada,
        Usuario $usuario,
        string $detalle = ''
    ): array {
        $bloqueos = self::deJornada($jornada);
        $plan = self::planCerrarAuditoria($bloqueos);
        $cerradas = [];
        $omitidas = [];

        DB::transaction(function () use ($plan, $usuario, $detalle, &$cerradas, &$omitidas) {
            foreach ($plan as $fila) {
                $cierre = TesoreriaCrownCierre::query()->lockForUpdate()->find($fila['cierre_id']);
                if ($cierre === null || $cierre->estado !== TesoreriaCrownCierreSupport::AUDITORIA) {
                    $omitidas[] = 'Caja '.$fila['caja'].': ya no está en auditoría.';
                    continue;
                }
                $motivo = self::motivoNoPuedeFirmarAuditoria($cierre, $usuario);
                if ($motivo !== null) {
                    $omitidas[] = 'Caja '.$fila['caja'].': '.$motivo;
                    continue;
                }
                self::cerrarAuditoria($cierre, $usuario, $detalle);
                $cerradas[] = 'Caja '.$fila['caja'];
            }
        });

        return ['cerradas' => $cerradas, 'omitidas' => $omitidas];
    }

    /**
     * Consola / mantenimiento: fuerza A→F sin control de firmante (usuario_id opcional).
     *
     * @param  list<array{cierre_id: int, asignacion_id: int}>  $plan
     */
    public static function aplicarCerrarAuditoria(array $plan, string $detalle, ?int $usuarioId = null): int
    {
        $grabados = 0;
        DB::transaction(function () use ($plan, $detalle, $usuarioId, &$grabados) {
            foreach ($plan as $fila) {
                $cierre = TesoreriaCrownCierre::query()->lockForUpdate()->find($fila['cierre_id']);
                if ($cierre === null || $cierre->estado !== TesoreriaCrownCierreSupport::AUDITORIA) {
                    continue;
                }
                $cierre->estado = TesoreriaCrownCierreSupport::CIERRE_AUDITORIA;
                $cierre->auditor_usuario_id = $usuarioId;
                $cierre->auditor_at = now();
                $obs = trim((string) ($cierre->observacion ?? ''));
                $nota = trim($detalle);
                if ($nota !== '') {
                    $cierre->observacion = $obs === '' ? $nota : ($obs.' | '.$nota);
                }
                $cierre->save();
                TesoreriaCrownCierreEvento::query()->create([
                    'cierre_id' => $cierre->id,
                    'estado' => TesoreriaCrownCierreSupport::CIERRE_AUDITORIA,
                    'usuario_id' => $usuarioId,
                    'detalle' => $nota !== '' ? $nota : null,
                ]);
                $grabados++;
            }
        });

        return $grabados;
    }

    public static function resumenTexto(array $bloqueos): string
    {
        if ($bloqueos === []) {
            return 'No hay cajas que bloqueen el cierre de la jornada.';
        }
        $lineas = ['Quedan cajas sin un cierre terminado (conforme, revisada o cierre de auditoría):'];
        foreach ($bloqueos as $b) {
            $dif = $b['diferencia'] === null
                ? ''
                : ' | dif. '.number_format((float) $b['diferencia'], 2, ',', '.');
            $lineas[] = sprintf(
                '· Caja %s (%s) — %s%s. %s',
                $b['caja'],
                $b['cajero'],
                $b['estado_txt'],
                $dif,
                $b['accion']
            );
        }

        return implode("\n", $lineas);
    }
}
