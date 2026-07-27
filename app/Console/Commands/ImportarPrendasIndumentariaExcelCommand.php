<?php

namespace App\Console\Commands;

use App\Models\Stock\Articulo;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Models\Sueldos\Prenda_Articulo_Sueldos;
use App\Models\Sueldos\Prenda_Sueldos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reemplaza catálogo indumentaria (color, talle, prenda + variantes) con el Excel de Capital Humano.
 *
 * Fuente de prendas: solapa "Detalle de prendas" (codigo CH-IND*, articulo, talles, colores).
 * Borra datos Anita previos (entregas históricas de prueba, prendas, dotación, color/talle).
 */
class ImportarPrendasIndumentariaExcelCommand extends Command
{
    protected $signature = 'indumentaria:importar-prendas-excel
                            {--file=/home/sergio/tmp/Uniformes x Puesto + Articulos actualizados Final (1).xlsx : Excel origen}
                            {--dry-run : Solo informar, no grabar}
                            {--forzar : Ejecutar el wipe+import sin confirmación interactiva}';

    protected $description = 'Reemplaza prendas/colores/talles de indumentaria desde Excel (Capital Humano)';

    /** @var array<string, string> */
    private const ALIAS_COLOR = [
        'NEGRA' => 'NEGRO',
        'CHANPAGNE' => 'CHAMPAGNE',
        'CHAMPAGNE' => 'CHAMPAGNE',
        'PETROLEO' => 'PETROLEO',
        'PETRÓLEO' => 'PETROLEO',
        'MARRON' => 'MARRON',
        'MARRÓN' => 'MARRON',
    ];

