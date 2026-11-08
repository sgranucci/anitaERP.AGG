<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCrownSuscripcion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FlashCrownSuscripcionSupport
{
    /**
     * @return Collection<int, FlashCrownSuscripcion>
     */
    public function listar(): Collection
    {
        return FlashCrownSuscripcion::query()->orderBy('nombre')->get();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, ?int $usuarioId): FlashCrownSuscripcion
    {
        $atributos = $this->normalizar($datos);
        $atributos['usuario_id'] = $usuarioId;

        return FlashCrownSuscripcion::query()->create($atributos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(int $id, array $datos): FlashCrownSuscripcion
    {
        $suscripcion = FlashCrownSuscripcion::query()->whereKey($id)->firstOrFail();
        $suscripcion->update($this->normalizar($datos));

        return $suscripcion->refresh();
    }

    public function eliminar(int $id): void
    {
        FlashCrownSuscripcion::query()->whereKey($id)->delete();
    }

    /**
     * @return Collection<int, FlashCrownSuscripcion>
     */
    public function vencidas(Carbon $ahora, ?int $suscripcionId = null, bool $forzar = false): Collection
    {
        $query = FlashCrownSuscripcion::query();
        if ($suscripcionId !== null) {
            $query->whereKey($suscripcionId);
        } else {
            $query->where('activo', true);
        }

        return $query->get()->filter(
            fn (FlashCrownSuscripcion $s) => $forzar || $this->correspondeEnviar($s, $ahora)
        )->values();
    }

    public function correspondeEnviar(FlashCrownSuscripcion $suscripcion, Carbon $ahora): bool
    {
        if (! $suscripcion->activo) {
            return false;
        }
        $programada = $this->proximaVencida($suscripcion, $ahora);
        if ($programada === null) {
            return false;
        }

        return $suscripcion->ultima_ejecucion === null
            || $suscripcion->ultima_ejecucion->lt($programada);
    }

    /**
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public function periodoEfectivo(FlashCrownSuscripcion $suscripcion, ?Carbon $ahora = null): array
    {
        $ahora = $ahora ?? Carbon::now();
        $hasta = $ahora->copy()->subDay()->startOfDay();

        if ($suscripcion->periodo_relativo === FlashCrownSuscripcion::PERIODO_FIJO
            && is_string($suscripcion->mes_fijo)
            && preg_match('/^\d{4}-\d{2}$/', $suscripcion->mes_fijo)
        ) {
            $desde = Carbon::createFromFormat('Y-m', $suscripcion->mes_fijo)->startOfMonth();
            $hastaMes = $desde->copy()->endOfMonth();

            return ['desde' => $desde, 'hasta' => $hastaMes];
        }

        if ($suscripcion->periodo_relativo === FlashCrownSuscripcion::PERIODO_MES_ANTERIOR) {
            $ref = $ahora->copy()->subMonthNoOverflow();

            return [
                'desde' => $ref->copy()->startOfMonth(),
                'hasta' => $ref->copy()->endOfMonth(),
            ];
        }

        return [
            'desde' => $hasta->copy()->startOfMonth(),
            'hasta' => $hasta,
        ];
    }

    /**
     * @return list<string>
     */
    public function destinatariosResueltos(FlashCrownSuscripcion $suscripcion): array
    {
        $raw = preg_split('/[\s,;]+/', (string) $suscripcion->destinatarios) ?: [];
        $mails = [];
        foreach ($raw as $mail) {
            $mail = strtolower(trim($mail));
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $mails[] = $mail;
            }
        }

        return array_values(array_unique($mails));
    }

    public function marcarResultado(FlashCrownSuscripcion $suscripcion, string $estado, string $mensaje): void
    {
        $suscripcion->ultima_ejecucion = now();
        $suscripcion->ultimo_estado = $estado;
        $suscripcion->ultimo_mensaje = mb_substr($mensaje, 0, 500);
        $suscripcion->save();
    }

    private function proximaVencida(FlashCrownSuscripcion $suscripcion, Carbon $ahora): ?Carbon
    {
        $hora = $this->parseHora((string) $suscripcion->hora);
        if ($hora === null) {
            return null;
        }

        if ($suscripcion->periodicidad === FlashCrownSuscripcion::PERIODICIDAD_DIARIA) {
            $slot = $ahora->copy()->setTime($hora[0], $hora[1], 0);
            if ($ahora->lt($slot)) {
                $slot->subDay();
            }

            return $slot;
        }

        if ($suscripcion->periodicidad === FlashCrownSuscripcion::PERIODICIDAD_SEMANAL) {
            $dia = (int) ($suscripcion->dia_semana ?? 0);
            if ($dia < 1 || $dia > 7) {
                return null;
            }
            $slot = $ahora->copy()->startOfWeek(Carbon::MONDAY)->addDays($dia - 1)->setTime($hora[0], $hora[1], 0);
            if ($ahora->lt($slot)) {
                $slot->subWeek();
            }

            return $slot;
        }

        $diaMes = (int) ($suscripcion->dia_mes ?? 0);
        if ($diaMes < 1 || $diaMes > 31) {
            return null;
        }
        $slot = $ahora->copy()->startOfMonth()->day(min($diaMes, $ahora->daysInMonth))->setTime($hora[0], $hora[1], 0);
        if ($ahora->lt($slot)) {
            $slot = $ahora->copy()->subMonthNoOverflow()->startOfMonth()
                ->day(min($diaMes, $ahora->copy()->subMonthNoOverflow()->daysInMonth))
                ->setTime($hora[0], $hora[1], 0);
        }

        return $slot;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function parseHora(string $hora): ?array
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($hora), $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }

        return [$h, $min];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Ingresá un nombre para el envío.']);
        }
        $destinatarios = trim((string) ($datos['destinatarios'] ?? ''));
        if ($destinatarios === '') {
            throw ValidationException::withMessages(['destinatarios' => 'Ingresá al menos un mail.']);
        }

        return [
            'nombre' => $nombre,
            'activo' => (bool) ($datos['activo'] ?? true),
            'periodicidad' => (string) ($datos['periodicidad'] ?? FlashCrownSuscripcion::PERIODICIDAD_DIARIA),
            'dia_mes' => isset($datos['dia_mes']) && $datos['dia_mes'] !== '' ? (int) $datos['dia_mes'] : null,
            'dia_semana' => isset($datos['dia_semana']) && $datos['dia_semana'] !== '' ? (int) $datos['dia_semana'] : null,
            'hora' => (string) ($datos['hora'] ?? '08:00'),
            'periodo_relativo' => (string) ($datos['periodo_relativo'] ?? FlashCrownSuscripcion::PERIODO_MES_ACTUAL),
            'mes_fijo' => ($datos['mes_fijo'] ?? null) ?: null,
            'perfil_vista' => FlashCrownPerfilVistaSupport::normalizar($datos['perfil_vista'] ?? null),
            'solo_validados' => (bool) ($datos['solo_validados'] ?? true),
            'destinatarios' => $destinatarios,
            'mensaje' => ($datos['mensaje'] ?? null) ?: null,
        ];
    }
}
