<?php

namespace App\Support\Configuracion;

use App\Models\Caja\Flash\FlashCrownSuscripcion;
use App\Models\Caja\Flash\FlashReporteSuscripcion;
use App\Models\Contable\ReporteContableSuscripcion;
use App\Models\Sueldos\ReporteSueldosDefinibleSuscripcion;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripciones de envío (Flash / definibles) visibles desde el Programador.
 * La edición fina sigue en cada módulo; acá se listan, enlazan y pausan.
 */
final class ModuloAvisoProgramadoSuscripcionesSupport
{
    public const FAMILIA_FLASH_AGG = 'flash_agg';

    public const FAMILIA_FLASH_CROWN = 'flash_crown';

    public const FAMILIA_CONTABLE = 'contable_definible';

    public const FAMILIA_SUELDOS = 'sueldos_definible';

    /**
     * @return list<array{
     *   familia: string,
     *   familia_label: string,
     *   id: int,
     *   nombre: string,
     *   activo: bool,
     *   horario: string,
     *   destinatarios: string,
     *   ultimo: string,
     *   estado: string|null,
     *   url: string|null,
     *   clave_batch: string
     * }>
     */
    public static function listarParaClave(string $clave, int $limite = 80): array
    {
        return match ($clave) {
            'flash.distribuir_reportes' => self::flashAgg($limite),
            'flash_crown.distribuir_reportes' => self::flashCrown($limite),
            'contable.reportes_definibles' => self::contable($limite),
            'sueldos.reportes_definibles' => self::sueldos($limite),
            default => [],
        };
    }

    /**
     * Resumen para el cockpit (todas las familias aplicables al entorno).
     *
     * @return list<array{clave: string, label: string, activas: int, total: int, url_batch: string|null, filas: list<array<string, mixed>>}>
     */
    public static function resumenCockpit(int $porFamilia = 8): array
    {
        $bloques = [];
        $mapa = [
            'flash.distribuir_reportes' => 'Flash AGG',
            'flash_crown.distribuir_reportes' => 'Flash Crown',
            'contable.reportes_definibles' => 'Definibles Contable',
            'sueldos.reportes_definibles' => 'Definibles Sueldos',
        ];
        foreach ($mapa as $clave => $label) {
            if (! ModuloAvisoProgramadoDespachoSupport::entornoPermite($clave)) {
                continue;
            }
            $filas = self::listarParaClave($clave, $porFamilia);
            $todas = self::listarParaClave($clave, 500);
            $activas = count(array_filter($todas, static fn ($f) => ! empty($f['activo'])));
            $urlBatch = null;
            try {
                $tipo = \App\Models\Configuracion\ModuloAvisoTipo::query()
                    ->where('modulo', explode('.', $clave)[0])
                    ->where('codigo', explode('.', $clave, 2)[1] ?? '')
                    ->value('id');
                if ($tipo) {
                    $urlBatch = route('editar_modulo_aviso', ['id' => $tipo, 'tab' => 'suscripciones']);
                }
            } catch (\Throwable) {
            }
            $bloques[] = [
                'clave' => $clave,
                'label' => $label,
                'activas' => $activas,
                'total' => count($todas),
                'url_batch' => $urlBatch,
                'filas' => $filas,
            ];
        }

        return $bloques;
    }

    public static function toggle(string $familia, int $id): ?array
    {
        $row = self::modelo($familia, $id);
        if (! $row) {
            return null;
        }
        $row->activo = ! (bool) $row->activo;
        $row->save();

        return [
            'id' => (int) $row->id,
            'activo' => (bool) $row->activo,
            'nombre' => (string) ($row->nombre ?? '#'.$row->id),
        ];
    }

    /**
     * Datos para el modal de edición inline.
     *
     * @return array<string, mixed>|null
     */
    public static function detalle(string $familia, int $id): ?array
    {
        $row = self::modelo($familia, $id);
        if (! $row) {
            return null;
        }

        return [
            'familia' => $familia,
            'id' => (int) $row->id,
            'nombre' => (string) ($row->nombre ?? ''),
            'activo' => (bool) $row->activo,
            'periodicidad' => (string) ($row->periodicidad ?? 'diaria'),
            'hora' => (string) ($row->hora ?? '08:00'),
            'dia_mes' => (int) ($row->dia_mes ?? 1),
            'dia_semana' => (int) ($row->dia_semana ?? 1),
            'destinatarios' => (string) ($row->destinatarios ?? $row->email ?? ''),
        ];
    }

