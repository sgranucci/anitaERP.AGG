<?php

namespace App\Support\Logistica;

use App\Models\Contable\Centrocosto;
use App\Models\Logistica\ArticuloCatalogoLogistica;
use App\Models\Logistica\LogisticaCatalogoCategoria;
use App\Models\Logistica\LogisticaCentrocostoTope;
use App\Models\Logistica\LogisticaHabilitacion;
use App\Models\Logistica\LogisticaParametro;
use App\Models\Logistica\LogisticaSla;
use App\Models\Logistica\LogisticaTipoSolicitud;
use App\Models\Logistica\LogisticaTrabajoTipo;
use App\Models\Logistica\LogisticaUbicacion;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\Request;
use RuntimeException;

final class LogisticaConfiguracionSupport
{
    public static function guardar(Request $request): void
    {
        self::guardarParametro($request);
        self::guardarSla($request);
        self::guardarTopes($request);
        self::guardarCategorias($request);
        self::guardarTipos($request);
        self::guardarTrabajos($request);
        self::guardarUbicaciones($request);
        self::guardarHabilitaciones($request);
    }

    private static function guardarParametro(Request $request): void
    {
        $monto = (float) str_replace(',', '.', (string) $request->input('monto_aprobacion', '0'));
        if ($monto < 0) {
            $monto = 0;
        }
        $parametro = LogisticaParametro::query()->orderBy('id')->first();
        if ($parametro === null) {
            LogisticaParametro::query()->create(['monto_aprobacion' => $monto]);

            return;
        }
        $parametro->monto_aprobacion = $monto;
        $parametro->save();
    }

    private static function guardarSla(Request $request): void
    {
        $prioridades = (array) $request->input('sla_prioridad', []);
        $preparacion = (array) $request->input('sla_horas_preparacion', []);
        $entrega = (array) $request->input('sla_horas_entrega', []);
        foreach ($prioridades as $i => $prioridad) {
            $prioridad = trim((string) $prioridad);
            $fila = LogisticaSla::query()->where('prioridad', $prioridad)->first();
            if ($fila === null) {
                continue;
            }
            $horasPrep = max(1, (int) ($preparacion[$i] ?? 1));
            $horasEnt = max($horasPrep, (int) ($entrega[$i] ?? $horasPrep));
            $fila->horas_preparacion = $horasPrep;
            $fila->horas_entrega = $horasEnt;
            $fila->save();
        }
    }

    private static function guardarTopes(Request $request): void
    {
        $ids = (array) $request->input('tope_id', []);
        $montos = (array) $request->input('tope_monto', []);
        $conservar = [];
        foreach ($ids as $i => $id) {
            $id = (int) $id;
            $monto = (float) str_replace(',', '.', (string) ($montos[$i] ?? '0'));
            $fila = LogisticaCentrocostoTope::query()->find($id);
            if ($fila === null) {
                continue;
            }
            if ($monto <= 0) {
                continue;
            }
            $fila->monto_mensual = $monto;
            $fila->save();
            $conservar[] = $fila->id;
        }

        $nuevoCc = (int) $request->input('tope_cc_nuevo', 0);
        $nuevoMonto = (float) str_replace(',', '.', (string) $request->input('tope_monto_nuevo', '0'));
        if ($nuevoCc > 0 && $nuevoMonto > 0) {
            if (! Centrocosto::query()->whereKey($nuevoCc)->exists()) {
                throw new RuntimeException('El centro de costo del tope no existe.');
            }
            $fila = LogisticaCentrocostoTope::query()->updateOrCreate(
                ['centrocosto_id' => $nuevoCc],
                ['monto_mensual' => $nuevoMonto]
            );
            $conservar[] = (int) $fila->id;
        }

        $borrar = LogisticaCentrocostoTope::query();
        if ($conservar !== []) {
            $borrar->whereNotIn('id', $conservar);
        }
        EloquentAuditDeleteSupport::each($borrar);
    }

