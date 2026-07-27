<?php

namespace App\Support\Configuracion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resuelve código / nombre / fantasia → id(s) del registro auditable.
 */
class AuditoriaDatosRegistroResolver
{
    /** @var list<string> */
    private const COLUMNAS_GENERICAS = [
        'codigo',
        'sku',
        'nombre',
        'fantasia',
        'descripcion',
        'usuario',
        'email',
        'nroinscripcion',
        'cuit',
        'razon_social',
        'razonsocial',
        'numero',
    ];

    /**
     * @return list<array{id:int, etiqueta:string, codigo:?string, extra:string}>
     */
    public static function buscar(string $auditableType, string $texto, int $limite = 20): array
    {
        $texto = trim($texto);
        if ($texto === '' || ! class_exists($auditableType)) {
            return [];
        }

        $limite = max(1, min(50, $limite));
        $tabla = AuditoriaDatosCatalogoSupport::inferirTablaPublica($auditableType);
        if ($tabla === '' || ! Schema::hasTable($tabla)) {
            return [];
        }

        $columnas = self::columnasBusqueda($auditableType, $tabla);
        if ($columnas === []) {
            // Solo por id numérico.
            if (ctype_digit($texto)) {
                $row = DB::table($tabla)->where('id', (int) $texto)->first();
                if ($row) {
                    return [self::mapearFila($row, $columnas)];
                }
            }

            return [];
        }

        $query = DB::table($tabla)->select(array_values(array_unique(array_merge(['id'], $columnas))));

        if (Schema::hasColumn($tabla, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $query->where(function ($q) use ($texto, $columnas, $tabla) {
            if (ctype_digit($texto)) {
                $q->orWhere($tabla.'.id', (int) $texto);
            }
            foreach ($columnas as $col) {
                if (in_array($col, ['codigo', 'sku', 'numero', 'usuario'], true)) {
                    $q->orWhere($tabla.'.'.$col, $texto);
                }
            }
            foreach ($columnas as $col) {
                $q->orWhere($tabla.'.'.$col, 'like', '%'.$texto.'%');
            }
        });

        // Prioriza coincidencia exacta de código / id.
        if (ctype_digit($texto) && in_array('codigo', $columnas, true)) {
            $query->orderByRaw('CASE WHEN codigo = ? THEN 0 WHEN id = ? THEN 1 ELSE 2 END', [$texto, (int) $texto]);
        } elseif (in_array('codigo', $columnas, true)) {
            $query->orderByRaw('CASE WHEN codigo = ? THEN 0 WHEN LOWER(codigo) = ? THEN 1 ELSE 2 END', [$texto, mb_strtolower($texto)]);
        }

        if (in_array('nombre', $columnas, true)) {
            $query->orderBy('nombre');
        } elseif (in_array('descripcion', $columnas, true)) {
            $query->orderBy('descripcion');
        } else {
            $query->orderBy('id');
        }

        $filas = $query->limit($limite)->get();
        $out = [];
        foreach ($filas as $row) {
            $out[] = self::mapearFila($row, $columnas);
        }

        return $out;
    }

    /**
     * Si hay un único match claro, devuelve su id; si no, null.
     *
     * @param  list<array{id:int, etiqueta:string, codigo:?string, extra:string}>  $candidatos
     */
    public static function idUnicoSiClaro(array $candidatos, string $texto): ?int
    {
        if (count($candidatos) === 1) {
            return (int) $candidatos[0]['id'];
        }
        if ($candidatos === []) {
            return null;
        }

        $texto = trim($texto);
        foreach ($candidatos as $c) {
            if ((string) ($c['codigo'] ?? '') === $texto || (string) $c['id'] === $texto) {
                return (int) $c['id'];
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function columnasBusqueda(string $auditableType, ?string $tabla = null): array
    {
        $cfg = config('auditoria_datos.busqueda_registro.'.$auditableType);
        if (is_array($cfg) && $cfg !== []) {
            $candidatas = array_values(array_filter(array_map('strval', $cfg)));
        } else {
            $candidatas = self::COLUMNAS_GENERICAS;
        }

        $tabla = $tabla ?: AuditoriaDatosCatalogoSupport::inferirTablaPublica($auditableType);
        if ($tabla === '' || ! Schema::hasTable($tabla)) {
            return [];
        }

        $existentes = Schema::getColumnListing($tabla);

        return array_values(array_filter(
            $candidatas,
            static fn (string $c) => in_array($c, $existentes, true)
        ));
    }

    /**
     * @param  list<string>  $columnas
     * @return array{id:int, etiqueta:string, codigo:?string, extra:string}
     */
    private static function mapearFila(object $row, array $columnas): array
    {
        $id = (int) $row->id;
        $codigo = isset($row->codigo) ? (string) $row->codigo : (isset($row->sku) ? (string) $row->sku : null);
        $nombre = null;
        foreach (['nombre', 'fantasia', 'descripcion', 'usuario'] as $campo) {
            if (isset($row->{$campo}) && trim((string) $row->{$campo}) !== '') {
                $nombre = (string) $row->{$campo};
                break;
            }
        }
        if ($nombre === null) {
            $nombre = '#'.$id;
        }

        $partes = [];
        if ($codigo !== null && $codigo !== '') {
            $partes[] = 'cód. '.$codigo;
        }
        $partes[] = 'id '.$id;

        return [
            'id' => $id,
            'etiqueta' => $nombre,
            'codigo' => $codigo,
            'extra' => implode(' · ', $partes),
        ];
    }
}
