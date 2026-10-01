<?php

namespace App\Support\Stock;

use App\Models\Stock\Mventa;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del listado de precios de Ferli (un artículo por fila, listas en columnas).
 */
class PrecioListaFerliFiltros
{
    /** @var list<string> */
    public const MARCAS = ['FERLI', 'FRAGOLA', 'TOMAHAWK', 'BOAONDA'];

    /** @var array<string, string> */
    public const CANALES = [
        '' => 'Todos',
        'FABRICA' => 'Fábrica',
        'LOCAL' => 'Local',
        'SIN' => 'Sin canal',
    ];

    /** @var array<string, string> */
    public const ESTADOS = [
        'ACTIVO' => 'Activos',
        'INACTIVO' => 'Inactivos',
        'TODOS' => 'Todos',
    ];

    /** @var array<string, string> */
    public const FACTURABLES = [
        '0' => 'Facturables',
        '1' => 'No facturables',
    ];

    /**
     * @return list<array{id: int, nombre: string}>
     */
    public static function marcas(): array
    {
        $filas = Mventa::query()->get(['id', 'codigo', 'nombre']);
        $porClave = [];
        foreach ($filas as $fila) {
            $porClave[self::clave((string) $fila->nombre)] = $fila;
            if (trim((string) $fila->codigo) !== '') {
                $porClave[self::clave((string) $fila->codigo)] = $fila;
            }
        }

        $out = [];
        foreach (self::MARCAS as $nombre) {
            $fila = $porClave[$nombre] ?? null;
            if ($fila === null) {
                continue;
            }
            $out[] = [
                'id' => (int) $fila->id,
                'nombre' => (string) $fila->nombre,
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   fecha_vigencia: string,
     *   mventa_ids: list<int>,
     *   canal: string,
     *   estado: string,
     *   facturable: string,
     *   listaprecio_ids: list<int>
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $marcas = self::marcas();
        $idsMarca = array_map(static fn (array $marca): int => $marca['id'], $marcas);

        $fecha = Carbon::today()->format('Y-m-d');
        if ($request->filled('fecha_vigencia')) {
            try {
                $fecha = Carbon::parse((string) $request->input('fecha_vigencia'))->format('Y-m-d');
            } catch (\Throwable) {
                $fecha = Carbon::today()->format('Y-m-d');
            }
        }

        $canal = strtoupper(trim((string) $request->input('canal', '')));
        if (! array_key_exists($canal, self::CANALES)) {
            $canal = '';
        }

        $estadoRaw = strtoupper(trim((string) $request->input('estado', '')));
        if (! $request->has('estado')) {
            $estadoRaw = 'ACTIVO';
        }
        if ($estadoRaw === 'TODOS' || $estadoRaw === '') {
            $estado = '';
        } elseif (in_array($estadoRaw, ['ACTIVO', 'INACTIVO'], true)) {
            $estado = $estadoRaw;
        } else {
            $estado = 'ACTIVO';
        }

        $facturable = (string) $request->input('facturable', '0');
        if (! array_key_exists($facturable, self::FACTURABLES)) {
            $facturable = '0';
        }

        if (! $request->has('mventa_id') && ! $request->boolean('consultar')) {
            $mventaIds = $idsMarca;
        } else {
            $mventaIds = self::idsEnteros($request->input('mventa_id'));
            $mventaIds = array_values(array_intersect($mventaIds, $idsMarca));
        }

        return [
            'fecha_vigencia' => $fecha,
            'mventa_ids' => $mventaIds,
            'canal' => $canal,
            'estado' => $estado,
            'facturable' => $facturable,
            'listaprecio_ids' => self::idsEnteros($request->input('listaprecio_id')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [
            'consultar' => 1,
            'fecha_vigencia' => (string) ($filtros['fecha_vigencia'] ?? Carbon::today()->format('Y-m-d')),
            'estado' => ((string) ($filtros['estado'] ?? '')) === '' ? 'TODOS' : (string) $filtros['estado'],
            'facturable' => (string) ($filtros['facturable'] ?? '0'),
        ];

        $canal = (string) ($filtros['canal'] ?? '');
        if ($canal !== '') {
            $params['canal'] = $canal;
        }

        $marcas = array_values(array_filter(array_map('intval', $filtros['mventa_ids'] ?? [])));
        if ($marcas !== []) {
            $params['mventa_id'] = $marcas;
        }

        $listas = array_values(array_filter(array_map('intval', $filtros['listaprecio_ids'] ?? [])));
        if ($listas !== []) {
            $params['listaprecio_id'] = $listas;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array{id: int, codigo: string, nombre: string, encabezado: string}>  $listas
     */
    public static function subtitulo(array $filtros, array $listas): string
    {
        $marcas = self::marcas();
        $nombres = [];
        foreach ($marcas as $marca) {
            if (in_array($marca['id'], $filtros['mventa_ids'] ?? [], true)) {
                $nombres[] = $marca['nombre'];
            }
        }

        $canal = self::CANALES[(string) ($filtros['canal'] ?? '')] ?? 'Todos';
        $estadoClave = ((string) ($filtros['estado'] ?? '')) === '' ? 'TODOS' : (string) $filtros['estado'];
        $estado = self::ESTADOS[$estadoClave] ?? 'Activos';
        $facturable = self::FACTURABLES[(string) ($filtros['facturable'] ?? '0')] ?? 'Facturables';

        $listasTxt = [];
        foreach ($listas as $lista) {
            $codigo = trim((string) ($lista['codigo'] ?? ''));
            $nombre = trim((string) ($lista['nombre'] ?? ''));
            $listasTxt[] = trim($codigo.($nombre !== '' ? ' '.$nombre : ''));
        }

        $fecha = Carbon::parse((string) ($filtros['fecha_vigencia'] ?? Carbon::today()->format('Y-m-d')))->format('d/m/Y');

        return 'Vigente al '.$fecha
            .' · Marcas: '.($nombres === [] ? 'ninguna' : implode(', ', $nombres))
            .' · Canal: '.$canal
            .' · Estado: '.$estado
            .' · '.$facturable
            .' · Listas: '.($listasTxt === [] ? 'ninguna' : implode(', ', $listasTxt));
    }

    public static function validar(array $filtros): void
    {
        if (($filtros['mventa_ids'] ?? []) === []) {
            throw new \InvalidArgumentException('Elegí al menos una marca.');
        }
        if (($filtros['listaprecio_ids'] ?? []) === []) {
            throw new \InvalidArgumentException('Agregá al menos una lista. Por ejemplo la 11, o la 11, 12 y 13.');
        }
        if (count($filtros['listaprecio_ids']) > 12) {
            throw new \InvalidArgumentException('Se pueden incluir hasta 12 listas.');
        }
    }

    /**
     * @return list<int>
     */
    private static function idsEnteros(mixed $valor): array
    {
        if ($valor === null || $valor === '') {
            return [];
        }
        $items = is_array($valor) ? $valor : [$valor];
        $ids = [];
        foreach ($items as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function clave(string $texto): string
    {
        $texto = strtoupper(trim($texto));

        return str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü'],
            ['A', 'E', 'I', 'O', 'U', 'U'],
            $texto
        );
    }
}
