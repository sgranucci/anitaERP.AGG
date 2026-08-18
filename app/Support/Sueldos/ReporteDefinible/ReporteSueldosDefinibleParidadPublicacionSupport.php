<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleAlerta;
use App\Models\Sueldos\ReporteSueldosDefinibleCertificacion;
use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use App\Models\Sueldos\ReporteSueldosDefinibleParidad;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corta publicación de versión o dataset si hay control bloqueante de paridad;
 * gestiona el acta formal de certificación por liquidación/nómina.
 */
final class ReporteSueldosDefinibleParidadPublicacionSupport
{
    /**
     * @param  bool  $exigirEvidencia  true al publicar dataset (exige certificación cuando corresponde)
     */
    public static function assertPublicable(
        ReporteSueldosDefinible $reporte,
        ?int $ejecucionId,
        bool $exigirEvidencia = false
    ): void {
        if ((string) $reporte->origen !== 'anita') {
            return;
        }
        $alerta = self::alertaParidadBloqueante($reporte);
        if ($alerta) {
            $maxDiff = self::diferenciaMaxima($reporte, $ejecucionId);
            if ($maxDiff === null) {
                if ($exigirEvidencia) {
                    throw ValidationException::withMessages([
                        'paridad' => 'No se puede publicar: falta evidencia de paridad con Anita para este listado.',
                    ]);
                }

                return;
            }
            if ($alerta->comparar(abs($maxDiff))) {
                throw ValidationException::withMessages([
                    'paridad' => 'No se puede publicar: la paridad con Anita supera el umbral (máxima diferencia '.$maxDiff.').',
                ]);
            }
        }

        if ($exigirEvidencia) {
            self::assertCertificacionSiCorresponde($reporte, $ejecucionId, $alerta !== null);
        }
    }

    public static function certificar(
        ReporteSueldosDefinible $reporte,
        ReporteSueldosDefinibleEjecucion $ejecucion,
        int $liquidacionId,
        string $nomina,
        int $usuarioId,
        ?string $comentario = null
    ): ReporteSueldosDefinibleCertificacion {
        abort_unless(
            (int) $ejecucion->reporte_sueldos_definible_id === (int) $reporte->id,
            422,
            'Ejecución ajena al informe.'
        );
        $nomina = strtolower(trim($nomina));
        if (! array_key_exists($nomina, ReporteSueldosDefinibleCertificacion::nominas())) {
            throw ValidationException::withMessages(['nomina' => 'Nómina inválida para certificar.']);
        }
        if ($liquidacionId <= 0) {
            throw ValidationException::withMessages(['liquidacion_id' => 'Indique la liquidación a certificar.']);
        }

        $filas = ReporteSueldosDefinibleParidad::query()
            ->where('ejecucion_id', (int) $ejecucion->id)
            ->orderBy('columna_nro')
            ->get();
        if ($filas->isEmpty()) {
            throw ValidationException::withMessages([
                'paridad' => 'No hay matriz de paridad persistida para esta ejecución. Ejecute la paridad con --ejecutar antes de certificar.',
            ]);
        }

        $fuera = $filas->filter(fn ($f) => ! (bool) $f->coincide);
        if ($fuera->isNotEmpty()) {
            throw ValidationException::withMessages([
                'paridad' => 'No se puede certificar: hay '.$fuera->count().' columna(s) con diferencia fuera de tolerancia.',
            ]);
        }

        $maxDiff = round((float) $filas->max(fn ($f) => abs((float) $f->diferencia)), 4);
        $ok = $filas->where('coincide', true)->count();

        return DB::transaction(function () use (
            $reporte,
            $ejecucion,
            $liquidacionId,
            $nomina,
            $usuarioId,
            $comentario,
            $maxDiff,
            $ok
        ) {
            ReporteSueldosDefinibleCertificacion::query()
                ->where('reporte_sueldos_definible_id', (int) $reporte->id)
                ->where('liquidacion_id', $liquidacionId)
                ->where('nomina', $nomina)
                ->where('estado', ReporteSueldosDefinibleCertificacion::ESTADO_CERTIFICADA)
                ->update(['estado' => ReporteSueldosDefinibleCertificacion::ESTADO_REVOCADA]);

            return ReporteSueldosDefinibleCertificacion::query()->create([
                'reporte_sueldos_definible_id' => (int) $reporte->id,
                'liquidacion_id' => $liquidacionId,
                'ejecucion_id' => (int) $ejecucion->id,
                'nomina' => $nomina,
                'estado' => ReporteSueldosDefinibleCertificacion::ESTADO_CERTIFICADA,
                'max_diferencia' => $maxDiff,
                'columnas_ok' => $ok,
                'columnas_dif' => 0,
                'usuario_id' => $usuarioId,
                'certificada_at' => now(),
                'comentario' => $comentario !== null && trim($comentario) !== '' ? trim($comentario) : null,
            ]);
        });
    }

