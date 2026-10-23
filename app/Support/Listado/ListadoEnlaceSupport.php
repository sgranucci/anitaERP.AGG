<?php

declare(strict_types=1);

namespace App\Support\Listado;

use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Route;

/**
 * Link azul de una celda del workbench hacia el ABM del registro relacionado.
 *
 * Una columna se enlaza de dos maneras:
 * - Declarada: `enlace` (tabla destino) y `enlace_ids` (atributo con uno o varios ids,
 *   en el mismo orden y separados igual que el texto). Sirve cuando el valor es un
 *   agregado, como el número de orden de pago.
 * - Automática: el `source` es `tabla.columna` y `tabla` tiene pantalla. El id se lee
 *   de `{tabla}_id` en la fila (la clave foránea que ya trae el comprobante).
 */
final class ListadoEnlaceSupport
{
    public const SEPARADOR = ' | ';

    /**
     * Tabla destino → ruta de edición y permisos que alcanzan para abrirla en consulta.
     *
     * @var array<string, array{route: string, permisos: list<string>}>
     */
    private const DESTINOS = [
        'pagoproveedor' => [
            'route' => 'editar_pagoproveedor',
            'permisos' => [
                'editar-pagoproveedor',
                'listar-pagoproveedor',
                'listar-comprobante-proveedor',
                'editar-comprobante-proveedor',
            ],
        ],
        'ordencompra' => [
            'route' => 'editar_ordencompra',
            'permisos' => ['editar-ordencompra', 'listar-ordencompra'],
        ],
        'proveedor' => [
            'route' => 'editar_proveedor',
            'permisos' => ['editar-proveedor', 'listar-proveedor'],
        ],
        'asiento' => [
            'route' => 'editar_asiento',
            'permisos' => ['editar-asiento', 'listar-asiento'],
        ],
        'comprobante_proveedor' => [
            'route' => 'editar_comprobante_proveedor',
            'permisos' => ['editar-comprobante-proveedor', 'listar-comprobante-proveedor', 'listar-ordencompra', 'editar-ordencompra'],
        ],
        'recepcion_proveedor' => [
            'route' => 'editar_recepcion_proveedor',
            'permisos' => ['editar-recepcion-proveedor', 'listar-recepcion-proveedor', 'listar-ordencompra', 'editar-ordencompra'],
        ],
    ];

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{texto: string, url: ?string}>|null
     */
    public static function partes(object $fila, array $meta, string $texto): ?array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        $destino = (string) ($meta['enlace'] ?? '');
        $ids = '';
        if ($destino !== '') {
            $attrIds = (string) ($meta['enlace_ids'] ?? '');
            $ids = $attrIds !== '' ? trim((string) ($fila->{$attrIds} ?? '')) : '';
        } else {
            $destino = self::tablaDelSource((string) ($meta['source'] ?? ''));
            if ($destino === '' || ! isset(self::DESTINOS[$destino])) {
                return null;
            }
            $id = (int) ($fila->{$destino.'_id'} ?? 0);
            if ($id <= 0) {
                return null;
            }
            $ids = (string) $id;
        }

        if (! isset(self::DESTINOS[$destino]) || ! self::puedeAbrir($destino)) {
            return null;
        }

        $etiquetas = self::partir($texto);
        $claves = $ids === '' ? [] : self::partir($ids);
        $partes = [];
        foreach ($etiquetas as $i => $etiqueta) {
            $id = (int) ($claves[$i] ?? 0);
            $partes[] = [
                'texto' => $etiqueta,
                'url' => $id > 0 ? self::url($destino, $id) : null,
            ];
        }

        foreach ($partes as $parte) {
            if ($parte['url'] !== null) {
                return $partes;
            }
        }

        return null;
    }

    public static function puedeAbrir(string $destino): bool
    {
        $def = self::DESTINOS[$destino] ?? null;
        if ($def === null || ! Route::has($def['route'])) {
            return false;
        }
        foreach ($def['permisos'] as $permiso) {
            if (can($permiso, false)) {
                return true;
            }
        }

        return false;
    }

    public static function url(string $destino, int $id): ?string
    {
        $def = self::DESTINOS[$destino] ?? null;
        if ($def === null || $id <= 0 || ! self::puedeAbrir($destino)) {
            return null;
        }

        return ModoConsultaUrlSupport::route($def['route'], ['id' => $id]);
    }

    private static function tablaDelSource(string $source): string
    {
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\./', $source, $coincidencias) !== 1) {
            return '';
        }

        return $coincidencias[1];
    }

    /**
     * @return list<string>
     */
    private static function partir(string $texto): array
    {
        $partes = array_map('trim', explode(self::SEPARADOR, $texto));

        return array_values(array_filter($partes, static fn (string $parte): bool => $parte !== ''));
    }
}
