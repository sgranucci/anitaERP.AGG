<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleColumna;
use App\Models\Sueldos\ReporteSueldosDefinibleConcepto;
use App\Models\Sueldos\ReporteSueldosDefinibleVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ReporteSueldosDefinibleVersionSupport
{
    public function publicar(ReporteSueldosDefinible $reporte, ?string $comentario = null): ReporteSueldosDefinibleVersion
    {
        ReporteSueldosDefinibleParidadPublicacionSupport::assertPublicable(
            $reporte,
            $reporte->publicado_ejecucion_id ? (int) $reporte->publicado_ejecucion_id : null
        );
        $reporte->load(['columnas.conceptos']);
        $snapshot = [
            'codigo' => $reporte->codigo,
            'titulo' => $reporte->titulo,
            'tipo' => $reporte->tipo,
            'asociado_codigo' => $reporte->asociado_codigo,
            'columnas' => $reporte->columnas->map(fn (ReporteSueldosDefinibleColumna $c) => [
                'nro_columna' => $c->nro_columna,
                'descripcion' => $c->descripcion,
                'contenido' => $c->contenido,
                'campo_empleado' => $c->campo_empleado,
                'largo' => $c->largo,
                'formula' => $c->formula,
                'orden' => $c->orden,
                'conceptos' => $c->conceptos->map(fn (ReporteSueldosDefinibleConcepto $x) => [
                    'concepto_codigo' => $x->concepto_codigo,
                    'orden' => $x->orden,
                    'signo' => $x->signo,
                ])->values()->all(),
            ])->values()->all(),
        ];

        $version = ((int) $reporte->version_actual) + 1;

        return DB::transaction(function () use ($reporte, $version, $snapshot, $comentario) {
            $row = ReporteSueldosDefinibleVersion::query()->create([
                'reporte_sueldos_definible_id' => $reporte->id,
                'version' => $version,
                'snapshot' => $snapshot,
                'usuario_id' => Auth::id(),
                'comentario' => $comentario,
            ]);
            $reporte->update([
                'version_actual' => $version,
                'estado_publicacion' => 'publicado',
            ]);

            return $row;
        });
    }

    public function restaurar(ReporteSueldosDefinible $reporte, int $versionId): void
    {
        $ver = ReporteSueldosDefinibleVersion::query()
            ->where('reporte_sueldos_definible_id', $reporte->id)
            ->where('id', $versionId)
            ->firstOrFail();
        $snap = $ver->snapshot ?? [];

        DB::transaction(function () use ($reporte, $snap, $ver) {
            $reporte->columnas()->each(function (ReporteSueldosDefinibleColumna $col) {
                $col->conceptos()->delete();
                $col->delete();
            });
            $reporte->update([
                'titulo' => $snap['titulo'] ?? $reporte->titulo,
                'tipo' => $snap['tipo'] ?? $reporte->tipo,
                'asociado_codigo' => $snap['asociado_codigo'] ?? null,
            ]);
            foreach ($snap['columnas'] ?? [] as $col) {
                $columna = ReporteSueldosDefinibleColumna::query()->create([
                    'reporte_sueldos_definible_id' => $reporte->id,
                    'nro_columna' => (int) ($col['nro_columna'] ?? 1),
                    'descripcion' => (string) ($col['descripcion'] ?? ''),
                    'contenido' => (string) ($col['contenido'] ?? 'importe'),
                    'campo_empleado' => $col['campo_empleado'] ?? null,
                    'largo' => $col['largo'] ?? null,
                    'formula' => $col['formula'] ?? null,
                    'orden' => (int) ($col['orden'] ?? 0),
                ]);
                foreach ($col['conceptos'] ?? [] as $con) {
                    ReporteSueldosDefinibleConcepto::query()->create([
                        'columna_id' => $columna->id,
                        'concepto_codigo' => (int) ($con['concepto_codigo'] ?? 0),
                        'orden' => (int) ($con['orden'] ?? 0),
                        'signo' => (($con['signo'] ?? '+') === '-') ? '-' : '+',
                    ]);
                }
            }

            $this->publicar(
                $reporte,
                'Restauración de la versión '.(int) $ver->version
            );
        });
    }
}
