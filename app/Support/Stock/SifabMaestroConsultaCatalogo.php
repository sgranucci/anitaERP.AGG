<?php

namespace App\Support\Stock;

use App\Models\Stock\Clasematerial;
use App\Models\Stock\Gestioncompra;
use App\Models\Stock\Grupoproducto;
use App\Models\Stock\Lineamaterial;
use App\Models\Stock\Rubro;
use App\Models\Stock\Subrubro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de maestros SIFAB usables en consulta modal (solo INTERFORMING).
 */
final class SifabMaestroConsultaCatalogo
{
    /**
     * @return array<string, array{
     *   model: class-string<Model>,
     *   label: string,
     *   titulo_modal: string,
     *   edit_route: string,
     *   permisos_abm: list<string>,
     *   input_name: string
     * }>
     */
    public static function todos(): array
    {
        return [
            'clasematerial' => [
                'model' => Clasematerial::class,
                'label' => 'Clase material',
                'titulo_modal' => 'Clases de material (SIFAB)',
                'edit_route' => 'editar_clasematerial',
                'permisos_abm' => ['listar-clases-material', 'editar-clases-material', 'crear-clases-material'],
                'input_name' => 'clasematerial',
            ],
            'lineamaterial' => [
                'model' => Lineamaterial::class,
                'label' => 'Línea material',
                'titulo_modal' => 'Líneas de material (SIFAB)',
                'edit_route' => 'editar_lineamaterial',
                'permisos_abm' => ['listar-lineas-material', 'editar-lineas-material', 'crear-lineas-material'],
                'input_name' => 'lineamaterial',
            ],
            'gestioncompra' => [
                'model' => Gestioncompra::class,
                'label' => 'Gestión compra',
                'titulo_modal' => 'Gestiones de compra (SIFAB)',
                'edit_route' => 'editar_gestioncompra',
                'permisos_abm' => ['listar-gestiones-compra', 'editar-gestiones-compra', 'crear-gestiones-compra'],
                'input_name' => 'gestioncompra',
            ],
            'rubro' => [
                'model' => Rubro::class,
                'label' => 'Rubro SIFAB',
                'titulo_modal' => 'Rubros compra (SIFAB)',
                'edit_route' => 'editar_rubro',
                'permisos_abm' => ['listar-rubros', 'editar-rubros', 'crear-rubros'],
                'input_name' => 'rubro_sifab',
            ],
            'subrubro' => [
                'model' => Subrubro::class,
                'label' => 'Subrubro',
                'titulo_modal' => 'Subrubros (SIFAB)',
                'edit_route' => 'editar_subrubro',
                'permisos_abm' => ['listar-subrubros', 'editar-subrubros', 'crear-subrubros'],
                'input_name' => 'subrubro',
            ],
            'grupoproducto' => [
                'model' => Grupoproducto::class,
                'label' => 'Grupo producto',
                'titulo_modal' => 'Grupos producto (SIFAB)',
                'edit_route' => 'editar_grupoproducto',
                'permisos_abm' => ['listar-grupos-producto', 'editar-grupos-producto', 'crear-grupos-producto'],
                'input_name' => 'grupoproducto',
            ],
        ];
    }

    /**
     * @return array{
     *   model: class-string<Model>,
     *   label: string,
     *   titulo_modal: string,
     *   edit_route: string,
     *   permisos_abm: list<string>,
     *   input_name: string
     * }|null
     */
    public static function def(string $recurso): ?array
    {
        return self::todos()[$recurso] ?? null;
    }

    public static function queryBase(string $recurso, bool $soloHabilitados = true): ?Builder
    {
        $def = self::def($recurso);
        if ($def === null) {
            return null;
        }

        /** @var Model $model */
        $model = $def['model'];
        $query = $model::query();
        if ($soloHabilitados) {
            $query->where('habilitado', true);
        }

        return $query;
    }