    /**
     * Opciones para el modal de alta desde el Programador.
     *
     * @return array{familias: list<array{value: string, label: string}>, reportes_contable: list<array{id: int, nombre: string}>, reportes_sueldos: list<array{id: int, nombre: string}>}
     */
    public static function opcionesAlta(): array
    {
        $familias = [];
        if (ModuloAvisoProgramadoDespachoSupport::entornoPermite('flash.distribuir_reportes')
            && Schema::hasTable('flash_reporte_suscripcion')) {
            $familias[] = ['value' => self::FAMILIA_FLASH_AGG, 'label' => 'Flash AGG'];
        }
        if (ModuloAvisoProgramadoDespachoSupport::entornoPermite('flash_crown.distribuir_reportes')
            && Schema::hasTable('flash_crown_suscripcion')) {
            $familias[] = ['value' => self::FAMILIA_FLASH_CROWN, 'label' => 'Flash Crown'];
        }
        if (ModuloAvisoProgramadoDespachoSupport::entornoPermite('contable.reportes_definibles')
            && Schema::hasTable('reporte_contable_suscripcion')) {
            $familias[] = ['value' => self::FAMILIA_CONTABLE, 'label' => 'Definible Contable'];
        }
        if (ModuloAvisoProgramadoDespachoSupport::entornoPermite('sueldos.reportes_definibles')
            && Schema::hasTable('reporte_sueldos_definible_suscripcion')) {
            $familias[] = ['value' => self::FAMILIA_SUELDOS, 'label' => 'Definible Sueldos'];
        }

        $reportesContable = [];
        if (Schema::hasTable('reporte_contable')) {
            $reportesContable = \DB::table('reporte_contable')
                ->orderBy('nombre')
                ->limit(200)
                ->get(['id', 'nombre'])
                ->map(static fn ($r) => ['id' => (int) $r->id, 'nombre' => (string) $r->nombre])
                ->all();
        }
        $reportesSueldos = [];
        if (Schema::hasTable('reporte_sueldos_definible')) {
            $reportesSueldos = \DB::table('reporte_sueldos_definible')
                ->orderBy('nombre')
                ->limit(200)
                ->get(['id', 'nombre'])
                ->map(static fn ($r) => ['id' => (int) $r->id, 'nombre' => (string) $r->nombre])
                ->all();
        }

        return [
            'familias' => $familias,
            'reportes_contable' => $reportesContable,
            'reportes_sueldos' => $reportesSueldos,
        ];
    }

    /**
     * Alta mínima (nombre + horario + mails). Definibles requieren reporte_id.
     *
     * @param  array<string, mixed>  $input
     * @return array{id: int, nombre: string, activo: bool, familia: string}
     */
    public static function crear(string $familia, array $input, ?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) (auth()->id() ?? 0);
        $dummy = new \stdClass;
        $dummy->nombre = '';
        $dummy->activo = true;
        $dummy->periodicidad = 'diaria';
        $dummy->hora = '08:00';
        $dummy->dia_mes = 1;
        $dummy->dia_semana = 1;
        $dummy->destinatarios = '';
        $campos = self::normalizarCamposComunes($dummy, $input);
        $campos['activo'] = array_key_exists('activo', $input)
            ? filter_var($input['activo'], FILTER_VALIDATE_BOOLEAN)
            : true;

