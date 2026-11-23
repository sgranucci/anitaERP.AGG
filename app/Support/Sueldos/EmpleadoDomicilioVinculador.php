<?php

namespace App\Support\Sueldos;

use Illuminate\Support\Facades\DB;

/**
 * Vincula los textos libres de provincia/localidad de los empleados (importados de Anita)
 * con los maestros reales (provincia / localidad), tolerando abreviaturas y errores de tipeo.
 *
 * Estrategia conservadora: alias conocidos → match exacto normalizado → expansión de
 * abreviaturas → quitar sufijo de dirección (ESTE/OESTE/…) → match difuso acotado (Levenshtein).
 * Sólo devuelve un ID cuando la coincidencia es clara; si no, deja el texto tal cual.
 */
class EmpleadoDomicilioVinculador
{
    /** @var array<string,int> normNombre => provincia_id */
    private array $provPorNombre = [];

    /** @var array<string,int> normAbreviatura => provincia_id */
    private array $provPorAbrev = [];

    /** @var list<array{norm:string,id:int}> */
    private array $provLista = [];

    private ?int $idBuenosAires = null;

    private ?int $idCaba = null;

    /** @var array<int, array<string,array{id:int,cp:?string}>> provincia_id => [normNombre => loc] */
    private array $locPorProv = [];

    /** @var array<int, list<array{norm:string,id:int,cp:?string}>> provincia_id => lista */
    private array $locListaPorProv = [];

    /** @var array<string, list<array{id:int,prov:int,cp:?string}>> normNombre global => localidades */
    private array $locGlobal = [];

    /** @var array<string,string> abreviatura => palabra completa (localidades) */
    private const ABREVIATURAS = [
        'FCIO' => 'FLORENCIO',
        'FCO' => 'FRANCISCO',
        'F' => 'FLORENCIO',
        'V' => 'VILLA',
        'ALTE' => 'ALMIRANTE',
        'GRAL' => 'GENERAL',
        'GNRAL' => 'GENERAL',
        'CNEL' => 'CORONEL',
        'STA' => 'SANTA',
        'STO' => 'SANTO',
        'SN' => 'SAN',
        'PQUE' => 'PARQUE',
        'CAP' => 'CAPITAL',
        'PTE' => 'PRESIDENTE',
        'ING' => 'INGENIERO',
        'PCIA' => 'PROVINCIA',
        'AVDA' => 'AVENIDA',
        'PTO' => 'PUERTO',
        'PDO' => 'PARTIDO',
    ];

