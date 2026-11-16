<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\AvisoRegla;
use App\Models\Configuracion\AvisoReglaConfig;
use App\Models\Configuracion\ModuloAvisoProgramadoLog;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Listado\ListadoEnvioProgramado;
use App\Services\Configuracion\AvisoReglaMotorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Cockpit del Programador: KPIs, próximas corridas, fallos y schedulers hermanos.
 */
final class ModuloAvisoProgramadorCockpitSupport
{
    private const TZ = 'America/Argentina/Buenos_Aires';

    /**
     * @return array{
     *   stats: array<string, int>,
     *   proximas: list<array<string, mixed>>,
     *   fallos: list<array<string, mixed>>,
     *   hermanos: list<array<string, mixed>>,
     *   dry_run_hoy: list<array<string, mixed>>,
     *   calendario: array<string, mixed>
     * }
     */
    public static function armar(
        ?Carbon $ahora = null,
        string $calModo = 'mes',
        ?string $calRef = null
    ): array {
        $ahora = ($ahora ?? now())->copy()->timezone(self::TZ);
        $mapa = config('modulo_aviso.programados', []);
        $claves = is_array($mapa) ? array_keys($mapa) : [];

        $tipos = self::tiposProgramables($claves);
        $feriados = ModuloAvisoProgramacionSupport::feriadosYmdEntre(
            $ahora->toDateString(),
            $ahora->copy()->addDays(45)->toDateString()
        );
        $refCal = $calRef
            ? Carbon::parse($calRef)->timezone(self::TZ)->startOfDay()
            : $ahora->copy()->startOfDay();
        $calendario = ModuloAvisoProgramadorCalendarioSupport::armar($calModo, $refCal, $tipos);

        $proximas = [];
        $habilitados = 0;
        $pausados = 0;
        foreach ($tipos as $tipo) {
            if ($tipo->activo && $tipo->programacion_habilitada) {
                $habilitados++;
            }
            if ((bool) ($tipo->programacion_pausada ?? false)) {
                $pausados++;
            }
            // Solo proyectar lo que el despachador puede tomar (activo).
            if (! $tipo->activo) {
                continue;
            }
            $prox = ModuloAvisoProgramacionSupport::proximaCorrida($tipo, $ahora, 45, $feriados);
            if ($prox !== null) {
                $proximas[] = [
                    'origen' => 'aviso',
                    'clave' => $tipo->claveCompleta(),
                    'nombre' => $tipo->nombre,
                    'tipo_id' => $tipo->id,
                    'cuando' => $prox->format('Y-m-d H:i'),
                    'cuando_label' => $prox->format('d/m/Y H:i'),
                    'etiqueta' => ModuloAvisoProgramacionSupport::etiquetaDesdeTipo($tipo),
                    'pausada' => (bool) ($tipo->programacion_pausada ?? false),
                    'url' => url('configuracion/modulo-aviso/'.$tipo->id.'/editar'),
                ];
            }
        }
        usort($proximas, static fn ($a, $b) => strcmp($a['cuando'], $b['cuando']));
        $proximas = array_slice($proximas, 0, 20);

        $fallos = self::fallosRecientes(15);
        $hermanos = self::hermanosListadosCi();
        $dryRunHoy = self::dryRunAhora($tipos, $ahora, $feriados, is_array($mapa) ? $mapa : []);
        $piloto = self::estadoPilotoReglas();

        return [
            'stats' => [
                'programables' => $tipos->count(),
                'habilitados' => $habilitados,
                'pausados' => $pausados,
                'fallos_7d' => self::contarFallosDesde($ahora->copy()->subDays(7)->startOfDay()),
                'hermanos' => count(array_filter($hermanos, static fn ($h) => ! empty($h['activo']))),
                'corresponden_ahora' => count($dryRunHoy),
            ],
            'proximas' => $proximas,
            'fallos' => $fallos,
            'hermanos' => $hermanos,
            'dry_run_hoy' => $dryRunHoy,
            'calendario' => $calendario,
            'piloto_reglas' => $piloto,
            'suscripciones_envio' => ModuloAvisoProgramadoSuscripcionesSupport::resumenCockpit(6),
            'jobs_plataforma' => ModuloAvisoJobsPlataformaSupport::resumenCockpit(),
            'webhooks_allowlist' => ModuloAvisoWebhookAllowlistSupport::claves(),
            'guia' => [
                'Si una tarea no corre' => 'Revisá Programación activa, Pausada, ventana horaria, feriados y Dependencia (modo) en Rango.',
                'Si falló el job' => 'Historial global (filtro Error) → Reintentar con impacto. Llega campanita/mail (1/día).',
                'Listados / CI' => 'Batch 08:05/08:10. Destinos en workbench/CI; pausá abajo.',
                'Flash / definibles' => 'Alta, pausa y edición de horario/mails acá; el poll 15′ dispara el envío.',
                'Reglas WHEN/IF/THEN' => 'Motor ON + piloto + plantillas. Webhooks solo con clave allowlist en .env.',
                'Jobs plataforma' => 'Solo lectura: purges/syncs del Kernel. No se despachan desde el Programador.',
            ],
        ];
    }