    private static function guardarCategorias(Request $request): void
    {
        $ids = (array) $request->input('cat_id', []);
        $codigos = (array) $request->input('cat_codigo', []);
        $nombres = (array) $request->input('cat_nombre', []);
        $iconos = (array) $request->input('cat_icono', []);
        $ordenes = (array) $request->input('cat_orden', []);
        $activos = (array) $request->input('cat_activo', []);
        $conservar = [];
        $vistos = [];

        foreach ($nombres as $i => $nombre) {
            $nombre = trim((string) $nombre);
            $codigo = strtoupper(trim((string) ($codigos[$i] ?? '')));
            if ($nombre === '' && $codigo === '') {
                continue;
            }
            if ($codigo === '' || $nombre === '') {
                throw new RuntimeException('Cada categoría de catálogo necesita código y nombre.');
            }
            if (isset($vistos[$codigo])) {
                throw new RuntimeException('Código de categoría repetido: '.$codigo);
            }
            $vistos[$codigo] = true;
            $id = (int) ($ids[$i] ?? 0);
            $payload = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'icono' => self::icono($iconos[$i] ?? '', 'fa-cube'),
                'orden' => (int) ($ordenes[$i] ?? 0),
                'activo' => ((string) ($activos[$i] ?? '1')) === '1',
            ];
            if ($id > 0) {
                $fila = LogisticaCatalogoCategoria::query()->find($id);
                if ($fila === null) {
                    throw new RuntimeException('Categoría inexistente.');
                }
                $fila->fill($payload)->save();
            } else {
                $fila = LogisticaCatalogoCategoria::query()->create($payload);
            }
            $conservar[] = (int) $fila->id;
        }

        $aBorrar = LogisticaCatalogoCategoria::query()
            ->when($conservar !== [], fn ($q) => $q->whereNotIn('id', $conservar))
            ->pluck('id');
        if ($aBorrar->isNotEmpty() && (
            LogisticaHabilitacion::query()->whereIn('catalogo_categoria_id', $aBorrar)->exists()
            || ArticuloCatalogoLogistica::query()->whereIn('catalogo_categoria_id', $aBorrar)->exists()
        )) {
            throw new RuntimeException('No se puede quitar una categoría en uso. Desactivala.');
        }