    /**
     * Frases completas Anita → nombre canónico del maestro (ya normalizado).
     *
     * @var array<string,string>
     */
    private const ALIAS_LOCALIDAD = [
        'G CATAN' => 'GONZALEZ CATAN',
        'G CATAN BO' => 'GONZALEZ CATAN',
        'GCATAN' => 'GONZALEZ CATAN',
        'I CASANOVA' => 'ISIDRO CASANOVA',
        'ICASANOVA' => 'ISIDRO CASANOVA',
        'CASANOVA' => 'ISIDRO CASANOVA',
        'R CASTILLO' => 'RAFAEL CASTILLO',
        'RCASTILLO' => 'RAFAEL CASTILLO',
        'ING BUDGE' => 'INGENIERO BUDGE',
        'L ZAMORA' => 'LOMAS DE ZAMORA',
        'LDE ZAMORA' => 'LOMAS DE ZAMORA',
        'L DE ZAMORA' => 'LOMAS DE ZAMORA',
        'V DEL PINO' => 'VIRREY DEL PINO',
        'VILLA TESEI HUR' => 'VILLA TESEI',
        'LA MATANZA' => 'LA MATANZA',
        'MATANZA' => 'LA MATANZA',
        'LDEL MIRADOR' => 'LOMAS DEL MIRADOR',
        'LOMAS MIRADOR' => 'LOMAS DEL MIRADOR',
        'L DEL MIRADOR' => 'LOMAS DEL MIRADOR',
        'LOMAS DEL MIRAD' => 'LOMAS DEL MIRADOR',
        'CIUDAD MADERO' => 'VILLA MADERO',
        'MADERO' => 'VILLA MADERO',
        'LIBERTAD MERLO' => 'LIBERTAD',
        'S A PADUA MERLO' => 'SAN ANTONIO DE PADUA',
        'S A PADUA' => 'SAN ANTONIO DE PADUA',
        'PADUA' => 'SAN ANTONIO DE PADUA',
        'PTO MADRYN' => 'PUERTO MADRYN',
        'S M TUCUMAN' => 'SAN MIGUEL DE TUCUMAN',
        'SM DE TUCUMAN' => 'SAN MIGUEL DE TUCUMAN',
        'SM TUCUMAN' => 'SAN MIGUEL DE TUCUMAN',
        'SMTUCUMAN' => 'SAN MIGUEL DE TUCUMAN',
        'DOK SUR AVELLAN' => 'DOCK SUD',
        'DOK SUR' => 'DOCK SUD',
        'CANNING EZEIZA' => 'CANNING',
        'WILDE AVELLANED' => 'WILDE',
        'LOMA HERMOSA' => 'VILLA LOMA HERMOSA',
        'PALOMAR' => 'EL PALOMAR',
        'VILLA INSUPERAB' => 'VILLA INSUPERABLE',
        'BANFIEL OESTE' => 'BANFIELD',
        'BANFIELD OESTE' => 'BANFIELD',
        'G MADARIAGA' => 'GENERAL MADARIAGA',
        'GREG DELA FERRE' => 'LAFERRERE',
        'GREGORIO DE LAFERRERE' => 'LAFERRERE',
        'LAFERRERE' => 'LAFERRERE',
        'RESISTENCIA CHA' => 'RESISTENCIA',
        'RIO CUARTO' => 'RIO CUARTO',
        'CABA LINIERS' => 'LA CAPITAL',
        'LUGANO' => 'VILLA LUGANO',
        'SAN JUSTO 1719' => 'SAN JUSTO',
    ];

    /**
     * Localidad (norm) → provincia_id a forzar cuando el texto del partido/ciudad contradice la provincia cargada.
     *
     * @var array<string,int>
     */
    private const FORZAR_PROVINCIA_LOCALIDAD = [
        'NEUQUEN' => 15,
        'CORDOBA' => 4,
        'RIO CUARTO' => 4,
        'RESISTENCIA' => 6,
        'BARRANQUERAS' => 6,
        'PUERTO MADRYN' => 7,
        'SAN MIGUEL DE TUCUMAN' => 24,
        'TRES ARROYOS' => 2,
        'GUERNICA' => 2,
        'CIUDAD EVITA' => 2,
        'MATADEROS' => 1,
        'BALVANERA' => 1,
        'CABALLITO' => 1,
        'FLORES' => 1,
        'NUEVA POMPEYA' => 1,
        'VILLA LUGANO' => 1,
        'LA CAPITAL' => 1,
    ];

    /**
     * Localidades a dar de alta si no existen (nombre + provincia).
     *
     * @var list<array{nombre:string,provincia_id:int,codigopostal:?string}>
     */
    private const LOCALIDADES_A_CREAR = [
        ['nombre' => 'INGENIERO BUDGE', 'provincia_id' => 2, 'codigopostal' => '1829'],
        ['nombre' => 'VILLA TESEI', 'provincia_id' => 2, 'codigopostal' => '1688'],
        ['nombre' => 'VILLA DIAMANTE', 'provincia_id' => 2, 'codigopostal' => '1822'],
        ['nombre' => 'LA TABLADA', 'provincia_id' => 2, 'codigopostal' => '1766'],
        ['nombre' => 'LA MATANZA', 'provincia_id' => 2, 'codigopostal' => null],
        ['nombre' => 'LIBERTAD', 'provincia_id' => 2, 'codigopostal' => '1716'],
        ['nombre' => 'MATADEROS', 'provincia_id' => 1, 'codigopostal' => '1440'],
        ['nombre' => 'BALVANERA', 'provincia_id' => 1, 'codigopostal' => '1032'],
        ['nombre' => 'NUEVA POMPEYA', 'provincia_id' => 1, 'codigopostal' => '1437'],
        ['nombre' => 'VILLA LUGANO', 'provincia_id' => 1, 'codigopostal' => '1439'],
        ['nombre' => 'FLORES', 'provincia_id' => 1, 'codigopostal' => '1406'],
    ];

