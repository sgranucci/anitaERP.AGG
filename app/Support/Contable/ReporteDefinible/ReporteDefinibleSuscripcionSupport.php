<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContableSuscripcion;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Suscripciones de distribución automática: cuándo sale el informe, con qué filtros y a quién.
 */
class ReporteDefinibleSuscripcionSupport
{
    /**
     * @return Collection<int, ReporteContableSuscripcion>
     */
    public function listar(int $reporteId): Collection
    {
        return ReporteContableSuscripcion::query()
            ->where('reporte_contable_id', $reporteId)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payloadUi(int $reporteId): array
    {
        $out = [];
        foreach ($this->listar($reporteId) as $s) {
            $out[] = [
                'id' => (int) $s->id,
                'nombre' => (string) $s->nombre,
                'activo' => (bool) $s->activo,
                'periodicidad' => (string) $s->periodicidad,
                'periodicidad_texto' => $s->periodicidadTexto(),
                'dia_mes' => (int) $s->dia_mes,
                'dia_semana' => (int) $s->dia_semana,
                'hora' => (string) $s->hora,
                'periodo_relativo' => (string) $s->periodo_relativo,
                'formato' => (string) $s->formato,
                'publicar' => (bool) $s->publicar,
                'solo_si_alertas' => (bool) $s->solo_si_alertas,
                'destinatarios' => (string) ($s->destinatarios ?? ''),
                'mensaje' => (string) ($s->mensaje ?? ''),
                'filtros_texto' => $this->filtrosTexto($s),
                'ultima_ejecucion' => $s->ultima_ejecucion?->format('d/m/Y H:i'),
                'ultimo_estado' => (string) ($s->ultimo_estado ?? ''),
                'ultimo_mensaje' => (string) ($s->ultimo_mensaje ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $filtros
     */
    public function crear(int $reporteId, array $datos, array $filtros, ?int $usuarioId): ReporteContableSuscripcion
    {
        $atributos = $this->normalizar($datos, $filtros);
        $atributos['reporte_contable_id'] = $reporteId;
        $atributos['usuario_id'] = $usuarioId;

        return ReporteContableSuscripcion::query()->create($atributos);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>|null  $filtros
     */
    public function actualizar(int $reporteId, int $suscripcionId, array $datos, ?array $filtros = null): ReporteContableSuscripcion
    {
        $suscripcion = ReporteContableSuscripcion::query()
            ->where('reporte_contable_id', $reporteId)
            ->whereKey($suscripcionId)
            ->firstOrFail();

        $atributos = $this->normalizar($datos, $filtros ?? ($suscripcion->filtros ?? []));
        if ($filtros === null) {
            unset($atributos['filtros']);
        }

        $suscripcion->update($atributos);

        return $suscripcion->refresh();
    }

    public function eliminar(int $reporteId, int $suscripcionId): void
    {
        ReporteContableSuscripcion::query()
            ->where('reporte_contable_id', $reporteId)
            ->whereKey($suscripcionId)
            ->delete();
    }

    /**
     * Suscripciones activas que corresponden enviar en el momento dado.
     *
     * @return Collection<int, ReporteContableSuscripcion>
     */
    public function vencidas(Carbon $ahora, ?int $suscripcionId = null, bool $forzar = false): Collection
    {
        $query = ReporteContableSuscripcion::query()->with('reporte');

        if ($suscripcionId !== null) {
            $query->whereKey($suscripcionId);
        } else {
            $query->where('activo', true);
        }

        return $query->get()->filter(
            fn (ReporteContableSuscripcion $s) => $forzar || $this->correspondeEnviar($s, $ahora)
        )->values();
    }

    public function correspondeEnviar(ReporteContableSuscripcion $suscripcion, Carbon $ahora): bool
    {
        if (! $suscripcion->activo) {
            return false;
        }

        $programada = $this->proximaVencida($suscripcion, $ahora);
        if ($programada === null) {
            return false;
        }

        // Un solo envío por corrida programada, aunque el comando corra cada hora.
        return $suscripcion->ultima_ejecucion === null
            || $suscripcion->ultima_ejecucion->lt($programada);
    }

    /**
     * Momento programado que ya venció y todavía no se envió, o null si no corresponde.
     *
     * Mensual recupera dentro del mismo mes: si el servidor estuvo caído el día 5, el envío
     * sale el 6. Semanal y diaria no arrastran entre días para no mandar informes viejos.
     */
    private function proximaVencida(ReporteContableSuscripcion $suscripcion, Carbon $ahora): ?Carbon
    {
        [$h, $m] = array_pad(explode(':', (string) $suscripcion->hora), 2, '0');

        if ($suscripcion->periodicidad === ReporteContableSuscripcion::PERIODICIDAD_MENSUAL) {
            $dia = max(1, min(28, (int) $suscripcion->dia_mes));
            if ($ahora->day < $dia) {
                return null;
            }
            $programada = $ahora->copy()->startOfMonth()->addDays($dia - 1)->setTime((int) $h, (int) $m, 0);

            return $ahora->gte($programada) ? $programada : null;
        }

        if ($suscripcion->periodicidad === ReporteContableSuscripcion::PERIODICIDAD_SEMANAL
            && (int) $ahora->isoWeekday() !== (int) $suscripcion->dia_semana) {
            return null;
        }

        $programada = $ahora->copy()->setTime((int) $h, (int) $m, 0);

        return $ahora->gte($programada) ? $programada : null;
    }

    /**
     * Filtros efectivos del envío: los guardados, con el período corrido según la configuración.
     *
     * @return array<string, mixed>
     */
    public function filtrosEfectivos(ReporteContableSuscripcion $suscripcion, ?Carbon $ahora = null): array
    {
        $filtros = $suscripcion->filtros ?? [];
        $ahora = $ahora ?? Carbon::now();

        if ($suscripcion->periodo_relativo === ReporteContableSuscripcion::PERIODO_FIJO) {
            return $filtros;
        }

        $base = $suscripcion->periodo_relativo === ReporteContableSuscripcion::PERIODO_MES_ACTUAL
            ? $ahora->copy()
            : $ahora->copy()->subMonthNoOverflow();

        $periodo = (int) $base->format('Ym');
        $filtros['modo_periodo'] = 'periodos';
        $filtros['periodo_desde'] = $periodo;
        $filtros['periodo_hasta'] = $periodo;
        $filtros['mes_desde'] = (int) $base->format('m');
        $filtros['anio_desde'] = (int) $base->format('Y');
        $filtros['mes_hasta'] = (int) $base->format('m');
        $filtros['anio_hasta'] = (int) $base->format('Y');

        return $filtros;
    }

    /**
     * Mails de destino: los escritos a mano más los de los usuarios elegidos (solo operativos).
     *
     * @return list<string>
     */
    public function destinatariosResueltos(ReporteContableSuscripcion $suscripcion): array
    {
        $mails = [];

        foreach (preg_split('/[;,\s]+/', (string) ($suscripcion->destinatarios ?? '')) ?: [] as $mail) {
            $mail = trim($mail);
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $mails[strtolower($mail)] = true;
            }
        }

        $usuarioIds = array_filter(array_map('intval', (array) ($suscripcion->usuario_ids ?? [])));
        if ($usuarioIds !== []) {
            $usuarios = UsuarioOperativoSupport::query()
                ->whereIn('id', $usuarioIds)
                ->get(['id', 'email']);
            foreach ($usuarios as $usuario) {
                $mail = trim((string) ($usuario->email ?? ''));
                if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                    $mails[strtolower($mail)] = true;
                }
            }
        }

        return array_keys($mails);
    }

    public function registrarResultado(
        ReporteContableSuscripcion $suscripcion,
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

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function normalizar(array $datos, array $filtros): array
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Poné un nombre para el envío.']);
        }

        $periodicidad = (string) ($datos['periodicidad'] ?? ReporteContableSuscripcion::PERIODICIDAD_MENSUAL);
        if (! array_key_exists($periodicidad, ReporteContableSuscripcion::periodicidades())) {
            $periodicidad = ReporteContableSuscripcion::PERIODICIDAD_MENSUAL;
        }

        $periodoRelativo = (string) ($datos['periodo_relativo'] ?? ReporteContableSuscripcion::PERIODO_MES_ANTERIOR);
        if (! array_key_exists($periodoRelativo, ReporteContableSuscripcion::periodosRelativos())) {
            $periodoRelativo = ReporteContableSuscripcion::PERIODO_MES_ANTERIOR;
        }

        $formato = (string) ($datos['formato'] ?? ReporteContableSuscripcion::FORMATO_PDF);
        if (! array_key_exists($formato, ReporteContableSuscripcion::formatos())) {
            $formato = ReporteContableSuscripcion::FORMATO_PDF;
        }

        $hora = trim((string) ($datos['hora'] ?? '07:00'));
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            $hora = '07:00';
        }
        [$hh, $mm] = explode(':', $hora);
        $hora = sprintf('%02d:%02d', min(23, (int) $hh), min(59, (int) $mm));

        $diaMes = (int) ($datos['dia_mes'] ?? 5);
        $diaMes = max(1, min(28, $diaMes));
        $diaSemana = (int) ($datos['dia_semana'] ?? 1);
        $diaSemana = max(1, min(7, $diaSemana));

        $destinatarios = trim((string) ($datos['destinatarios'] ?? ''));
        $usuarioIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($datos['usuario_ids'] ?? [])
        ))));

        if ($destinatarios === '' && $usuarioIds === []) {
            throw ValidationException::withMessages([
                'destinatarios' => 'Indicá al menos un mail o un usuario destinatario.',
            ]);
        }

        return [
            'nombre' => mb_substr($nombre, 0, 160),
            'activo' => (bool) ($datos['activo'] ?? true),
            'periodicidad' => $periodicidad,
            'dia_mes' => $diaMes,
            'dia_semana' => $diaSemana,
            'hora' => $hora,
            'filtros' => $filtros,
            'periodo_relativo' => $periodoRelativo,
            'formato' => $formato,
            'publicar' => (bool) ($datos['publicar'] ?? false),
            'solo_si_alertas' => (bool) ($datos['solo_si_alertas'] ?? false),
            'destinatarios' => $destinatarios !== '' ? $destinatarios : null,
            'usuario_ids' => $usuarioIds !== [] ? $usuarioIds : null,
            'mensaje' => trim((string) ($datos['mensaje'] ?? '')) ?: null,
        ];
    }

    private function filtrosTexto(ReporteContableSuscripcion $suscripcion): string
    {
        $filtros = $suscripcion->filtros ?? [];
        $partes = [];

        $empresas = array_filter(array_map('intval', (array) ($filtros['empresa_ids'] ?? [])));
        if ($empresas !== []) {
            $partes[] = 'empresas '.implode(', ', $empresas);
        }
        if (! empty($filtros['layout_id'])) {
            $partes[] = 'layout #'.(int) $filtros['layout_id'];
        }
        if (! empty($filtros['base_saldo'])) {
            $partes[] = 'base '.(string) $filtros['base_saldo'];
        }
        $partes[] = ReporteContableSuscripcion::periodosRelativos()[$suscripcion->periodo_relativo]
            ?? $suscripcion->periodo_relativo;

        return implode(' · ', $partes);
    }
}
