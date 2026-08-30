<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Entrega;
use Illuminate\Support\Facades\Storage;

/**
 * Completa provincia_id (solo vacía) y provincia_iibb_id (sede / zonamult).
 * No pisa domicilio existente. No usa zonamult para la calle.
 */
final class ClienteProvinciaIibbCompletarSupport
{
    /**
     * @return array{
     *   anita: int,
     *   erp: int,
     *   sin_anita: int,
     *   domicilio: array<string, int>,
     *   sede: array<string, int>,
     *   entregas: array<string, int>,
     *   diferencian: int,
     *   csv: string,
     *   ejemplos_domicilio: list<array<string, mixed>>,
     *   ejemplos_sede: list<array<string, mixed>>,
     *   ejemplos_diff: list<array<string, mixed>>,
     *   filas: list<array<string, mixed>>
     * }
     */
    public static function analizar(): array
    {
        $provincias = self::indiceProvincias();
        $anita = self::listarAnita();

        $clientes = Cliente::query()
            ->select(['id', 'codigo', 'nombre', 'estado', 'provincia_id', 'provincia_iibb_id', 'localidad_id'])
            ->get();

        $localidadProvincia = Localidad::query()
            ->whereIn('id', $clientes->pluck('localidad_id')->filter()->unique()->all())
            ->pluck('provincia_id', 'id');

        $porCodigo = [];
        foreach ($clientes as $cliente) {
            $porCodigo[self::normalizarCodigo((string) $cliente->codigo)] = $cliente;
        }

        $filas = [];
        $domicilio = [
            'candidatos' => 0,
            'por_codigo_anita' => 0,
            'por_texto_anita' => 0,
            'por_localidad' => 0,
            'sin_fuente' => 0,
            'ya_tenian' => 0,
            'activos' => 0,
        ];
        $sede = [
            'candidatos' => 0,
            'asignables' => 0,
            'zona_cero' => 0,
            'zona_sin_mapa' => 0,
            'ya_tenian' => 0,
            'activos' => 0,
        ];
        $ejDomicilio = [];
        $ejSede = [];
        $ejDiff = [];
        $diferencian = 0;
        $sinAnita = 0;

        foreach ($clientes as $cliente) {
            $codigo = self::normalizarCodigo((string) $cliente->codigo);
            $filaAnita = $anita[$codigo] ?? null;
            if ($filaAnita === null) {
                $sinAnita++;
            }

            $provinciaActual = (int) ($cliente->provincia_id ?? 0);
            $iibbActual = (int) ($cliente->provincia_iibb_id ?? 0);
            $localidadId = (int) ($cliente->localidad_id ?? 0);
            $provinciaLocalidad = $localidadId > 0 ? (int) ($localidadProvincia[$localidadId] ?? 0) : 0;

            $resDomicilio = self::resolverDomicilio($filaAnita, $provinciaLocalidad, $provincias);
            $resSede = self::resolverSede($filaAnita, $provincias);

            $propondriaDomicilio = $provinciaActual <= 0 && $resDomicilio['id'] !== null;
            $propondriaSede = $iibbActual <= 0 && $resSede['id'] !== null;

            if ($provinciaActual > 0) {
                $domicilio['ya_tenian']++;
            } elseif ($propondriaDomicilio) {
                $domicilio['candidatos']++;
                $domicilio[$resDomicilio['fuente']]++;
                if ((string) $cliente->estado === '0') {
                    $domicilio['activos']++;
                }
                if (count($ejDomicilio) < 15) {
                    $ejDomicilio[] = self::ejemplo($cliente, $filaAnita, $resDomicilio, $resSede, $provinciaActual, $iibbActual);
                }
            } else {
                $domicilio['sin_fuente']++;
            }

            if ($iibbActual > 0) {
                $sede['ya_tenian']++;
            } elseif ($propondriaSede) {
                $sede['candidatos']++;
                $sede['asignables']++;
                if ((string) $cliente->estado === '0') {
                    $sede['activos']++;
                }
                if (count($ejSede) < 15) {
                    $ejSede[] = self::ejemplo($cliente, $filaAnita, $resDomicilio, $resSede, $provinciaActual, $iibbActual);
                }
            } elseif ((int) ($filaAnita['zonamult'] ?? 0) <= 0) {
                $sede['zona_cero']++;
            } else {
                $sede['zona_sin_mapa']++;
            }

            $domicilioFinal = $provinciaActual > 0 ? $provinciaActual : ($resDomicilio['id'] ?? 0);
            $sedeFinal = $iibbActual > 0 ? $iibbActual : ($resSede['id'] ?? 0);
            $difieren = $domicilioFinal > 0 && $sedeFinal > 0 && $domicilioFinal !== $sedeFinal;
            if ($difieren) {
                $diferencian++;
                if (count($ejDiff) < 15) {
                    $ejDiff[] = self::ejemplo($cliente, $filaAnita, $resDomicilio, $resSede, $provinciaActual, $iibbActual);
                }
            }

            if ($propondriaDomicilio || $propondriaSede) {
                $filas[] = [
                    'cliente_id' => (int) $cliente->id,
                    'codigo' => (string) $cliente->codigo,
                    'nombre' => (string) $cliente->nombre,
                    'estado' => (string) $cliente->estado,
                    'provincia_id_actual' => $provinciaActual ?: null,
                    'provincia_id_nuevo' => $propondriaDomicilio ? $resDomicilio['id'] : null,
                    'fuente_domicilio' => $propondriaDomicilio ? $resDomicilio['fuente'] : '',
                    'provincia_iibb_id_actual' => $iibbActual ?: null,
                    'provincia_iibb_id_nuevo' => $propondriaSede ? $resSede['id'] : null,
                    'fuente_sede' => $propondriaSede ? $resSede['fuente'] : '',
                    'zonamult' => (int) ($filaAnita['zonamult'] ?? 0),
                    'difieren' => $difieren ? 1 : 0,
                ];
            }
        }

        $sedePorCliente = [];
        foreach ($clientes as $cliente) {
            $iibb = (int) ($cliente->provincia_iibb_id ?? 0);
            if ($iibb <= 0) {
                $codigo = self::normalizarCodigo((string) $cliente->codigo);
                $iibb = (int) (self::resolverSede($anita[$codigo] ?? null, $provincias)['id'] ?? 0);
            }
            $sedePorCliente[(int) $cliente->id] = $iibb;
        }

        $entregas = [
            'total' => 0,
            'candidatos' => 0,
            'sin_sede_cliente' => 0,
            'ya_tenian' => 0,
        ];
        Cliente_Entrega::query()
            ->select(['id', 'cliente_id', 'provincia_iibb_id'])
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$entregas, $sedePorCliente) {
                foreach ($chunk as $entrega) {
                    $entregas['total']++;
                    $actual = (int) ($entrega->provincia_iibb_id ?? 0);
                    if ($actual > 0) {
                        $entregas['ya_tenian']++;

                        continue;
                    }
                    $sedeCliente = (int) ($sedePorCliente[(int) $entrega->cliente_id] ?? 0);
                    if ($sedeCliente > 0) {
                        $entregas['candidatos']++;
                    } else {
                        $entregas['sin_sede_cliente']++;
                    }
                }
            });

        $csv = self::escribirCsv($filas);

        return [
            'anita' => count($anita),
            'erp' => $clientes->count(),
            'sin_anita' => $sinAnita,
            'domicilio' => $domicilio,
            'sede' => $sede,
            'entregas' => $entregas,
            'diferencian' => $diferencian,
            'csv' => $csv,
            'ejemplos_domicilio' => $ejDomicilio,
            'ejemplos_sede' => $ejSede,
            'ejemplos_diff' => $ejDiff,
            'filas' => $filas,
        ];
    }

    /**
     * Persiste solo null/0. No pisa provincia_id ni provincia_iibb_id ya cargados.
     *
     * @return array{domicilio: int, sede: int, entregas: int}
     */
    public static function persistir(array $analisis): array
    {
        $ok = ['domicilio' => 0, 'sede' => 0, 'entregas' => 0];

        foreach ($analisis['filas'] as $fila) {
            $cliente = Cliente::query()->find((int) $fila['cliente_id']);
            if (! $cliente) {
                continue;
            }

            $cambios = [];
            if ((int) ($cliente->provincia_id ?? 0) <= 0 && (int) ($fila['provincia_id_nuevo'] ?? 0) > 0) {
                $cambios['provincia_id'] = (int) $fila['provincia_id_nuevo'];
                $ok['domicilio']++;
            }
            if ((int) ($cliente->provincia_iibb_id ?? 0) <= 0 && (int) ($fila['provincia_iibb_id_nuevo'] ?? 0) > 0) {
                $cambios['provincia_iibb_id'] = (int) $fila['provincia_iibb_id_nuevo'];
                $ok['sede']++;
            }
            if ($cambios !== []) {
                $cliente->update($cambios);
            }
        }

        $clientes = Cliente::query()->select(['id', 'provincia_iibb_id'])->get();
        foreach ($clientes as $cliente) {
            $sede = (int) ($cliente->provincia_iibb_id ?? 0);
            if ($sede <= 0) {
                continue;
            }
            $ok['entregas'] += Cliente_Entrega::query()
                ->where('cliente_id', $cliente->id)
                ->whereRaw("TRIM(nombre) <> ''")
                ->where(function ($q) {
                    $q->whereNull('provincia_iibb_id')->orWhere('provincia_iibb_id', 0);
                })
                ->get()
                ->reduce(function (int $n, Cliente_Entrega $entrega) use ($sede) {
                    $entrega->update(['provincia_iibb_id' => $sede]);

                    return $n + 1;
                }, 0);
        }

        return $ok;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function listarAnita(): array
    {
        $api = new ApiAnita;
        $sistema = (string) config('cliente_anita.sistema', 'ventas');
        $intentos = [
            'clim_cliente,clim_nombre,clim_estado_cli,clim_zonamult,clim_cod_provincia,clim_provincia,clim_cod_localidad,clim_localidad',
            'clim_cliente,clim_nombre,clim_estado_cli,clim_zonamult,clim_provincia,clim_localidad',
        ];

        $filas = [];
        $error = null;
        foreach ($intentos as $campos) {
            $raw = (string) $api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => (string) config('cliente_anita.tabla', 'climae'),
                'campos' => $campos,
            ]);
            $parsed = ApiAnita::parsearRespuestaLista($raw);
            if ($parsed['error_lectura'] !== null) {
                $error = $parsed['error_lectura'];

                continue;
            }
            $filas = $parsed['filas'];
            if ($filas !== []) {
                break;
            }
        }

        if ($filas === [] && $error !== null) {
            throw new \RuntimeException('Anita no devolvió climae: '.$error);
        }

        $out = [];
        foreach ($filas as $fila) {
            $codigo = self::normalizarCodigo((string) ($fila->clim_cliente ?? ''));
            if ($codigo === '') {
                continue;
            }
            $out[$codigo] = [
                'codigo' => $codigo,
                'nombre' => trim((string) ($fila->clim_nombre ?? '')),
                'zonamult' => (int) ($fila->clim_zonamult ?? 0),
                'cod_provincia' => trim((string) ($fila->clim_cod_provincia ?? '')),
                'provincia' => trim((string) ($fila->clim_provincia ?? '')),
                'cod_localidad' => trim((string) ($fila->clim_cod_localidad ?? '')),
                'localidad' => trim((string) ($fila->clim_localidad ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   por_id: array<int, object>,
     *   por_codigo: array<int, int>,
     *   por_jurisdiccion: array<int, int>,
     *   por_nombre: array<string, int>
     * }
     */
    private static function indiceProvincias(): array
    {
        $porId = [];
        $porCodigo = [];
        $porJurisdiccion = [];
        $porNombre = [];

        foreach (Provincia::query()->get(['id', 'nombre', 'codigo', 'jurisdiccion']) as $provincia) {
            $id = (int) $provincia->id;
            $porId[$id] = $provincia;
            $codigo = (int) ($provincia->codigo ?? 0);
            if ($codigo > 0) {
                $porCodigo[$codigo] = $id;
            }
            $jur = (int) ($provincia->jurisdiccion ?? 0);
            if ($jur >= 900) {
                $porJurisdiccion[$jur] = $id;
            }
            $key = ClienteAnitaZonamultSupport::normalizarNombreProvincia((string) $provincia->nombre);
            if ($key !== '') {
                $porNombre[$key] = $id;
            }
        }

        return [
            'por_id' => $porId,
            'por_codigo' => $porCodigo,
            'por_jurisdiccion' => $porJurisdiccion,
            'por_nombre' => $porNombre,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $anita
     * @param  array<string, mixed>  $provincias
     * @return array{id: ?int, fuente: string, detalle: string}
     */
    private static function resolverDomicilio(?array $anita, int $provinciaLocalidad, array $provincias): array
    {
        if ($anita !== null) {
            $codigo = self::codigoEntero($anita['cod_provincia'] ?? '');
            if ($codigo > 0) {
                $id = $provincias['por_codigo'][$codigo]
                    ?? $provincias['por_jurisdiccion'][$codigo]
                    ?? $provincias['por_id'][$codigo]
                    ?? null;
                if ($id) {
                    return ['id' => $id, 'fuente' => 'por_codigo_anita', 'detalle' => (string) $codigo];
                }
            }

            $texto = trim((string) ($anita['provincia'] ?? ''));
            if ($texto !== '') {
                $key = ClienteAnitaZonamultSupport::normalizarNombreProvincia($texto);
                if ($key !== '' && isset($provincias['por_nombre'][$key])) {
                    return ['id' => $provincias['por_nombre'][$key], 'fuente' => 'por_texto_anita', 'detalle' => $texto];
                }
                $jur = ClienteAnitaZonamultSupport::jurisdiccionDesdeNombre($texto);
                if ($jur >= 900 && isset($provincias['por_jurisdiccion'][$jur])) {
                    return ['id' => $provincias['por_jurisdiccion'][$jur], 'fuente' => 'por_texto_anita', 'detalle' => $texto];
                }
            }
        }

        if ($provinciaLocalidad > 0) {
            return ['id' => $provinciaLocalidad, 'fuente' => 'por_localidad', 'detalle' => 'localidad ERP'];
        }

        return ['id' => null, 'fuente' => 'sin_fuente', 'detalle' => ''];
    }

    /**
     * @param  array<string, mixed>|null  $anita
     * @param  array<string, mixed>  $provincias
     * @return array{id: ?int, fuente: string, detalle: string}
     */
    private static function resolverSede(?array $anita, array $provincias): array
    {
        $zona = (int) ($anita['zonamult'] ?? 0);
        if ($zona <= 0) {
            return ['id' => null, 'fuente' => 'zona_cero', 'detalle' => '0'];
        }

        $id = ClienteAnitaZonamultSupport::provinciaIdDesdeCodigoZonamult($zona);
        if ($id) {
            return ['id' => $id, 'fuente' => 'zonamult', 'detalle' => (string) $zona];
        }

        return ['id' => null, 'fuente' => 'zona_sin_mapa', 'detalle' => (string) $zona];
    }

    /**
     * @param  array<string, mixed>|null  $anita
     * @param  array{id: ?int, fuente: string, detalle: string}  $dom
     * @param  array{id: ?int, fuente: string, detalle: string}  $sede
     * @return array<string, mixed>
     */
    private static function ejemplo(Cliente $cliente, ?array $anita, array $dom, array $sede, int $provinciaActual, int $iibbActual): array
    {
        return [
            'codigo' => (string) $cliente->codigo,
            'nombre' => (string) $cliente->nombre,
            'estado' => (string) $cliente->estado === '0' ? 'activo' : 'inactivo',
            'domicilio_erp' => $provinciaActual ?: '',
            'domicilio_nuevo' => $dom['id'] ?? '',
            'fuente_domicilio' => $dom['fuente'],
            'texto_anita' => $anita['provincia'] ?? '',
            'sede_nueva' => $sede['id'] ?? '',
            'zonamult' => $anita['zonamult'] ?? 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private static function escribirCsv(array $filas): string
    {
        $rel = 'clientes_completar_provincia_iibb_dryrun.csv';
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, [
            'cliente_id', 'codigo', 'nombre', 'estado',
            'provincia_id_actual', 'provincia_id_nuevo', 'fuente_domicilio',
            'provincia_iibb_id_actual', 'provincia_iibb_id_nuevo', 'fuente_sede',
            'zonamult', 'difieren',
        ]);
        foreach ($filas as $fila) {
            fputcsv($fh, [
                $fila['cliente_id'],
                $fila['codigo'],
                $fila['nombre'],
                $fila['estado'],
                $fila['provincia_id_actual'],
                $fila['provincia_id_nuevo'],
                $fila['fuente_domicilio'],
                $fila['provincia_iibb_id_actual'],
                $fila['provincia_iibb_id_nuevo'],
                $fila['fuente_sede'],
                $fila['zonamult'],
                $fila['difieren'],
            ]);
        }
        rewind($fh);
        Storage::disk('local')->put($rel, stream_get_contents($fh) ?: '');
        fclose($fh);

        return storage_path('app/'.$rel);
    }

    public static function normalizarCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        if (ctype_digit($codigo)) {
            $sin = ltrim($codigo, '0');

            return $sin !== '' ? $sin : '0';
        }

        return $codigo;
    }

    private static function codigoEntero(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '' || ! ctype_digit($valor)) {
            return 0;
        }

        return (int) ltrim($valor, '0') ?: 0;
    }
}
