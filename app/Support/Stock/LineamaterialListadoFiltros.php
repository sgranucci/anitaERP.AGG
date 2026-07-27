<?php

namespace App\Support\Stock;

/**
 * Filtros listado Líneas de material.
 */
class LineamaterialListadoFiltros extends AbstractSifabMaestroListadoFiltros
{
    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'lineamaterial.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo_interno_sifab' => ['column' => 'lineamaterial.codigo_interno_sifab', 'type' => 'entero', 'label' => 'Cód. interno SIFAB'],
        'codigo' => ['column' => 'lineamaterial.codigo', 'type' => 'texto', 'label' => 'Código'],
        'nombre' => ['column' => 'lineamaterial.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'habilitado' => ['column' => 'lineamaterial.habilitado', 'type' => 'texto', 'label' => 'Habilitado'],
    ];

    public static function tabla(): string
    {
        return 'lineamaterial';
    }

    public static function campos(): array
    {
        return self::CAMPOS;
    }
}
