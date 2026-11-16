<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\ModuloAvisoDestinatario;
use App\Models\Configuracion\ModuloAvisoTipo;
use Illuminate\Support\Facades\Schema;

/**
 * Analizador de impacto antes de «Ejecutar ahora» / dry-run de schedule.
 */
final class ModuloAvisoImpactoPreviewSupport
{
    /**
     * @return array{
     *   clave: string,
     *   nombre: string,
     *   comando: string|null,
     *   accion: string,
     *   canales: list<string>,
     *   destinatarios: list<array{email:?string,usuario:?string,empresa_id:?int,centrocosto_id:?int}>,
     *   destinatarios_count: int,
     *   condiciones: array<string, mixed>,
     *   proxima: string|null,
     *   simular_14d: list<string>,
     *   dependencia: string|null,
     *   dependencia_ok: bool|null,
     *   advertencias: list<string>
     * }
     */
    public static function paraTipo(ModuloAvisoTipo $tipo): array
    {
        $mapa = config('modulo_aviso.programados', []);
        $clave = $tipo->claveCompleta();
        $comando = is_array($mapa) ? ($mapa[$clave] ?? null) : null;
        $acciones = config('modulo_aviso.acciones_tipadas', []);
        $accion = is_array($acciones) && isset($acciones[$clave])
            ? (string) $acciones[$clave]
            : 'comando_artisan';

        $tipo->loadMissing(['destinatarios.usuarios']);
        $destinos = [];
        foreach ($tipo->destinatarios as $d) {
            /** @var ModuloAvisoDestinatario $d */
            if (! $d->activo) {
                continue;
            }
            $destinos[] = [
                'email' => $d->email,
                'usuario' => $d->usuarios->nombre ?? $d->usuarios->usuario ?? null,
                'empresa_id' => $d->empresa_id ? (int) $d->empresa_id : null,
                'centrocosto_id' => $d->centrocosto_id ? (int) $d->centrocosto_id : null,
            ];
        }

        $canales = ['mail'];
        if ($tipo->notificar_in_app) {
            $canales[] = 'campanita';
        }
        if ($tipo->notificar_whatsapp) {
            $canales[] = 'whatsapp';
        }

        $ahora = now()->timezone('America/Argentina/Buenos_Aires');
        $feriados = ModuloAvisoProgramacionSupport::feriadosYmdEntre(
            $ahora->toDateString(),
            $ahora->copy()->addDays(20)->toDateString()
        );
        $proxima = ModuloAvisoProgramacionSupport::proximaCorrida($tipo, $ahora, 45, $feriados);
        $simular = ModuloAvisoProgramacionSupport::simularProximosDias($tipo, $ahora, 14, $feriados);

        $dep = trim((string) ($tipo->programacion_depende_de ?? ''));
        $depOk = null;
        $advertencias = [];
        if ($dep !== '') {
            $modoDep = trim((string) ($tipo->programacion_depende_modo ?? 'ok_hoy')) ?: 'ok_hoy';
            $depOk = self::dependenciaSatisfactoria($dep, $ahora->toDateString(), $modoDep);
            if ($depOk === false) {
                $advertencias[] = 'La dependencia «'.$dep.'» (modo '.$modoDep.') no está satisfecha; el despacho la salteará.';
            }
        }
        if ((bool) ($tipo->programacion_pausada ?? false)) {
            $advertencias[] = 'La tarea está pausada: el cron no la dispara (Ejecutar ahora sí, si tenés permiso).';
        }
        if ($destinos === [] && ! in_array($accion, ['listado_excel', 'consulta_inteligente'], true)) {
            $advertencias[] = 'No hay destinatarios activos en el catálogo (el comando puede usar destinatarios dinámicos).';
        }
        if (! $tipo->activo) {
            $advertencias[] = 'El tipo está inactivo: el mail del handler puede no enviarse.';
        }

        $hermanos = self::resumenHermanosListadoCi($clave);
        if ($hermanos !== null) {
            if ($hermanos['activos'] === 0) {
                $advertencias[] = 'No hay envíos activos en listado_envio_programado para este batch.';
            }
            foreach ($hermanos['muestra'] as $fila) {
                $destinos[] = [
                    'email' => $fila['email'],
                    'usuario' => $fila['recurso'].' · '.$fila['frecuencia'],
                    'empresa_id' => null,
                    'centrocosto_id' => null,
                ];
            }
        }

        return [
            'clave' => $clave,
            'nombre' => $tipo->nombre,
            'comando' => is_string($comando) ? $comando : null,
            'accion' => $accion,
            'canales' => $canales,
            'destinatarios' => $destinos,
            'destinatarios_count' => count($destinos),
            'hermanos_activos' => $hermanos['activos'] ?? null,
            'condiciones' => is_array($tipo->condiciones) ? $tipo->condiciones : [],
            'proxima' => $proxima?->format('d/m/Y H:i'),
            'simular_14d' => $simular,
            'dependencia' => $dep !== '' ? $dep : null,
            'dependencia_ok' => $depOk,
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @return array{activos: int, muestra: list<array{email: string, recurso: string, frecuencia: string}>}|null
     */
    private static function resumenHermanosListadoCi(string $clave): ?array
    {
        if (! Schema::hasTable('listado_envio_programado')) {
            return null;
        }
        if ($clave === 'listado.envios_workbench') {
            $q = \App\Models\Listado\ListadoEnvioProgramado::query()
                ->where('activo', true)
                ->whereIn('recurso', [
                    \App\Support\Compras\PagoproveedorListadoColumnas::RECURSO,
                    \App\Support\Caja\IngresoEgresoListadoColumnas::RECURSO,
                    \App\Support\Ticket\AdministracionTicketListadoColumnas::RECURSO,
                ]);
        } elseif ($clave === 'consulta_inteligente.envios_programados') {
            $q = \App\Models\Listado\ListadoEnvioProgramado::query()
                ->where('activo', true)
                ->where('recurso', 'like', 'consulta.inteligente.%');
        } else {
            return null;
        }

        $activos = (int) (clone $q)->count();
        $muestra = $q->orderBy('recurso')->orderBy('email')->limit(20)
            ->get(['email', 'recurso', 'frecuencia'])
            ->map(static fn ($r) => [
                'email' => (string) $r->email,
                'recurso' => (string) $r->recurso,
                'frecuencia' => (string) $r->frecuencia,
            ])
            ->all();

        return ['activos' => $activos, 'muestra' => $muestra];
    }

    public static function dependenciaSatisfactoriaHoy(string $claveDepende, string $hoyYmd): bool
    {
        return self::dependenciaSatisfactoria($claveDepende, $hoyYmd, 'ok_hoy');
    }

    /**
     * @param  'ok_hoy'|'ok_reciente'|'sin_fallo_hoy'|string  $modo
     */
    public static function dependenciaSatisfactoria(
        string $claveDepende,
        string $hoyYmd,
        string $modo = 'ok_hoy'
    ): bool {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return true;
        }
        [$modulo, $codigo] = array_pad(explode('.', $claveDepende, 2), 2, null);
        if (! $modulo || ! $codigo) {
            return false;
        }
        $dep = ModuloAvisoTipo::porModuloYCodigo($modulo, $codigo);
        if (! $dep) {
            return false;
        }

        $modo = in_array($modo, ['ok_hoy', 'ok_reciente', 'sin_fallo_hoy'], true) ? $modo : 'ok_hoy';
        $tieneLog = Schema::hasTable('modulo_aviso_programado_log');

        if ($modo === 'ok_reciente') {
            if ($dep->programacion_ultima_en) {
                return true;
            }
            if (! $tieneLog) {
                return false;
            }

            return $dep->programadoLogs()->where('exit_code', 0)->exists();
        }

        if ($modo === 'sin_fallo_hoy') {
            if (! $tieneLog) {
                return true;
            }
            $falloHoy = $dep->programadoLogs()
                ->whereDate('inicio_en', $hoyYmd)
                ->where(function ($q) {
                    $q->whereNull('exit_code')->orWhere('exit_code', '!=', 0);
                })
                ->exists();
            if (! $falloHoy) {
                return true;
            }

            return $dep->programadoLogs()
                ->whereDate('inicio_en', $hoyYmd)
                ->where('exit_code', 0)
                ->exists();
        }

        // ok_hoy
        if ($dep->programacion_ultima_en && $dep->programacion_ultima_en->toDateString() === $hoyYmd) {
            return true;
        }
        if (! $tieneLog) {
            return false;
        }

        return $dep->programadoLogs()
            ->whereDate('inicio_en', $hoyYmd)
            ->where('exit_code', 0)
            ->exists();
    }
}
