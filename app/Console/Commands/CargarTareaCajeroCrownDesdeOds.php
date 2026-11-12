<?php

namespace App\Console\Commands;

use App\Models\Caja\CajeroCrown;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownTareaCatalogo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Carga tipo_tarea (y flag de rendir sin HAB) en cajero_crown desde el listado ODS.
 */
class CargarTareaCajeroCrownDesdeOds extends Command
{
    protected $signature = 'cajero-crown:cargar-tarea-ods
                            {archivo? : Ruta al .ods (default: /home/sergio/tmp/listado legajos nombres y roles.ods)}
                            {--dry-run : Solo mostrar impacto, sin grabar}
                            {--ejecutar : Persistir cambios}
                            {--crear-faltantes : Alta de legajos/módulos ausentes en sala CIP}';

    protected $description = 'Asigna tipo_tarea a cajeros Crown desde el ODS de legajos/roles.';

    /** @var array<string, string> */
    private const MAPA = [
        'tesoreria' => 'T',
        'tesorería' => 'T',
        'gatronomia' => 'G',
        'gatronomía' => 'G',
        'gastronomia' => 'G',
        'gastronomía' => 'G',
        'gastronomia encargado' => 'G',
        'gastronomía encargado' => 'G',
        'hotel' => 'H',
        'modulo' => 'K',
        'módulo' => 'K',
    ];

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Solo corre en EMPRESA=CROWN.');

