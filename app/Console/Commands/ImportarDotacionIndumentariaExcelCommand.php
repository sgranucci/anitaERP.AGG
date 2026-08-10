<?php

namespace App\Console\Commands;

use App\Support\Database\SqlDialectSupport;
use App\Models\Stock\Color;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Prenda_Agrupamiento_Sueldos;
use App\Models\Sueldos\Prenda_Sueldos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa la solapa "Uniforme por puesto" → prenda_agrupamiento_sueldos (dotación).
 */
class ImportarDotacionIndumentariaExcelCommand extends Command
{
    protected $signature = 'indumentaria:importar-dotacion-excel
                            {--file=/home/sergio/tmp/Uniformes x Puesto + Articulos actualizados Final (1).xlsx : Excel origen}
                            {--dry-run : Solo informar, no grabar}
                            {--forzar : Ejecutar sin confirmación}
                            {--reemplazar : Vaciar dotación actual antes de importar}';

    protected $description = 'Importa dotación de indumentaria (uniforme por puesto) desde Excel Capital Humano';

    /**
     * Excel puesto (normalizado) → descripciones exactas de agrupamiento_sueldos.
     * Se resuelven a IDs en runtime (puede haber varios con el mismo nombre).
     *
     * @var array<string, list<string>>
     */
    private const MAPA_PUESTO_AGRUP = [
        'CAMAREROS' => ['CAMARERO/A DE GASTRONOMIA', 'MOZO MOSTRADOR', 'MAITRE', 'BARMAN'],
        'COCINERO' => ['COCINERO', 'JEFE PARTIDA-COCINERO', 'JEFE DE COCINA', 'CHEF', 'JEFE DE PARTIDA'],
        'COCINA (NO COCINERO)' => [
            'AYUDANTE DE COCINA', 'COMIS DE COCINA', 'MONTAPLATO DE COCINA', 'LAVACOPAS',
            'SANDWICHERO', 'PIZZERO', 'POSTRERO', 'CAFETERO', 'FIAMB. SANDW PPAL',
        ],
        'REPOSITOR' => ['REPOSITOR'],
        'CAJEROS GASTRONOMIA' => ['CAJERO/A', 'AUXILIAR DE CAJA', 'CAJA ISLA'],
        'TECNICO OYM / CCTV' => [
            'TECNICO DE O Y M', 'TEC. EN CCTV Y CABLEADO ESTR.', 'ANALISTA DE O Y M',
        ],
        'LOGISTICA' => [
            'ASIST. DE LOGISTICA', 'ENCARGADO DE DEPOSITO', 'RECEPCION DE MERCADERIA',
            'ANALISTA DE INVENTARIOS', 'COORD DE INVENTARIO', 'ENCARGADO/A INVENTARIO',
        ],
        'ENCARGADOS TECNICOS/ TECNICOS / SOPORTE TECNICO' => [
            'TECNICO', 'TECNICO JR', 'TECNICO SSR', 'TECNICO SR',
            'TECNICO ESPECIALIZADO', 'TECNICO ESPECIALIZADO JR',
            'ASIST. DE AREA TECNICA', 'ENCARGADO/A GCIA TECNICA',
            'JEFE DE TURNO GCIA TECNICA', 'GERENTE TECNICO', 'TEAM LEADER TECNOLOGIA',
            'MANTENIMIENTO TECNICO',
        ],
        'VALET PARKING' => [
            'JR DE ESTACIONAMIENTO', 'SSR DE ESTACIONAMIENTO', 'SR DE ESTACIONAMIENTO',
            'COORD DE ESTACIONAMIENTO', 'JEFE DE TURNO ESTACIONAMIENTO',
        ],
        'BRANCH MANAGERS' => ['BRANCH MANAGER', 'REGIONAL MANAGER'],
        'SLOT ATTENDANT / CAJERO SALA' => [
            'JR DE CAJAS MAQUINAS', 'SSR CAJAS MAQUINAS', 'SR DE CAJAS MAQUINAS',
            'JR DE OP. MAQUINAS', 'SSR DE OP. MAQUINAS', 'SR DE OP. MAQUINAS',
            'APRENDIZ DE OP. MAQUINAS', 'SUPERVISOR DE MAQUINAS',
            'ENCARGADO/A DE MAQUINAS', 'REFERENTE DE MAQUINAS',
            'TEAM LEADER DE SLOTS Y JUEGOS',
        ],
        'TEAM LEADER SR/ TEAM MANAGER' => [
            'TEAM LEADER SR.', 'TEAM LEADER JR.', 'TEAM MANAGER',
        ],
        'BINGO' => [
            'JR DE OP. BINGO', 'SSR DE OP. BINGO', 'SR DE OP. BINGO',
            'JR CAJAS BINGO', 'SSR CAJAS BINGO', 'SR DE CAJAS BINGO',
            'JEFE DE SALA BINGO', 'JEFE DE TURNO BINGO', 'ENCARGADO/A DE BINGO',
            'APRENDIZ DE OP. SALA DE BINGO',
        ],
        'MANTENIMIENTO DE SALA' => [
            'MANTENIMIENTO', 'JR DE MANT. Y LIMPIEZA', 'SSR DE MANT Y LIMPIEZA',
            'SR DE MANT Y LIMPIEZA', 'ENCARGADO/A DE MANTENIMIENTO',
            'JEFE DE MANTENIMIENTO', 'JEFE DE TURNO MANTENIMIENTO',
            'JEFE DE TURNO MANT Y LIMP', 'JEFE DE CORP. MANTENIMIENTO',
            'OBRAS Y MANTENIMIENTO',
        ],
        'BILL DROP' => [
            'JR DE RECOLEC Y CONTEO', 'SSR DE RECOLECCION Y CONTEO', 'SR DE RECOLECCION Y CONTEO',
            'TESORERO', 'AUXILIAR DE TESORERIA', 'APRENDIZ DE OP. TESORERIA',
        ],
        'TEAM LEADER / ADMISION Y CONTROL' => [
            'JR DE ADMISION Y CONTROL', 'SSR DE ADMISION Y CONTROL', 'SR DE ADMISION Y CONTROL',
            'ADMISION Y CONTROL', 'ENCARGADO/A DE SEGURIDAD', 'JEFE DE TURNO SEGURIDAD',
        ],
        'CAC MUJER' => ['JR DE CAC', 'SSR DE CAC', 'SR DE CAC', 'APRENDIZ DE OP. CAC'],
        'CAC HOMBRE' => ['JR DE CAC', 'SSR DE CAC', 'SR DE CAC', 'APRENDIZ DE OP. CAC'],
        'VIP EVENTOS' => ['CONSERJE VIP', 'PROMOTORA VIP', 'RESP.DE EVENTOS', 'ANFITRION CEREMONIAL'],
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
        $reemplazar = (bool) $this->option('reemplazar');

        // Asegurar color PETROLEO si aparece en Excel
        $this->asegurarColor('PETROLEO');

        $mapaColor = Color::query()->get()
            ->mapWithKeys(fn (Color $c) => [mb_strtoupper(trim((string) $c->nombre)) => (int) $c->id])
            ->all();

        $prendasPorCodigo = Prenda_Sueldos::query()->get()->keyBy(fn ($p) => (int) $p->codigo);
        if ($prendasPorCodigo->isEmpty()) {
            $this->error('No hay prendas cargadas. Correr antes indumentaria:importar-prendas-excel');

            return self::FAILURE;
        }

        $mapaAgrupPorDesc = [];
        foreach (Agrupamiento_Sueldos::query()->get(['id', 'descripcion']) as $a) {
            $key = $this->norm($a->descripcion);
            $mapaAgrupPorDesc[$key][] = (int) $a->id;
        }

        $puestoAAgrupIds = [];
        $puestosSinMatch = [];
        foreach (self::MAPA_PUESTO_AGRUP as $puestoNorm => $descripciones) {
            $ids = [];
            foreach ($descripciones as $desc) {
                $key = $this->norm($desc);
                foreach ($mapaAgrupPorDesc[$key] ?? [] as $id) {
                    $ids[$id] = true;
                }
            }
            $puestoAAgrupIds[$puestoNorm] = array_keys($ids);
            if ($ids === []) {
                $puestosSinMatch[] = $puestoNorm;
            }
        }

        $this->info('Leyendo solapa "Uniforme por puesto"…');
        $wb = IOFactory::load($path);
        $sheet = null;
        foreach ($wb->getSheetNames() as $name) {
            if (trim($name) === 'Uniforme por puesto') {
                $sheet = $wb->getSheetByName($name);
                break;
            }
        }
        $sheet = $sheet ?: $wb->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);
        unset($rows[0]);

