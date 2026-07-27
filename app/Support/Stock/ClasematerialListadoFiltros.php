<?php

namespace App\Support\Stock;

/**
 * Filtros listado Clases de material.
 */
class ClasematerialListadoFiltros extends AbstractSifabMaestroListadoFiltros
{
    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'clasematerial.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo_interno_sifab' => ['column' => 'clasematerial.codigo_interno_sifab', 'type' => 'entero', 'label' => 'Cód. interno SIFAB'],
        'codigo' => ['column' => 'clasematerial.codigo', 'type' => 'texto', 'label' => 'Código'],
        'nombre' => ['column' => 'clasematerial.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'habilitado' => ['column' => 'clasematerial.habilitado', 'type' => 'texto', 'label' => 'Habilitado'],
    ];

    public static function tabla(): string
    {
        return 'clasematerial';
    }

    public static function campos(): array
    {
        return self::CAMPOS;
    }
}
