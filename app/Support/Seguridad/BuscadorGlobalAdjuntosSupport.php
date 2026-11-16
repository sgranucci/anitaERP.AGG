<?php

namespace App\Support\Seguridad;

use App\Models\Seguridad\BuscadorFtsDocumento;
use App\Repositories\Configuracion\EmpresaRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Busca adjuntos por nombre o contenido indexado (FTS / LIKE).
 */
class BuscadorGlobalAdjuntosSupport
{
    public const LIMITE = 8;

    public function __construct(
        private EmpresaRepository $empresaRepository
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buscar(string $consulta): array
    {
        $q = trim($consulta);
        if (mb_strlen($q) < BuscadorGlobalSupport::MIN_CARACTERES) {
            return [];
        }
        if (! Schema::hasTable('buscador_fts_documento')) {
            return $this->buscarSoloNombreSinIndice($q);
        }

        $query = BuscadorFtsDocumento::query()->select([
            'id',
            'origen_tipo',
            'padre_tipo',
            'padre_id',
            'empresa_id',
            'permiso_slug',
            'nombre_archivo',
            'contenido_texto',
            'ruta_relativa',
            'mime',
        ]);

        $this->aplicarPermisos($query);
        $this->aplicarEmpresa($query);
        $this->aplicarTexto($query, $q);
        $query->orderByDesc('indexado_at')->limit(self::LIMITE * 3);

        $filas = [];
        $vistos = [];
        foreach ($query->get() as $doc) {
            $fuente = BuscadorFtsCatalogoSupport::porOrigenTipo((string) $doc->origen_tipo)
                ?? BuscadorFtsCatalogoSupport::porPadreTipo((string) $doc->padre_tipo);
            if ($fuente === null) {
                continue;
            }
            if (! can((string) $doc->permiso_slug, false) && ! can($fuente['permiso'], false)) {
                continue;
            }
            $clavePadre = $doc->padre_tipo.':'.$doc->padre_id;
            if (isset($vistos[$clavePadre])) {
                continue;
            }
            $vistos[$clavePadre] = true;

            $matchEn = 'Adjunto';
            $enContenido = $doc->contenido_texto
                && mb_stripos((string) $doc->contenido_texto, $q) !== false;
            if ($enContenido && mb_stripos((string) $doc->nombre_archivo, $q) === false) {
                $matchEn = 'Contenido del adjunto';
            }

            $url = $this->urlPadre($fuente, (int) $doc->padre_id);
            if ($url === null) {
                continue;
            }

            $snippet = $enContenido
                ? $this->snippet((string) $doc->contenido_texto, $q)
                : '';

            $campos = [
                ['label' => 'Adjunto', 'valor' => (string) $doc->nombre_archivo],
                ['label' => 'Documento', 'valor' => $fuente['etiqueta'].' #'.$doc->padre_id],
            ];
            if ($snippet !== '') {
                $campos[] = ['label' => 'Encontrado', 'valor' => $snippet];
            }

            $filas[] = [
                'titulo' => $fuente['etiqueta'].' · '.$doc->nombre_archivo,
                'detalle' => $matchEn.' · '.$fuente['etiqueta'].' #'.$doc->padre_id,
                'url' => $url,
                'icono' => $fuente['icono'],
                'nueva_pestana' => true,
                'tipo' => 'adjunto',
                'clave' => 'adjunto:'.$doc->origen_tipo.':'.$doc->origen_id,
                'etiqueta' => 'Adjunto',
                'campos' => $campos,
                'match_en' => $matchEn,
                'adjunto' => [
                    'nombre' => (string) $doc->nombre_archivo,
                    'mime' => $doc->mime,
                ],
            ];

            if (count($filas) >= self::LIMITE) {
                break;
            }
        }

        return $filas;
    }

    /**
     * Fallback si aún no corrió la migración: busca por nombre en tablas de archivo.
     *
     * @return list<array<string, mixed>>
     */
    private function buscarSoloNombreSinIndice(string $q): array
    {
        $like = '%'.addcslashes($q, '%_\\').'%';
        $filas = [];
        foreach (BuscadorFtsCatalogoSupport::fuentes() as $fuente) {
            if (! Schema::hasTable($fuente['tabla'])) {
                continue;
            }
            if (! can($fuente['permiso'], false)) {
                continue;
            }
            $rows = \DB::table($fuente['tabla'])
                ->select(['id', $fuente['padre_fk'].' as padre_id', $fuente['nombre_col'].' as nombre'])
                ->where($fuente['nombre_col'], 'like', $like)
                ->orderByDesc('id')
                ->limit(2)
                ->get();
            foreach ($rows as $row) {
                $url = $this->urlPadre($fuente, (int) $row->padre_id);
                if ($url === null) {
                    continue;
                }
                $filas[] = [
                    'titulo' => $fuente['etiqueta'].' · '.$row->nombre,
                    'detalle' => 'Adjunto · '.$fuente['etiqueta'].' #'.$row->padre_id,
                    'url' => $url,
                    'icono' => $fuente['icono'],
                    'nueva_pestana' => true,
                    'tipo' => 'adjunto',
                    'clave' => 'adjunto:'.$fuente['origen_tipo'].':'.$row->id,
                    'etiqueta' => 'Adjunto',
                    'campos' => [
                        ['label' => 'Adjunto', 'valor' => (string) $row->nombre],
                        ['label' => 'Documento', 'valor' => $fuente['etiqueta'].' #'.$row->padre_id],
                    ],
                    'match_en' => 'Nombre de archivo',
                    'adjunto' => ['nombre' => (string) $row->nombre, 'mime' => null],
                ];
                if (count($filas) >= self::LIMITE) {
                    return $filas;
                }
            }
        }

        return $filas;
    }

    private function aplicarPermisos(Builder $query): void
    {
        $slugs = [];
        foreach (BuscadorFtsCatalogoSupport::fuentes() as $fuente) {
            if (can($fuente['permiso'], false)) {
                $slugs[] = $fuente['permiso'];
            }
        }
        if ($slugs === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn('permiso_slug', array_values(array_unique($slugs)));
    }

    private function aplicarEmpresa(Builder $query): void
    {
        $empresas = array_values(array_filter(array_map(
            'intval',
            $this->empresaRepository->traeEmpresasAsignadas()
        )));
        if ($empresas === []) {
            return;
        }
        $query->where(function ($w) use ($empresas) {
            $w->whereNull('empresa_id')->orWhereIn('empresa_id', $empresas);
        });
    }

    private function aplicarTexto(Builder $query, string $q): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $like = '%'.addcslashes($q, '%_\\').'%';

        if (in_array($driver, ['mysql', 'mariadb'], true) && mb_strlen($q) >= 3) {
            $boolean = $this->booleanQuery($q);
            $query->where(function ($w) use ($boolean, $like, $q) {
                $w->whereRaw(
                    'MATCH(nombre_archivo, contenido_texto) AGAINST (? IN BOOLEAN MODE)',
                    [$boolean]
                )->orWhere('nombre_archivo', 'like', $like);
                // Códigos con barras/guiones (ej. 114/1-KIT) no entran bien en FULLTEXT.
                if (preg_match('/[\/\-_]/', $q) === 1) {
                    $w->orWhere('contenido_texto', 'like', $like);
                }
            });

            return;
        }

        $query->where(function ($w) use ($like) {
            $w->where('nombre_archivo', 'like', $like)
                ->orWhere('contenido_texto', 'like', $like);
        });
    }

    private function booleanQuery(string $q): string
    {
        $tokens = preg_split('/[\s\/\-_]+/u', $q) ?: [];
        $partes = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if (mb_strlen($token) < 2) {
                continue;
            }
            $partes[] = '+'.$token.'*';
        }

        return $partes !== [] ? implode(' ', $partes) : $q.'*';
    }

    /**
     * @param  array<string, mixed>  $fuente
     */
    private function urlPadre(array $fuente, int $padreId): ?string
    {
        $ruta = (string) ($fuente['ruta_editar'] ?? '');
        if ($ruta === '' || ! \Route::has($ruta)) {
            return null;
        }

        $params = ['id' => $padreId];
        if (! in_array($ruta, ['editar_cobranza', 'edita_administracion_ticket'], true)) {
            $params['origen'] = 'modal_consulta';
            $params['vista'] = 'consulta';
        }

        try {
            return route($ruta, $params);
        } catch (\Throwable) {
            return null;
        }
    }

    private function snippet(string $texto, string $q): string
    {
        $pos = mb_stripos($texto, $q);
        if ($pos === false) {
            return '';
        }
        $desde = max(0, $pos - 40);
        $trozo = mb_substr($texto, $desde, 120);
        $trozo = preg_replace('/\s+/u', ' ', trim($trozo)) ?? trim($trozo);
        if ($desde > 0) {
            $trozo = '…'.$trozo;
        }
        if ($desde + 120 < mb_strlen($texto)) {
            $trozo .= '…';
        }

        return $trozo;
    }
}
