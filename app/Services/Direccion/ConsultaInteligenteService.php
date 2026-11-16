<?php

declare(strict_types=1);

namespace App\Services\Direccion;

use App\Models\Listado\ListadoVista;
use App\Support\ConsultaInteligente\ConsultaInteligenteDatasetSupport;
use App\Support\ConsultaInteligente\ConsultaInteligenteGrillaSupport;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\ConsultaInteligente\EntidadSemanticaCatalogo;
use App\Support\ConsultaInteligente\EntidadSemanticaPuenteReporte;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Listado\ListadoVistaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class ConsultaInteligenteService
{
    /**
     * @return list<array{
     *   clave: string,
     *   nombre: string,
     *   descripcion: string,
     *   icono: string,
     *   modulo: string,
     *   color: string,
     *   ruta_nativa: string,
     *   plantillas: list<array{clave: string, titulo: string, descripcion: string, url: string}>
     * }>
     */
    public function catalogoParaVista(?string $filtroModulo = null, ?string $texto = null): array
    {
        $texto = mb_strtolower(trim((string) $texto));
        $filtroModulo = $filtroModulo !== null && $filtroModulo !== '' ? $filtroModulo : null;
        $out = [];

        foreach (EntidadSemanticaCatalogo::disponiblesParaUsuario() as $entidad) {
            if ($filtroModulo !== null && $entidad->modulo() !== $filtroModulo) {
                continue;
            }
            if ($texto !== '' && ! $this->coincideTexto($entidad, $texto)) {
                continue;
            }

            $plantillas = [];
            foreach ($entidad->plantillas() as $p) {
                $plantillas[] = [
                    'clave' => $p['clave'],
                    'titulo' => $p['titulo'],
                    'descripcion' => $p['descripcion'],
                    'url' => $entidad instanceof EntidadSemanticaPuenteReporte
                        ? $entidad->urlReporte($p['query'])
                        : EntidadSemanticaCatalogo::urlExplorar($entidad->clave(), $p['query']),
                ];
            }

            $out[] = [
                'clave' => $entidad->clave(),
                'nombre' => $entidad->nombre(),
                'descripcion' => $entidad->descripcion(),
                'icono' => $entidad->icono(),
                'modulo' => $entidad->modulo(),
                'color' => $entidad->color(),
                'ruta_nativa' => $entidad->rutaNativa(),
                'url_explorar' => EntidadSemanticaCatalogo::urlExplorar($entidad->clave()),
                'url_nativa' => route($entidad->rutaNativa()),
                'plantillas' => $plantillas,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function modulosDisponibles(): array
    {
        $mods = [];
        foreach (EntidadSemanticaCatalogo::disponiblesParaUsuario() as $entidad) {
            $mods[$entidad->modulo()] = true;
        }

        return array_keys($mods);
    }

    /**
     * @return list<array{titulo: string, descripcion: string, url: string, entidad: string, color: string}>
     */
    public function plantillasDestacadas(int $limite = 10): array
    {
        $out = [];
        foreach (EntidadSemanticaCatalogo::disponiblesParaUsuario() as $entidad) {
            foreach ($entidad->plantillas() as $p) {
                $out[] = [
                    'titulo' => $p['titulo'],
                    'descripcion' => $entidad->nombre().' · '.$p['descripcion'],
                    'url' => $entidad instanceof EntidadSemanticaPuenteReporte
                        ? $entidad->urlReporte($p['query'])
                        : EntidadSemanticaCatalogo::urlExplorar($entidad->clave(), $p['query']),
                    'entidad' => $entidad->nombre(),
                    'color' => $entidad->color(),
                ];
                if (count($out) >= $limite) {
                    return $out;
                }
            }
        }

        return $out;
    }

    public function entidadAutorizada(string $clave): EntidadSemantica
    {
        if (! EntidadSemanticaCatalogo::existe($clave)) {
            throw new InvalidArgumentException("Entidad desconocida: {$clave}");
        }
        $entidad = EntidadSemanticaCatalogo::porClave($clave);
        can($entidad->permisoListar());

        return $entidad;
    }

    /**
     * @return array{
     *   entidad: EntidadSemantica,
     *   filtros: array<string, mixed>,
     *   filtrosQuery: array<string, mixed>,
     *   camposFiltro: array<string, array{label: string, type?: string}>,
     *   columnasVisibles: list<string>,
     *   etiquetas: array<string, string>,
     *   grillaLayout: list<array<string, mixed>>,
     *   filas: mixed,
     *   consultado: bool,
     *   vistas: Collection<int, ListadoVista>,
     *   vistaActiva: ?ListadoVista,
     *   workbenchListo: bool
     * }
     */
    public function explorar(string $clave, Request $request): array
    {
        $entidad = $this->entidadAutorizada($clave);
        $usuarioId = (int) auth()->id();
        $recursoVista = EntidadSemanticaCatalogo::recursoVista($clave);
        $vistas = ListadoVistaSupport::listarParaUsuario($recursoVista, $usuarioId);
        $vistaActiva = null;

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                $recursoVista,
                $usuarioId
            );
        }

        $filtros = $entidad->resolverFiltros($request);
        $qbeVacio = ! ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []));
        $sinCriteriosRequest = $qbeVacio
            && ! $request->filled('filtro_valor')
            && ! $request->boolean('para_depositar')
            && ! $request->boolean('cartera')
            && ! $request->boolean('rechazados')
            && ! $request->filled('origen');
        if ($vistaActiva && is_array($vistaActiva->filtros_json) && $sinCriteriosRequest) {
            $filtros = array_merge($filtros, $vistaActiva->filtros_json);
        }

        $consultado = $request->boolean('consultar')
            || $request->has('qbe')
            || $request->filled('filtro_valor')
            || $request->boolean('para_depositar')
            || $request->boolean('cartera')
            || $request->boolean('rechazados')
            || $request->filled('origen')
            || ($vistaActiva !== null && $request->filled('vista_id'));

        $grillaLayout = $vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []
            ? ConsultaInteligenteGrillaSupport::normalizar($entidad, $vistaActiva->columnas_json)
            : ConsultaInteligenteGrillaSupport::layoutEstandar($entidad);
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        if ($columnasVisibles === []) {
            $columnasVisibles = $entidad->columnasVisibles();
        }
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);
        if ($etiquetas === []) {
            $etiquetas = $entidad->etiquetasColumnas();
        }

        $filas = null;
        $esPuente = $entidad instanceof EntidadSemanticaPuenteReporte;
        if ($consultado && ! $esPuente) {
            $filas = ConsultaInteligenteDatasetSupport::consultar(
                $entidad,
                $filtros,
                true,
                $request->boolean('refresh_dataset'),
                max(1, (int) $request->input('page', 1))
            );
        }

        $filtrosQuery = array_merge(
            [
                'consultar' => $consultado ? 1 : null,
                'vista_id' => $vistaActiva?->id,
            ],
            $entidad->paraQueryString($filtros)
        );
        $filtrosQuery = array_filter(
            $filtrosQuery,
            static fn ($v) => $v !== null && $v !== '' && $v !== []
        );

        return [
            'entidad' => $entidad,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => $entidad->camposQbe(),
            'columnasVisibles' => $columnasVisibles,
            'etiquetas' => $etiquetas,
            'grillaLayout' => $grillaLayout,
            'filas' => $filas,
            'consultado' => $consultado,
            'vistas' => $vistas,
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
        ];
    }

    /**
     * @return array{
     *   entidad: EntidadSemantica,
     *   filas: Collection<int, object>,
     *   columnasVisibles: list<string>,
     *   etiquetas: array<string, string>,
     *   filtrosQuery: array<string, mixed>
     * }
     */
    public function datosExport(string $clave, Request $request): array
    {
        $entidad = $this->entidadAutorizada($clave);
        $filtros = $entidad->resolverFiltros($request);
        $filas = ConsultaInteligenteDatasetSupport::consultar(
            $entidad,
            $filtros,
            false,
            $request->boolean('refresh_dataset')
        );
        if (! $filas instanceof Collection) {
            $filas = collect($filas);
        }

        $layout = $request->filled('grilla')
            ? ConsultaInteligenteGrillaSupport::normalizar($entidad, $request->input('grilla'))
            : ConsultaInteligenteGrillaSupport::layoutEstandar($entidad);
        $columnas = ListadoGrillaConfigSupport::keysVisibles($layout);
        if ($columnas === []) {
            $columnas = $entidad->columnasVisibles();
        }

        return [
            'entidad' => $entidad,
            'filas' => $filas,
            'columnasVisibles' => $columnas,
            'etiquetas' => ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout) ?: $entidad->etiquetasColumnas(),
            'filtrosQuery' => $entidad->paraQueryString($filtros),
        ];
    }

    public function guardarVista(string $clave, Request $request): ?ListadoVista
    {
        $entidad = $this->entidadAutorizada($clave);
        $usuarioId = (int) auth()->id();
        $nombre = trim((string) $request->input('nombre', ''));
        $filtros = $entidad->resolverFiltros($request);
        $filtrosPersistir = $entidad->paraQueryString($filtros);
        $filtrosPersistir['consultar'] = 1;

        $layout = $request->filled('grilla')
            ? ConsultaInteligenteGrillaSupport::normalizar($entidad, $request->input('grilla'))
            : ConsultaInteligenteGrillaSupport::layoutEstandar($entidad);

        return ListadoVistaSupport::guardar(
            EntidadSemanticaCatalogo::recursoVista($clave),
            $usuarioId,
            $nombre,
            $filtrosPersistir,
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
    }

    public function eliminarVista(string $clave, int $vistaId): bool
    {
        $this->entidadAutorizada($clave);

        return ListadoVistaSupport::eliminar(
            $vistaId,
            EntidadSemanticaCatalogo::recursoVista($clave),
            (int) auth()->id()
        );
    }

    private function coincideTexto(EntidadSemantica $entidad, string $texto): bool
    {
        $hay = mb_strtolower(
            $entidad->nombre().' '.$entidad->descripcion().' '.$entidad->modulo().' '.$entidad->clave()
        );

        return str_contains($hay, $texto);
    }
}
