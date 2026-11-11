<?php

namespace App\Services\Caja;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownAviso;
use App\Models\Caja\TesoreriaCrownCierre;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Caja\TesoreriaCrownAvisoSupport as Aviso;
use App\Support\Caja\TesoreriaCrownCierreCalculo;
use App\Support\Caja\TesoreriaCrownCierreSupport;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownRendicionCampos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Genera los avisos de auditoría de tesorería Crown.
 * Las reglas de carga (rendición sin habilitación, X tipeado sin cierre) miran solo comprobantes
 * cargados en el ERP: los importados de Anita siguen las reglas de Anita (kiosco y QR rinden sin habilitar).
 * Las reglas de estado (no conforme, rendición sin habilitación, jornada abierta…) se cierran solas
 * como «superadas» cuando la condición deja de cumplirse. Las de hecho (reversa, reapertura,
 * diferencia) quedan hasta que alguien las revise o descarte.
 */
class TesoreriaCrownAvisoGenerador
{
    /** Reglas que se cierran solas, por jornada. */
    private const SUPERABLES_JORNADA = [
        Aviso::NO_CONFORME,
        Aviso::SIN_HABILITACION,
        Aviso::GASTRO_X_SIN_CIERRE,
        Aviso::CAJERO_DOS_CAJAS,
        Aviso::VIII_VARIACION,
        Aviso::COTIZACION_OTRA_FECHA,
    ];

    /** Reglas que se cierran solas, en toda la sala. */
    private const SUPERABLES_GLOBALES = [
        Aviso::JORNADA_ABIERTA,
        Aviso::RECALCULO_PENDIENTE,
    ];