    /**
     * @return array{motor_on: bool, piloto_total: int, piloto_activas: int, url: string}
     */
    public static function estadoPilotoReglas(): array
    {
        $pilotoCodigos = [
            'piloto_cheque_aging_campanita_admin',
            'piloto_recepcion_confirmada_aviso',
            'piloto_prestamo_solicitud_campanita',
        ];
        $total = 0;
        $activas = 0;
        if (Schema::hasTable('aviso_regla')) {
            $total = (int) AvisoRegla::query()->whereIn('codigo', $pilotoCodigos)->count();
            $activas = (int) AvisoRegla::query()->whereIn('codigo', $pilotoCodigos)->where('activo', true)->count();
        }
        $motorOn = false;
        try {
            $motorOn = app(AvisoReglaMotorService::class)->motorHabilitado();
        } catch (\Throwable) {
            if (Schema::hasTable('aviso_regla_config')) {
                $motorOn = (bool) AvisoReglaConfig::actual()->motor_habilitado;
            }
        }

        return [
            'motor_on' => $motorOn,
            'piloto_total' => $total,
            'piloto_activas' => $activas,
            'url' => route('consultar_aviso_regla'),
        ];
    }

    /**
     * @param  list<string>  $claves
     * @return Collection<int, ModuloAvisoTipo>
     */
    public static function tiposProgramables(array $claves): Collection
    {
        if ($claves === [] || ! Schema::hasTable('modulo_aviso_tipo')) {
            return collect();
        }

        return ModuloAvisoTipo::query()
            ->withCount(['destinatarios as destinatarios_activos_count' => function ($q) {
                $q->where('activo', true);
            }])
            ->where(function ($q) use ($claves) {
                foreach ($claves as $clave) {
                    [$modulo, $codigo] = array_pad(explode('.', (string) $clave, 2), 2, null);
                    if ($modulo && $codigo) {
                        $q->orWhere(function ($qq) use ($modulo, $codigo) {
                            $qq->where('modulo', $modulo)->where('codigo', $codigo);
                        });
                    }
                }
            })
            ->orderBy('modulo')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fallosRecientes(int $limit = 20): array
    {
        if (! Schema::hasTable('modulo_aviso_programado_log')) {
            return [];
        }

        return ModuloAvisoProgramadoLog::query()
            ->where(function ($q) {
                $q->where('exit_code', '!=', 0)->orWhereNotNull('error');
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn (ModuloAvisoProgramadoLog $log) => [
                'id' => $log->id,
                'clave' => $log->clave,
                'origen' => $log->origen,
                'inicio' => optional($log->inicio_en)->format('d/m/Y H:i'),
                'exit_code' => $log->exit_code,
                'detalle' => mb_substr((string) ($log->error ?: $log->salida ?: '—'), 0, 180),
                'tipo_id' => $log->modulo_aviso_tipo_id,
                'url' => $log->modulo_aviso_tipo_id
                    ? url('configuracion/modulo-aviso/'.$log->modulo_aviso_tipo_id.'/editar')
                    : null,
            ])
            ->all();
    }

    /**
     * Historial global paginable (query base).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Configuracion\ModuloAvisoProgramadoLog>
     */
    public static function queryHistorial(?string $clave = null, ?string $estado = null)
    {
        $q = ModuloAvisoProgramadoLog::query()->orderByDesc('id');
        if ($clave !== null && $clave !== '') {
            $q->where('clave', 'like', '%'.$clave.'%');
        }
        if ($estado === 'ok') {
            $q->where('exit_code', 0);
        } elseif ($estado === 'error') {
            $q->where(function ($qq) {
                $qq->where('exit_code', '!=', 0)->orWhereNotNull('error');
            });
        }

        return $q;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function hermanosListadosCi(): array
    {
        if (! Schema::hasTable('listado_envio_programado')) {
            return [];
        }

        return ListadoEnvioProgramado::query()
            ->with(['usuario:id,nombre,usuario'])
            ->orderByDesc('activo')
            ->orderBy('recurso')
            ->orderBy('email')
            ->limit(100)
            ->get()
            ->map(static function (ListadoEnvioProgramado $row) {
                $recurso = (string) $row->recurso;
                $familia = str_starts_with($recurso, 'consulta.inteligente.')
                    ? 'ci'
                    : 'listado';

                return [
                    'id' => $row->id,
                    'activo' => (bool) $row->activo,
                    'origen' => $familia,
                    'origen_label' => $familia === 'ci' ? 'Consulta Inteligente' : 'Listado workbench',
                    'recurso' => $recurso,
                    'email' => $row->email,
                    'frecuencia' => $row->frecuencia,
                    'usuario' => $row->usuario->nombre ?? $row->usuario->usuario ?? ('#'.$row->usuario_id),
                    'ultimo' => optional($row->ultimo_envio_at)->format('d/m/Y H:i') ?? '—',
                    'cron' => $familia === 'ci'
                        ? 'Programador 08:10'
                        : 'Programador 08:05',
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, ModuloAvisoTipo>  $tipos
     * @param  array<string, string>  $mapa
     * @param  list<string>  $feriados
     * @return list<array<string, mixed>>
     */
    private static function dryRunAhora(Collection $tipos, Carbon $ahora, array $feriados, array $mapa): array
    {
        $out = [];
        foreach ($tipos as $tipo) {
            if (! $tipo->activo || ! isset($mapa[$tipo->claveCompleta()])) {
                continue;
            }
            if (! ModuloAvisoProgramacionSupport::correspondeTipo($tipo, $ahora, $feriados)) {
                continue;
            }
            $out[] = [
                'clave' => $tipo->claveCompleta(),
                'nombre' => $tipo->nombre,
                'comando' => $mapa[$tipo->claveCompleta()],
                'tipo_id' => $tipo->id,
            ];
        }

        return $out;
    }

    private static function contarFallosDesde(Carbon $desde): int
    {
        if (! Schema::hasTable('modulo_aviso_programado_log')) {
            return 0;
        }

        return (int) ModuloAvisoProgramadoLog::query()
            ->where('inicio_en', '>=', $desde)
            ->where(function ($q) {
                $q->where('exit_code', '!=', 0)->orWhereNotNull('error');
            })
            ->count();
    }
}
