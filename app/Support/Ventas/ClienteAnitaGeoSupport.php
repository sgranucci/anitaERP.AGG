<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Cliente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve localidad_id / provincia_id desde campos Anita (clim_*).
 *
 * INTERFORMING/FRASLE: el maestro localidad no tiene CP; clim_cod_localidad no viene en climae.
 * No exigir nombre+CP a la vez: match por código, luego nombre (+CP si el maestro lo tiene),
 * normalización (acentos / # de encoding) y prefijo para textos truncados en Informix.
 */
final class ClienteAnitaGeoSupport
{
    /** @var array<string, string> clave normalizada Anita → nombre normalizado ERP */
    private const ALIAS_PROVINCIA = [
        'BS AS' => 'BUENOS AIRES',
        'BSAS' => 'BUENOS AIRES',
        'PCIA BS AS' => 'BUENOS AIRES',
        'PROVINCIA DE BUENOS AIRES' => 'BUENOS AIRES',
        'CABA' => 'CAPITAL FEDERAL',
        'C A B A' => 'CAPITAL FEDERAL',
        'CAP FED' => 'CAPITAL FEDERAL',
        'CAP FEDERAL' => 'CAPITAL FEDERAL',
        'CIUDAD AUTONOMA DE BUENOS AIRES' => 'CAPITAL FEDERAL',
        'CIUDAD DE BUENOS AIRES' => 'CAPITAL FEDERAL',
        'ENTRE RIOS' => 'ENTRE RIOS',
        'RIO NEGRO' => 'RIO NEGRO',
        'TIERRA DEL FUEGO' => 'TIERRA DEL FUEGO',
        'SANTIAGO DEL ESTERO' => 'SANTIAGO DEL ESTERO',
    ];

    /** @var array<string, int>|null clave normalizada => provincia.id */
    private static ?array $provinciaPorNorm = null;

    /** @var Collection<int, Localidad>|null */
    private static ?Collection $localidades = null;

    public static function resetCache(): void
    {
        self::$provinciaPorNorm = null;
        self::$localidades = null;
    }

    /**
     * @param  object  $filaAnita  con clim_cod_localidad?, clim_localidad, clim_cod_postal, clim_cod_provincia?, clim_provincia
     * @return array{localidad_id: ?int, provincia_id: ?int}
     */
    public static function resolverDesdeFilaAnita(object $filaAnita): array
    {
        $codLocalidad = self::textoONull($filaAnita->clim_cod_localidad ?? null);
        $nombreLocalidad = self::textoONull($filaAnita->clim_localidad ?? null);
        $cp = self::textoONull($filaAnita->clim_cod_postal ?? null);
        $codProvincia = self::textoONull($filaAnita->clim_cod_provincia ?? null);
        $nombreProvincia = self::textoONull($filaAnita->clim_provincia ?? null);

        $provinciaId = self::resolverProvinciaId($codProvincia, $nombreProvincia);
        $localidadId = self::resolverLocalidadId($codLocalidad, $nombreLocalidad, $cp, $provinciaId);

        if ($localidadId !== null) {
            $provDesdeLoc = (int) (self::localidades()->firstWhere('id', $localidadId)?->provincia_id ?? 0);
            if ($provDesdeLoc > 0) {
                $provinciaId = $provDesdeLoc;
            }
        }

        return [
            'localidad_id' => $localidadId,
            'provincia_id' => $provinciaId,
        ];
    }

    public static function resolverProvinciaId(?string $codigo, ?string $nombre): ?int
    {
        $codigo = self::textoONull($codigo);
        if ($codigo !== null) {
            $porCodigo = Provincia::query()->where('codigo', $codigo)->value('id');
            if ($porCodigo) {
                return (int) $porCodigo;
            }
        }

        $nombre = self::textoONull($nombre);
        if ($nombre === null) {
            return null;
        }

        $porNombre = Provincia::query()->where('nombre', $nombre)->value('id');
        if ($porNombre) {
            return (int) $porNombre;
        }

        $norm = self::normalizar($nombre);
        if ($norm === '') {
            return null;
        }

        $alias = self::ALIAS_PROVINCIA[$norm] ?? null;
        if ($alias !== null) {
            $norm = $alias;
        }

        $mapa = self::mapaProvinciasNormalizadas();

        if (isset($mapa[$norm])) {
            return $mapa[$norm];
        }

        // Encoding Informix (Tucum##n → TUCUMN): lev ≤ 2 y único
        $mejorId = null;
        $mejorDist = 99;
        $segundoDist = 99;
        foreach ($mapa as $clave => $id) {
            if (abs(strlen($clave) - strlen($norm)) > 2) {
                continue;
            }
            $d = levenshtein($norm, $clave);
            if ($d < $mejorDist) {
                $segundoDist = $mejorDist;
                $mejorDist = $d;
                $mejorId = $id;
            } elseif ($d < $segundoDist) {
                $segundoDist = $d;
            }
        }
        if ($mejorId !== null && $mejorDist <= 2 && $mejorDist < $segundoDist) {
            return $mejorId;
        }

        return null;
    }

    public static function resolverLocalidadId(
        ?string $codigo,
        ?string $nombre,
        ?string $codigoPostal = null,
        ?int $provinciaId = null
    ): ?int {
        $codigo = self::textoONull($codigo);
        if ($codigo !== null) {
            $porCodigo = Localidad::query()->where('codigo', $codigo)->value('id');
            if ($porCodigo) {
                return (int) $porCodigo;
            }
        }

        $nombre = self::textoONull($nombre);
        if ($nombre === null) {
            return null;
        }

        $cp = self::textoONull($codigoPostal);
        $candidatos = self::localidades();

        // 1) Nombre exacto + CP (solo si el maestro tiene ese CP)
        if ($cp !== null) {
            $conCp = $candidatos->filter(function (Localidad $l) use ($nombre, $cp) {
                return (string) $l->nombre === $nombre
                    && self::textoONull($l->codigopostal) === $cp;
            });
            $id = self::elegirEntre($conCp, $provinciaId);
            if ($id !== null) {
                return $id;
            }
        }

        // 2) Nombre exacto (sin exigir CP): INTERFORMING maestro sin CP
        $exactos = $candidatos->filter(fn (Localidad $l) => (string) $l->nombre === $nombre);
        $id = self::elegirEntre($exactos, $provinciaId);
        if ($id !== null) {
            return $id;
        }

        // 3) Nombre case-insensitive
        $lower = mb_strtolower($nombre);
        $ci = $candidatos->filter(fn (Localidad $l) => mb_strtolower((string) $l->nombre) === $lower);
        $id = self::elegirEntre($ci, $provinciaId);
        if ($id !== null) {
            return $id;
        }

        // 4) Normalizado (acentos / # de encoding roto)
        $norm = self::normalizar($nombre);
        if ($norm === '') {
            return null;
        }

        $normMatch = $candidatos->filter(fn (Localidad $l) => self::normalizar((string) $l->nombre) === $norm);
        $id = self::elegirEntre($normMatch, $provinciaId);
        if ($id !== null) {
            return $id;
        }

        // 5) Prefijo exacto: Anita trunca (ej. "Lomas del Mirad" → "Lomas del Mirador")
        if (mb_strlen($norm) >= 8) {
            $prefijo = $candidatos->filter(function (Localidad $l) use ($norm, $provinciaId) {
                $n = self::normalizar((string) $l->nombre);
                if ($n === '' || ! str_starts_with($n, $norm)) {
                    return false;
                }
                if ($provinciaId && (int) $l->provincia_id !== $provinciaId) {
                    return false;
                }

                return true;
            });
            $id = self::elegirEntre($prefijo, $provinciaId, exigirUnico: true);
            if ($id !== null) {
                return $id;
            }
        }

        // 6) Prefijo con 1 error (Concepci##n del → Concepcion del Uruguay)
        if (strlen($norm) >= 8) {
            $fuzzy = $candidatos->filter(function (Localidad $l) use ($norm, $provinciaId) {
                if ($provinciaId && (int) $l->provincia_id !== $provinciaId) {
                    return false;
                }
                $n = self::normalizar((string) $l->nombre);
                if ($n === '' || strlen($n) < strlen($norm)) {
                    return false;
                }
                $head = substr($n, 0, strlen($norm));

                return levenshtein($norm, $head) <= 2;
            });
            $id = self::elegirEntre($fuzzy, $provinciaId, exigirUnico: true);
            if ($id !== null) {
                return $id;
            }
        }

        // 7) Alias CABA / Cap. Fed. como localidad
        if (self::esTextoCaba($norm)) {
            $caba = $candidatos->filter(fn (Localidad $l) => self::normalizar((string) $l->nombre) === 'CAPITAL FEDERAL');
            $id = self::elegirEntre($caba, $provinciaId);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    private static function esTextoCaba(string $norm): bool
    {
        if (in_array($norm, ['CABA', 'C A B A', 'CAP FED', 'CAP FEDERAL', 'CAPITAL FEDERAL', 'CIUDAD AUTONOMA DE BUENOS AIRES', 'CIUDAD DE BUENOS AIRES'], true)) {
            return true;
        }

        return str_contains($norm, 'FEDERAL') && str_contains($norm, 'CAP');
    }

    /**
     * Completa localidad_id / provincia_id vacíos desde Anita. No pisa valores ya cargados.
     *
     * @return array{
     *   en_anita:int,
     *   erp_sin_geo:int,
     *   completar_localidad:int,
     *   completar_provincia:int,
     *   sin_match:int,
     *   sin_cliente:int,
     *   actualizados:int,
     *   filas:list<array<string,mixed>>,
     *   ejemplos:list<array<string,mixed>>,
     *   errores:list<string>
     * }
     */
    public static function completarVaciosDesdeAnita(bool $ejecutar = false, ?string $codigoFiltro = null): array
    {
        self::resetCache();

        $ret = [
            'en_anita' => 0,
            'erp_sin_geo' => 0,
            'completar_localidad' => 0,
            'completar_provincia' => 0,
            'sin_match' => 0,
            'sin_cliente' => 0,
            'actualizados' => 0,
            'filas' => [],
            'ejemplos' => [],
            'errores' => [],
        ];

        $api = new ApiAnita();
        $campos = 'clim_cliente, clim_nombre, clim_localidad, clim_cod_postal, clim_provincia';
        if (config('app.empresa') === 'EL BIERZO') {
            $campos .= ', clim_cod_localidad, clim_cod_provincia';
        }

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'tabla' => 'climae',
            'sistema' => 'ventas',
            'campos' => $campos,
        ]));
        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException($parsed['error_lectura']);
        }

        // Índice clientes ERP por código (evita 1 query por fila Anita)
        $clientesPorCodigo = [];
        foreach (Cliente::query()->get(['id', 'codigo', 'nombre', 'localidad_id', 'provincia_id']) as $cli) {
            foreach (self::variantesCodigo((string) $cli->codigo) as $v) {
                $clientesPorCodigo[$v] = $cli;
            }
        }

        $provinciaPorLocalidad = [];
        foreach (self::localidades() as $loc) {
            $provinciaPorLocalidad[(int) $loc->id] = (int) ($loc->provincia_id ?? 0);
        }

        $filtroNorm = $codigoFiltro !== null && $codigoFiltro !== ''
            ? ltrim(trim($codigoFiltro), '0')
            : null;

        foreach ($parsed['filas'] as $row) {
            $ret['en_anita']++;
            $codigoAnita = trim((string) ($row->clim_cliente ?? ''));
            if ($codigoAnita === '') {
                continue;
            }

            if ($filtroNorm !== null) {
                $variantesFiltro = self::variantesCodigo($filtroNorm);
                if (! in_array($codigoAnita, $variantesFiltro, true)
                    && ! in_array(ltrim($codigoAnita, '0') ?: '0', $variantesFiltro, true)) {
                    continue;
                }
            }

            try {
                $cliente = null;
                foreach (self::variantesCodigo($codigoAnita) as $v) {
                    if (isset($clientesPorCodigo[$v])) {
                        $cliente = $clientesPorCodigo[$v];
                        break;
                    }
                }

                if ($cliente === null) {
                    $ret['sin_cliente']++;

                    continue;
                }

                $sinLoc = empty($cliente->localidad_id);
                $sinProv = empty($cliente->provincia_id);
                if (! $sinLoc && ! $sinProv) {
                    continue;
                }

                $ret['erp_sin_geo']++;

                $geo = self::resolverDesdeFilaAnita($row);
                $update = [];

                if ($sinLoc && $geo['localidad_id'] !== null) {
                    $update['localidad_id'] = $geo['localidad_id'];
                    $ret['completar_localidad']++;
                }

                if ($sinProv && $geo['provincia_id'] !== null) {
                    $update['provincia_id'] = $geo['provincia_id'];
                    $ret['completar_provincia']++;
                } elseif ($sinProv && isset($update['localidad_id'])) {
                    $provLoc = $provinciaPorLocalidad[(int) $update['localidad_id']] ?? 0;
                    if ($provLoc > 0) {
                        $update['provincia_id'] = $provLoc;
                        $ret['completar_provincia']++;
                    }
                }

                if (isset($update['localidad_id']) && ! empty($cliente->provincia_id)) {
                    $provLoc = $provinciaPorLocalidad[(int) $update['localidad_id']] ?? 0;
                    if ($provLoc > 0 && $provLoc !== (int) $cliente->provincia_id) {
                        $update['provincia_id'] = $provLoc;
                    }
                }

                if ($update === []) {
                    $ret['sin_match']++;
                    if (count($ret['ejemplos']) < 15) {
                        $ret['ejemplos'][] = [
                            'codigo' => $cliente->codigo,
                            'nombre' => $cliente->nombre,
                            'anita_loc' => self::textoONull($row->clim_localidad ?? null) ?? '',
                            'anita_cp' => self::textoONull($row->clim_cod_postal ?? null) ?? '',
                            'anita_prov' => self::textoONull($row->clim_provincia ?? null) ?? '',
                            'localidad_id' => null,
                            'provincia_id' => null,
                            'estado' => 'sin_match',
                        ];
                    }

                    continue;
                }

                $fila = [
                    'codigo' => $cliente->codigo,
                    'nombre' => $cliente->nombre,
                    'anita_loc' => self::textoONull($row->clim_localidad ?? null) ?? '',
                    'anita_cp' => self::textoONull($row->clim_cod_postal ?? null) ?? '',
                    'anita_prov' => self::textoONull($row->clim_provincia ?? null) ?? '',
                    'localidad_id' => $update['localidad_id'] ?? null,
                    'provincia_id' => $update['provincia_id'] ?? null,
                    'estado' => 'completar',
                ];
                if (count($ret['filas']) < 50) {
                    $ret['filas'][] = $fila;
                }
                if (count($ret['ejemplos']) < 15) {
                    $ret['ejemplos'][] = $fila;
                }

                if ($ejecutar) {
                    // Console: audit.console=false; update directo para no N+1 Eloquent
                    $update['updated_at'] = now();
                    \Illuminate\Support\Facades\DB::table('cliente')
                        ->where('id', $cliente->id)
                        ->update($update);
                    $cliente->localidad_id = $update['localidad_id'] ?? $cliente->localidad_id;
                    $cliente->provincia_id = $update['provincia_id'] ?? $cliente->provincia_id;
                    $ret['actualizados']++;
                }
            } catch (\Throwable $e) {
                $msg = "Cliente Anita clim_cliente={$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('ClienteAnitaGeoSupport: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    public static function normalizar(string $v): string
    {
        $v = strtoupper(trim($v));
        // Encoding roto en bridge Informix (Córdoba → C##rdoba)
        $v = str_replace(['#', '?', '¿', '¡'], '', $v);
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        if (is_string($trans) && $trans !== '') {
            $v = $trans;
        }
        $v = preg_replace('/[^A-Z0-9 ]+/', ' ', $v) ?? $v;

        return trim(preg_replace('/\s+/', ' ', $v) ?? $v);
    }

    /**
     * @return list<string>
     */
    private static function variantesCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }
        if (! ctype_digit($codigo)) {
            return [$codigo];
        }
        $norm = ltrim($codigo, '0');
        if ($norm === '') {
            $norm = '0';
        }

        return array_values(array_unique([$codigo, $norm, str_pad($norm, 6, '0', STR_PAD_LEFT)]));
    }

    private static function textoONull(mixed $valor): ?string
    {
        if ($valor === null || $valor === false) {
            return null;
        }
        $t = trim((string) $valor);
        if ($t === '' || $t === '0') {
            return null;
        }

        return $t;
    }

    /**
     * @param  Collection<int, Localidad>  $candidatos
     */
    private static function elegirEntre(Collection $candidatos, ?int $provinciaId, bool $exigirUnico = false): ?int
    {
        if ($candidatos->isEmpty()) {
            return null;
        }

        if ($provinciaId) {
            $enProv = $candidatos->filter(fn (Localidad $l) => (int) $l->provincia_id === $provinciaId);
            if ($enProv->count() === 1) {
                return (int) $enProv->first()->id;
            }
            if ($enProv->count() > 1) {
                // Varias con mismo nombre en la provincia (duplicados sync): tomar la de menor id
                return (int) $enProv->sortBy('id')->first()->id;
            }
        }

        if ($candidatos->count() === 1) {
            return (int) $candidatos->first()->id;
        }

        if ($exigirUnico) {
            return null;
        }

        // Varias provincias: sin provincia no adivinamos
        if ($provinciaId) {
            return null;
        }

        // Si todas apuntan a la misma provincia, cualquiera sirve (menor id)
        $provs = $candidatos->pluck('provincia_id')->unique()->filter()->values();
        if ($provs->count() === 1) {
            return (int) $candidatos->sortBy('id')->first()->id;
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private static function mapaProvinciasNormalizadas(): array
    {
        if (self::$provinciaPorNorm !== null) {
            return self::$provinciaPorNorm;
        }

        $mapa = [];
        foreach (Provincia::query()->get(['id', 'nombre']) as $p) {
            $n = self::normalizar((string) $p->nombre);
            if ($n !== '' && ! isset($mapa[$n])) {
                $mapa[$n] = (int) $p->id;
            }
        }
        self::$provinciaPorNorm = $mapa;

        return $mapa;
    }

    /**
     * @return Collection<int, Localidad>
     */
    private static function localidades(): Collection
    {
        if (self::$localidades !== null) {
            return self::$localidades;
        }

        self::$localidades = Localidad::query()
            ->get(['id', 'codigo', 'nombre', 'codigopostal', 'provincia_id']);

        return self::$localidades;
    }
}
