<?php

namespace App\Support\Compras;

use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Localidad y provincia de proveedor desde promae.
 *
 * prom_cod_localidad es loc_localidad de Anita. En Ferli ese valor quedó en
 * localidad.id; localidad.codigo tiene otra numeración y choca con códigos
 * chicos (el 2 es «ALTO DE LAS PLUMAS» y no «DE LAS CATALINAS»).
 * Un where nombre = texto or codigo = código toma el primero y pisa la provincia.
 *
 * La provincia de la pantalla Anita es prom_provincia (texto). No se reemplaza
 * por la de la localidad cuando el texto resuelve a otra (CABA en el maestro,
 * «BUENOS AIRES» en el proveedor).
 */
final class ProveedorAnitaGeoSupport
{
    /** @var array<string, string> */
    private const ALIAS_PROVINCIA = [
        'BS AS' => 'BUENOS AIRES',
        'BSAS' => 'BUENOS AIRES',
        'BUE' => 'BUENOS AIRES',
        'PCIA BS AS' => 'BUENOS AIRES',
        'PROVINCIA DE BUENOS AIRES' => 'BUENOS AIRES',
        'CABA' => 'CAPITAL FEDERAL',
        'CAP FED' => 'CAPITAL FEDERAL',
        'CAPFED' => 'CAPITAL FEDERAL',
        'CAP FEDERAL' => 'CAPITAL FEDERAL',
        'CIUDAD AUTONOMA DE BUENOS AIRES' => 'CAPITAL FEDERAL',
        'CIUDAD DE BUENOS AIRES' => 'CAPITAL FEDERAL',
    ];

    /** @var list<array{id:int,codigo:string,nombre:string,provincia_id:int}>|null */
    private static ?array $localidades = null;

    /** @var list<array{id:int,nombre:string}>|null */
    private static ?array $provincias = null;

    public static function resetCache(): void
    {
        self::$localidades = null;
        self::$provincias = null;
    }

    /**
     * Código que Anita guarda en prom_cod_localidad.
     * En Ferli es localidad.id (loc_localidad). localidad.codigo es otra numeración.
     */
    public static function codigoParaAnita(int|string|null $localidadId, mixed $codigo): int
    {
        if (EntornoEmpresaSupport::esFerli()) {
            $id = (int) $localidadId;

            return $id > 0 ? $id : 0;
        }

        return ProveedorExclusionAnitaSupport::codigoEnteroAnita($codigo);
    }

    /**
     * @return array{localidad_id: ?int, provincia_id: ?int}
     */
    public static function resolverDesdeFilaAnita(object $fila): array
    {
        return self::resolver(
            self::localidades(),
            self::provincias(),
            self::texto($fila->prom_cod_localidad ?? null),
            self::texto($fila->prom_localidad ?? null),
            self::texto($fila->prom_provincia ?? null),
            EntornoEmpresaSupport::esFerli(),
        );
    }

    /**
     * @param  list<array{id:int,codigo:string,nombre:string,provincia_id:int}>  $localidades
     * @param  list<array{id:int,nombre:string}>  $provincias
     * @return array{localidad_id: ?int, provincia_id: ?int}
     */
    public static function resolver(
        array $localidades,
        array $provincias,
        ?string $codigoAnita,
        ?string $nombreLocalidad,
        ?string $nombreProvincia,
        bool $preferirIdComoCodigoAnita = false,
    ): array {
        $codigoAnita = self::texto($codigoAnita);
        $nombreLocalidad = self::texto($nombreLocalidad);
        $nombreProvincia = self::texto($nombreProvincia);

        $provinciaId = self::resolverProvinciaId($provincias, $nombreProvincia);
        $localidadId = self::resolverLocalidadId(
            $localidades,
            $codigoAnita,
            $nombreLocalidad,
            $provinciaId,
            $preferirIdComoCodigoAnita,
        );

        if ($provinciaId === null && $localidadId !== null) {
            foreach ($localidades as $loc) {
                if ((int) $loc['id'] === $localidadId && (int) $loc['provincia_id'] > 0) {
                    $provinciaId = (int) $loc['provincia_id'];
                    break;
                }
            }
        }

        return [
            'localidad_id' => $localidadId,
            'provincia_id' => $provinciaId,
        ];
    }

