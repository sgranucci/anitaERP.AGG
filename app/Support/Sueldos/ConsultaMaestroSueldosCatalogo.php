<?php

declare(strict_types=1);

namespace App\Support\Sueldos;

use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Art_Sueldos;
use App\Models\Sueldos\Categoria_Sueldos;
use App\Models\Sueldos\Lugartrabajo_Sueldos;
use App\Models\Sueldos\Vacacion_Sueldos;

/**
 * Maestros de sueldos consultables desde el legajo (F1 / código + Enter).
 *
 * @phpstan-type MaestroConfig array{
 *   model: class-string,
 *   titulo: string,
 *   label: string,
 *   campo_nombre: string,
 *   extras: list<string>,
 *   permiso_listar: string,
 *   permiso_editar: string,
 *   ruta_editar: string
 * }
 */
final class ConsultaMaestroSueldosCatalogo
{
    /**
     * @return array<string, MaestroConfig>
     */
    public static function todos(): array
    {
        return [
            'categoria' => [
                'model' => Categoria_Sueldos::class,
                'titulo' => 'Categorías',
                'label' => 'Categoría',
                'campo_nombre' => 'descripcion',
                'extras' => ['origen_bases'],
                'permiso_listar' => 'listar-categoria-sueldos',
                'permiso_editar' => 'editar-categoria-sueldos',
                'ruta_editar' => 'editar_categoria_sueldos',
            ],
            'agrupamiento' => [
                'model' => Agrupamiento_Sueldos::class,
                'titulo' => 'Agrupamientos',
                'label' => 'Agrupamiento',
                'campo_nombre' => 'descripcion',
                'extras' => [],
                'permiso_listar' => 'listar-agrupamiento-sueldos',
                'permiso_editar' => 'editar-agrupamiento-sueldos',
                'ruta_editar' => 'editar_agrupamiento_sueldos',
            ],
            'lugartrabajo' => [
                'model' => Lugartrabajo_Sueldos::class,
                'titulo' => 'Lugares de trabajo',
                'label' => 'Lugar de trabajo',
                'campo_nombre' => 'nombre',
                'extras' => [],
                'permiso_listar' => 'listar-lugartrabajo-sueldos',
                'permiso_editar' => 'editar-lugartrabajo-sueldos',
                'ruta_editar' => 'editar_lugartrabajo_sueldos',
            ],
            'vacacion' => [
                'model' => Vacacion_Sueldos::class,
                'titulo' => 'Vacaciones',
                'label' => 'Vacaciones',
                'campo_nombre' => 'descripcion',
                'extras' => [],
                'permiso_listar' => 'listar-vacacion-sueldos',
                'permiso_editar' => 'editar-vacacion-sueldos',
                'ruta_editar' => 'editar_vacacion_sueldos',
            ],
            'art' => [
                'model' => Art_Sueldos::class,
                'titulo' => 'ART',
                'label' => 'ART',
                'campo_nombre' => 'nombre',
                'extras' => [],
                'permiso_listar' => 'listar-art-sueldos',
                'permiso_editar' => 'editar-art-sueldos',
                'ruta_editar' => 'editar_art_sueldos',
            ],
        ];
    }

    /**
     * @return MaestroConfig|null
     */
    public static function get(string $maestro): ?array
    {
        return self::todos()[$maestro] ?? null;
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::todos());
    }
}
