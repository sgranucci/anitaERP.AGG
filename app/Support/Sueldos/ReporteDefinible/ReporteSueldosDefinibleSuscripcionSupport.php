<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\ReporteSueldosDefinibleSuscripcion;
use App\Support\Seguridad\UsuarioOperativoSupport;
use App\Support\Sueldos\EmpleadoEstados;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class ReporteSueldosDefinibleSuscripcionSupport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function guardar(int $reporteId, array $data): ReporteSueldosDefinibleSuscripcion
    {
        $payload = [
            'reporte_sueldos_definible_id' => $reporteId,
            'usuario_id' => Auth::id(),
            'nombre' => trim((string) ($data['nombre'] ?? '')) ?: trim((string) ($data['email'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'destinatarios' => trim((string) ($data['destinatarios'] ?? '')),
            'usuario_ids' => array_values(array_filter(array_map('intval', (array) ($data['usuario_ids'] ?? [])))),
            'formato' => strtoupper((string) ($data['formato'] ?? 'PDF')),
            'activo' => (bool) ($data['activo'] ?? true),
            'periodicidad' => (string) ($data['periodicidad'] ?? ReporteSueldosDefinibleSuscripcion::PERIODICIDAD_MENSUAL),
            'dia_mes' => max(1, min(28, (int) ($data['dia_mes'] ?? 5))),
            'dia_semana' => max(1, min(7, (int) ($data['dia_semana'] ?? 1))),
            'hora' => $this->horaValida((string) ($data['hora'] ?? '07:00')),
            'periodo_relativo' => (string) ($data['periodo_relativo'] ?? ReporteSueldosDefinibleSuscripcion::PERIODO_ULTIMA_LIQUIDACION),
            'publicar' => (bool) ($data['publicar'] ?? true),
            'solo_si_alertas' => (bool) ($data['solo_si_alertas'] ?? false),
            'burst_dimension' => (string) ($data['burst_dimension'] ?? ReporteSueldosDefinibleSuscripcion::BURST_NINGUNA),
            'filtros_default' => $data['filtros_default'] ?? null,
            'mensaje' => trim((string) ($data['mensaje'] ?? '')) ?: null,
        ];
        $suscripcionId = (int) ($data['suscripcion_id'] ?? 0);
        if ($suscripcionId > 0) {
            $existente = ReporteSueldosDefinibleSuscripcion::query()
                ->where('reporte_sueldos_definible_id', $reporteId)
                ->whereKey($suscripcionId)
                ->firstOrFail();
            unset($payload['usuario_id']);
            $existente->update($payload);

            return $existente->fresh();
        }

        return ReporteSueldosDefinibleSuscripcion::query()->create($payload);
    }

    public function eliminar(int $reporteId, int $suscripcionId): void
    {
        ReporteSueldosDefinibleSuscripcion::query()
            ->where('reporte_sueldos_definible_id', $reporteId)
            ->where('id', $suscripcionId)
            ->delete();
    }

    /**
     * @return list<ReporteSueldosDefinibleSuscripcion>
     */
    public function listar(int $reporteId): array
    {
        return ReporteSueldosDefinibleSuscripcion::query()
            ->where('reporte_sueldos_definible_id', $reporteId)
            ->with('destinatariosBurst')
            ->orderBy('email')
            ->get()
            ->all();
    }

    /**
     * @return Collection<int, ReporteSueldosDefinibleSuscripcion>
     */
    public function vencidas(Carbon $ahora, ?int $suscripcionId = null, bool $forzar = false): Collection
    {
        $q = ReporteSueldosDefinibleSuscripcion::query()
            ->with(['reporte.columnas.conceptos', 'destinatariosBurst.usuario']);
        if ($suscripcionId !== null) {
            $q->whereKey($suscripcionId);
        } else {
            $q->where('activo', true)
                ->whereHas('reporte', fn ($reporte) => $reporte->where('activo', true))
                ->where(function ($lease) use ($ahora) {
                    $lease->whereNull('lease_until')
                        ->orWhere('lease_until', '<', $ahora);
                })
                ->where(function ($next) use ($ahora) {
                    $next->whereNull('next_run_at')
                        ->orWhere('next_run_at', '<=', $ahora);
                });
        }

        return $q->get()
            ->filter(fn (ReporteSueldosDefinibleSuscripcion $s) => $forzar || $this->correspondeEnviar($s, $ahora))
            ->values();
    }

    public function calcularProximoRun(ReporteSueldosDefinibleSuscripcion $suscripcion, Carbon $desde): Carbon
    {
        [$h, $m] = array_pad(explode(':', (string) $suscripcion->hora), 2, '0');
        $candidato = $desde->copy()->addDay()->setTime((int) $h, (int) $m, 0);

        if ($suscripcion->periodicidad === ReporteSueldosDefinibleSuscripcion::PERIODICIDAD_MENSUAL) {
            $dia = max(1, min(28, (int) $suscripcion->dia_mes));
            $candidato = $desde->copy()->startOfMonth()->addMonth()->addDays($dia - 1)->setTime((int) $h, (int) $m, 0);
        } elseif ($suscripcion->periodicidad === ReporteSueldosDefinibleSuscripcion::PERIODICIDAD_SEMANAL) {
            $candidato = $desde->copy()->addDay()->setTime((int) $h, (int) $m, 0);
            $target = max(1, min(7, (int) $suscripcion->dia_semana));
            while ($candidato->isoWeekday() !== $target) {
                $candidato->addDay();
            }
        }

        return $candidato;
    }

    public function correspondeEnviar(ReporteSueldosDefinibleSuscripcion $suscripcion, Carbon $ahora): bool
    {
        if (! $suscripcion->activo) {
            return false;
        }
        [$h, $m] = array_pad(explode(':', (string) $suscripcion->hora), 2, '0');
        $programada = $ahora->copy()->setTime((int) $h, (int) $m, 0);

        if ($suscripcion->periodicidad === ReporteSueldosDefinibleSuscripcion::PERIODICIDAD_MENSUAL) {
            $dia = max(1, min(28, (int) $suscripcion->dia_mes));
            if ($ahora->day < $dia) {
                return false;
            }
            $programada = $ahora->copy()->startOfMonth()->addDays($dia - 1)->setTime((int) $h, (int) $m, 0);
        } elseif ($suscripcion->periodicidad === ReporteSueldosDefinibleSuscripcion::PERIODICIDAD_SEMANAL
            && $ahora->isoWeekday() !== (int) $suscripcion->dia_semana) {
            return false;
        }

        return $ahora->gte($programada)
            && ($suscripcion->ultima_ejecucion === null || $suscripcion->ultima_ejecucion->lt($programada));
    }

    /**
     * @return array<string, mixed>
     */
    public function filtrosEfectivos(ReporteSueldosDefinibleSuscripcion $suscripcion): array
    {
        $filtros = (array) ($suscripcion->filtros_default ?? []);
        if ($suscripcion->periodo_relativo === ReporteSueldosDefinibleSuscripcion::PERIODO_FIJO
            && ! empty($filtros['liquidacion_id'])) {
            return $filtros;
        }

        $q = Liquidacion_Sueldos::query()->orderByDesc('numero')->orderByDesc('id');
        if (! empty($filtros['empresa_id'])) {
            $q->where('empresa_id', (int) $filtros['empresa_id']);
        }
        $filtros['liquidacion_id'] = (int) ($q->value('id') ?? 0);
        $filtros['origen'] = ReporteSueldosDefinibleSupport::ORIGEN_LIQUIDACION;

        return $filtros;
    }

    /**
     * @return list<string>
     */
    public function destinatariosResueltos(
        ReporteSueldosDefinibleSuscripcion $suscripcion,
        string $dimensionClave = '*'
    ): array {
        if (
            (string) $suscripcion->burst_dimension === ReporteSueldosDefinibleSuscripcion::BURST_EMPLEADO
            && str_starts_with($dimensionClave, 'emp:')
        ) {
            return $this->destinatariosBurstEmpleado($suscripcion, $dimensionClave);
        }

        $mails = [];
        $this->agregarMails($mails, (string) $suscripcion->email);
        $this->agregarMails($mails, (string) $suscripcion->destinatarios);

        $usuarioIds = array_values(array_filter(array_map('intval', (array) ($suscripcion->usuario_ids ?? []))));
        foreach ($suscripcion->destinatariosBurst as $destino) {
            if (! $destino->activo || ! in_array($destino->dimension_clave, ['*', $dimensionClave], true)) {
                continue;
            }
            $this->agregarMails($mails, (string) ($destino->email ?? ''));
            if ($destino->usuario_id) {
                $usuarioIds[] = (int) $destino->usuario_id;
            }
        }

        if ($usuarioIds !== []) {
            foreach (UsuarioOperativoSupport::query()->whereIn('id', array_unique($usuarioIds))->get(['email']) as $usuario) {
                $this->agregarMails($mails, (string) ($usuario->email ?? ''));
            }
        }

        return array_keys($mails);
    }

    /**
     * Burst empleado: solo email del legajo (+ mapeos por clave), sin reenviar el email principal N veces.
     *
     * @return list<string>
     */
    private function destinatariosBurstEmpleado(
        ReporteSueldosDefinibleSuscripcion $suscripcion,
        string $dimensionClave
    ): array {
        $mails = [];
        $legajo = (int) substr($dimensionClave, 4);
        if ($legajo > 0) {
            $empleado = Empleado_Sueldos::query()
                ->where('legajo', $legajo)
                ->whereIn('estado', [EmpleadoEstados::ACTIVO, EmpleadoEstados::PROVISORIO])
                ->orderByDesc('id')
                ->first(['email', 'legajo', 'estado']);
            $this->agregarMails($mails, (string) ($empleado?->email ?? ''));
        }

        foreach ($suscripcion->destinatariosBurst as $destino) {
            if (! $destino->activo || ! in_array($destino->dimension_clave, ['*', $dimensionClave], true)) {
                continue;
            }
            $this->agregarMails($mails, (string) ($destino->email ?? ''));
        }

        return array_keys($mails);
    }

    public function registrarResultado(
        ReporteSueldosDefinibleSuscripcion $suscripcion,
        string $estado,
        string $mensaje,
        ?Carbon $cuando = null
    ): void {
        $suscripcion->update([
            'ultima_ejecucion' => $cuando ?? Carbon::now(),
            'ultimo_estado' => $estado,
            'ultimo_mensaje' => mb_substr($mensaje, 0, 65535),
        ]);
    }

    private function horaValida(string $hora): string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($hora), $m)) {
            return '07:00';
        }

        return sprintf('%02d:%02d', min(23, (int) $m[1]), min(59, (int) $m[2]));
    }

    /**
     * @param  array<string, bool>  $mails
     */
    private function agregarMails(array &$mails, string $texto): void
    {
        foreach (preg_split('/[;,\s]+/', $texto) ?: [] as $mail) {
            $mail = strtolower(trim($mail));
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $mails[$mail] = true;
            }
        }
    }
}