        if ($familia === self::FAMILIA_FLASH_AGG) {
            $row = app(\App\Support\Caja\Flash\FlashReporteAggSuscripcionSupport::class)->crear($campos, $usuarioId ?: null);
        } elseif ($familia === self::FAMILIA_FLASH_CROWN) {
            $row = app(\App\Support\Caja\Flash\FlashCrownSuscripcionSupport::class)->crear($campos, $usuarioId ?: null);
        } elseif ($familia === self::FAMILIA_CONTABLE) {
            $reporteId = (int) ($input['reporte_id'] ?? 0);
            if ($reporteId <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reporte_id' => 'Elegí el reporte contable definible.',
                ]);
            }
            $row = app(\App\Support\Contable\ReporteDefinible\ReporteDefinibleSuscripcionSupport::class)
                ->crear($reporteId, $campos, [], $usuarioId ?: null);
        } elseif ($familia === self::FAMILIA_SUELDOS) {
            $reporteId = (int) ($input['reporte_id'] ?? 0);
            if ($reporteId <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reporte_id' => 'Elegí el reporte de sueldos definible.',
                ]);
            }
            $primerMail = preg_split('/[;,\s]+/', (string) $campos['destinatarios'])[0] ?? '';
            $campos['email'] = filter_var($primerMail, FILTER_VALIDATE_EMAIL) ? strtolower($primerMail) : '';
            if ($campos['email'] === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'destinatarios' => 'Indicá al menos un mail válido.',
                ]);
            }
            $row = app(\App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSuscripcionSupport::class)
                ->guardar($reporteId, $campos);
        } else {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'familia' => 'Familia de suscripción no válida.',
            ]);
        }

        return [
            'id' => (int) $row->id,
            'nombre' => (string) ($row->nombre ?? '#'.$row->id),
            'activo' => (bool) $row->activo,
            'familia' => $familia,
        ];
    }

    /**
     * Actualiza horario + destinatarios (y nombre/activo) sin tocar filtros del informe.
     *
     * @param  array<string, mixed>  $input
     * @return array{id: int, nombre: string, activo: bool}
     */
    public static function actualizar(string $familia, int $id, array $input): array
    {
        $row = self::modelo($familia, $id);
        if (! $row) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id' => 'No se encontró la suscripción.',
            ]);
        }

        if ($familia === self::FAMILIA_FLASH_AGG) {
            $payload = self::payloadFlashDesdeFila($row, $input);
            app(\App\Support\Caja\Flash\FlashReporteAggSuscripcionSupport::class)->actualizar($id, $payload);
            $row->refresh();
        } elseif ($familia === self::FAMILIA_FLASH_CROWN) {
            $payload = self::payloadFlashDesdeFila($row, $input);
            app(\App\Support\Caja\Flash\FlashCrownSuscripcionSupport::class)->actualizar($id, $payload);
            $row->refresh();
        } else {
            $row->fill(self::normalizarCamposComunes($row, $input));
            $row->save();
        }

        return [
            'id' => (int) $row->id,
            'nombre' => (string) ($row->nombre ?? '#'.$row->id),
            'activo' => (bool) $row->activo,
        ];
    }

    private static function modelo(string $familia, int $id): ?\Illuminate\Database\Eloquent\Model
    {
        return match ($familia) {
            self::FAMILIA_FLASH_AGG => Schema::hasTable('flash_reporte_suscripcion')
                ? FlashReporteSuscripcion::query()->find($id)
                : null,
            self::FAMILIA_FLASH_CROWN => Schema::hasTable('flash_crown_suscripcion')
                ? FlashCrownSuscripcion::query()->find($id)
                : null,
            self::FAMILIA_CONTABLE => Schema::hasTable('reporte_contable_suscripcion')
                ? ReporteContableSuscripcion::query()->find($id)
                : null,
            self::FAMILIA_SUELDOS => Schema::hasTable('reporte_sueldos_definible_suscripcion')
                ? ReporteSueldosDefinibleSuscripcion::query()->find($id)
                : null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function payloadFlashDesdeFila(\Illuminate\Database\Eloquent\Model $row, array $input): array
    {
        $base = [
            'nombre' => $row->nombre,
            'activo' => $row->activo,
            'periodicidad' => $row->periodicidad,
            'dia_mes' => $row->dia_mes,
            'dia_semana' => $row->dia_semana,
            'hora' => $row->hora,
            'periodo_relativo' => $row->periodo_relativo ?? null,
            'mes_fijo' => $row->mes_fijo ?? null,
            'perfil_vista' => $row->perfil_vista ?? null,
            'destinatarios' => $row->destinatarios,
            'mensaje' => $row->mensaje ?? null,
        ];
        if (isset($row->solo_validados)) {
            $base['solo_validados'] = $row->solo_validados;
        }

        return array_merge($base, self::normalizarCamposComunes($row, $input));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function normalizarCamposComunes(object $row, array $input): array
    {
        $nombre = trim((string) ($input['nombre'] ?? $row->nombre ?? ''));
        if ($nombre === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'nombre' => 'Poné un nombre.',
            ]);
        }

        $periodicidad = strtolower(trim((string) ($input['periodicidad'] ?? $row->periodicidad ?? 'diaria')));
        if (! in_array($periodicidad, ['diaria', 'semanal', 'mensual'], true)) {
            $periodicidad = 'diaria';
        }

        $hora = trim((string) ($input['hora'] ?? $row->hora ?? '08:00'));
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'hora' => 'Hora inválida (HH:MM).',
            ]);
        }
        [$hh, $mm] = explode(':', $hora);
        $hora = sprintf('%02d:%02d', min(23, (int) $hh), min(59, (int) $mm));

        $destinatarios = trim((string) ($input['destinatarios'] ?? $row->destinatarios ?? ''));
        if ($destinatarios === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'destinatarios' => 'Indicá al menos un mail.',
            ]);
        }

        $out = [
            'nombre' => $nombre,
            'activo' => array_key_exists('activo', $input)
                ? filter_var($input['activo'], FILTER_VALIDATE_BOOLEAN)
                : (bool) ($row->activo ?? true),
            'periodicidad' => $periodicidad,
            'hora' => $hora,
            'dia_mes' => max(1, min(28, (int) ($input['dia_mes'] ?? $row->dia_mes ?? 1))),
            'dia_semana' => max(1, min(7, (int) ($input['dia_semana'] ?? $row->dia_semana ?? 1))),
            'destinatarios' => $destinatarios,
        ];

        // Sueldos a veces usa email suelto además de destinatarios.
        if ($row instanceof ReporteSueldosDefinibleSuscripcion) {
            $primer = preg_split('/[;,\s]+/', $destinatarios)[0] ?? '';
            if (filter_var($primer, FILTER_VALIDATE_EMAIL)) {
                $out['email'] = strtolower($primer);
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function flashAgg(int $limite): array
    {
        if (! Schema::hasTable('flash_reporte_suscripcion')) {
            return [];
        }
        $urlModulo = route('flash_reporte_agg');

        return FlashReporteSuscripcion::query()
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->limit($limite)
            ->get()
            ->map(static fn (FlashReporteSuscripcion $s) => [
                'familia' => self::FAMILIA_FLASH_AGG,
                'familia_label' => 'Flash AGG',
                'id' => (int) $s->id,
                'nombre' => (string) ($s->nombre ?: 'Suscripción #'.$s->id),
                'activo' => (bool) $s->activo,
                'horario' => self::horario($s->periodicidad, $s->hora, $s->dia_mes, $s->dia_semana),
                'destinatarios' => self::acortarDestinatarios((string) ($s->destinatarios ?? '')),
                'ultimo' => optional($s->ultima_ejecucion)->format('d/m/Y H:i') ?? '—',
                'estado' => $s->ultimo_estado,
                'url' => $urlModulo,
                'clave_batch' => 'flash.distribuir_reportes',
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private static function flashCrown(int $limite): array
    {
        if (! Schema::hasTable('flash_crown_suscripcion')) {
            return [];
        }
        $urlModulo = route('flash_reporte_crown');

        return FlashCrownSuscripcion::query()
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->limit($limite)
            ->get()
            ->map(static fn (FlashCrownSuscripcion $s) => [
                'familia' => self::FAMILIA_FLASH_CROWN,
                'familia_label' => 'Flash Crown',
                'id' => (int) $s->id,
                'nombre' => (string) ($s->nombre ?: 'Suscripción #'.$s->id),
                'activo' => (bool) $s->activo,
                'horario' => self::horario($s->periodicidad ?? null, $s->hora ?? null, $s->dia_mes ?? null, $s->dia_semana ?? null),
                'destinatarios' => self::acortarDestinatarios((string) ($s->destinatarios ?? '')),
                'ultimo' => optional($s->ultima_ejecucion)->format('d/m/Y H:i') ?? '—',
                'estado' => $s->ultimo_estado ?? null,
                'url' => $urlModulo,
                'clave_batch' => 'flash_crown.distribuir_reportes',
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private static function contable(int $limite): array
    {
        if (! Schema::hasTable('reporte_contable_suscripcion')) {
            return [];
        }

        return ReporteContableSuscripcion::query()
            ->with(['reporte:id,nombre'])
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->limit($limite)
            ->get()
            ->map(static function (ReporteContableSuscripcion $s) {
                $reporteId = (int) $s->reporte_contable_id;
                $url = $reporteId > 0
                    ? route('editar_reporte_definible', $reporteId).'?origen=modal_consulta&vista=consulta#suscripciones'
                    : route('reporte_definible');

                return [
                    'familia' => self::FAMILIA_CONTABLE,
                    'familia_label' => 'Definible Contable',
                    'id' => (int) $s->id,
                    'nombre' => trim(($s->reporte->nombre ?? 'Reporte').' · '.($s->nombre ?: '#'.$s->id)),
                    'activo' => (bool) $s->activo,
                    'horario' => self::horario($s->periodicidad, $s->hora, $s->dia_mes, $s->dia_semana),
                    'destinatarios' => self::acortarDestinatarios((string) ($s->destinatarios ?? '')),
                    'ultimo' => optional($s->ultima_ejecucion)->format('d/m/Y H:i') ?? '—',
                    'estado' => $s->ultimo_estado,
                    'url' => $url,
                    'clave_batch' => 'contable.reportes_definibles',
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private static function sueldos(int $limite): array
    {
        if (! Schema::hasTable('reporte_sueldos_definible_suscripcion')) {
            return [];
        }

        return ReporteSueldosDefinibleSuscripcion::query()
            ->with(['reporte:id,nombre'])
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->limit($limite)
            ->get()
            ->map(static function (ReporteSueldosDefinibleSuscripcion $s) {
                $reporteId = (int) $s->reporte_sueldos_definible_id;
                $url = $reporteId > 0
                    ? route('editar_reporte_sueldos_definible', $reporteId).'?origen=modal_consulta&vista=consulta#suscripciones'
                    : route('reporte_sueldos_definible');

                return [
                    'familia' => self::FAMILIA_SUELDOS,
                    'familia_label' => 'Definible Sueldos',
                    'id' => (int) $s->id,
                    'nombre' => trim(($s->reporte->nombre ?? 'Reporte').' · '.($s->nombre ?: '#'.$s->id)),
                    'activo' => (bool) $s->activo,
                    'horario' => self::horario($s->periodicidad, $s->hora, $s->dia_mes, $s->dia_semana),
                    'destinatarios' => self::acortarDestinatarios((string) ($s->destinatarios ?? $s->email ?? '')),
                    'ultimo' => optional($s->ultima_ejecucion)->format('d/m/Y H:i') ?? '—',
                    'estado' => $s->ultimo_estado,
                    'url' => $url,
                    'clave_batch' => 'sueldos.reportes_definibles',
                ];
            })
            ->all();
    }

    private static function horario(?string $periodicidad, ?string $hora, mixed $diaMes, mixed $diaSemana): string
    {
        $hora = $hora ?: '—';
        $p = strtolower((string) $periodicidad);
        if ($p === 'diaria') {
            return 'Diaria '.$hora;
        }
        if ($p === 'semanal') {
            $dias = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
            $d = $dias[(int) $diaSemana] ?? ('d'.(int) $diaSemana);

            return 'Semanal '.$d.' '.$hora;
        }
        if ($p === 'mensual') {
            return 'Mensual día '.(int) $diaMes.' '.$hora;
        }

        return trim($p.' '.$hora);
    }

    private static function acortarDestinatarios(string $raw): string
    {
        $parts = preg_split('/[;,\s]+/', $raw) ?: [];
        $mails = [];
        foreach ($parts as $p) {
            $p = strtolower(trim($p));
            if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $mails[] = $p;
            }
        }
        $mails = array_values(array_unique($mails));
        if ($mails === []) {
            return '—';
        }
        if (count($mails) <= 2) {
            return implode(', ', $mails);
        }

        return $mails[0].', '.$mails[1].' +'.(count($mails) - 2);
    }
}