    /**
     * Revisa una jornada después de un cierre, reversa, no conforme o reapertura.
     * No corta la operación si falla: queda en el log y la corrida nocturna lo vuelve a evaluar.
     */
    public static function alEvento(?int $jornadaId): void
    {
        if (! $jornadaId || ! Aviso::disponible()) {
            return;
        }
        try {
            $jornada = TesoreriaCrownJornada::query()->find($jornadaId);
            if ($jornada) {
                $generador = app(self::class);
                $generador->aplicar($generador->evaluarJornada($jornada), [self::alcanceJornada($jornada)], true);
            }
        } catch (\Throwable $e) {
            Log::warning('Tesorería Crown: no se pudieron generar los avisos de la jornada', [
                'jornada_id' => $jornadaId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{regla: list<string>, jornada_id: int}
     */
    public static function alcanceJornada(TesoreriaCrownJornada $jornada): array
    {
        return ['regla' => self::SUPERABLES_JORNADA, 'jornada_id' => (int) $jornada->id];
    }

    /**
     * @return array{regla: list<string>, global: true}
     */
    public static function alcanceGlobal(): array
    {
        return ['regla' => self::SUPERABLES_GLOBALES, 'global' => true];
    }

    /**
     * @return array{regla: list<string>, desde: string, hasta: string}
     */
    public static function alcanceAnita(string $desde, string $hasta): array
    {
        return ['regla' => [Aviso::ANITA], 'desde' => $desde, 'hasta' => $hasta];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function evaluarJornada(TesoreriaCrownJornada $jornada): array
    {
        $fecha = $jornada->fecha->format('Y-m-d');
        $base = [
            'sala_id' => (int) $jornada->sala_id,
            'fecha' => $fecha,
            'jornada_id' => (int) $jornada->id,
        ];
        $tolerancia = TesoreriaCrownCierreSupport::tolerancia();
        $asignaciones = TesoreriaCrownAsignacion::query()
            ->where('jornada_id', $jornada->id)
            ->with(['cajero', 'deposito', 'cierres.ajustes', 'comprobantes' => fn ($q) => $q->withCount('cierresFiscales')])
            ->get();

        $out = [];
        foreach ($asignaciones as $asignacion) {
            $caja = $this->caja($asignacion);
            $deCaja = $base + [
                'asignacion_id' => (int) $asignacion->id,
                'cajero_crown_id' => $asignacion->cajero_crown_id ? (int) $asignacion->cajero_crown_id : null,
            ];
            $cajeroUsuarioId = (int) ($asignacion->cajero->usuario_id ?? 0);

            foreach ($asignacion->cierres as $cierre) {
                $this->reglasCierre($out, $deCaja, $caja, $cierre, $tolerancia, $cajeroUsuarioId);
            }
            $this->reglasComprobantes($out, $deCaja, $caja, $asignacion, $cajeroUsuarioId);
            if (! $asignacion->permite_rendir_sin_habilitacion
                && TesoreriaCrownCierreCalculo::formula((string) $asignacion->tipo_tarea) !== 'empleados') {
                $this->reglaSinHabilitacion($out, $deCaja, $caja, $asignacion->comprobantes);
            }
            $this->reglaEdicionPostCierre($out, $deCaja, $caja, $asignacion);
            $this->reglaFallosRepetidos($out, $deCaja, $caja, $asignacion, $fecha);
        }
        $this->reglaCajeroDosCajas($out, $base, $asignaciones);
        $this->reglaViiiVsVariacion($out, $base, $jornada);
        $this->reglaCotizacionOtraFecha($out, $base, $jornada, $asignaciones);

        return $out;
    }

    /**
     * Jornadas abiertas de más y recálculo pendiente (toda la sala).
     *
     * @return list<array<string, mixed>>
     */
    public function evaluarGlobal(): array
    {
        $out = [];
        $limite = now()->subDays(Aviso::jornadaAbiertaDias())->format('Y-m-d');
        $abiertas = TesoreriaCrownJornada::query()
            ->where('estado', TesoreriaCrownJornada::ABIERTA)
            ->whereDate('fecha', '<', $limite)
            ->orderBy('fecha')
            ->get();
        foreach ($abiertas as $jornada) {
            $out[] = $this->aviso(Aviso::JORNADA_ABIERTA, 'jornada_abierta|'.$jornada->id, [
                'sala_id' => (int) $jornada->sala_id,
                'fecha' => $jornada->fecha->format('Y-m-d'),
                'jornada_id' => (int) $jornada->id,
            ], 'Jornada del '.$jornada->fecha->format('d/m/Y').' sigue abierta',
                'Lleva '.$jornada->fecha->diffInDays(now()->startOfDay()).' días abierta. Cerrarla o revisar por qué no se cerró.');
        }

        $pendientes = DB::table('tesoreria_crown_jornada')
            ->where('recalculo_pendiente', true)
            ->groupBy('sala_id')
            ->selectRaw('sala_id, MIN(fecha) as desde, COUNT(*) as dias')
            ->get();
        foreach ($pendientes as $fila) {
            $desde = substr((string) $fila->desde, 0, 10);
            $out[] = $this->aviso(Aviso::RECALCULO_PENDIENTE, 'recalculo_pendiente|'.$fila->sala_id, [
                'sala_id' => (int) $fila->sala_id,
                'fecha' => $desde,
            ], 'Recálculo hacia adelante pendiente desde el '.date('d/m/Y', strtotime($desde)),
                $fila->dias.' jornada(s) con poder público y Anexo VIII grabados que pueden estar viejos. Se recalculan al abrir un anexo.');
        }

        return $out;
    }

    /**
     * Diferencias del control contra Anita (tesoreria-crown:control-anita).
     *
     * @return list<array<string, mixed>>
     */
    public function evaluarAnita(string $desde, string $hasta): array
    {
        $jornadas = TesoreriaCrownJornada::query()
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get()
            ->keyBy(fn (TesoreriaCrownJornada $j) => $j->fecha->format('Y-m-d'));
        $out = [];
        foreach (app(TesoreriaCrownControlAnitaService::class)->controlar($desde, $hasta) as $fila) {
            if ($fila['ok'] || $fila['area'] === 'Recálculo') {
                continue;
            }
            $jornada = $jornadas[$fila['fecha']] ?? null;
            $formato = fn (?float $v) => $v === null ? '—' : number_format($v, 2, ',', '.');
            $out[] = $this->aviso(Aviso::ANITA, 'anita|'.$fila['fecha'].'|'.$fila['area'].'|'.$fila['concepto'], [
                'sala_id' => $jornada ? (int) $jornada->sala_id : null,
                'fecha' => $fila['fecha'],
                'jornada_id' => $jornada ? (int) $jornada->id : null,
            ], $fila['area'].': '.$fila['concepto'].($fila['nota'] !== '' ? ' — '.$fila['nota'] : ''),
                'ERP '.$formato($fila['erp']).' · Anita '.$formato($fila['anita']),
                $fila['dif']);
        }

        return $out;
    }

    /**
     * Graba los candidatos y cierra como superados los abiertos de las reglas de estado que ya no aparecen.
     * Con $escribir = false solo cuenta (dry-run).
     *
     * @param  list<array<string, mixed>>  $candidatos
     * @param  list<array<string, mixed>>  $alcances
     * @return array{nuevos: int, actualizados: int, reabiertos: int, superados: int, nuevos_detalle: list<array<string, mixed>>, superados_detalle: list<string>}
     */
    public function aplicar(array $candidatos, array $alcances, bool $escribir): array
    {
        $res = ['nuevos' => 0, 'actualizados' => 0, 'reabiertos' => 0, 'superados' => 0, 'nuevos_detalle' => [], 'superados_detalle' => []];
        $candidatos = array_values(array_column($candidatos, null, 'clave'));
        $claves = array_column($candidatos, 'clave');
        $existentes = $claves === []
            ? collect()
            : TesoreriaCrownAviso::query()->whereIn('clave', $claves)->get()->keyBy('clave');

        foreach ($candidatos as $candidato) {
            /** @var TesoreriaCrownAviso|null $existente */
            $existente = $existentes[$candidato['clave']] ?? null;
            if ($existente === null) {
                $res['nuevos']++;
                $res['nuevos_detalle'][] = $candidato;
                if ($escribir) {
                    TesoreriaCrownAviso::query()->create($candidato + ['estado' => Aviso::ABIERTO]);
                }

                continue;
            }
            if ($existente->estado === Aviso::SUPERADO) {
                $res['reabiertos']++;
                if ($escribir) {
                    $existente->fill($candidato + ['estado' => Aviso::ABIERTO, 'resuelto_usuario_id' => null, 'resuelto_at' => null]);
                    $existente->comentario = null;
                    $existente->save();
                }

                continue;
            }
            if ($existente->estado !== Aviso::ABIERTO) {
                continue;
            }
            $existente->fill(array_intersect_key($candidato, array_flip(['titulo', 'detalle', 'importe', 'severidad'])));
            if ($existente->isDirty()) {
                $res['actualizados']++;
                if ($escribir) {
                    $existente->save();
                }
            }
        }

        foreach ($alcances as $alcance) {
            $query = TesoreriaCrownAviso::query()
                ->where('estado', Aviso::ABIERTO)
                ->whereIn('regla', $alcance['regla'])
                ->when($claves !== [], fn ($q) => $q->whereNotIn('clave', $claves));
            if (isset($alcance['jornada_id'])) {
                $query->where('jornada_id', $alcance['jornada_id']);
            }
            if (isset($alcance['desde'])) {
                $query->whereDate('fecha', '>=', $alcance['desde'])->whereDate('fecha', '<=', $alcance['hasta']);
            }
            foreach ($query->get() as $viejo) {
                $res['superados']++;
                $res['superados_detalle'][] = $viejo->clave;
                if ($escribir) {
                    $viejo->estado = Aviso::SUPERADO;
                    $viejo->comentario = 'Se cerró solo: la condición ya no se cumple.';
                    $viejo->resuelto_at = now();
                    $viejo->save();
                }
            }
        }

        return $res;
    }

    /**
     * @param  list<array<string, mixed>>  $out
     * @param  array<string, mixed>  $deCaja
     */
    private function reglasCierre(array &$out, array $deCaja, string $caja, TesoreriaCrownCierre $cierre, float $tolerancia, int $cajeroUsuarioId): void
    {
        $numero = TesoreriaCrownNumeradorSupport::visible((int) $cierre->numero);
        $deCaja['cierre_id'] = (int) $cierre->id;
        if ($cierre->reabierta_at !== null) {
            $out[] = $this->aviso(Aviso::REAPERTURA, 'reapertura|'.$cierre->id, $deCaja,
                $caja.': cierre '.$numero.' reabierto',
                'Reabierto el '.$cierre->reabierta_at->format('d/m/Y H:i').'. Motivo: '.trim((string) $cierre->reabierta_motivo));
        }
        $diferencia = round((float) $cierre->diferencia, 2);
        if ($cierre->reabierta_at === null && abs($diferencia) > $tolerancia + 0.001) {
            $vigente = TesoreriaCrownCierreSupport::vigente($cierre, $cierre->ajustes);
            $ajustada = round((float) ($vigente['diferencia'] ?? $diferencia), 2);
            $out[] = $this->aviso(Aviso::DIFERENCIA, 'diferencia|'.$cierre->id, $deCaja,
                $caja.': diferencia de '.number_format($diferencia, 2, ',', '.').' en el cierre '.$numero,
                'Tolerancia '.number_format($tolerancia, 2, ',', '.').'. Estado del cierre: '.$cierre->estadoTexto().'.'
                    .(abs($ajustada - $diferencia) > 0.005 ? ' Con ajustes queda en '.number_format($ajustada, 2, ',', '.').'.' : ''),
                $diferencia);
        }
        if ($cajeroUsuarioId > 0 && (int) $cierre->tesoreria_usuario_id === $cajeroUsuarioId) {
            $out[] = $this->aviso(Aviso::MISMA_PERSONA, 'misma_persona|cierre|'.$cierre->id, $deCaja,
                $caja.': el cierre '.$numero.' lo firmó por tesorería el mismo usuario del cajero',
                'Tesorería y cajero tienen que ser dos personas.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $out
     * @param  array<string, mixed>  $deCaja
     */
    private function reglasComprobantes(array &$out, array $deCaja, string $caja, TesoreriaCrownAsignacion $asignacion, int $cajeroUsuarioId): void
    {
        $gastroHotel = TesoreriaCrownRendicionCampos::esCajaGastroHotel($asignacion->tipo_tarea ?? '');
        foreach ($asignacion->comprobantes as $doc) {
            $deDoc = $deCaja + ['comprobante_id' => (int) $doc->id];
            $nombre = TesoreriaCrownComprobanteSupport::tipo($doc->tipo).' '.TesoreriaCrownNumeradorSupport::visible((int) $doc->numero);
            if (in_array($doc->estado, TesoreriaCrownComprobanteSupport::SIN_RESOLVER, true)) {
                $out[] = $this->aviso(Aviso::NO_CONFORME, 'no_conforme|'.$doc->id, $deDoc,
                    $caja.': '.$nombre.' no conforme sin resolver',
                    $this->ultimaLinea($doc->observacion));
            }
            if ($doc->revierte_id !== null && $doc->estado !== TesoreriaCrownComprobanteSupport::ANULADO) {
                $out[] = $this->aviso(Aviso::REVERSA, 'reversa|'.$doc->id, $deDoc,
                    $caja.': '.$nombre.' revierte otro comprobante',
                    trim((string) $doc->observacion));
            }
            if ($doc->estado === TesoreriaCrownComprobanteSupport::CONFORME) {
                $tesoreriaId = (int) ($doc->tipo === TesoreriaCrownComprobanteSupport::HABILITACION ? $doc->entrega_usuario_id : $doc->recibe_usuario_id);
                $mismaFirma = $doc->entrega_usuario_id && (int) $doc->entrega_usuario_id === (int) $doc->recibe_usuario_id;
                if ($mismaFirma || ($cajeroUsuarioId > 0 && $tesoreriaId === $cajeroUsuarioId)) {
                    $out[] = $this->aviso(Aviso::MISMA_PERSONA, 'misma_persona|comprobante|'.$doc->id, $deDoc,
                        $caja.': '.$nombre.' firmado por la misma persona de los dos lados',
                        'El usuario que firmó por tesorería es el del cajero.');
                }
                if ($gastroHotel && $doc->tipo === TesoreriaCrownComprobanteSupport::RENDICION
                    && $doc->creado_usuario_id !== null
                    && (float) $doc->total_x > 0 && (int) $doc->cierres_fiscales_count === 0) {
                    $out[] = $this->aviso(Aviso::GASTRO_X_SIN_CIERRE, 'gastro_x|'.$doc->id, $deDoc,
                        $caja.': '.$nombre.' con X tipeado sin cierre de ventas',
                        'Total X '.number_format((float) $doc->total_x, 2, ',', '.').' cargado a mano: no está vinculado a ningún cierre fiscal.',
                        round((float) $doc->total_x, 2));
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $out
     * @param  array<string, mixed>  $deCaja
     * @param  Collection<int, TesoreriaCrownComprobante>  $docs
     */
    private function reglaSinHabilitacion(array &$out, array $deCaja, string $caja, Collection $docs): void
    {
        $conforme = $docs->where('estado', TesoreriaCrownComprobanteSupport::CONFORME);
        $revertidos = $conforme->whereNotNull('revierte_id')->pluck('revierte_id')->map(fn ($id) => (int) $id)->all();
        $vigentes = $conforme->whereNull('revierte_id')->reject(fn ($doc) => in_array((int) $doc->id, $revertidos, true));
        $hayRendicion = $vigentes->contains(fn ($doc) => $doc->tipo === TesoreriaCrownComprobanteSupport::RENDICION && $doc->creado_usuario_id !== null);
        $hayHabilitacion = $vigentes->contains('tipo', TesoreriaCrownComprobanteSupport::HABILITACION);
        if ($hayRendicion && ! $hayHabilitacion) {
            $out[] = $this->aviso(Aviso::SIN_HABILITACION, 'sin_habilitacion|'.$deCaja['asignacion_id'], $deCaja,
                $caja.': rendición conforme sin habilitación conforme',
                'La caja rindió sin una habilitación vigente (o la habilitación fue revertida).');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $out
     * @param  array<string, mixed>  $deCaja
     */
    private function reglaEdicionPostCierre(array &$out, array $deCaja, string $caja, TesoreriaCrownAsignacion $asignacion): void
    {
        $cierre = $asignacion->cierres->firstWhere('reabierta_at', null);
        $ids = $asignacion->comprobantes->pluck('id')->all();
        if ($cierre === null || $ids === []) {
            return;
        }
        $cambios = DB::table('audits')
            ->where('auditable_type', TesoreriaCrownComprobante::class)
            ->whereIn('auditable_id', $ids)
            ->whereNotNull('user_id')
            ->where('created_at', '>', $cierre->created_at->copy()->addSeconds(5))
            ->orderBy('id')
            ->get(['auditable_id', 'event', 'user_id', 'created_at']);
        foreach ($cambios->groupBy('auditable_id') as $docId => $filas) {
            $doc = $asignacion->comprobantes->firstWhere('id', (int) $docId);
            $nombre = $doc ? TesoreriaCrownComprobanteSupport::tipo($doc->tipo).' '.TesoreriaCrownNumeradorSupport::visible((int) $doc->numero) : 'Comprobante '.$docId;
            $ultimo = $filas->last();
            $out[] = $this->aviso(Aviso::EDICION_POST_CIERRE, 'post_cierre|'.$docId.'|'.$cierre->id, $deCaja + ['comprobante_id' => (int) $docId],
                $caja.': '.$nombre.' modificado después del cierre',
                $filas->count().' cambio(s) después del cierre del '.$cierre->created_at->format('d/m/Y H:i')
                    .'. Último: '.$ultimo->event.' el '.date('d/m/Y H:i', strtotime((string) $ultimo->created_at))
                    .' (usuario '.$ultimo->user_id.'). Ver la auditoría del comprobante.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $out
     * @param  array<string, mixed>  $deCaja
     */
    private function reglaFallosRepetidos(array &$out, array $deCaja, string $caja, TesoreriaCrownAsignacion $asignacion, string $fecha): void
    {
        $cierre = $asignacion->cierres->firstWhere('reabierta_at', null);
        $cajero = $asignacion->cajero;
        $minimo = Aviso::falloMinimo();
        if ($cierre === null || $cajero === null || abs((float) $cierre->diferencia) < $minimo) {
            return;
        }
        $legajo = (int) $cajero->legajo;
        $cajeroIds = $legajo > 0
            ? DB::table('cajero_crown')->where('legajo', $legajo)->pluck('id')->all()
            : [(int) $cajero->id];
        $desde = date('Y-m-d', strtotime($fecha.' -'.(Aviso::fallosDias() - 1).' days'));
        $fallos = DB::table('tesoreria_crown_cierre as c')
            ->join('tesoreria_crown_asignacion as a', 'a.id', '=', 'c.asignacion_id')
            ->join('tesoreria_crown_jornada as j', 'j.id', '=', 'c.jornada_id')
            ->whereIn('a.cajero_crown_id', $cajeroIds)
            ->whereNull('c.reabierta_at')
            ->whereRaw('ABS(c.diferencia) >= ?', [$minimo])
            ->whereDate('j.fecha', '>=', $desde)
            ->whereDate('j.fecha', '<=', $fecha)
            ->orderBy('j.fecha')
            ->get(['j.fecha', 'c.diferencia']);
        if ($fallos->count() < Aviso::fallosCantidad()) {
            return;
        }
        $suma = round((float) $fallos->sum('diferencia'), 2);
        $out[] = $this->aviso(Aviso::FALLOS_REPETIDOS, 'fallos|'.($legajo > 0 ? 'L'.$legajo : 'C'.$cajero->id).'|'.substr($fecha, 0, 7), $deCaja,
            trim((string) $cajero->nombre).': '.$fallos->count().' cierres con diferencia en '.Aviso::fallosDias().' días',
            'Fechas: '.$fallos->map(fn ($f) => date('d/m', strtotime((string) $f->fecha)).' ('.number_format((float) $f->diferencia, 2, ',', '.').')')->implode(', ')
                .'. Suma: '.number_format($suma, 2, ',', '.').'.',
            $suma);
    }

    /**
     * @param  list<array<string, mixed>>  $out
     * @param  array<string, mixed>  $base
     * @param  Collection<int, TesoreriaCrownAsignacion>  $asignaciones
     */
    private function reglaCajeroDosCajas(array &$out, array $base, Collection $asignaciones): void
    {
        $porPersona = $asignaciones
            ->filter(fn (TesoreriaCrownAsignacion $a) => $a->cajero !== null)
            ->groupBy(fn (TesoreriaCrownAsignacion $a) => (int) $a->cajero->legajo > 0 ? 'L'.(int) $a->cajero->legajo : 'C'.$a->cajero->id);
        foreach ($porPersona as $persona => $filas) {
            if ($filas->pluck('deposito_id')->unique()->count() < 2) {
                continue;
            }
            $out[] = $this->aviso(Aviso::CAJERO_DOS_CAJAS, 'dos_cajas|'.$base['jornada_id'].'|'.$persona, $base + [
                'cajero_crown_id' => (int) $filas->first()->cajero_crown_id,
            ], trim((string) $filas->first()->cajero->nombre).' asignado a '.$filas->count().' cajas',
                'Cajas: '.$filas->map(fn ($a) => $this->caja($a).($a->turno ? ' (turno '.$a->turno.')' : ''))->implode(', ').'.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $out
     * @param  array<string, mixed>  $base
     */
    private function reglaViiiVsVariacion(array &$out, array $base, TesoreriaCrownJornada $jornada): void
    {
        if ($jornada->caja_final_viii === null || $jornada->caja_final_dia === null) {
            return;
        }
        $viii = (float) $jornada->caja_final_viii;
        $final = (float) $jornada->caja_final_dia;
        $dif = round($viii - $final, 2);
        if (abs($dif) <= 1.0) {
            return;
        }
        $out[] = $this->aviso(
            Aviso::VIII_VARIACION,
            'viii_variacion|'.$jornada->id,
            $base,
            'Anexo VIII y variación del Anexo I no cierran',
            'VIII '.number_format($viii, 2, ',', '.')
                .' · caja final por variación '.number_format($final, 2, ',', '.')
                .' · diferencia '.number_format($dif, 2, ',', '.')
                .'. Revisá bóveda, slots, drops, propina o FX del día.',
            $dif
        );
    }

    /**
     * @param  list<array<string, mixed>>  $out
     * @param  array<string, mixed>  $base
     * @param  Collection<int, TesoreriaCrownAsignacion>  $asignaciones
     */
    private function reglaCotizacionOtraFecha(array &$out, array $base, TesoreriaCrownJornada $jornada, Collection $asignaciones): void
    {
        $cambios = TesoreriaCrownCierreCalculo::cambiosDelDia((int) $jornada->sala_id, $base['fecha']);
        $viejas = array_values(array_filter(
            $cambios,
            fn (array $c) => (float) ($c['tasa'] ?? 0) > 0 && empty($c['exacta'])
        ));
        if ($viejas === []) {
            return;
        }
        $comprobanteIds = $asignaciones->flatMap(fn (TesoreriaCrownAsignacion $a) => $a->comprobantes->pluck('id'))->all();
        $usaMe = $asignaciones->flatMap(fn (TesoreriaCrownAsignacion $a) => $a->comprobantes)
            ->contains(fn (TesoreriaCrownComprobante $d) => (float) ($d->cotiz_dolar ?? 0) > 0 || (float) ($d->cotiz_euro ?? 0) > 0);
        if (! $usaMe && $comprobanteIds !== []) {
            $usaMe = DB::table('tesoreria_crown_comprobante_linea as l')
                ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
                ->join('tesoreria_crown_tipo_valor as t', 't.id', '=', 'v.tipo_valor_id')
                ->whereIn('l.comprobante_id', $comprobanteIds)
                ->whereIn('t.codigo', ['USD', 'EUR'])
                ->where('l.cantidad', '<>', 0)
                ->exists();
        }
        if (! $usaMe) {
            return;
        }
        $detalle = collect($viejas)->map(fn (array $c) => $c['codigo'].' '
            .number_format((float) $c['tasa'], 4, ',', '.')
            .' del '.($c['fecha'] ? date('d/m/Y', strtotime((string) $c['fecha'])) : '—')
        )->implode(' · ');
        $out[] = $this->aviso(
            Aviso::COTIZACION_OTRA_FECHA,
            'cotizacion_otra_fecha|'.$jornada->id,
            $base,
            'Cotización de moneda extranjera de otra fecha',
            'La jornada usó '.$detalle.'. No hay venta cargada para el día: la conversión tomó la última vigente.'
        );
    }

    private function caja(TesoreriaCrownAsignacion $asignacion): string
    {
        $deposito = $asignacion->deposito;
        $caja = $deposito ? trim($deposito->codigo.' '.$deposito->nombre) : 'Caja '.$asignacion->deposito_id;
        $cajero = trim((string) ($asignacion->cajero->nombre ?? ''));

        return $cajero !== '' ? $caja.' — '.$cajero : $caja;
    }

    private function ultimaLinea(?string $texto): string
    {
        $lineas = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $texto) ?: [])));

        return $lineas === [] ? '' : end($lineas);
    }

    /**
     * @param  array<string, mixed>  $donde
     * @return array<string, mixed>
     */
    private function aviso(string $regla, string $clave, array $donde, string $titulo, string $detalle = '', ?float $importe = null): array
    {
        return [
            'sala_id' => $donde['sala_id'] ?? null,
            'fecha' => $donde['fecha'] ?? null,
            'jornada_id' => $donde['jornada_id'] ?? null,
            'asignacion_id' => $donde['asignacion_id'] ?? null,
            'comprobante_id' => $donde['comprobante_id'] ?? null,
            'cierre_id' => $donde['cierre_id'] ?? null,
            'cajero_crown_id' => $donde['cajero_crown_id'] ?? null,
            'regla' => $regla,
            'severidad' => Aviso::REGLAS[$regla]['severidad'],
            'clave' => mb_substr($clave, 0, 191),
            'titulo' => mb_substr($titulo, 0, 255),
            'detalle' => $detalle !== '' ? $detalle : null,
            'importe' => $importe,
        ];
    }
}
