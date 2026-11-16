<?php

namespace App\Services\Seguridad;

use App\Models\Seguridad\BuscadorFtsDocumento;
use App\Support\Seguridad\BuscadorFtsCatalogoSupport;
use App\Support\Seguridad\BuscadorFtsExtractorSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexa adjuntos en buscador_fts_documento (nombre + contenido extraído).
 */
class BuscadorFtsIndexadorService
{
    public function __construct(
        private BuscadorFtsExtractorSupport $extractor
    ) {}

    /**
     * @return array{fuentes: int, candidatos: int, a_crear: int, a_actualizar: int, omitidos: int, sin_archivo: int}
     */
    public function estimar(?string $origenFiltro = null, ?int $limite = null): array
    {
        $totales = [
            'fuentes' => 0,
            'candidatos' => 0,
            'a_crear' => 0,
            'a_actualizar' => 0,
            'omitidos' => 0,
            'sin_archivo' => 0,
        ];

        foreach ($this->fuentesFiltradas($origenFiltro) as $fuente) {
            if (! Schema::hasTable($fuente['tabla'])) {
                continue;
            }
            $totales['fuentes']++;
            $filas = $this->candidatosFuente($fuente, $limite);
            $totales['candidatos'] += count($filas);
            foreach ($filas as $fila) {
                $ruta = $this->rutaAbsoluta($fuente['carpeta'], (int) $fila->padre_id, (string) $fila->nombre);
                if (! is_file($ruta)) {
                    $totales['sin_archivo']++;
                    continue;
                }
                $existente = BuscadorFtsDocumento::query()
                    ->where('origen_tipo', $fuente['origen_tipo'])
                    ->where('origen_id', (int) $fila->id)
                    ->first();
                $hash = $this->hashArchivo($ruta);
                if ($existente === null) {
                    $totales['a_crear']++;
                } elseif ($existente->contenido_hash !== $hash) {
                    $totales['a_actualizar']++;
                } else {
                    $totales['omitidos']++;
                }
            }
        }

        return $totales;
    }

    /**
     * @return array{creados: int, actualizados: int, omitidos: int, sin_archivo: int, errores: int}
     */
    public function indexar(?string $origenFiltro = null, ?int $limite = null): array
    {
        $totales = [
            'creados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'sin_archivo' => 0,
            'errores' => 0,
        ];

        foreach ($this->fuentesFiltradas($origenFiltro) as $fuente) {
            if (! Schema::hasTable($fuente['tabla'])) {
                continue;
            }
            foreach ($this->candidatosFuente($fuente, $limite) as $fila) {
                try {
                    $resultado = $this->indexarFila($fuente, $fila);
                    $totales[$resultado]++;
                } catch (\Throwable) {
                    $totales['errores']++;
                }
            }
        }

        return $totales;
    }

    /**
     * @param  array<string, mixed>  $fuente
     * @param  object{id: int|string, padre_id: int|string, nombre: string}  $fila
     */
    private function indexarFila(array $fuente, object $fila): string
    {
        $padreId = (int) $fila->padre_id;
        $nombre = (string) $fila->nombre;
        $rutaAbs = $this->rutaAbsoluta($fuente['carpeta'], $padreId, $nombre);
        if (! is_file($rutaAbs)) {
            return 'sin_archivo';
        }

        $hash = $this->hashArchivo($rutaAbs);
        $existente = BuscadorFtsDocumento::query()
            ->where('origen_tipo', $fuente['origen_tipo'])
            ->where('origen_id', (int) $fila->id)
            ->first();

        if ($existente !== null && $existente->contenido_hash === $hash) {
            return 'omitidos';
        }

        $extraido = $this->extractor->extraer($rutaAbs, $nombre);
        $empresaId = $this->resolverEmpresaId($fuente, $padreId);
        $payload = [
            'origen_tipo' => $fuente['origen_tipo'],
            'origen_id' => (int) $fila->id,
            'padre_tipo' => $fuente['padre_tipo'],
            'padre_id' => $padreId,
            'empresa_id' => $empresaId,
            'permiso_slug' => $fuente['permiso'],
            'nombre_archivo' => $nombre,
            'ruta_relativa' => 'storage/archivos/'.$fuente['carpeta'].'/'.$padreId.'/'.$nombre,
            'mime' => $extraido['mime'],
            'contenido_texto' => $extraido['texto'] !== '' ? $extraido['texto'] : null,
            'contenido_hash' => $hash,
            'bytes_contenido' => $extraido['bytes'],
            'indexado_at' => now(),
        ];

        if ($existente === null) {
            BuscadorFtsDocumento::query()->create($payload);

            return 'creados';
        }

        $existente->fill($payload)->save();

        return 'actualizados';
    }

    /**
     * @return list<object{id: int|string, padre_id: int|string, nombre: string}>
     */
    private function candidatosFuente(array $fuente, ?int $limite): array
    {
        $q = DB::table($fuente['tabla'])
            ->select([
                'id',
                $fuente['padre_fk'].' as padre_id',
                $fuente['nombre_col'].' as nombre',
            ])
            ->whereNotNull($fuente['nombre_col'])
            ->where($fuente['nombre_col'], '!=', '')
            ->orderBy('id');

        if ($limite !== null && $limite > 0) {
            $q->limit($limite);
        }

        return $q->get()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fuentesFiltradas(?string $origenFiltro): array
    {
        $fuentes = BuscadorFtsCatalogoSupport::fuentes();
        if ($origenFiltro === null || $origenFiltro === '') {
            return $fuentes;
        }

        return array_values(array_filter(
            $fuentes,
            fn (array $f): bool => $f['origen_tipo'] === $origenFiltro || $f['padre_tipo'] === $origenFiltro
        ));
    }

    private function rutaAbsoluta(string $carpeta, int $padreId, string $nombre): string
    {
        return public_path('storage/archivos/'.$carpeta.'/'.$padreId.'/'.$nombre);
    }

    private function hashArchivo(string $ruta): string
    {
        $size = (int) filesize($ruta);
        $mtime = (int) filemtime($ruta);

        return hash('sha256', $ruta.'|'.$size.'|'.$mtime);
    }

    /**
     * @param  array<string, mixed>  $fuente
     */
    private function resolverEmpresaId(array $fuente, int $padreId): ?int
    {
        $via = $fuente['empresa_via'] ?? null;
        if ($via === null || $via === '') {
            return null;
        }
        if (! str_contains($via, '.')) {
            return null;
        }
        [$tabla, $col] = explode('.', $via, 2);
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $col)) {
            return null;
        }
        $valor = DB::table($tabla)->where('id', $padreId)->value($col);

        return $valor !== null ? (int) $valor : null;
    }
}
