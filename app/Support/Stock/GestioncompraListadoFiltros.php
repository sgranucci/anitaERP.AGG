<?php

namespace App\Support\Stock;

/**
 * Filtros listado Gestiones de compra.
 */
class GestioncompraListadoFiltros extends AbstractSifabMaestroListadoFiltros
{
    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'gestioncompra.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo_interno_sifab' => ['column' => 'gestioncompra.codigo_interno_sifab', 'type' => 'entero', 'label' => 'Cód. interno SIFAB'],
        'codigo' => ['column' => 'gestioncompra.codigo', 'type' => 'texto', 'label' => 'Código'],
        'nombre' => ['column' => 'gestioncompra.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'habilitado' => ['column' => 'gestioncompra.habilitado', 'type' => 'texto', 'label' => 'Habilitado'],
    ];

    public static function tabla(): string
    {
        return 'gestioncompra';
    }

    public static function campos(): array
    {
        return self::CAMPOS;
    }
}