    /** @var list<string> */
    private const DIRECCIONES = ['ESTE', 'OESTE', 'NORTE', 'SUR'];

    public function __construct()
    {
        $this->cargarProvincias();
        $this->cargarLocalidades();
    }

    private function cargarProvincias(): void
    {
        foreach (DB::table('provincia')->get(['id', 'nombre', 'abreviatura']) as $p) {
            $id = (int) $p->id;
            $norm = self::norm($p->nombre);
            if ($norm !== '') {
                $this->provPorNombre[$norm] ??= $id;
                $this->provLista[] = ['norm' => $norm, 'id' => $id];
            }
            $normAb = self::norm($p->abreviatura ?? '');
            if ($normAb !== '') {
                $this->provPorAbrev[$normAb] ??= $id;
            }
            // Detectar Buenos Aires y CABA por su nombre (en el maestro CABA figura como "Capital Federal").
            if ($norm === 'BUENOS AIRES') {
                $this->idBuenosAires = $id;
            }
            if (str_starts_with($norm, 'CIUDAD AUT') || $norm === 'CABA' || $norm === 'CAPITAL FEDERAL'
                || $normAb === 'CABA') {
                $this->idCaba = $id;
            }
        }
    }

    private function cargarLocalidades(): void
    {
        foreach (DB::table('localidad')->get(['id', 'nombre', 'codigopostal', 'provincia_id']) as $l) {
            $id = (int) $l->id;
            $prov = (int) $l->provincia_id;
            $norm = self::norm($l->nombre);
            if ($norm === '') {
                continue;
            }
            $cp = ($l->codigopostal ?? '') !== '' ? (string) $l->codigopostal : null;

            $this->locPorProv[$prov][$norm] ??= ['id' => $id, 'cp' => $cp];
            $this->locListaPorProv[$prov][] = ['norm' => $norm, 'id' => $id, 'cp' => $cp];
            $this->locGlobal[$norm][] = ['id' => $id, 'prov' => $prov, 'cp' => $cp];
        }
    }

    /**
     * Normaliza texto: mayúsculas, sin acentos, sin puntuación, espacios colapsados.
     */
    public static function norm(?string $s): string
    {
        $s = (string) $s;
        if ($s === '') {
            return '';
        }
        $s = mb_strtoupper($s, 'UTF-8');
        $s = strtr($s, [
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
            'Ñ' => 'N', 'Ç' => 'C',
        ]);
        $s = preg_replace('/[^A-Z0-9 ]+/u', ' ', $s) ?? '';
        $s = preg_replace('/\s+/', ' ', $s) ?? '';

        return trim($s);
    }

    /**
     * Resuelve provincia_id desde texto libre. Devuelve null si no hay coincidencia clara.
     */
    public function matchProvincia(?string $texto): ?int
    {
        $p = self::norm($texto);
        if ($p === '') {
            return null;
        }

        // 1) Exacto por nombre / abreviatura.
        if (isset($this->provPorNombre[$p])) {
            return $this->provPorNombre[$p];
        }
        if (isset($this->provPorAbrev[$p])) {
            return $this->provPorAbrev[$p];
        }

        // 2) Alias conocidos.
        $aliasBsAs = ['BS AS', 'BSAS', 'BS', 'BS A', 'B AIRES', 'PROV BUENOS AIRES', 'PROVINCIA DE BUENOS AIRES', 'PCIA BS AS', 'PCIA BUENOS AIRES', 'GBA'];
        if ($this->idBuenosAires && (in_array($p, $aliasBsAs, true) || $p === 'BUENOS AIRES')) {
            return $this->idBuenosAires;
        }
        if ($this->idCaba && (str_contains($p, 'CAPITAL FEDERAL') || str_contains($p, 'CAP FEDERAL')
            || in_array($p, ['CABA', 'C A B A', 'CIUDAD DE BUENOS AIRES', 'CIUDAD AUTONOMA DE BUENOS AIRES', 'CAPITAL'], true))) {
            return $this->idCaba;
        }

        // 3) Difuso (Levenshtein) sobre los 59 nombres de provincia.
        return $this->mejorDifusoProvincia($p);
    }