    /**
     * @param  list<array{id:int,nombre:string}>  $provincias
     */
    public static function resolverProvinciaId(array $provincias, ?string $nombre): ?int
    {
        $norm = self::normalizar($nombre);
        if ($norm === '') {
            return null;
        }

        $norm = self::ALIAS_PROVINCIA[$norm] ?? $norm;
        $mapa = [];
        foreach ($provincias as $provincia) {
            $clave = self::normalizar($provincia['nombre'] ?? '');
            if ($clave !== '') {
                $mapa[$clave] = (int) $provincia['id'];
            }
        }

        if (isset($mapa[$norm])) {
            return $mapa[$norm];
        }

        $mejorId = null;
        $mejorDist = 99;
        $segundoDist = 99;
        foreach ($mapa as $clave => $id) {
            if (abs(strlen($clave) - strlen($norm)) > 2) {
                continue;
            }
            $distancia = levenshtein($norm, $clave);
            if ($distancia < $mejorDist) {
                $segundoDist = $mejorDist;
                $mejorDist = $distancia;
                $mejorId = $id;
            } elseif ($distancia < $segundoDist) {
                $segundoDist = $distancia;
            }
        }

        if ($mejorId !== null && $mejorDist <= 2 && $mejorDist < $segundoDist) {
            return $mejorId;
        }

        return null;
    }

    /**
     * @param  list<array{id:int,codigo:string,nombre:string,provincia_id:int}>  $localidades
     */
    public static function resolverLocalidadId(
        array $localidades,
        ?string $codigoAnita,
        ?string $nombreLocalidad,
        ?int $provinciaId,
        bool $preferirIdComoCodigoAnita = false,
    ): ?int {
        $codigoAnita = self::texto($codigoAnita);
        $porClave = self::filasPorClaveAnita($localidades, $codigoAnita);
        $compatibles = array_values(array_filter(
            $porClave,
            fn (array $loc) => self::nombresCompatibles($nombreLocalidad, (string) $loc['nombre'])
        ));

        $elegida = self::elegirMejor($compatibles, $nombreLocalidad, $codigoAnita, $provinciaId, $preferirIdComoCodigoAnita);
        if ($elegida !== null) {
            return $elegida;
        }

        if ($nombreLocalidad === null) {
            return null;
        }

        $norm = self::normalizar($nombreLocalidad);
        if ($norm === '') {
            return null;
        }

        $exactas = array_values(array_filter(
            $localidades,
            fn (array $loc) => self::normalizar((string) $loc['nombre']) === $norm
        ));
        $elegida = self::elegirMejor($exactas, $nombreLocalidad, $codigoAnita, $provinciaId, $preferirIdComoCodigoAnita);
        if ($elegida !== null) {
            return $elegida;
        }

        if (mb_strlen($norm) < 8) {
            return null;
        }

        $prefijos = array_values(array_filter($localidades, function (array $loc) use ($norm, $provinciaId) {
            $nombre = self::normalizar((string) $loc['nombre']);
            if ($nombre === '' || ! str_starts_with($nombre, $norm)) {
                return false;
            }
            // Solo truncado de Informix (una o dos letras). No «BUENOS AIRES» → «BUENOS AIRES CHICO».
            if (strlen($nombre) - strlen($norm) > 4) {
                return false;
            }
            if ($provinciaId !== null && (int) $loc['provincia_id'] !== $provinciaId) {
                return false;
            }

            return true;
        }));

        return self::elegirMejor($prefijos, $nombreLocalidad, $codigoAnita, $provinciaId, $preferirIdComoCodigoAnita, true);
    }