    public static function certificacionVigente(
        ReporteSueldosDefinible $reporte,
        int $liquidacionId,
        string $nomina
    ): ?ReporteSueldosDefinibleCertificacion {
        return ReporteSueldosDefinibleCertificacion::query()
            ->where('reporte_sueldos_definible_id', (int) $reporte->id)
            ->where('liquidacion_id', $liquidacionId)
            ->where('nomina', $nomina)
            ->where('estado', ReporteSueldosDefinibleCertificacion::ESTADO_CERTIFICADA)
            ->orderByDesc('id')
            ->first();
    }

    public static function nominaRequerida(ReporteSueldosDefinible $reporte): string
    {
        return (bool) ($reporte->incluye_confidencial ?? false)
            ? ReporteSueldosDefinibleCertificacion::NOMINA_AMBOS
            : ReporteSueldosDefinibleCertificacion::NOMINA_NORMAL;
    }

    private static function assertCertificacionSiCorresponde(
        ReporteSueldosDefinible $reporte,
        ?int $ejecucionId,
        bool $tieneAlertaBloqueante
    ): void {
        $requiere = (bool) ($reporte->incluye_confidencial ?? false) || $tieneAlertaBloqueante;
        if (! $requiere) {
            return;
        }

        $ejecucion = $ejecucionId
            ? ReporteSueldosDefinibleEjecucion::query()
                ->where('reporte_sueldos_definible_id', (int) $reporte->id)
                ->whereKey($ejecucionId)
                ->first()
            : null;
        if (! $ejecucion) {
            throw ValidationException::withMessages([
                'paridad' => 'No se puede publicar el dataset: falta ejecución con certificación de paridad.',
            ]);
        }

        $liquidacionId = (int) (($ejecucion->filtros['liquidacion_id'] ?? 0));
        if ($liquidacionId <= 0) {
            throw ValidationException::withMessages([
                'paridad' => 'No se puede publicar el dataset: la ejecución no tiene liquidación asociada para certificar.',
            ]);
        }

        $nomina = self::nominaRequerida($reporte);
        if (! self::certificacionVigente($reporte, $liquidacionId, $nomina)) {
            throw ValidationException::withMessages([
                'paridad' => 'No se puede publicar el dataset: falta certificación formal de paridad (nómina '.$nomina.', liquidación #'.$liquidacionId.').',
            ]);
        }
    }

    private static function alertaParidadBloqueante(ReporteSueldosDefinible $reporte): ?ReporteSueldosDefinibleAlerta
    {
        return ReporteSueldosDefinibleAlerta::query()
            ->where('reporte_sueldos_definible_id', (int) $reporte->id)
            ->where('tipo', ReporteSueldosDefinibleAlerta::TIPO_PARIDAD)
            ->where('activo', true)
            ->where('bloqueante', true)
            ->first();
    }

    private static function diferenciaMaxima(ReporteSueldosDefinible $reporte, ?int $ejecucionId): ?float
    {
        $query = ReporteSueldosDefinibleParidad::query()
            ->whereHas('ejecucion', fn ($q) => $q->where('reporte_sueldos_definible_id', (int) $reporte->id));
        if ($ejecucionId) {
            $query->where('ejecucion_id', $ejecucionId);
        } else {
            $ultima = (clone $query)->max('ejecucion_id');
            if (! $ultima) {
                return self::diferenciaDesdeMeta($reporte, $ejecucionId);
            }
            $query->where('ejecucion_id', $ultima);
        }
        $filas = $query->get();
        if ($filas->isNotEmpty()) {
            return round((float) $filas->max(fn ($fila) => abs((float) $fila->diferencia)), 4);
        }

        return self::diferenciaDesdeMeta($reporte, $ejecucionId);
    }

    private static function diferenciaDesdeMeta(ReporteSueldosDefinible $reporte, ?int $ejecucionId): ?float
    {
        $ejecucion = $ejecucionId
            ? ReporteSueldosDefinibleEjecucion::query()
                ->where('reporte_sueldos_definible_id', (int) $reporte->id)
                ->whereKey($ejecucionId)
                ->first()
            : $reporte->ejecuciones()->first();
        if (! $ejecucion) {
            return null;
        }
        $meta = (array) (($ejecucion->resultadoDecodificado()['meta'] ?? []));
        if (! array_key_exists('paridad_diferencia_maxima', $meta)) {
            return null;
        }

        return round((float) $meta['paridad_diferencia_maxima'], 4);
    }
}