    private function mejorDifusoProvincia(string $p): ?int
    {
        $len = strlen($p);
        $mejor = null;
        $mejorDist = PHP_INT_MAX;
        $segundoDist = PHP_INT_MAX;
        foreach ($this->provLista as $prov) {
            if (abs(strlen($prov['norm']) - $len) > 3) {
                continue;
            }
            $d = levenshtein($p, $prov['norm']);
            if ($d < $mejorDist) {
                $segundoDist = $mejorDist;
                $mejorDist = $d;
                $mejor = $prov['id'];
            } elseif ($d < $segundoDist) {
                $segundoDist = $d;
            }
        }
        $umbral = $len >= 8 ? 2 : 1;
        if ($mejor !== null && $mejorDist <= $umbral && ($segundoDist - $mejorDist) >= 1) {
            return $mejor;
        }

        return null;
    }

    /**
     * Alta de localidades del GBA/CABA que Anita usa y no están en el maestro.
     * Idempotente: no duplica por nombre+provincia (normalizado).
     *
     * @return array{creadas:int, existentes:int, ids:list<int>}
     */
    public function asegurarLocalidadesFaltantes(bool $ejecutar = true): array
    {
        $stats = ['creadas' => 0, 'existentes' => 0, 'ids' => []];
        $maxCodigo = (int) DB::table('localidad')->max(DB::raw('CAST(codigo AS UNSIGNED)'));

        foreach (self::LOCALIDADES_A_CREAR as $def) {
            $norm = self::norm($def['nombre']);
            $prov = (int) $def['provincia_id'];
            if (isset($this->locPorProv[$prov][$norm])) {
                $stats['existentes']++;
                $stats['ids'][] = $this->locPorProv[$prov][$norm]['id'];

                continue;
            }

            if (! $ejecutar) {
                $stats['creadas']++;

                continue;
            }

            $maxCodigo++;
            $id = (int) DB::table('localidad')->insertGetId([
                'nombre' => $def['nombre'],
                'codigopostal' => $def['codigopostal'],
                'codigo' => (string) $maxCodigo,
                'provincia_id' => $prov,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $cp = $def['codigopostal'];
            $this->locPorProv[$prov][$norm] = ['id' => $id, 'cp' => $cp];
            $this->locListaPorProv[$prov][] = ['norm' => $norm, 'id' => $id, 'cp' => $cp];
            $this->locGlobal[$norm][] = ['id' => $id, 'prov' => $prov, 'cp' => $cp];
            $stats['creadas']++;
            $stats['ids'][] = $id;
        }

        return $stats;
    }

    /**
     * Resuelve localidad desde texto libre, preferentemente dentro de la provincia dada.
     * `forzar_provincia` = true cuando el propio texto de localidad determina la provincia
     * de forma inequívoca (ej. "Capital Federal" ⇒ CABA), aun si la provincia cargada difiere.
     *
     * @return array{localidad_id:?int, provincia_id:?int, cp:?string, forzar_provincia:bool}
     */
    public function matchLocalidad(?string $texto, ?int $provinciaId): array
    {
        $vacio = ['localidad_id' => null, 'provincia_id' => $provinciaId, 'cp' => null, 'forzar_provincia' => false];
        $base = $this->normalizarTextoLocalidad($texto);
        if ($base === '') {
            return $vacio;
        }

        // Señal fuerte de CABA por el texto de localidad (corrige provincia si viene floja).
        if ($this->idCaba && $this->esTextoCaba($base)) {
            $canon = $this->localidadCanonCaba();
            if ($canon !== null) {
                return [
                    'localidad_id' => $canon['id'],
                    'provincia_id' => $this->idCaba,
                    'cp' => $canon['cp'],
                    'forzar_provincia' => true,
                ];
            }

            return [
                'localidad_id' => null,
                'provincia_id' => $this->idCaba,
                'cp' => null,
                'forzar_provincia' => true,
            ];
        }

        $forzarProv = self::FORZAR_PROVINCIA_LOCALIDAD[$base] ?? null;
        if ($forzarProv) {
            $provinciaId = $forzarProv;
        }

        $variantes = $this->variantesLocalidad($base);

        // Con provincia conocida: buscar dentro del scope.
        if ($provinciaId && isset($this->locPorProv[$provinciaId])) {
            $hit = $this->buscarEnProvincia($variantes, $provinciaId);
            if ($hit !== null) {
                return [
                    'localidad_id' => $hit['id'],
                    'provincia_id' => $provinciaId,
                    'cp' => $hit['cp'],
                    'forzar_provincia' => $forzarProv !== null,
                ];
            }
        }

        // Global: coincidencia única, o preferir provincia forzada / Buenos Aires.
        foreach ($variantes as $v) {
            if (! isset($this->locGlobal[$v]) || $this->locGlobal[$v] === []) {
                continue;
            }
            $cands = $this->locGlobal[$v];
            if ($forzarProv) {
                $filtrados = array_values(array_filter($cands, fn ($c) => (int) $c['prov'] === $forzarProv));
                if (count($filtrados) === 1) {
                    $loc = $filtrados[0];

                    return [
                        'localidad_id' => $loc['id'],
                        'provincia_id' => $loc['prov'],
                        'cp' => $loc['cp'],
                        'forzar_provincia' => true,
                    ];
                }
            }
            if (count($cands) === 1) {
                $loc = $cands[0];

                return [
                    'localidad_id' => $loc['id'],
                    'provincia_id' => $loc['prov'],
                    'cp' => $loc['cp'],
                    'forzar_provincia' => $forzarProv !== null,
                ];
            }
            // Varias provincias: preferir la del empleado, luego BA / CABA.
            $preferidas = array_values(array_unique(array_filter([
                $provinciaId ? (int) $provinciaId : null,
                $this->idBuenosAires,
                $this->idCaba,
            ])));
            foreach ($preferidas as $prefProv) {
                $filtrados = array_values(array_filter($cands, fn ($c) => (int) $c['prov'] === (int) $prefProv));
                if (count($filtrados) >= 1) {
                    usort($filtrados, fn ($a, $b) => strlen((string) ($a['cp'] ?? '')) <=> strlen((string) ($b['cp'] ?? '')));
                    $loc = $filtrados[0];

                    return [
                        'localidad_id' => $loc['id'],
                        'provincia_id' => $loc['prov'],
                        'cp' => $loc['cp'],
                        'forzar_provincia' => $forzarProv !== null || ($provinciaId && (int) $loc['prov'] !== (int) $provinciaId),
                    ];
                }
            }
        }

        // Prefijo global único (ej. "CASEROS" → "CASEROS (PDO. …)" en una sola provincia).
        $pref = $this->buscarPrefijoGlobalUnico($variantes);
        if ($pref !== null) {
            return [
                'localidad_id' => $pref['id'],
                'provincia_id' => $pref['prov'],
                'cp' => $pref['cp'],
                'forzar_provincia' => $forzarProv !== null && (int) $forzarProv !== (int) ($vacio['provincia_id'] ?? 0),
            ];
        }

        if ($forzarProv) {
            return ['localidad_id' => null, 'provincia_id' => $forzarProv, 'cp' => null, 'forzar_provincia' => true];
        }

        return $vacio;
    }

    /**
     * @param  list<string>  $variantes
     * @return array{id:int,cp:?string}|null
     */
    private function buscarEnProvincia(array $variantes, int $provinciaId): ?array
    {
        foreach ($variantes as $v) {
            if (isset($this->locPorProv[$provinciaId][$v])) {
                $loc = $this->locPorProv[$provinciaId][$v];

                return ['id' => $loc['id'], 'cp' => $loc['cp']];
            }
        }

        $lista = $this->locListaPorProv[$provinciaId] ?? [];
        $pref = $this->mejorPrefijoLocalidad($variantes, $lista);
        if ($pref !== null) {
            return $pref;
        }

        $dif = $this->mejorDifusoLocalidad($variantes[0], $lista);
        if ($dif !== null) {
            return $dif;
        }

        if (count($lista) === 1) {
            return ['id' => $lista[0]['id'], 'cp' => $lista[0]['cp']];
        }

        return null;
    }

    /**
     * @param  list<string>  $variantes
     * @param  list<array{norm:string,id:int,cp:?string}>  $lista
     * @return array{id:int,cp:?string}|null
     */
    private function mejorPrefijoLocalidad(array $variantes, array $lista): ?array
    {
        foreach ($variantes as $v) {
            if (strlen($v) < 4) {
                continue;
            }
            $cands = [];
            foreach ($lista as $loc) {
                if ($loc['norm'] === $v
                    || str_starts_with($loc['norm'], $v.' ')
                    || str_starts_with($loc['norm'], $v.' (')) {
                    $cands[] = $loc;
                }
            }
            if (count($cands) === 1) {
                return ['id' => $cands[0]['id'], 'cp' => $cands[0]['cp']];
            }
            // Varios: preferir el de menor longitud (más genérico / cabecera).
            if (count($cands) > 1) {
                usort($cands, fn ($a, $b) => strlen($a['norm']) <=> strlen($b['norm']));

                return ['id' => $cands[0]['id'], 'cp' => $cands[0]['cp']];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $variantes
     * @return array{id:int,prov:int,cp:?string}|null
     */
    private function buscarPrefijoGlobalUnico(array $variantes): ?array
    {
        foreach ($variantes as $v) {
            if (strlen($v) < 5) {
                continue;
            }
            $cands = [];
            foreach ($this->locGlobal as $norm => $locs) {
                if ($norm === $v || str_starts_with($norm, $v.' ') || str_starts_with($norm, $v.' (')) {
                    foreach ($locs as $loc) {
                        $cands[] = $loc + ['norm' => $norm];
                    }
                }
            }
            if (count($cands) === 1) {
                return ['id' => $cands[0]['id'], 'prov' => $cands[0]['prov'], 'cp' => $cands[0]['cp']];
            }
            $provs = array_unique(array_map(fn ($c) => $c['prov'], $cands));
            if (count($cands) > 1 && count($provs) === 1) {
                usort($cands, fn ($a, $b) => strlen($a['norm']) <=> strlen($b['norm']));

                return ['id' => $cands[0]['id'], 'prov' => $cands[0]['prov'], 'cp' => $cands[0]['cp']];
            }
        }

        return null;
    }

    private function normalizarTextoLocalidad(?string $texto): string
    {
        $base = self::norm($texto);
        if ($base === '') {
            return '';
        }
        // Quita CP / números sueltos al final ("SAN JUSTO 1719", "G CATAN BO" se deja).
        $base = trim(preg_replace('/\b\d{3,5}\b$/', '', $base) ?? $base);
        $base = preg_replace('/\s+/', ' ', $base) ?? $base;

        return self::ALIAS_LOCALIDAD[$base] ?? $base;
    }

    /**
     * Localidad canónica dentro de CABA cuando Anita trae solo "CABA" / "CAPITAL" / "CAPITAL FEDERAL".
     * El maestro de CABA tiene miles de calles como "localidad"; no tomar la primera al azar.
     *
     * @return array{id:int,cp:?string}|null
     */
    private function localidadCanonCaba(): ?array
    {
        if (! $this->idCaba) {
            return null;
        }
        foreach (['LA CAPITAL', 'CAPITAL FEDERAL', 'CABA', 'CIUDAD AUTONOMA DE BUENOS AIRES', 'CIUDAD DE BUENOS AIRES'] as $pref) {
            $norm = self::norm($pref);
            if (isset($this->locPorProv[$this->idCaba][$norm])) {
                $loc = $this->locPorProv[$this->idCaba][$norm];

                return ['id' => $loc['id'], 'cp' => $loc['cp']];
            }
        }

        return null;
    }

    /**
     * Texto de localidad que identifica inequívocamente a la Ciudad Autónoma (Capital Federal).
     */
    private function esTextoCaba(string $base): bool
    {
        $tokens = explode(' ', $base);
        if (in_array('FEDERAL', $tokens, true)
            && (in_array('CAPITAL', $tokens, true) || in_array('CAP', $tokens, true))) {
            return true; // CAPITAL FEDERAL, CAP FEDERAL
        }
        if (in_array('CAP', $tokens, true) && in_array('FED', $tokens, true)) {
            return true; // CAP FED, CAP.FED.
        }

        return in_array($base, [
            'CABA', 'C A B A', 'CAPITAL', 'CAPITAL FEDERAL',
            'CIUDAD AUTONOMA DE BUENOS AIRES', 'CIUDAD DE BUENOS AIRES', 'CIUDAD AUTONOMA BUENOS AIRES',
        ], true);
    }

    /**
     * @return list<string> Variantes normalizadas a probar (exacta, alias, expandida, sin dirección).
     */
    private function variantesLocalidad(string $base): array
    {
        $variantes = [$base];
        if (isset(self::ALIAS_LOCALIDAD[$base])) {
            $variantes[] = self::ALIAS_LOCALIDAD[$base];
        }

        $tokens = explode(' ', $base);
        $expandido = implode(' ', array_map(fn ($t) => self::ABREVIATURAS[$t] ?? $t, $tokens));
        if ($expandido !== $base) {
            $variantes[] = $expandido;
            if (isset(self::ALIAS_LOCALIDAD[$expandido])) {
                $variantes[] = self::ALIAS_LOCALIDAD[$expandido];
            }
        }

        foreach ($variantes as $v) {
            $sinDir = trim(preg_replace('/\b('.implode('|', self::DIRECCIONES).')\b/', ' ', $v) ?? $v);
            $sinDir = preg_replace('/\s+/', ' ', $sinDir) ?? $sinDir;
            if ($sinDir !== '' && ! in_array($sinDir, $variantes, true)) {
                $variantes[] = $sinDir;
            }
        }

        return array_values(array_unique($variantes));
    }

    /**
     * @param  list<array{norm:string,id:int,cp:?string}>  $lista
     * @return array{id:int,cp:?string}|null
     */
    private function mejorDifusoLocalidad(string $p, array $lista): ?array
    {
        if ($p === '' || strlen($p) < 5) {
            return null;
        }
        $len = strlen($p);
        $mejor = null;
        $mejorDist = PHP_INT_MAX;
        $segundoDist = PHP_INT_MAX;
        foreach ($lista as $loc) {
            if (abs(strlen($loc['norm']) - $len) > 2) {
                continue;
            }
            $d = levenshtein($p, $loc['norm']);
            if ($d < $mejorDist) {
                $segundoDist = $mejorDist;
                $mejorDist = $d;
                $mejor = $loc;
            } elseif ($d < $segundoDist) {
                $segundoDist = $d;
            }
        }
        if ($mejor !== null && $mejorDist <= 1 && ($segundoDist - $mejorDist) >= 1) {
            return ['id' => $mejor['id'], 'cp' => $mejor['cp']];
        }

        return null;
    }
}
