<?php

declare(strict_types=1);

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContablePublicacion;
use Illuminate\Support\Collection;

/**
 * Publicación inmutable del resultado de un informe.
 *
 * Versionar la definición no alcanza: el número presentado depende también de los
 * asientos del momento y de los filtros usados. Acá se congela la corrida completa
 * (columnas + filas) con un hash, para reimprimirla idéntica y para avisar cuando
 * la corrida de hoy ya no reproduce lo publicado.
 */
class ReporteDefiniblePublicacionSupport
{
    /**
     * @return Collection<int, ReporteContablePublicacion>
     */
    public function listar(int $reporteId, int $limite = 50): Collection
    {
        return ReporteContablePublicacion::query()
            ->where('reporte_contable_id', $reporteId)
            ->with('usuario:id,nombre')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    public function ultima(int $reporteId): ?ReporteContablePublicacion
    {
        return ReporteContablePublicacion::query()
            ->where('reporte_contable_id', $reporteId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    public function publicar(
        ReporteContable $reporte,
        array $filtros,
        array $resultado,
        ?int $usuarioId,
        ?string $nombre = null,
        ?string $observacion = null,
        ?string $periodoTexto = null,
    ): ReporteContablePublicacion {
        $congelado = $this->congelar($resultado);
        // Las notas viajan con el documento pero afuera del hash: reescribir un texto no
        // cambia el número presentado, así que no debe marcar la publicación como distinta.
        $documento = $congelado;
        $documento['notas'] = array_values((array) ($resultado['notas'] ?? []));
        $documento['notas_marcas'] = (array) ($resultado['notas_marcas'] ?? []);

        return ReporteContablePublicacion::query()->create([
            'reporte_contable_id' => (int) $reporte->id,
            'nombre' => trim((string) $nombre) !== ''
                ? trim((string) $nombre)
                : trim(($reporte->titulo1 ?: $reporte->nombre).' '.($periodoTexto ?? '')),
            'hash' => $this->hash($congelado),
            'filtros' => $this->filtrosPersistibles($filtros),
            'resultado' => json_encode($documento, JSON_UNESCAPED_UNICODE),
            'periodo_texto' => $periodoTexto,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'filas' => count($congelado['filas'] ?? []),
            'definicion_version' => (int) ($reporte->version_actual ?? 0),
            'observacion' => trim((string) $observacion) ?: null,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * ¿La corrida actual reproduce lo último publicado con los mismos filtros?
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     * @return array{publicacion: ReporteContablePublicacion, coincide: bool, mensaje: string}|null
     */
    public function compararConPublicado(int $reporteId, array $filtros, array $resultado): ?array
    {
        $firma = $this->firmaFiltros($filtros);
        $publicacion = ReporteContablePublicacion::query()
            ->where('reporte_contable_id', $reporteId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->first(fn (ReporteContablePublicacion $p) => $this->firmaFiltros((array) $p->filtros) === $firma);

        if ($publicacion === null) {
            return null;
        }

        $hashActual = $this->hash($this->congelar($resultado));
        $coincide = $hashActual === (string) $publicacion->hash;

        return [
            'publicacion' => $publicacion,
            'coincide' => $coincide,
            'mensaje' => $coincide
                ? sprintf(
                    'Coincide con la publicación «%s» del %s: lo presentado se sigue reproduciendo.',
                    $publicacion->nombre,
                    $publicacion->created_at?->format('d/m/Y H:i') ?? ''
                )
                : sprintf(
                    'Esta corrida NO coincide con la publicación «%s» del %s (mismos filtros). '
                    .'Cambió la definición o los asientos del período: revise antes de volver a presentar.',
                    $publicacion->nombre,
                    $publicacion->created_at?->format('d/m/Y H:i') ?? ''
                ),
        ];
    }

    /**
     * Publicaciones que dejarían de reproducirse si se toca la definición.
     *
     * @return array{cantidad: int, ultima: ReporteContablePublicacion|null}
     */
    public function impactoDefinicion(int $reporteId): array
    {
        $cantidad = (int) ReporteContablePublicacion::query()
            ->where('reporte_contable_id', $reporteId)
            ->count();

        return ['cantidad' => $cantidad, 'ultima' => $cantidad > 0 ? $this->ultima($reporteId) : null];
    }

    /**
     * Solo lo que se imprime: columnas y filas con sus valores. Se descartan el modelo
     * Eloquent del informe y los avisos de la corrida (no forman parte del documento).
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function congelar(array $resultado): array
    {
        $columnas = [];
        foreach ($resultado['columnas'] ?? [] as $col) {
            $columnas[] = [
                'key' => (string) ($col['key'] ?? ''),
                'label' => (string) ($col['label'] ?? ''),
                'tipo' => (string) ($col['tipo'] ?? ''),
            ];
        }

        $filas = [];
        foreach ($resultado['filas'] ?? [] as $fila) {
            $saldos = null;
            if (is_array($fila['saldos'] ?? null)) {
                $saldos = [];
                foreach ($fila['saldos'] as $key => $valor) {
                    $saldos[(string) $key] = $valor === null ? null : round((float) $valor, 2);
                }
            }
            $filas[] = [
                'kind' => (string) ($fila['kind'] ?? 'rubro'),
                'depth' => (int) ($fila['depth'] ?? 0),
                'codigo' => (string) ($fila['codigo'] ?? ''),
                'nombre' => (string) ($fila['nombre'] ?? ''),
                'tipo' => (string) ($fila['tipo'] ?? ''),
                'tipo_label' => (string) ($fila['tipo_label'] ?? ''),
                'negrita' => (bool) ($fila['negrita'] ?? false),
                'subrayado' => (bool) ($fila['subrayado'] ?? false),
                'nivel' => (int) ($fila['nivel'] ?? 0),
                'saldos' => $saldos,
            ];
        }

        return [
            'columnas' => $columnas,
            'filas' => $filas,
            'fuente' => (string) ($resultado['fuente'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $congelado
     */
    public function hash(array $congelado): string
    {
        return hash('sha256', json_encode($congelado, JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * Filtros que definen el documento: los de presentación (ocultar ceros, etc.) no cuentan.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosPersistibles(array $filtros): array
    {
        $claves = [
            'empresa_ids', 'consolidar_empresas', 'modo_periodo', 'periodo_desde', 'periodo_hasta',
            'fecha_desde', 'fecha_hasta', 'base_saldo', 'modo_inclusion_asientos', 'moneda_id',
            'solo_moneda_origen', 'columnas_layout', 'layout_id', 'nivel_max', 'mostrar_cuentas',
            'incluir_presupuesto', 'presupuesto_escenario_id', 'ccosto_desde', 'ccosto_hasta',
        ];

        $out = [];
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $filtros)) {
                $out[$clave] = $filtros[$clave];
            }
        }
        if (isset($out['empresa_ids']) && is_array($out['empresa_ids'])) {
            $out['empresa_ids'] = array_values(array_map('intval', $out['empresa_ids']));
            sort($out['empresa_ids']);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function firmaFiltros(array $filtros): string
    {
        $persistibles = $this->filtrosPersistibles($filtros);
        ksort($persistibles);

        return hash('sha256', json_encode($persistibles) ?: '');
    }
}
