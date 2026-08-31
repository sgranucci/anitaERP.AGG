<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Support\Configuracion\ArchivoLogSupport;
use App\Support\Configuracion\AuditoriaDatosCatalogoSupport;
use App\Support\Configuracion\AuditoriaDatosAbmLinkSupport;
use App\Support\Configuracion\AuditoriaDatosFavoritoSupport;
use App\Support\Configuracion\AuditoriaDatosListadoFiltros;
use App\Support\Configuracion\AuditoriaDatosRegistroResolver;
use App\Support\Configuracion\BitacoraAccesoDiscoSupport;
use App\Support\Configuracion\BitacoraAccesoListadoFiltros;
use App\Models\Seguridad\Usuario;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Panel de auditoría: navegación + cambios de datos (audits) + logs de archivo.
 */
class AuditoriaSesionController extends Controller
{
    public function index(Request $request)
    {
        can('listar-auditoria-sesiones');

        $filtros = BitacoraAccesoListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = BitacoraAccesoListadoFiltros::paraQueryString($filtros);

        $coleccion = null;
        $coleccionDatos = null;
        $contenidoLog = null;
        $avisoDatos = null;
        $catalogoDatos = [];
        $archivosLog = [];

        $pestana = (string) ($filtros['pestana'] ?? 'navegacion');

        // AVG/COUNT sobre bitácora (~millones) solo en pestaña Navegación (y cacheados).
        $disco = BitacoraAccesoDiscoSupport::resumenCompleto($filtros, [
            'incluir_proceso' => $pestana === 'navegacion',
        ]);

        if ($pestana === 'navegacion' && Schema::hasTable('bitacora_acceso')) {
            $coleccion = BitacoraAccesoListadoFiltros::queryBase($filtros)->paginate(25);
            $coleccion->appends($filtrosQuery);
        }

        if ($pestana === 'archivos') {
            $archivosLog = ArchivoLogSupport::listar();
            $archivo = (string) ($filtros['archivo_log'] ?? '');
            // Solo leer contenido cuando el usuario eligió un archivo (no al abrir la tabla).
            if ($archivo !== '') {
                $contenidoLog = ArchivoLogSupport::leerCola($archivo, (int) $filtros['lineas_log']);
            }
        }

        $favoritosAnclados = [];
        $registroResuelto = null;
        $registroCandidatos = [];

        if ($pestana === 'datos') {
            $catalogoDatos = AuditoriaDatosCatalogoSupport::catalogo();
            $favoritosAnclados = AuditoriaDatosFavoritoSupport::listar();
            if (! config('auditoria_datos.panel_habilitado', true)) {
                $avisoDatos = 'El panel de cambios de datos está desconectado (AUDITORIA_DATOS_PANEL_HABILITADO=false).';
            } elseif (! Schema::hasTable('audits')) {
                $avisoDatos = 'No existe la tabla audits.';
            } elseif (! AuditoriaDatosListadoFiltros::criteriosSuficientes($filtros)) {
                $avisoDatos = 'Elegí un modelo/tabla o un usuario. Con ~millones de filas no se consulta sin filtro (evita saturar MySQL).';
            } else {
                $bloqueoPorBusqueda = false;
                $type = (string) ($filtros['auditable_type'] ?? '');
                $textoReg = (string) ($filtros['registro_busqueda'] ?? '');

                if ($type !== '' && $textoReg !== '' && empty($filtros['auditable_id'])) {
                    $registroCandidatos = AuditoriaDatosRegistroResolver::buscar($type, $textoReg, 20);
                    $idClaro = AuditoriaDatosRegistroResolver::idUnicoSiClaro($registroCandidatos, $textoReg);
                    if ($idClaro !== null) {
                        $filtros['auditable_id'] = $idClaro;
                        foreach ($registroCandidatos as $cand) {
                            if ((int) $cand['id'] === $idClaro) {
                                $registroResuelto = $cand;
                                break;
                            }
                        }
                        $registroCandidatos = [];
                        $filtrosQuery = BitacoraAccesoListadoFiltros::paraQueryString($filtros);
                    } elseif ($registroCandidatos === []) {
                        $avisoDatos = 'No se encontró ningún registro con «'.$textoReg.'» en '
                            .AuditoriaDatosCatalogoSupport::etiquetaTipo($type)
                            .'. Probá código, nombre o fantasia.';
                        $bloqueoPorBusqueda = true;
                    } else {
                        $avisoDatos = 'Hay varios registros que coinciden con «'.$textoReg.'». Elegí uno de la lista.';
                        $bloqueoPorBusqueda = true;
                    }
                } elseif ($type !== '' && ! empty($filtros['auditable_id'])) {
                    $registroResuelto = AuditoriaDatosRegistroResolver::buscar($type, (string) $filtros['auditable_id'], 1)[0] ?? [
                        'id' => (int) $filtros['auditable_id'],
                        'etiqueta' => '#'.$filtros['auditable_id'],
                        'codigo' => null,
                        'extra' => 'id '.$filtros['auditable_id'],
                    ];
                }

                if (is_array($registroResuelto) && $type !== '') {
                    $registroResuelto['abm_link'] = AuditoriaDatosAbmLinkSupport::linkConsulta(
                        $type,
                        (int) $registroResuelto['id']
                    );
                }

                if (! $bloqueoPorBusqueda) {
                    $page = max(1, (int) $request->input('page', 1));
                    $perPage = 25;
                    $base = AuditoriaDatosListadoFiltros::queryBase($filtros)
                        ->leftJoin('usuario', 'usuario.id', '=', 'audits.user_id')
                        ->select(
                            'audits.id',
                            'audits.user_id',
                            'audits.event',
                            'audits.auditable_type',
                            'audits.auditable_id',
                            'audits.old_values',
                            'audits.new_values',
                            'audits.created_at',
                            'usuario.nombre as usuario_nombre',
                            'usuario.usuario as usuario_login'
                        );

                    $tieneTipo = ($filtros['auditable_type'] ?? '') !== '';
                    // Con modelo: COUNT es barato vía índice. Solo usuario: evitar COUNT (scan).
                    if ($tieneTipo) {
                        $total = (clone $base)->count('audits.id');
                        $rows = (clone $base)->forPage($page, $perPage)->get();
                    } else {
                        $rows = (clone $base)->forPage($page, $perPage + 1)->get();
                        $tieneMas = $rows->count() > $perPage;
                        $rows = $rows->take($perPage)->values();
                        // total sintético para LengthAware (links prev/next sin COUNT real).
                        $total = (($page - 1) * $perPage) + $rows->count() + ($tieneMas ? 1 : 0);
                    }

                    $items = $rows->map(static function ($row) {
                        $row->diff = AuditoriaDatosListadoFiltros::diffValores(
                            $row->old_values ?? null,
                            $row->new_values ?? null
                        );
                        $row->etiqueta_tipo = AuditoriaDatosCatalogoSupport::etiquetaTipo((string) $row->auditable_type);
                        $row->abm_link = AuditoriaDatosAbmLinkSupport::linkConsulta(
                            (string) $row->auditable_type,
                            (int) $row->auditable_id
                        );

                        return $row;
                    });
                    $coleccionDatos = new LengthAwarePaginator(
                        $items,
                        $total,
                        $perPage,
                        $page,
                        ['path' => $request->url(), 'query' => $filtrosQuery]
                    );
                }
            }
        }

        // Precarga del filtro (incluye suspendidos: histórico de auditoría).
        $usuarioFiltro = null;
        $usuarioFiltroId = (int) ($filtros['usuario_id'] ?? 0);
        if ($usuarioFiltroId > 0) {
            $usuarioFiltro = Usuario::query()
                ->select(['id', 'nombre', 'usuario'])
                ->find($usuarioFiltroId);
        }

        return view('configuracion.auditoria_sesion.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'disco' => $disco,
            'coleccion' => $coleccion,
            'coleccionDatos' => $coleccionDatos,
            'contenidoLog' => $contenidoLog,
            'usuarioFiltro' => $usuarioFiltro,
            'archivosLog' => $archivosLog,
            'catalogoDatos' => $catalogoDatos,
            'favoritosAnclados' => $favoritosAnclados,
            'avisoDatos' => $avisoDatos,
            'registroResuelto' => $registroResuelto,
            'registroCandidatos' => $registroCandidatos,
        ]);
    }

    public function anclarFavorito(Request $request)
    {
        can('listar-auditoria-sesiones');

        $type = trim((string) $request->input('auditable_type', ''));

        try {
            $favoritos = AuditoriaDatosFavoritoSupport::anclar($type);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'favoritos' => $favoritos,
            'auditable_type' => $type,
            'anclado' => true,
        ]);
    }

    public function desanclarFavorito(Request $request)
    {
        can('listar-auditoria-sesiones');

        $type = trim((string) $request->input('auditable_type', ''));

        try {
            $favoritos = AuditoriaDatosFavoritoSupport::desanclar($type);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'favoritos' => $favoritos,
            'auditable_type' => $type,
            'anclado' => false,
        ]);
    }

    public function listarFavoritos()
    {
        can('listar-auditoria-sesiones');

        return response()->json([
            'ok' => true,
            'favoritos' => AuditoriaDatosFavoritoSupport::listar(),
            'catalogo' => AuditoriaDatosCatalogoSupport::catalogo(),
        ]);
    }

    public function buscarRegistro(Request $request)
    {
        can('listar-auditoria-sesiones');

        $type = trim((string) $request->input('auditable_type', ''));
        $texto = trim((string) $request->input('q', $request->input('registro_busqueda', '')));

        if ($type === '' || ! AuditoriaDatosListadoFiltros::tipoPermitido($type)) {
            return response()->json(['ok' => false, 'mensaje' => 'Elegí un modelo válido.', 'resultados' => []], 422);
        }
        if (mb_strlen($texto) < 1) {
            return response()->json(['ok' => true, 'resultados' => []]);
        }

        return response()->json([
            'ok' => true,
            'resultados' => AuditoriaDatosRegistroResolver::buscar($type, $texto, 20),
        ]);
    }
}
