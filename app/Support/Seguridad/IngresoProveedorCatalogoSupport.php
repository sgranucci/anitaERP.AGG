<?php

namespace App\Support\Seguridad;

use App\Models\Seguridad\IngresoProveedorArea;
use App\Models\Seguridad\IngresoProveedorMotivo;
use App\Models\Seguridad\IngresoProveedorPunto;
use App\Models\Seguridad\IngresoProveedorSector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class IngresoProveedorCatalogoSupport
{
    /** @var array<string, array{modelo: class-string<Model>, titulo: string, ruta: string}> */
    public const TIPOS = [
        'punto' => [
            'modelo' => IngresoProveedorPunto::class,
            'titulo' => 'Puntos de ingreso',
            'ruta' => 'ingreso_proveedor_punto',
        ],
        'area' => [
            'modelo' => IngresoProveedorArea::class,
            'titulo' => 'Áreas de destino',
            'ruta' => 'ingreso_proveedor_area',
        ],
        'motivo' => [
            'modelo' => IngresoProveedorMotivo::class,
            'titulo' => 'Motivos de visita',
            'ruta' => 'ingreso_proveedor_motivo',
        ],
        'sector' => [
            'modelo' => IngresoProveedorSector::class,
            'titulo' => 'Sectores de ingreso',
            'ruta' => 'ingreso_proveedor_sector',
        ],
    ];

    public static function tipoDesdeRequest(Request $request): string
    {
        $tipo = (string) $request->route('tipo', '');
        if (isset(self::TIPOS[$tipo])) {
            return $tipo;
        }

        $path = (string) $request->path();
        foreach (array_keys(self::TIPOS) as $key) {
            if (str_contains($path, 'ingreso-proveedor-'.$key)) {
                return $key;
            }
        }

        abort(404);
    }

    public static function def(string $tipo): array
    {
        if (! isset(self::TIPOS[$tipo])) {
            abort(404);
        }

        return self::TIPOS[$tipo];
    }

    public static function modelo(string $tipo): Model
    {
        $clase = self::def($tipo)['modelo'];

        return new $clase;
    }
}