        $agr = null;
        $pue = null;
        $filasExcel = [];
        foreach ($rows as $row) {
            $a = trim((string) ($row[0] ?? ''));
            $p = trim((string) ($row[1] ?? ''));
            $pr = trim((string) ($row[2] ?? ''));
            $col = trim((string) ($row[3] ?? ''));
            $cant = $row[4] ?? null;
            if ($a !== '') {
                $agr = $a;
            }
            if ($p !== '') {
                $pue = $p;
            }
            if ($pr === '') {
                continue;
            }
            $filasExcel[] = [
                'agrupamiento_excel' => $agr,
                'puesto' => $pue,
                'prenda' => $pr,
                'color' => $col !== '' ? $col : null,
                'cant' => is_numeric($cant) ? (float) $cant : 1.0,
            ];
        }

        $altas = [];
        $sinPuesto = [];
        $sinPrenda = [];
        $sinColor = [];
        $sinAgrup = [];

        foreach ($filasExcel as $fila) {
            $puestoNorm = $this->normPuesto($fila['puesto'] ?? '');
            if ($puestoNorm === '' || ! isset($puestoAAgrupIds[$puestoNorm])) {
                $sinPuesto[$fila['puesto'] ?? '(vacío)'] = true;
                continue;
            }
            $agrupIds = $puestoAAgrupIds[$puestoNorm];
            if ($agrupIds === []) {
                $sinAgrup[$fila['puesto']] = true;
                continue;
            }

            $sexos = $this->sexosParaPuesto($puestoNorm);
            $colorId = $this->resolverColorId($fila['color'], $mapaColor, $sinColor);

            foreach ($sexos as $sexo) {
                $prendas = $this->resolverPrendas($fila['prenda'], $sexo, $prendasPorCodigo);
                if ($prendas === []) {
                    $sinPrenda[$fila['prenda']] = true;
                    continue;
                }
                foreach ($agrupIds as $agrupId) {
                    foreach ($prendas as $idx => $prenda) {
                        // Ambo cocina expandido: color por ítem
                        $colorFila = $colorId;
                        if ($this->norm($fila['prenda']) === 'AMBO COCINA') {
                            $colorFila = $idx === 0
                                ? ($mapaColor['BLANCO'] ?? null)
                                : ($mapaColor['NEGRO'] ?? null);
                        }
                        $clave = $agrupId.'|'.$sexo.'|'.$prenda->id.'|'.($colorFila ?? 'null');
                        $altas[$clave] = [
                            'agrupamiento_id' => $agrupId,
                            'sexo' => $sexo,
                            'prenda_id' => (int) $prenda->id,
                            'color_id' => $colorFila,
                            'limite_anual' => $fila['cant'],
                            'orden' => 0,
                            '_puesto' => $fila['puesto'],
                            '_prenda_excel' => $fila['prenda'],
                        ];
                    }
                }
            }
        }

