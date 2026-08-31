<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashReporteSuscripcion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FlashReporteAggSuscripcionSupport
{
    /**
     * @return Collection<int, FlashReporteSuscripcion>
     */
    public function listar(): Collection
    {
        return FlashReporteSuscripcion::query()->orderBy('nombre')->get();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, ?int $usuarioId): FlashReporteSuscripcion
    {
        $atributos = $this->normalizar($datos);
        $atributos['usuario_id'] = $usuarioId;

        return FlashReporteSuscripcion::query()->create($atributos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(int $id, array $datos): FlashReporteSuscripcion
    {
        $suscripcion = FlashReporteSuscripcion::query()->whereKey($id)->firstOrFail();
        $suscripcion->update($this->normalizar($datos));

        return $suscripcion->refresh();
    }

    public function eliminar(int $id): void
    {
        FlashReporteSuscripcion::query()->whereKey($id)->delete();
    }

    /**
     * @return Collection<int, FlashReporteSuscripcion>
     */
    public function vencidas(Carbon $ahora, ?int $suscripcionId = null, bool $forzar = false): Collection
    {
        $query = FlashReporteSuscripcion::query();
        if ($suscripcionId !== null) {
            $query->whereKey($suscripcionId);
        } else {
            $query->where('activo', true);
        }

        return $query->get()->filter(
            fn (FlashReporteSuscripcion $s) => $forzar || $this->correspondeEnviar($s, $ahora)
        )->values();
    }

    public function correspondeEnviar(FlashReporteSuscripcion $suscripcion, Carbon $ahora): bool
    {
        if (! $suscripcion->activo) {
            return false;
        }

        $programada = $this->proximaVencida($suscripcion, $ahora);
        if ($programada === null) {
            return false;
        }

        if ($this->debeReintentarTrasFalloSmtp($suscripcion, $ahora, $programada)) {
            return true;
        }

        return $suscripcion->ultima_ejecucion === null
            || $suscripcion->ultima_ejecucion->lt($programada);
    }

    private function debeReintentarTrasFalloSmtp(
        FlashReporteSuscripcion $suscripcion,
        Carbon $ahora,
        Carbon $programada,
    ): bool {
        if (! FlashReporteAggSmtpReintentoSupport::habilitado()) {
            return false;
        }
        if ($suscripcion->ultimo_estado !== FlashReporteSuscripcion::ESTADO_ERROR) {
            return false;
        }
        if (! FlashReporteAggSmtpReintentoSupport::esErrorTransporte((string) $suscripcion->ultimo_mensaje)) {
            return false;
        }

        $ultima = $suscripcion->ultima_ejecucion;
        if ($ultima === null) {
            return true;
        }
        if ($ultima->lt($programada)) {
            return false;
        }

        return $ultima->lte(
            $ahora->copy()->subMinutes(FlashReporteAggSmtpReintentoSupport::esperaMinutos())
        );
    }

    /**
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public function periodoEfectivo(FlashReporteSuscripcion $suscripcion, ?Carbon $ahora = null): array
    {
        $ahora = $ahora ?? Carbon::now();

        if ($suscripcion->periodo_relativo === FlashReporteSuscripcion::PERIODO_FIJO
            && is_string($suscripcion->mes_fijo)
            && preg_match('/^\d{4}-\d{2}$/', $suscripcion->mes_fijo)
        ) {
            $base = Carbon::createFromFormat('Y-m-d', $suscripcion->mes_fijo.'-01') ?: $ahora->copy();
            $desde = $base->copy()->startOfMonth();
            $hasta = $base->copy()->endOfMonth()->startOfDay();

            return ['desde' => $desde, 'hasta' => $hasta];
        }

        if ($suscripcion->periodo_relativo === FlashReporteSuscripcion::PERIODO_MES_ANTERIOR) {
            $base = $ahora->copy()->subMonthNoOverflow();
            $desde = $base->copy()->startOfMonth();
            $hasta = $base->copy()->endOfMonth()->startOfDay();

            return ['desde' => $desde, 'hasta' => $hasta];
        }

        // Mes en curso: hasta = fecha de producción (ayer), mes anclado a esa fecha.
        return FlashReporteAggFechaProduccionSupport::periodoMesEnCurso($ahora);
    }

    /**
     * @return list<string>
     */
    public function destinatariosResueltos(FlashReporteSuscripcion $suscripcion): array
    {
        $mails = [];
        foreach (preg_split('/[;,\s]+/', (string) ($suscripcion->destinatarios ?? '')) ?: [] as $mail) {
            $mail = trim($mail);
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $mails[strtolower($mail)] = true;
            }
        }

        return array_keys($mails);
    }

    public function registrarResultado(
        FlashReporteSuscripcion $suscripcion,
        string $estado,
        string $mensaje,
        ?Carbon $cuando = null,
    ): void {
        $suscripcion->update([
            'ultima_ejecucion' => $cuando ?? Carbon::now(),
            'ultimo_estado' => $estado,
            'ultimo_mensaje' => mb_substr($mensaje, 0, 1000),
        ]);
    }

    private function proximaVencida(FlashReporteSuscripcion $suscripcion, Carbon $ahora): ?Carbon
    {
        [$h, $m] = array_pad(explode(':', (string) $suscripcion->hora), 2, '0');

        if ($suscripcion->periodicidad === FlashReporteSuscripcion::PERIODICIDAD_MENSUAL) {
            $dia = max(1, min(28, (int) $suscripcion->dia_mes));
            if ($ahora->day < $dia) {
                return null;
            }
            $programada = $ahora->copy()->startOfMonth()->addDays($dia - 1)->setTime((int) $h, (int) $m, 0);

            return $ahora->gte($programada) ? $programada : null;
        }

        if ($suscripcion->periodicidad === FlashReporteSuscripcion::PERIODICIDAD_SEMANAL
            && (int) $ahora->isoWeekday() !== (int) $suscripcion->dia_semana) {
            return null;
        }

        $programada = $ahora->copy()->setTime((int) $h, (int) $m, 0);

        return $ahora->gte($programada) ? $programada : null;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Poné un nombre para el envío.']);
        }

        $periodicidad = (string) ($datos['periodicidad'] ?? FlashReporteSuscripcion::PERIODICIDAD_DIARIA);
        if (! array_key_exists($periodicidad, FlashReporteSuscripcion::periodicidades())) {
            $periodicidad = FlashReporteSuscripcion::PERIODICIDAD_DIARIA;
        }

        $periodoRelativo = (string) ($datos['periodo_relativo'] ?? FlashReporteSuscripcion::PERIODO_MES_ACTUAL);
        if (! array_key_exists($periodoRelativo, FlashReporteSuscripcion::periodosRelativos())) {
            $periodoRelativo = FlashReporteSuscripcion::PERIODO_MES_ACTUAL;
        }

        $hora = trim((string) ($datos['hora'] ?? '16:00'));
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            $hora = '16:00';
        }
        [$hh, $mm] = explode(':', $hora);
        $hora = sprintf('%02d:%02d', min(23, (int) $hh), min(59, (int) $mm));

        $diaMes = max(1, min(28, (int) ($datos['dia_mes'] ?? 5)));
        $diaSemana = max(1, min(7, (int) ($datos['dia_semana'] ?? 1)));

        $destinatarios = trim((string) ($datos['destinatarios'] ?? ''));
        if ($destinatarios === '') {
            throw ValidationException::withMessages([
                'destinatarios' => 'Indicá al menos un mail destino.',
            ]);
        }

        $mesFijo = trim((string) ($datos['mes_fijo'] ?? ''));
        if ($mesFijo !== '' && ! preg_match('/^\d{4}-\d{2}$/', $mesFijo)) {
            $mesFijo = '';
        }

        $activo = $datos['activo'] ?? true;
        if (is_string($activo) || is_int($activo)) {
            $activo = filter_var($activo, FILTER_VALIDATE_BOOLEAN);
        }

        return [
            'nombre' => mb_substr($nombre, 0, 160),
            'activo' => (bool) $activo,
            'periodicidad' => $periodicidad,
            'dia_mes' => $diaMes,
            'dia_semana' => $diaSemana,
            'hora' => $hora,
            'periodo_relativo' => $periodoRelativo,
            'mes_fijo' => $mesFijo !== '' ? $mesFijo : null,
            'destinatarios' => $destinatarios,
            'mensaje' => trim((string) ($datos['mensaje'] ?? '')) ?: null,
        ];
    }
}