    /**
     * @param  list<array{id:int,codigo:string,nombre:string,provincia_id:int}>  $localidades
     * @return list<array{id:int,codigo:string,nombre:string,provincia_id:int}>
     */
    private static function filasPorClaveAnita(array $localidades, ?string $codigoAnita): array
    {
        if ($codigoAnita === null || $codigoAnita === '0') {
            return [];
        }

        $filas = [];
        $vistos = [];
        foreach ($localidades as $loc) {
            $id = (int) $loc['id'];
            $codigo = trim((string) $loc['codigo']);
            $coincide = $codigo === $codigoAnita
                || (ctype_digit($codigoAnita) && $id === (int) $codigoAnita);
            if (! $coincide || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $filas[] = $loc;
        }

        return $filas;
    }

    /**
     * @param  list<array{id:int,codigo:string,nombre:string,provincia_id:int}>  $filas
     */
    private static function elegirMejor(
        array $filas,
        ?string $nombreLocalidad,
        ?string $codigoAnita,
        ?int $provinciaId,
        bool $preferirIdComoCodigoAnita,
        bool $exigirUnico = false,
    ): ?int {
        if ($filas === []) {
            return null;
        }

        if ($provinciaId !== null) {
            $enProvincia = array_values(array_filter(
                $filas,
                fn (array $loc) => (int) $loc['provincia_id'] === $provinciaId
            ));
            if ($enProvincia !== []) {
                $filas = $enProvincia;
            }
        }

        usort($filas, function (array $a, array $b) use ($nombreLocalidad, $codigoAnita, $preferirIdComoCodigoAnita) {
            $distA = self::distanciaNombre($nombreLocalidad, (string) $a['nombre']);
            $distB = self::distanciaNombre($nombreLocalidad, (string) $b['nombre']);
            if ($distA !== $distB) {
                return $distA <=> $distB;
            }

            $rankA = self::rankClave($a, $codigoAnita, $preferirIdComoCodigoAnita);
            $rankB = self::rankClave($b, $codigoAnita, $preferirIdComoCodigoAnita);

            return $rankA <=> $rankB;
        });

        if ($exigirUnico && count($filas) > 1) {
            $primera = $filas[0];
            $segunda = $filas[1];
            $iguales = self::distanciaNombre($nombreLocalidad, (string) $primera['nombre'])
                === self::distanciaNombre($nombreLocalidad, (string) $segunda['nombre'])
                && self::rankClave($primera, $codigoAnita, $preferirIdComoCodigoAnita)
                === self::rankClave($segunda, $codigoAnita, $preferirIdComoCodigoAnita);
            if ($iguales) {
                return null;
            }
        }

        return (int) $filas[0]['id'];
    }

    /**
     * @param  array{id:int,codigo:string,nombre:string,provincia_id:int}  $loc
     */
    private static function rankClave(array $loc, ?string $codigoAnita, bool $preferirId): int
    {
        if ($codigoAnita === null) {
            return 2;
        }

        $idCoincide = ctype_digit($codigoAnita) && (int) $loc['id'] === (int) $codigoAnita;
        $codigoCoincide = trim((string) $loc['codigo']) === $codigoAnita;
        if ($preferirId) {
            if ($idCoincide) {
                return 0;
            }
            if ($codigoCoincide) {
                return 1;
            }
        } else {
            if ($codigoCoincide) {
                return 0;
            }
            if ($idCoincide) {
                return 1;
            }
        }

        return 2;
    }

    private static function distanciaNombre(?string $textoAnita, string $nombreMaestro): int
    {
        $texto = self::normalizar($textoAnita);
        $maestro = self::normalizar($nombreMaestro);
        if ($texto === '' || $maestro === '') {
            return 999;
        }
        if ($texto === $maestro) {
            return 0;
        }

        return abs(strlen($maestro) - strlen($texto));
    }

    private static function nombresCompatibles(?string $textoAnita, string $nombreMaestro): bool
    {
        $texto = self::normalizar($textoAnita);
        $maestro = self::normalizar($nombreMaestro);
        if ($texto === '' || $maestro === '' || mb_strlen($texto) < 4) {
            return false;
        }

        return $texto === $maestro
            || str_starts_with($maestro, $texto)
            || str_starts_with($texto, $maestro);
    }

    private static function normalizar(?string $valor): string
    {
        $texto = mb_strtoupper(trim((string) $valor));
        $texto = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
            ['A', 'E', 'I', 'O', 'U', 'U', 'N'],
            $texto
        );
        $texto = preg_replace('/[^A-Z0-9 ]+/', ' ', $texto) ?? '';

        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    private static function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);
        if ($texto === '' || strcasecmp($texto, 'NULL') === 0) {
            return null;
        }

        return $texto;
    }

    /**
     * @return list<array{id:int,codigo:string,nombre:string,provincia_id:int}>
     */
    private static function localidades(): array
    {
        if (self::$localidades === null) {
            self::$localidades = Localidad::query()
                ->get(['id', 'codigo', 'nombre', 'provincia_id'])
                ->map(fn (Localidad $loc) => [
                    'id' => (int) $loc->id,
                    'codigo' => trim((string) $loc->codigo),
                    'nombre' => (string) $loc->nombre,
                    'provincia_id' => (int) ($loc->provincia_id ?? 0),
                ])
                ->all();
        }

        return self::$localidades;
    }

    /**
     * @return list<array{id:int,nombre:string}>
     */
    private static function provincias(): array
    {
        if (self::$provincias === null) {
            self::$provincias = Provincia::query()
                ->get(['id', 'nombre'])
                ->map(fn (Provincia $provincia) => [
                    'id' => (int) $provincia->id,
                    'nombre' => (string) $provincia->nombre,
                ])
                ->all();
        }

        return self::$provincias;
    }
}