    /**
     * Resuelve etiqueta (nombre/código) desde el código interno grabado en articulo.
     *
     * @return array{id: int|null, codigo_interno_sifab: string|null, codigo: string|null, nombre: string|null, etiqueta: string|null}
     */
    public static function etiquetar(string $recurso, mixed $codigoInterno): array
    {
        $vacio = [
            'id' => null,
            'codigo_interno_sifab' => null,
            'codigo' => null,
            'nombre' => null,
            'etiqueta' => null,
        ];
        if ($codigoInterno === null || $codigoInterno === '') {
            return $vacio;
        }

        $q = self::queryBase($recurso, false);
        if ($q === null) {
            return $vacio;
        }

        $codigoStr = trim((string) $codigoInterno);
        $row = null;
        if (preg_match('/^-?\d+$/', $codigoStr)) {
            $row = (clone $q)->where('codigo_interno_sifab', (int) $codigoStr)->first();
        }
        if ($row === null) {
            $row = (clone $q)->where('codigo', $codigoStr)->first();
        }
        if ($row === null) {
            return array_merge($vacio, ['codigo_interno_sifab' => $codigoStr]);
        }

        return [
            'id' => (int) $row->id,
            'codigo_interno_sifab' => $row->codigo_interno_sifab !== null ? (string) $row->codigo_interno_sifab : null,
            'codigo' => $row->codigo !== null ? (string) $row->codigo : null,
            'nombre' => (string) $row->nombre,
            'etiqueta' => self::formatearEtiqueta(
                $row->codigo !== null ? (string) $row->codigo : null,
                $row->codigo_interno_sifab !== null ? (string) $row->codigo_interno_sifab : null,
                (string) $row->nombre
            ),
        ];
    }

    public static function formatearEtiqueta(?string $codigo, ?string $codigoInterno, ?string $nombre): string
    {
        $nombre = trim((string) $nombre);
        $codigo = trim((string) $codigo);
        $codigoInterno = trim((string) $codigoInterno);
        if ($codigo !== '' && $codigo !== $codigoInterno) {
            return $codigo.($nombre !== '' ? ' — '.$nombre : '');
        }

        return $nombre;
    }

    /**
     * @param  object|array<string, mixed>|null  $producto
     * @return array<string, array{id: int|null, codigo_interno_sifab: string|null, codigo: string|null, nombre: string|null}>
     */
    public static function etiquetasDesdeProducto($producto): array
    {
        $get = static function ($p, string $k) {
            if (is_array($p)) {
                return $p[$k] ?? null;
            }

            return $p->{$k} ?? null;
        };

        return [
            'rubro' => self::etiquetar('rubro', $get($producto, 'rubro_sifab')),
            'subrubro' => self::etiquetar('subrubro', $get($producto, 'subrubro')),
            'lineamaterial' => self::etiquetar('lineamaterial', $get($producto, 'lineamaterial')),
            'grupoproducto' => self::etiquetar('grupoproducto', $get($producto, 'grupoproducto')),
            'clasematerial' => self::etiquetar('clasematerial', $get($producto, 'clasematerial')),
            'gestioncompra' => self::etiquetar('gestioncompra', $get($producto, 'gestioncompra')),
        ];
    }

    public static function puedeConsultar(): bool
    {
        if (! InterformingSifabSupport::esInterforming()) {
            return false;
        }

        return can('listar-articulos', false)
            || can('crear-articulos', false)
            || can('editar-articulos', false)
            || can('actualizar-articulos', false)
            || can('listar-clases-material', false)
            || can('listar-lineas-material', false)
            || can('listar-gestiones-compra', false)
            || can('listar-rubros', false)
            || can('listar-subrubros', false)
            || can('listar-grupos-producto', false);
    }

    public static function puedeAbrirAbm(string $recurso): bool
    {
        $def = self::def($recurso);
        if ($def === null) {
            return false;
        }
        foreach ($def['permisos_abm'] as $slug) {
            if (can($slug, false)) {
                return true;
            }
        }

        return false;
    }
}