        // Asignar orden por agrupamiento+sexo
        $ordenPorGrupo = [];
        foreach ($altas as &$alta) {
            $gk = $alta['agrupamiento_id'].'|'.$alta['sexo'];
            $ordenPorGrupo[$gk] = ($ordenPorGrupo[$gk] ?? 0) + 1;
            $alta['orden'] = $ordenPorGrupo[$gk];
        }
        unset($alta);

        $this->newLine();
        $this->info('Cobertura de mapeo:');
        foreach ($puestoAAgrupIds as $puesto => $ids) {
            $this->line(sprintf('  %-45s → %d agrupamiento(s)', $puesto, count($ids)));
        }
        if ($puestosSinMatch !== []) {
            $this->warn('Puestos del mapa sin match en ERP: '.implode(', ', $puestosSinMatch));
        }
        if ($sinPuesto !== []) {
            $this->warn('Puestos Excel sin mapa: '.implode(' | ', array_keys($sinPuesto)));
        }
        if ($sinPrenda !== []) {
            $this->warn('Prendas Excel sin mapa: '.implode(' | ', array_keys($sinPrenda)));
        }
        if ($sinColor !== []) {
            $this->warn('Colores Excel sin mapa (queda null): '.implode(' | ', array_keys($sinColor)));
        }

