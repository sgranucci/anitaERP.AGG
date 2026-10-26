<?php

namespace App\Support\Caja;

use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TesoreriaCrownWorkbenchSupport
{
    /**
     * @param  array<string, array<string, mixed>>  $catalogo
     * @param  callable(Request): array<string, mixed>  $resolver
     * @param  callable(): array<string, array<string, mixed>>  $camposOrdenables
     * @param  callable(): array<string, array<string, mixed>>  $camposQbe
     * @param  callable(string, string): string  $normalizarOperador
     * @param  callable(array<string, mixed>): array<string, mixed>  $paraQueryString
     * @return array<string, mixed>|RedirectResponse
     */
    public static function preparar(
        Request $request,
        string $recurso,
        string $rutaIndex,
        array $catalogo,
        callable $resolver,
        callable $camposOrdenables,
        callable $camposQbe,
        callable $normalizarOperador,
        callable $paraQueryString,
    ): array|RedirectResponse {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario($recurso, $usuarioId);
        $forzarEstandar = $request->boolean('vista_estandar') || $request->input('vista_modo') === 'estandar';
        $vistaActiva = null;
        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario((int) $request->input('vista_id'), $recurso, $usuarioId);
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('filtro_limpiar')
            && ! $request->has('sala_id')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario($recurso, $usuarioId);
        }

        $filtros = ListadoVistaSupport::prepararQbeContraVista($resolver($request), $request);
        $vistaJson = ($vistaActiva && is_array($vistaActiva->filtros_json)) ? $vistaActiva->filtros_json : null;
        $filtros = self::fusionar($filtros, $vistaJson, $camposQbe(), $normalizarOperador, $camposOrdenables());
        ListadoVistaSupport::recordarQbeSiEnvio($vistaActiva, $request, $filtros);
        if ($vistaActiva && ($request->exists('group') || $request->exists('sort'))) {
            ListadoVistaSupport::recordarOrdenYAgrupar(
                $vistaActiva,
                $filtros['sort'] ?? [],
                $filtros['agrupar'] ?? []
            );
        }

        if ($request->boolean('quitar_orden')) {
            $filtros['sort'] = [];
            if ($vistaActiva) {
                ListadoVistaSupport::recordarOrdenYAgrupar($vistaActiva, [], $filtros['agrupar'] ?? []);
            }
            $params = $paraQueryString($filtros);
            if ($vistaActiva) {
                $params['vista_id'] = $vistaActiva->id;
            }

            return redirect()->route($rutaIndex, $params)->with('mensaje', 'Se quitó el orden de la grilla.');
        }

        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas($recurso, $catalogo);
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = ListadoGrillaConfigSupport::normalizar($vistaActiva->columnas_json, $catalogo, $etiquetasInstalacion);
        } else {
            $cached = cache()->get(generaKey('tc-grilla-'.$recurso));
            $grillaLayout = is_array($cached) && $cached !== []
                ? ListadoGrillaConfigSupport::normalizar($cached, $catalogo, $etiquetasInstalacion)
                : ListadoGrillaConfigSupport::layoutDefault($catalogo, $etiquetasInstalacion);
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);
        $camposFiltro = $camposQbe();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }
        $filtrosQuery = $paraQueryString($filtros);
        $filtrosQuery['columnas'] = implode(',', $columnasVisibles);
        if ($vistaActiva) {
            $filtrosQuery['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $filtrosQuery['vista_estandar'] = 1;
        }

        return [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => $camposFiltro,
            'camposOrdenables' => $camposOrdenables(),
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => $vistas,
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'qbeAbierto' => ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []),
        ];
    }

    public static function guardarVista(
        Request $request,
        string $recurso,
        string $rutaIndex,
        array $catalogo,
        callable $resolver,
        callable $paraQueryString,
    ): RedirectResponse {
        $filtros = $resolver($request);
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas($recurso, $catalogo);
        $layout = ListadoGrillaConfigSupport::normalizar($request->input('grilla'), $catalogo, $etiquetas);
        $vista = ListadoVistaSupport::guardar(
            $recurso,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'qbe' => $filtros['qbe'] ?? [],
                'sort' => $filtros['sort'] ?? [],
                'agrupar' => $filtros['agrupar'] ?? [],
                'valor' => $filtros['valor'] ?? '',
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        $qs = $paraQueryString($filtros);
        if (! $vista) {
            return redirect()->route($rutaIndex, $qs)->with('mensaje-error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs['vista_id'] = $vista->id;
        $qs['columnas'] = implode(',', ListadoGrillaConfigSupport::keysVisibles($layout));

        return redirect()->route($rutaIndex, $qs)->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public static function guardarColumnas(
        Request $request,
        string $recurso,
        string $rutaIndex,
        array $catalogo,
        callable $resolver,
        callable $paraQueryString,
    ): RedirectResponse {
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas($recurso, $catalogo);
        $layout = ListadoGrillaConfigSupport::normalizar($request->input('grilla'), $catalogo, $etiquetas);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, $recurso, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            cache()->forever(generaKey('tc-grilla-'.$recurso), $layout);
        }
        $qs = $paraQueryString($resolver($request));
        $qs['columnas'] = implode(',', ListadoGrillaConfigSupport::keysVisibles($layout));
        if ($vistaId > 0) {
            $qs['vista_id'] = $vistaId;
        }

        return redirect()->route($rutaIndex, $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public static function preview(
        Request $request,
        array $catalogo,
        callable $resolver,
        string $recurso,
        callable $consulta,
        callable $valorCelda,
        callable $camposOrdenables,
    ) {
        $filtros = $resolver($request);
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas($recurso, $catalogo);
        $layout = ListadoGrillaConfigSupport::normalizar($request->input('grilla'), $catalogo, $etiquetas);
        $pagina = $consulta($filtros)->paginate(ListadoDisenadorPreviewSupport::LIMITE_MUESTRA);
        $orden = ListadoOrdenamientoSupport::normalizar($request->input('sort', $filtros['sort'] ?? []), $camposOrdenables());
        $agrupar = ListadoAgrupacionSupport::normalizar($request->input('group', $filtros['agrupar'] ?? []), $camposOrdenables());

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            [],
            $pagina->getCollection(),
            $valorCelda,
            (int) $pagina->total(),
            ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout),
            ['activo' => false]
        ));
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>|null  $vistaJson
     * @param  array<string, array<string, mixed>>  $camposQbe
     * @param  array<string, array<string, mixed>>  $camposOrdenables
     * @return array<string, mixed>
     */
    private static function fusionar(array $base, ?array $vistaJson, array $camposQbe, callable $normalizarOperador, array $camposOrdenables): array
    {
        $base['sort'] = ListadoOrdenamientoSupport::normalizar($base['sort'] ?? [], $camposOrdenables);
        $base['agrupar'] = ListadoAgrupacionSupport::normalizar($base['agrupar'] ?? [], $camposOrdenables);
        if ($vistaJson === null) {
            unset($base['_qbe_explicito']);

            return $base;
        }
        $explicito = ! empty($base['_qbe_explicito']);
        unset($base['_qbe_explicito']);
        $sortVista = ListadoOrdenamientoSupport::normalizar($vistaJson['sort'] ?? [], $camposOrdenables);
        if ($base['sort'] === [] && $sortVista !== []) {
            $base['sort'] = $sortVista;
        }
        if ($explicito || trim((string) ($base['valor'] ?? '')) !== '' || ListadoQbeSupport::tieneCriterios((array) ($base['qbe'] ?? []))) {
            return $base;
        }
        $base['qbe'] = ListadoQbeSupport::normalizar($vistaJson['qbe'] ?? [], $camposQbe, $normalizarOperador);
        if (trim((string) ($base['valor'] ?? '')) === '') {
            $base['valor'] = (string) ($vistaJson['valor'] ?? '');
        }

        return $base;
    }
}