        EloquentAuditDeleteSupport::exceptIds(LogisticaCatalogoCategoria::query(), $conservar);
    }

    private static function guardarTipos(Request $request): void
    {
        $ids = (array) $request->input('tipo_id', []);
        foreach ($ids as $i => $id) {
            $fila = LogisticaTipoSolicitud::query()->find((int) $id);
            if ($fila === null) {
                continue;
            }
            $fila->nombre = trim((string) ($request->input('tipo_nombre.'.$i) ?? $fila->nombre));
            $fila->icono = self::icono($request->input('tipo_icono.'.$i), $fila->icono);
            $fila->orden = (int) $request->input('tipo_orden.'.$i, $fila->orden);
            $fila->activo = ((string) $request->input('tipo_activo.'.$i, '1')) === '1';
            $fila->save();
        }
    }

    private static function guardarTrabajos(Request $request): void
    {
        $ids = (array) $request->input('trab_id', []);
        $codigos = (array) $request->input('trab_codigo', []);
        $nombres = (array) $request->input('trab_nombre', []);
        $iconos = (array) $request->input('trab_icono', []);
        $responsables = (array) $request->input('trab_responsable', []);
        $emails = (array) $request->input('trab_email', []);
        $pisos = (array) $request->input('trab_piso', []);
        $ordenes = (array) $request->input('trab_orden', []);
        $activos = (array) $request->input('trab_activo', []);
        $conservar = [];

        foreach ($nombres as $i => $nombre) {
            $nombre = trim((string) $nombre);
            $codigo = strtolower(trim((string) ($codigos[$i] ?? '')));
            if ($nombre === '' && $codigo === '') {
                continue;
            }
            if ($codigo === '' || $nombre === '' || trim((string) ($responsables[$i] ?? '')) === '') {
                throw new RuntimeException('Cada tipo de trabajo necesita código, nombre y responsable.');
            }
            $piso = (string) ($pisos[$i] ?? 'Media');
            if (! in_array($piso, ['Baja', 'Media', 'Alta'], true)) {
                $piso = 'Media';
            }
            $payload = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'icono' => self::icono($iconos[$i] ?? '', 'fa-wrench'),
                'responsable' => trim((string) $responsables[$i]),
                'email' => trim((string) ($emails[$i] ?? '')) ?: null,
                'prioridad_piso' => $piso,
                'orden' => (int) ($ordenes[$i] ?? 0),
                'activo' => ((string) ($activos[$i] ?? '1')) === '1',
            ];
            $id = (int) ($ids[$i] ?? 0);
            $fila = $id > 0 ? LogisticaTrabajoTipo::query()->find($id) : null;
            if ($fila === null) {
                $fila = LogisticaTrabajoTipo::query()->create($payload);
            } else {
                $fila->fill($payload)->save();
            }
            $conservar[] = (int) $fila->id;
        }

        EloquentAuditDeleteSupport::exceptIds(LogisticaTrabajoTipo::query(), $conservar);
    }

    private static function guardarUbicaciones(Request $request): void
    {
        $ids = (array) $request->input('ubi_id', []);
        $nombres = (array) $request->input('ubi_nombre', []);
        $iconos = (array) $request->input('ubi_icono', []);
        $ordenes = (array) $request->input('ubi_orden', []);
        $activos = (array) $request->input('ubi_activo', []);
        $conservar = [];

        foreach ($nombres as $i => $nombre) {
            $nombre = trim((string) $nombre);
            if ($nombre === '') {
                continue;
            }
            $payload = [
                'nombre' => $nombre,
                'icono' => self::icono($iconos[$i] ?? '', 'fa-map-marker'),
                'orden' => (int) ($ordenes[$i] ?? 0),
                'activo' => ((string) ($activos[$i] ?? '1')) === '1',
            ];
            $id = (int) ($ids[$i] ?? 0);
            $fila = $id > 0 ? LogisticaUbicacion::query()->find($id) : null;
            if ($fila === null) {
                $fila = LogisticaUbicacion::query()->create($payload);
            } else {
                $fila->fill($payload)->save();
            }
            $conservar[] = (int) $fila->id;
        }

        EloquentAuditDeleteSupport::exceptIds(LogisticaUbicacion::query(), $conservar);
    }

    private static function guardarHabilitaciones(Request $request): void
    {
        $niveles = (array) $request->input('hab_nivel', []);
        $alcances = (array) $request->input('hab_alcance', []);
        $refs = (array) $request->input('hab_ref_id', []);
        $usuarioIds = (array) $request->input('hab_usuario_id', []);
        $usuarioCodigos = (array) $request->input('hab_usuario_codigo', []);
        $rolIds = (array) $request->input('hab_rol_id', []);
        $rolNombres = (array) $request->input('hab_rol_nombre', []);
        $habilitados = (array) $request->input('hab_habilitado', []);

        EloquentAuditDeleteSupport::each(
            LogisticaHabilitacion::query()->whereIn('nivel', ['tipo', 'categoria'])
        );

        $vistos = [];
        foreach ($niveles as $i => $nivel) {
            $nivel = (string) $nivel;
            $alcance = (string) ($alcances[$i] ?? '');
            $refId = (int) ($refs[$i] ?? 0);
            if (! in_array($nivel, ['tipo', 'categoria'], true) || ! in_array($alcance, ['rol', 'usuario'], true) || $refId <= 0) {
                continue;
            }
            if ($nivel === 'tipo' && ! LogisticaTipoSolicitud::query()->whereKey($refId)->exists()) {
                throw new RuntimeException('Habilitación con un tipo de solicitud inexistente.');
            }
            if ($nivel === 'categoria' && ! LogisticaCatalogoCategoria::query()->whereKey($refId)->exists()) {
                throw new RuntimeException('Habilitación con una categoría inexistente.');
            }
            $rolId = null;
            $usuarioId = null;
            if ($alcance === 'rol') {
                $rol = LogisticaVisibilidadSupport::resolverRol((int) ($rolIds[$i] ?? 0), (string) ($rolNombres[$i] ?? ''));
                if ($rol === null) {
                    throw new RuntimeException('Habilitación: el rol no existe.');
                }
                $rolId = (int) $rol->id;
            } else {
                $usuario = LogisticaVisibilidadSupport::resolverUsuario((int) ($usuarioIds[$i] ?? 0), (string) ($usuarioCodigos[$i] ?? ''));
                if ($usuario === null) {
                    throw new RuntimeException('Habilitación: el usuario no existe o está suspendido.');
                }
                $usuarioId = (int) $usuario->id;
            }
            $clave = $nivel.'|'.$refId.'|'.$alcance.'|'.($rolId ?? 0).'|'.($usuarioId ?? 0);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            LogisticaHabilitacion::query()->create([
                'nivel' => $nivel,
                'alcance' => $alcance,
                'rol_id' => $rolId,
                'usuario_id' => $usuarioId,
                'tipo_solicitud_id' => $nivel === 'tipo' ? $refId : null,
                'catalogo_categoria_id' => $nivel === 'categoria' ? $refId : null,
                'habilitado' => ((string) ($habilitados[$i] ?? '0')) === '1',
            ]);
        }
    }

    private static function icono(mixed $valor, string $defecto): string
    {
        $icono = trim((string) $valor);
        if ($icono === '') {
            return $defecto;
        }
        if (! str_starts_with($icono, 'fa-')) {
            $icono = 'fa-'.$icono;
        }

        return mb_substr($icono, 0, 40);
    }
}
