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
            // Detectar Buenos Aires y CABA por su nombre.
            if ($norm === 'BUENOS AIRES') {
                $this->idBuenosAires = $id;
            }
            if (str_starts_with($norm, 'CIUDAD AUT') || $norm === 'CABA' || $normAb === 'CABA') {
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
     * Resuelve localidad desde texto libre, preferentemente dentro de la provincia dada.
     * `forzar_provincia` = true cuando el propio texto de localidad determina la provincia
     * de forma inequívoca (ej. "Capital Federal" ⇒ CABA), aun si la provincia cargada difiere.
     *
     * @return array{localidad_id:?int, provincia_id:?int, cp:?string, forzar_provincia:bool}
     */
    public function matchLocalidad(?string $texto, ?int $provinciaId): array
    {
        $vacio = ['localidad_id' => null, 'provincia_id' => $provinciaId, 'cp' => null, 'forzar_provincia' => false];
        $base = self::norm($texto);
        if ($base === '') {
            return $vacio;
        }

        // Señal fuerte de CABA por el texto de localidad (corrige provincia si viene floja).
        if ($this->idCaba && $this->esTextoCaba($base)) {
            $lista = $this->locListaPorProv[$this->idCaba] ?? [];
            if ($lista !== []) {
                return ['localidad_id' => $lista[0]['id'], 'provincia_id' => $this->idCaba, 'cp' => $lista[0]['cp'], 'forzar_provincia' => true];
            }
        }

        $variantes = $this->variantesLocalidad($base);

        // Con provincia conocida: buscar dentro del scope.
        if ($provinciaId && isset($this->locPorProv[$provinciaId])) {
            foreach ($variantes as $v) {
                if (isset($this->locPorProv[$provinciaId][$v])) {
                    $loc = $this->locPorProv[$provinciaId][$v];

                    return ['localidad_id' => $loc['id'], 'provincia_id' => $provinciaId, 'cp' => $loc['cp'], 'forzar_provincia' => false];
                }
            }
            $lista = $this->locListaPorProv[$provinciaId] ?? [];
            $dif = $this->mejorDifusoLocalidad($variantes[0], $lista);
            if ($dif !== null) {
                return ['localidad_id' => $dif['id'], 'provincia_id' => $provinciaId, 'cp' => $dif['cp'], 'forzar_provincia' => false];
            }

            // Provincia con una sola localidad (ej. CABA): usarla directamente.
            if (count($lista) === 1) {
                return ['localidad_id' => $lista[0]['id'], 'provincia_id' => $provinciaId, 'cp' => $lista[0]['cp'], 'forzar_provincia' => false];
            }

            return $vacio;
        }

        // Sin provincia: sólo aceptar coincidencia global ÚNICA (evita vínculos ambiguos).
        foreach ($variantes as $v) {
            if (isset($this->locGlobal[$v]) && count($this->locGlobal[$v]) === 1) {
                $loc = $this->locGlobal[$v][0];

                return ['localidad_id' => $loc['id'], 'provincia_id' => $loc['prov'], 'cp' => $loc['cp'], 'forzar_provincia' => false];
            }
        }

        return $vacio;
    }

    /**
     * Texto de localidad que identifica inequívocamente a la Ciudad Autónoma (Capital Federal).
     */
    private function esTextoCaba(string $base): bool
    {
        $tokens = explode(' ', $base);
        if (in_array('FEDERAL', $tokens, true)) {
            return true; // CAPITAL FEDERAL, CAP FEDERAL
        }
        if (in_array('CAP', $tokens, true) && in_array('FED', $tokens, true)) {
            return true; // CAP FED, CAP.FED.
        }

        return in_array($base, ['CABA', 'C A B A', 'CIUDAD AUTONOMA DE BUENOS AIRES', 'CIUDAD DE BUENOS AIRES', 'CIUDAD AUTONOMA BUENOS AIRES'], true);
    }

    /**
     * @return list<string> Variantes normalizadas a probar (exacta, expandida, sin dirección).
     */
    private function variantesLocalidad(string $base): array
    {
        $variantes = [$base];

        $tokens = explode(' ', $base);
        $expandido = implode(' ', array_map(fn ($t) => self::ABREVIATURAS[$t] ?? $t, $tokens));
        if ($expandido !== $base) {
            $variantes[] = $expandido;
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