            return self::FAILURE;
        }
        $ejecutar = (bool) $this->option('ejecutar');
        $dry = (bool) $this->option('dry-run') || ! $ejecutar;
        if ($ejecutar && $this->option('dry-run')) {
            $this->error('Usá --dry-run o --ejecutar, no ambos.');

            return self::FAILURE;
        }

        $archivo = (string) ($this->argument('archivo')
            ?: '/home/sergio/tmp/listado legajos nombres y roles.ods');
        if (! is_readable($archivo)) {
            $this->error('No se lee el archivo: '.$archivo);

            return self::FAILURE;
        }

        $filas = $this->leerOds($archivo);
        if ($filas === []) {
            $this->error('El ODS no tiene filas de datos.');

            return self::FAILURE;
        }

        $sala = Sala::query()->where('codigo', 'CIP')->first()
            ?? Sala::query()->orderBy('id')->first();
        if (! $sala) {
            $this->error('No hay sala para asignar faltantes.');

            return self::FAILURE;
        }

        $actualizar = [];
        $crear = [];
        $sinMapa = [];
        $yaIgual = 0;

        foreach ($filas as $fila) {
            $codigoTarea = $this->mapearTarea($fila['tarea']);
            if ($codigoTarea === null) {
                $sinMapa[] = $fila;
                continue;
            }
            $permite = TesoreriaCrownTareaCatalogo::sugiereRendirSinHabilitacion($codigoTarea);
            $cajeros = CajeroCrown::query()
                ->where(function ($q) use ($fila) {
                    $q->where('legajo', $fila['legajo'])
                        ->orWhere('codigo', $fila['legajo']);
                })
                ->get();
            if ($cajeros->isEmpty()) {
                $crear[] = [
                    'legajo' => $fila['legajo'],
                    'nombre' => $fila['nombre'],
                    'tipo_tarea' => $codigoTarea,
                    'permite_rendir_sin_habilitacion' => $permite,
                ];
                continue;
            }
            foreach ($cajeros as $cajero) {
                $cambia = (string) ($cajero->tipo_tarea ?? '') !== $codigoTarea
                    || (bool) $cajero->permite_rendir_sin_habilitacion !== $permite;
                if (! $cambia) {
                    $yaIgual++;
                    continue;
                }
                $actualizar[] = [
                    'id' => (int) $cajero->id,
                    'codigo' => (int) $cajero->codigo,
                    'legajo' => $cajero->legajo,
                    'nombre' => $cajero->nombre,
                    'de_tarea' => $cajero->tipo_tarea,
                    'a_tarea' => $codigoTarea,
                    'permite' => $permite,
                ];
            }
        }

        $this->info(($dry ? 'DRY-RUN' : 'EJECUTAR').': filas ODS='.count($filas)
            .' | actualizar='.count($actualizar)
            .' | crear='.count($crear)
            .' | sin cambio='.$yaIgual
            .' | sin mapa='.count($sinMapa));

        if ($sinMapa !== []) {
            $this->warn('Sin mapa de tarea:');
            foreach ($sinMapa as $f) {
                $this->line('  '.$f['legajo'].' '.$f['nombre'].' → '.$f['tarea']);
            }
        }
        if ($crear !== []) {
            $this->warn('Ausentes en cajero_crown'.($this->option('crear-faltantes') ? ' (se crean)' : ' (no se crean sin --crear-faltantes)').':');
            foreach ($crear as $f) {
                $this->line('  '.$f['legajo'].' '.$f['nombre'].' → '.$f['tipo_tarea']);
            }
        }
        foreach (array_slice($actualizar, 0, 25) as $u) {
            $this->line(sprintf(
                '  id=%d cod=%d leg=%s %s: %s → %s (sinHAB=%s)',
                $u['id'],
                $u['codigo'],
                (string) $u['legajo'],
                $u['nombre'],
                $u['de_tarea'] ?: '∅',
                $u['a_tarea'],
                $u['permite'] ? '1' : '0'
            ));
        }
        if (count($actualizar) > 25) {
            $this->line('  … y '.(count($actualizar) - 25).' más');
        }

        if ($dry) {
            $this->warn('Sin grabar. Para persistir: --ejecutar [--crear-faltantes]');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($actualizar, $crear, $sala) {
            foreach ($actualizar as $u) {
                CajeroCrown::query()->whereKey($u['id'])->update([
                    'tipo_tarea' => $u['a_tarea'],
                    'permite_rendir_sin_habilitacion' => $u['permite'],
                    'updated_at' => now(),
                ]);
            }
            if ($this->option('crear-faltantes')) {
                foreach ($crear as $f) {
                    $existe = CajeroCrown::query()
                        ->where('empresa_id', $sala->empresa_id)
                        ->where('codigo', $f['legajo'])
                        ->exists();
                    if ($existe) {
                        continue;
                    }
                    CajeroCrown::query()->create([
                        'empresa_id' => $sala->empresa_id,
                        'sala_id' => $sala->id,
                        'codigo' => $f['legajo'],
                        'clave' => (string) $f['legajo'],
                        'nombre' => $f['nombre'],
                        'legajo' => $f['legajo'],
                        'tipo_tarea' => $f['tipo_tarea'],
                        'permite_rendir_sin_habilitacion' => $f['permite_rendir_sin_habilitacion'],
                        'activo' => true,
                    ]);
                }
            }
        });

        $this->info('Listo.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{legajo: int, nombre: string, tarea: string}>
     */
    private function leerOds(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }
        $xml = $zip->getFromName('content.xml');
        $zip->close();
        if ($xml === false) {
            return [];
        }

        $root = new \SimpleXMLElement($xml);
        $root->registerXPathNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $root->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $rows = $root->xpath('//table:table-row') ?: [];
        $out = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row->children('urn:oasis:names:tc:opendocument:xmlns:table:1.0') as $cell) {
                if ($cell->getName() !== 'table-cell') {
                    continue;
                }
                $repeat = (int) ($cell['number-columns-repeated'] ?? 1);
                $text = '';
                foreach ($cell->children('urn:oasis:names:tc:opendocument:xmlns:text:1.0') as $p) {
                    if ($p->getName() === 'p') {
                        $text .= trim((string) $p);
                    }
                }
                for ($i = 0; $i < min($repeat, 5); $i++) {
                    $cells[] = $text;
                }
                if (count($cells) >= 3) {
                    break;
                }
            }
            if (count($cells) < 3) {
                continue;
            }
            if (! ctype_digit(trim($cells[0]))) {
                continue;
            }
            $out[] = [
                'legajo' => (int) $cells[0],
                'nombre' => trim($cells[1]),
                'tarea' => trim($cells[2]),
            ];
        }

        return $out;
    }

    private function mapearTarea(string $texto): ?string
    {
        $key = mb_strtolower(trim($texto));
        $key = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $key);
        foreach (self::MAPA as $etiqueta => $codigo) {
            $norm = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $etiqueta);
            if ($key === $norm || $key === $etiqueta) {
                return $codigo;
            }
        }
        // sin acentos en key ya
        return self::MAPA[$key] ?? null;
    }
}