        $this->line('Filas Excel: '.count($filasExcel));
        $this->line('Registros dotación a grabar (únicos): '.count($altas));

        if ($dry) {
            $this->info('DRY-RUN — no se graba.');
            $muestra = array_slice(array_values($altas), 0, 8);
            foreach ($muestra as $a) {
                $this->line(sprintf(
                    '  agr=%d sexo=%s prenda=%d color=%s lim=%s | %s / %s',
                    $a['agrupamiento_id'],
                    $a['sexo'],
                    $a['prenda_id'],
                    $a['color_id'] ?? 'null',
                    $a['limite_anual'],
                    $a['_puesto'],
                    $a['_prenda_excel']
                ));
            }

            return self::SUCCESS;
        }

        if (! $forzar && ! $this->confirm('¿Importar '.count($altas).' filas de dotación?', false)) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($altas, $reemplazar) {
            if ($reemplazar) {
                $this->info('Vaciando dotación actual…');
                DB::table('prenda_agrupamiento_sueldos')->delete();
            }

            $insertados = 0;
            $omitidos = 0;
            foreach ($altas as $alta) {
                $existe = Prenda_Agrupamiento_Sueldos::query()
                    ->where('agrupamiento_id', $alta['agrupamiento_id'])
                    ->where('sexo', $alta['sexo'])
                    ->where('prenda_id', $alta['prenda_id'])
                    ->when($alta['color_id'] === null, fn ($q) => $q->whereNull('color_id'))
                    ->when($alta['color_id'] !== null, fn ($q) => $q->where('color_id', $alta['color_id']))
                    ->exists();
                if ($existe) {
                    $omitidos++;
                    continue;
                }
                Prenda_Agrupamiento_Sueldos::create([
                    'agrupamiento_id' => $alta['agrupamiento_id'],
                    'sexo' => $alta['sexo'],
                    'prenda_id' => $alta['prenda_id'],
                    'color_id' => $alta['color_id'],
                    'limite_anual' => $alta['limite_anual'],
                    'orden' => $alta['orden'],
                ]);
                $insertados++;
            }
            $this->info("Listo: insertados={$insertados} omitidos={$omitidos}");
        });

        return self::SUCCESS;
    }

    private function asegurarColor(string $nombre): void
    {
        $nombre = mb_strtoupper($nombre);
        if (Color::query()->whereRaw('UPPER(nombre) = ?', [$nombre])->exists()) {
            return;
        }
        $max = (int) Color::query()->max(DB::raw(SqlDialectSupport::castEntero('codigo')));
        Color::create(['codigo' => (string) ($max + 1), 'nombre' => $nombre]);
        $this->line("Color creado: {$nombre}");
    }

    private function norm(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        $s = strtr($s, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            'Ä' => 'A', 'Ü' => 'U',
        ]);
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return $s;
    }

    private function normPuesto(string $s): string
    {
        $s = $this->norm($s);
        // Alias de escritura del Excel
        $s = str_replace(['OYM', 'O Y M'], 'OYM', $s);

        return $s;
    }

    /**
     * @return list<string>
     */
    private function sexosParaPuesto(string $puestoNorm): array
    {
        if (str_contains($puestoNorm, 'MUJER')) {
            return ['F'];
        }
        if (str_contains($puestoNorm, 'HOMBRE')) {
            return ['M'];
        }

        return ['M', 'F'];
    }

    /**
     * @param  array<string, int>  $mapaColor
     * @param  array<string, bool>  $sinColor
     */
    private function resolverColorId(?string $colorExcel, array $mapaColor, array &$sinColor): ?int
    {
        if ($colorExcel === null || trim($colorExcel) === '') {
            return null;
        }
        $n = $this->norm($colorExcel);
        $aliases = [
            'NEGRA' => 'NEGRO',
            'NEGRO LOGO' => 'NEGRO',
            'CHANPAGNE' => 'CHAMPAGNE',
            'CHAMPAGNE' => 'CHAMPAGNE',
            'PETROLEO' => 'PETROLEO',
            'PETRÓLEO' => 'PETROLEO',
            'CHAQ BLANCO / PANTA NEGRO' => null, // compuesto → sin color único
            'AZUL' => 'AZUL',
        ];
        if (array_key_exists($n, $aliases)) {
            $n = $aliases[$n];
            if ($n === null) {
                return null;
            }
        }
        if (! isset($mapaColor[$n])) {
            $sinColor[$colorExcel] = true;

            return null;
        }

        return $mapaColor[$n];
    }

    /**
     * @return list<Prenda_Sueldos>
     */
    private function resolverPrendas(string $prendaExcel, string $sexo, $prendasPorCodigo): array
    {
        $n = $this->norm($prendaExcel);
        // Normalizar variantes ortográficas
        $n = str_replace(['  ', 'SWETER', 'FALDON', 'PANTALON', 'ZAPATOS', 'ZAPATILLAS'],
            [' ', 'SWEATER', 'FALDON', 'PANTALON', 'ZAPATO', 'ZAPATO'], $n);
        $n = preg_replace('/\s+/', ' ', $n) ?? $n;

        $codigo = null;
        $codigos = [];

        $map = [
            'AMBO COCINA' => 'AMBO_COCINA', // especial: 2 prendas
            'AMBO DE VESTIR' => 'AMBO_VESTIR',
            'BLUSA' => 3,
            'BOTAS LLUVIA' => 4,
            'BOTAS DE LLUVIA' => 4,
            'BUZO POLAR' => 31,
            'BUZO FRIZA' => 5,
            'CAMISA' => 'CAMISA',
            'CAMPERA ABRIGO' => 8,
            'CASCO C/ ARNES' => 9,
            'CHALECO' => 10,
            'CHAQUETA COCINERO' => 12,
            'CHOMBA' => 14,
            'CINTURON' => 15,
            'COFIA' => 16,
            'CORBATA' => 17,
            'DELANTAL COCINA' => 18,
            'FAJA LUMBAR' => 19,
            'FALDON' => 20,
            'GORRO COCINERO' => 21,
            'GUANTES ANTICORTE' => 22,
            'GUANTES MOTEADO' => 23,
            'GUANTES MOTEADOS' => 23,
            'PANTALON GRAFA' => 27,
            'PANTALON VESTIR' => 'PANTALON_VESTIR',
            'PANTALON COCINERO' => 28,
            'PANUELO' => 29,
            'PAÑUELO' => 29,
            'PILOTO LLUVIA' => 30,
            'PROTEC. AUDITIVO' => 32,
            'PROTEC. OCULAR' => 33,
            'PROTEC. RESPIRATORIA' => 34,
            'PROTEC. RESPIRATORIA N95' => 34,
            'REFLECTIVOS' => 11,
            'REMERA' => 'REMERA',
            'SACO VESTIR' => 'SACO',
            'SWEATER HILO' => 40,
            'VESTIDO' => 41,
            'ZAPATO SEGURIDAD (X TEMPORADA)' => 42,
            'ZAPATO SEGURIDAD PP' => 42,
            'ZAPATO VESTIR' => 'ZAPATO_VESTIR',
            'ZAPATO SEGURIDAD HOM. (PA)' => 43,
        ];

        // Reintentos con normalización extra
        $n2 = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $n);
        $n2 = str_replace('Ñ', 'N', $n2);

        $key = $map[$n] ?? $map[$n2] ?? null;
        // Heurísticas sueltas
        if ($key === null) {
            if (str_contains($n, 'BUZO') && str_contains($n, 'POLAR')) {
                $key = 31;
            } elseif (str_contains($n, 'BUZO')) {
                $key = 5;
            } elseif (str_contains($n, 'PANTALON') && str_contains($n, 'GRAFA')) {
                $key = 27;
            } elseif (str_contains($n, 'PANTALON') && str_contains($n, 'VESTIR')) {
                $key = 'PANTALON_VESTIR';
            } elseif (str_contains($n, 'PANTALON') && str_contains($n, 'COCIN')) {
                $key = 28;
            } elseif (str_contains($n, 'SACO')) {
                $key = 'SACO';
            } elseif (str_contains($n, 'ZAPATO') && str_contains($n, 'VESTIR')) {
                $key = 'ZAPATO_VESTIR';
            } elseif (str_contains($n, 'ZAPATO') && (str_contains($n, 'PA') || str_contains($n, 'ACERO'))) {
                $key = 43;
            } elseif (str_contains($n, 'ZAPATO') && str_contains($n, 'SEGUR')) {
                $key = 42;
            } elseif (str_contains($n, 'BOTAS')) {
                $key = 4;
            } elseif (str_contains($n, 'PROTEC') && str_contains($n, 'AUDIT')) {
                $key = 32;
            } elseif (str_contains($n, 'PROTEC') && str_contains($n, 'OCUL')) {
                $key = 33;
            } elseif (str_contains($n, 'PROTEC') && str_contains($n, 'RESP')) {
                $key = 34;
            } elseif (str_contains($n, 'GUANTES') && str_contains($n, 'MOTE')) {
                $key = 23;
            } elseif (str_contains($n, 'GUANTES') && str_contains($n, 'ANTIC')) {
                $key = 22;
            } elseif (str_contains($n, 'FALDON') || str_contains($n, 'FALDÓN')) {
                $key = 20;
            } elseif (str_contains($n, 'SWEATER') || str_contains($n, 'SWETER')) {
                $key = 40;
            } elseif (str_contains($n, 'PANUELO') || str_contains($n, 'PAÑUELO')) {
                $key = 29;
            }
        }

        if ($key === 'AMBO_COCINA') {
            $codigos = [13, 27]; // chaqueta cocina + pantalón grafa
        } elseif ($key === 'AMBO_VESTIR') {
            $codigos = [$sexo === 'F' ? 2 : 1];
        } elseif ($key === 'CAMISA') {
            $codigos = [$sexo === 'F' ? 7 : 6];
        } elseif ($key === 'PANTALON_VESTIR') {
            $codigos = [$sexo === 'F' ? 26 : 25];
        } elseif ($key === 'REMERA') {
            $codigos = [37]; // unisex
        } elseif ($key === 'SACO') {
            $codigos = [$sexo === 'F' ? 39 : 38];
        } elseif ($key === 'ZAPATO_VESTIR') {
            $codigos = [$sexo === 'F' ? 45 : 44];
        } elseif (is_int($key)) {
            $codigos = [$key];
        }

        $out = [];
        foreach ($codigos as $c) {
            if ($prendasPorCodigo->has($c)) {
                $out[] = $prendasPorCodigo->get($c);
            }
        }

        return $out;
    }
}