    public function handle(): int
    {
        $path = (string) $this->option('file');
        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $forzar = (bool) $this->option('forzar');

        $this->info('Leyendo solapa "Detalle de prendas"…');
        $wb = IOFactory::load($path);
        $sheet = $this->resolverHojaDetalle($wb);
        $rows = $sheet->toArray(null, true, true, false);
        if ($rows === []) {
            $this->error('Solapa vacía.');

            return self::FAILURE;
        }

        $headers = array_map(static fn ($h) => mb_strtolower(trim((string) $h)), $rows[0]);
        $idx = [
            'codigo' => $this->buscarColumna($headers, ['codigo']),
            'articulo' => $this->buscarColumna($headers, ['articulo']),
            'talle1' => $this->buscarColumna($headers, ['talle 1', 'talle1']),
            'talle2' => $this->buscarColumna($headers, ['talle 2', 'talle2']),
            'talle3' => $this->buscarColumna($headers, ['talle 3', 'talle3']),
            'color1' => $this->buscarColumna($headers, ['color 1', 'color1']),
            'color2' => $this->buscarColumna($headers, ['color 2', 'color2']),
            'color3' => $this->buscarColumna($headers, ['color 3', 'color3']),
        ];
        if ($idx['codigo'] === null || $idx['articulo'] === null) {
            $this->error('Faltan columnas Codigo/Articulo. Headers: '.implode(' | ', $headers));

            return self::FAILURE;
        }

        unset($rows[0]);
        $prendasExcel = [];
        $coloresNecesarios = [];
        $tallesNecesarios = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row[$idx['codigo']] ?? ''));
            $descripcion = trim((string) ($row[$idx['articulo']] ?? ''));
            if ($sku === '' || $descripcion === '') {
                continue;
            }

            $colores = [];
            foreach (['color1', 'color2', 'color3'] as $k) {
                if ($idx[$k] === null) {
                    continue;
                }
                $c = $this->normalizarColor((string) ($row[$idx[$k]] ?? ''));
                if ($c !== '') {
                    $colores[$c] = true;
                    $coloresNecesarios[$c] = true;
                }
            }
            if ($colores === []) {
                $colores['UNICO'] = true;
                $coloresNecesarios['UNICO'] = true;
            }

            $talles = [];
            foreach (['talle1', 'talle2', 'talle3'] as $k) {
                if ($idx[$k] === null) {
                    continue;
                }
                foreach ($this->expandirRangoTalle((string) ($row[$idx[$k]] ?? '')) as $t) {
                    $talles[$t] = true;
                    $tallesNecesarios[$t] = true;
                }
            }
            if ($talles === []) {
                $talles['UNICO'] = true;
                $tallesNecesarios['UNICO'] = true;
            }

            $codigoPrenda = $this->codigoPrendaDesdeSku($sku);
            $prendasExcel[] = [
                'sku' => $sku,
                'codigo' => $codigoPrenda,
                'descripcion' => mb_substr($descripcion, 0, 60),
                'colores' => array_keys($colores),
                'talles' => array_keys($talles),
                'es_seguridad' => $this->detectarEpp($descripcion),
            ];
        }

        ksort($coloresNecesarios);
        $coloresLista = array_keys($coloresNecesarios);
        $tallesLista = $this->ordenarTalles(array_keys($tallesNecesarios));

        $this->analizarCatalogos($coloresLista, $tallesLista);

        $this->line('Prendas en Excel: '.count($prendasExcel));
        $variantesEstimadas = 0;
        foreach ($prendasExcel as $p) {
            $variantesEstimadas += count($p['colores']) * count($p['talles']);
        }
        $this->line("Variantes estimadas (color×talle): {$variantesEstimadas}");

        $skusFaltantes = [];
        foreach ($prendasExcel as $p) {
            if (! Articulo::query()->where('sku', $p['sku'])->exists()) {
                $skusFaltantes[] = $p['sku'];
            }
        }
        if ($skusFaltantes !== []) {
            $this->error('Faltan artículos stock para SKUs: '.implode(', ', $skusFaltantes));
            $this->line('Correr antes: php artisan stock:importar-articulos-indumentaria-rrhh');

            return self::FAILURE;
        }

        if ($dry) {
            $this->newLine();
            $this->info('DRY-RUN — no se graba. Muestra:');
            foreach (array_slice($prendasExcel, 0, 5) as $p) {
                $this->line(sprintf(
                    '  %s codigo=%d | %s | colores=[%s] talles=%d | epp=%s',
                    $p['sku'],
                    $p['codigo'],
                    $p['descripcion'],
                    implode(',', $p['colores']),
                    count($p['talles']),
                    $p['es_seguridad'] ? 'si' : 'no'
                ));
            }
            $this->line('  …');
            $this->line('Colores a crear: '.implode(', ', $coloresLista));
            $this->line('Talles a crear ('.count($tallesLista).'): '.implode(', ', $tallesLista));

            return self::SUCCESS;
        }

        if (! $forzar && ! $this->confirm(
            'Esto BORRA entregas de indumentaria, prendas Anita, dotación, y recrea color/talle. ¿Continuar?',
            false
        )) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($prendasExcel, $coloresLista, $tallesLista) {
            $this->info('1) Borrando entregas históricas…');
            DB::table('entrega_prenda_articulo_sueldos')->delete();
            DB::table('entrega_prenda_sueldos')->delete();

            $this->info('2) Borrando prendas Anita (cascada variantes + dotación)…');
            // CASCADE: prenda_articulo_sueldos, prenda_agrupamiento_sueldos, empleado_talle_sueldos
            DB::table('prenda_sueldos')->delete();

            $this->info('3) Recreando catálogo color…');
            DB::table('color')->delete();
            $mapaColor = [];
            $codigoColor = 1;
            foreach ($coloresLista as $nombre) {
                $color = Color::create([
                    'codigo' => (string) $codigoColor,
                    'nombre' => $nombre,
                ]);
                $mapaColor[$nombre] = (int) $color->id;
                $codigoColor++;
            }

            $this->info('4) Recreando catálogo talle…');
            DB::table('talle')->delete();
            $mapaTalle = [];
            $codigoTalle = 1;
            foreach ($tallesLista as $nombre) {
                $talle = Talle::create([
                    'codigo' => $codigoTalle,
                    'nombre' => $nombre,
                ]);
                $mapaTalle[$nombre] = (int) $talle->id;
                $codigoTalle++;
            }

            $this->info('5) Creando prendas + variantes…');
            $articulos = Articulo::query()
                ->whereIn('sku', array_column($prendasExcel, 'sku'))
                ->pluck('id', 'sku');

            $orden = 1;
            $totalVariantes = 0;
            foreach ($prendasExcel as $p) {
                $prenda = Prenda_Sueldos::create([
                    'codigo' => $p['codigo'],
                    'descripcion' => $p['descripcion'],
                    'marca' => null,
                    'es_seguridad' => $p['es_seguridad'],
                    'vida_util_meses' => null,
                    'requiere_certificacion' => false,
                    'norma' => null,
                    'porcentaje_pedido' => null,
                    'activo' => true,
                    'orden' => $orden++,
                ]);

                $articuloId = (int) ($articulos[$p['sku']] ?? 0) ?: null;
                foreach ($p['colores'] as $nombreColor) {
                    foreach ($p['talles'] as $nombreTalle) {
                        Prenda_Articulo_Sueldos::create([
                            'prenda_id' => $prenda->id,
                            'color_id' => $mapaColor[$nombreColor],
                            'talle_id' => $mapaTalle[$nombreTalle],
                            'articulo_id' => $articuloId,
                            'sku' => $p['sku'],
                        ]);
                        $totalVariantes++;
                    }
                }
                $this->line("  OK {$p['sku']} prenda#{$p['codigo']} variantes=".
                    (count($p['colores']) * count($p['talles'])));
            }

            $this->info("Listo: prendas=".count($prendasExcel)." variantes={$totalVariantes} colores=".count($mapaColor).' talles='.count($mapaTalle));
        });

        return self::SUCCESS;
    }

    private function analizarCatalogos(array $coloresExcel, array $tallesExcel): void
    {
        $coloresDb = Color::query()->pluck('nombre')->map(fn ($n) => mb_strtoupper(trim((string) $n)))->all();
        $tallesDb = Talle::query()->pluck('nombre')->map(fn ($n) => mb_strtoupper(trim((string) $n)))->all();

        $coloresOk = empty(array_diff($coloresExcel, $coloresDb));
        $tallesOk = empty(array_diff($tallesExcel, $tallesDb));

        $this->newLine();
        $this->info('Análisis catálogos vs Excel:');
        $this->line('  Colores Excel ('.count($coloresExcel).'): '.implode(', ', $coloresExcel));
        $this->line('  Colores DB actuales ('.count($coloresDb).'): '.implode(', ', $coloresDb));
        $faltanC = array_values(array_diff($coloresExcel, $coloresDb));
        $extraC = array_values(array_diff($coloresDb, $coloresExcel));
        $this->line('  Faltan en DB: '.($faltanC === [] ? '(ninguno)' : implode(', ', $faltanC)));
        $this->line('  Sobran en DB: '.($extraC === [] ? '(ninguno)' : implode(', ', $extraC)));
        $this->line('  → '.($coloresOk && $extraC === [] ? 'coinciden' : 'NO coinciden → se recrea catálogo color'));

        $this->line('  Talles Excel ('.count($tallesExcel).'): '.implode(', ', $tallesExcel));
        $faltanT = array_values(array_diff($tallesExcel, $tallesDb));
        $extraT = array_values(array_diff($tallesDb, $tallesExcel));
        $this->line('  Faltan en DB: '.($faltanT === [] ? '(ninguno)' : implode(', ', $faltanT)));
        $this->line('  Sobran en DB ('.count($extraT).'): '.($extraT === [] ? '(ninguno)' : implode(', ', array_slice($extraT, 0, 15)).(count($extraT) > 15 ? '…' : '')));
        $this->line('  → '.($tallesOk && $extraT === [] ? 'coinciden' : 'NO coinciden → se recrea catálogo talle'));
        $this->newLine();
    }

    /**
     * @param  \PhpOffice\PhpSpreadsheet\Spreadsheet  $wb
     * @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
     */
    private function resolverHojaDetalle($wb)
    {
        foreach ($wb->getSheetNames() as $name) {
            if (trim($name) === 'Detalle de prendas') {
                return $wb->getSheetByName($name);
            }
        }

        return $wb->getSheet(1);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $candidatos
     */
    private function buscarColumna(array $headers, array $candidatos): ?int
    {
        foreach ($headers as $i => $h) {
            foreach ($candidatos as $c) {
                if ($h === $c || str_starts_with($h, $c)) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function normalizarColor(string $raw): string
    {
        $v = mb_strtoupper(trim($raw));
        if ($v === '') {
            return '';
        }
        // Quitar acentos simples
        $v = strtr($v, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ]);
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;

        return self::ALIAS_COLOR[$v] ?? $v;
    }

    /**
     * Expande rangos del Excel a talles individuales.
     *
     * @return list<string>
     */
    private function expandirRangoTalle(string $raw): array
    {
        $v = mb_strtoupper(trim($raw));
        $v = strtr($v, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;
        if ($v === '') {
            return [];
        }

        if (in_array($v, ['UNICO', 'ÚNICO', 'S/TALLE', 'ST', 'U'], true)) {
            return ['UNICO'];
        }

        // "XS AL XXXXL"
        if (preg_match('/^XS\s+AL\s+X{0,4}L$/u', $v) || $v === 'XS AL XXXXL') {
            return ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'XXXXL'];
        }

        // "36 AL 64" (talles de ropa de a 2)
        if (preg_match('/^(\d+)\s+AL\s+(\d+)$/', $v, $m)) {
            $desde = (int) $m[1];
            $hasta = (int) $m[2];
            if ($desde > $hasta) {
                return [];
            }
            // Rango de calzado/ropa numérico: si ambos pares (o 36-64), paso 2; si 1-10, paso 1
            $paso = ($desde >= 20 && $hasta >= 20 && $desde % 2 === 0 && $hasta % 2 === 0) ? 2 : 1;
            $out = [];
            for ($i = $desde; $i <= $hasta; $i += $paso) {
                $out[] = (string) $i;
            }

            return $out;
        }

        // Valor suelto
        return [$v];
    }

    /**
     * @param  list<string>  $talles
     * @return list<string>
     */
    private function ordenarTalles(array $talles): array
    {
        $letraOrden = ['XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4, 'XL' => 5, 'XXL' => 6, 'XXXL' => 7, 'XXXXL' => 8];
        $letras = [];
        $numeros = [];
        $otros = [];
        $tieneUnico = false;

        foreach ($talles as $t) {
            // array_keys puede devolver int si el nombre es numérico ("36" → 36);
            // ctype_digit(int) interpreta el entero como código ASCII — forzar string.
            $t = (string) $t;
            if ($t === 'UNICO') {
                $tieneUnico = true;
            } elseif (isset($letraOrden[$t])) {
                $letras[] = $t;
            } elseif (ctype_digit($t)) {
                $numeros[] = $t;
            } else {
                $otros[] = $t;
            }
        }

        usort($letras, static fn ($a, $b) => $letraOrden[$a] <=> $letraOrden[$b]);
        usort($numeros, static fn ($a, $b) => ((int) $a) <=> ((int) $b));
        sort($otros);

        return array_values(array_merge($letras, $numeros, $otros, $tieneUnico ? ['UNICO'] : []));
    }

    private function codigoPrendaDesdeSku(string $sku): int
    {
        if (preg_match('/(\d+)$/', $sku, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    private function detectarEpp(string $descripcion): bool
    {
        $d = mb_strtoupper($descripcion);
        foreach (['CASCO', 'REFRACTARIO', 'GUANTE', 'PROTECTOR', 'SEGURIDAD', 'FAJA', 'BOTAS DE LLUVIA', 'PILOTO'] as $kw) {
            if (str_contains($d, $kw)) {
                return true;
            }
        }

        return false;
    }
}
