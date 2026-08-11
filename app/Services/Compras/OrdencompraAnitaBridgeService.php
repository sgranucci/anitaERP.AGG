<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaEscrituraSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaErpContext;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaLineaSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaNumeracionSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaOcfpagoCuotaExpander;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaWhereSupport;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use App\Support\Stock\RecepcionProveedorAnitaReferenciaSupport;
use App\Support\Stock\SurmarSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Escritura ERP → Anita (pendmaep, pendmovp, movpresup, ocvley, occuota, ocfpagocuota,
 * pendfecha, legcompra) con rollback ante fallos.
 */
class OrdencompraAnitaBridgeService
{
    /** Empresa de la OC en curso; define path Anita Surmar vs default. */
    private ?int $empresaIdParaPath = null;

    public function habilitado(): bool
    {
        return OrdencompraAnitaNumeracionSupport::estaHabilitada();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadAnita(array $payload): array
    {
        return SurmarSupport::mergePathSistema($payload, $this->empresaIdParaPath);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function anitaEscritura(ApiAnita $api, array $payload, ?string $contexto = null): string
    {
        return $api->apiCallEscritura($this->payloadAnita($payload), $contexto);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function anitaCall(ApiAnita $api, array $payload): mixed
    {
        return $api->apiCall($this->payloadAnita($payload));
    }

    private function fijarEmpresaPath(Ordencompra $oc): void
    {
        $this->empresaIdParaPath = (int) ($oc->empresa_id ?? 0);
    }

    public function sincronizarAlta(Ordencompra $oc): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $this->cargarRelaciones($oc);
        $this->validarCabeceraMinima($oc);
        $this->fijarEmpresaPath($oc);

        $ctx = OrdencompraAnitaErpContext::desdeUsuarioActual();
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        $estado = [
            'cabecera_nueva' => false,
            'detalle_grabado' => false,
            'comprobantes_grabados' => false,
            'pendfecha_grabado' => false,
            'legcompra_grabado' => false,
            'numero' => (int) $oc->numeroordencompra,
        ];

        try {
            OrdencompraAnitaLineaSupport::asignarClavesLineas($oc);
            $this->cargarRelaciones($oc);

            if ($this->existePendmaep($clave)) {
                throw new \RuntimeException(
                    'Ya existe una OC PEP/X/0 #'.$oc->numeroordencompra.' en Anita (pendmaep).'
                );
            }

            $this->insertarPendmaep($oc, $ctx, $clave);
            $estado['cabecera_nueva'] = true;

            $this->grabarDetalle($oc, $ctx, $clave);
            $estado['detalle_grabado'] = true;

            $this->grabarComprobantesCuotas($oc, $ctx, $clave);
            $estado['comprobantes_grabados'] = true;

            $this->grabarPendfecha($oc, $ctx, $clave);
            $estado['pendfecha_grabado'] = true;

            $this->grabarLegcompraAlta($oc, $ctx);
            $estado['legcompra_grabado'] = true;

            OrdencompraAnitaNumeracionSupport::registrarNumeroAsignadoEnNumerador(
                (int) $oc->numeroordencompra,
                (int) ($oc->empresa_id ?? 0)
            );
        } catch (\Throwable $e) {
            $this->revertir($clave, $estado);
            throw new \RuntimeException('Error al grabar la orden de compra en Anita: '.$e->getMessage(), 0, $e);
        }
    }

    public function sincronizarActualizacion(Ordencompra $oc): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $this->cargarRelaciones($oc);
        $this->validarCabeceraMinima($oc);
        $this->fijarEmpresaPath($oc);

        $ctx = OrdencompraAnitaErpContext::desdeUsuarioActual();
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        $backup = $this->leerBackupAnita($clave);
        $estado = [
            'cabecera_nueva' => false,
            'detalle_grabado' => false,
            'comprobantes_grabados' => false,
            'pendfecha_grabado' => false,
            'legcompra_grabado' => false,
            'numero' => (int) $oc->numeroordencompra,
        ];

        try {
            OrdencompraAnitaLineaSupport::asignarClavesLineas($oc);
            $this->cargarRelaciones($oc);

            if ($backup['cabecera'] === null) {
                $this->insertarPendmaep($oc, $ctx, $clave);
                $estado['cabecera_nueva'] = true;
            } else {
                $this->actualizarPendmaep($oc, $ctx, $clave);
            }

            $this->eliminarDetalle($clave);
            $this->grabarDetalle($oc, $ctx, $clave);
            $estado['detalle_grabado'] = true;

            $this->grabarComprobantesCuotas($oc, $ctx, $clave);
            $estado['comprobantes_grabados'] = true;

            $this->grabarPendfecha($oc, $ctx, $clave);
            $estado['pendfecha_grabado'] = true;

            $this->asegurarLegcompra($oc, $ctx);

            OrdencompraAnitaNumeracionSupport::registrarNumeroAsignadoEnNumerador(
                (int) $oc->numeroordencompra,
                (int) ($oc->empresa_id ?? 0)
            );
        } catch (\Throwable $e) {
            $this->revertirConBackup($clave, $estado, $backup);
            throw new \RuntimeException('Error al actualizar la orden de compra en Anita: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Repara legcompra y pendfecha faltantes en Anita para OCs ya grabadas en pendmaep.
     *
     * @return array{numero: int, legcompra: string, pendfecha: string}
     */
    public function repararRegistrosAnitaFaltantes(Ordencompra $oc): array
    {
        if (! $this->habilitado()) {
            throw new \RuntimeException('Escritura OC Anita deshabilitada.');
        }

        $this->cargarRelaciones($oc);
        $this->fijarEmpresaPath($oc);
        $numero = (int) $oc->numeroordencompra;
        if ($numero <= 0) {
            throw new \RuntimeException('La orden de compra no tiene número asignado.');
        }

        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        if (! $this->existePendmaep($clave)) {
            throw new \RuntimeException("La OC #{$numero} no existe en pendmaep (Anita).");
        }

        $ctx = OrdencompraAnitaErpContext::desdeUsuarioId(
            $oc->creousuario_id !== null ? (int) $oc->creousuario_id : null
        );
        $created = $oc->created_at !== null ? Carbon::parse($oc->created_at) : Carbon::now();
        $fechaYmd = $ctx->fechaYmd($created->format('Y-m-d'));
        $hora = $created->format('H:i:s');

        $result = [
            'numero' => $numero,
            'legcompra' => 'ok',
            'pendfecha' => 'ok',
        ];

        if (! $this->existeLegcompra($numero)) {
            $insert = OrdencompraAnitaEscrituraSupport::legcompraInsert(
                $numero,
                $ctx,
                OrdencompraAnitaEscrituraSupport::sectorLegajoCompras(),
                'Alta de OC (reparacion legcompra faltante)',
                $fechaYmd > 0 ? $fechaYmd : null,
                $hora,
            );

            $api = new ApiAnita;
            $this->anitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
                'tabla' => config('ordencompra_anita.tablas.historia'),
                'campos' => $insert['campos'],
                'valores' => $insert['valores'],
            ], 'ordencompra legcompra reparacion');

            $result['legcompra'] = 'insertado';
        }

        $proveedor6 = $ctx->codigoProveedor6((int) $oc->proveedor_id);
        if (! $this->existePendfecha($clave, $proveedor6)) {
            $this->grabarPendfecha($oc, $ctx, $clave);
            $result['pendfecha'] = 'insertado';
        }

        return $result;
    }

    /**
     * Diagnóstico ERP → Anita (cabecera, proveedor pad, líneas, auxiliares).
     *
     * @return array{
     *   numero: int,
     *   problemas: list<string>,
     *   cabecera: bool,
     *   proveedor_anita: ?string,
     *   proveedor_esperado: string,
     *   lineas_anita: int,
     *   cantentr_por_interno: array<int, float>
     * }
     */
    public function diagnosticarSincronizacionAnita(Ordencompra $oc): array
    {
        $this->cargarRelaciones($oc);
        $this->fijarEmpresaPath($oc);
        $numero = (int) $oc->numeroordencompra;
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        $ctx = OrdencompraAnitaErpContext::desdeUsuarioId(
            $oc->creousuario_id !== null ? (int) $oc->creousuario_id : null
        );
        $proveedorEsperado = $ctx->codigoProveedor6((int) $oc->proveedor_id);
        $problemas = [];

        $cabecera = $this->leerCabeceraPendmaep($clave);
        $lineas = $this->listarPendmovp($clave);
        $cantentrPorInterno = [];
        foreach ($lineas as $linea) {
            $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
            if ($nroInterno > 0) {
                $cantentrPorInterno[$nroInterno] = max(
                    (float) ($cantentrPorInterno[$nroInterno] ?? 0),
                    (float) ($linea->penvp_cantentr ?? 0)
                );
            }
        }

        if ($cabecera === null) {
            $problemas[] = 'Falta cabecera pendmaep en Anita.';
            if ($lineas !== []) {
                $problemas[] = 'Hay '.count($lineas).' línea(s) pendmovp huérfana(s) sin cabecera.';
            }
        } else {
            $proveedorAnita = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6(
                (string) ($cabecera->penmp_proveedor ?? '')
            );
            $proveedorRaw = trim((string) ($cabecera->penmp_proveedor ?? ''));
            if ($proveedorRaw === '' || ! preg_match('/^\d{6}$/', $proveedorRaw)) {
                $problemas[] = 'Proveedor cabecera sin pad de 6 dígitos (Anita="'.$proveedorRaw.'", esperado='.$proveedorEsperado.').';
            } elseif ($proveedorAnita !== $proveedorEsperado) {
                $problemas[] = 'Proveedor cabecera distinto al ERP (Anita='.$proveedorAnita.', esperado='.$proveedorEsperado.').';
            }
        }

        $esperadas = $oc->ordencompra_articulos->count();
        if ($cabecera !== null && count($lineas) === 0 && $esperadas > 0) {
            $problemas[] = 'Cabecera Anita sin líneas pendmovp.';
        }
        if (count($lineas) > $esperadas && $esperadas > 0) {
            $problemas[] = 'Líneas Anita duplicadas o de más (Anita='.count($lineas).', ERP='.$esperadas.').';
        }

        if ($cabecera !== null) {
            if (! $this->existeLegcompra($numero)) {
                $problemas[] = 'Falta legcompra en Anita.';
            }
            if (! $this->existePendfecha($clave, $proveedorEsperado)) {
                $problemas[] = 'Falta pendfecha en Anita.';
            }
            if (! $this->existeOccuota($clave)) {
                $problemas[] = 'Falta occuota en Anita.';
            }
        }

        return [
            'numero' => $numero,
            'problemas' => $problemas,
            'cabecera' => $cabecera !== null,
            'proveedor_anita' => $cabecera !== null
                ? trim((string) ($cabecera->penmp_proveedor ?? ''))
                : null,
            'proveedor_esperado' => $proveedorEsperado,
            'lineas_anita' => count($lineas),
            'cantentr_por_interno' => $cantentrPorInterno,
        ];
    }

    /**
     * Repara gaps ERP → Anita sin perder penvp_cantentr de recepciones aplicadas.
     *
     * @return array{numero: int, acciones: list<string>, problemas_restantes: list<string>}
     */
    public function repararSincronizacionAnita(Ordencompra $oc): array
    {
        if (! $this->habilitado()) {
            throw new \RuntimeException('Escritura OC Anita deshabilitada.');
        }

        $this->cargarRelaciones($oc);
        $this->validarCabeceraMinima($oc);
        $this->fijarEmpresaPath($oc);

        $numero = (int) $oc->numeroordencompra;
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        $ctx = OrdencompraAnitaErpContext::desdeUsuarioId(
            $oc->creousuario_id !== null ? (int) $oc->creousuario_id : null
        );
        $proveedorEsperado = $ctx->codigoProveedor6((int) $oc->proveedor_id);
        $acciones = [];

        $diag = $this->diagnosticarSincronizacionAnita($oc);
        $cantentrPreserve = $diag['cantentr_por_interno'];

        if (! $diag['cabecera']) {
            OrdencompraAnitaLineaSupport::asignarClavesLineas($oc);
            $this->cargarRelaciones($oc);
            $this->insertarPendmaep($oc, $ctx, $clave);
            $acciones[] = 'insertó pendmaep';

            $lineasAnita = $this->listarPendmovp($clave);
            if (count($lineasAnita) !== $oc->ordencompra_articulos->count()) {
                $this->eliminarDetalle($clave);
                $this->grabarDetalle($oc, $ctx, $clave);
                $acciones[] = 'regrabó pendmovp/movpresup';
            }

            $this->restaurarCantentrLineas($clave, $cantentrPreserve);
            if ($cantentrPreserve !== []) {
                $acciones[] = 'restauró penvp_cantentr';
            }

            $estadoAnita = $ctx->mapEstadoAnita((string) $oc->estadoordencompra);
            $api = new ApiAnita;
            $this->anitaEscritura($api, [
                'acc' => 'update',
                'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
                'tabla' => config('ordencompra_anita.tablas.cabecera'),
                'valores' => RecepcionProveedorAnitaEscrituraSupport::penmpEstadoUpdateSet($estadoAnita),
                'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
            ], 'ordencompra pendmaep estado reparacion');
            $acciones[] = 'estado cabecera='.$estadoAnita;
        } else {
            $proveedorRaw = (string) ($diag['proveedor_anita'] ?? '');
            if ($proveedorRaw === '' || ! preg_match('/^\d{6}$/', $proveedorRaw) || $proveedorRaw !== $proveedorEsperado) {
                $this->actualizarProveedorPendmaep($clave, $proveedorEsperado);
                $acciones[] = 'corrigió penmp_proveedor a '.$proveedorEsperado;
            }

            $lineasAnita = $this->listarPendmovp($clave);
            $esperadas = $oc->ordencompra_articulos->count();
            // Faltan líneas (0 tras rollback incompleto) o sobran (duplicados): regrabar detalle.
            if ($esperadas > 0 && count($lineasAnita) !== $esperadas) {
                foreach ($lineasAnita as $linea) {
                    $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
                    if ($nroInterno > 0) {
                        $cantentrPreserve[$nroInterno] = max(
                            (float) ($cantentrPreserve[$nroInterno] ?? 0),
                            (float) ($linea->penvp_cantentr ?? 0)
                        );
                    }
                }

                $internosValidos = [];
                foreach ($lineasAnita as $linea) {
                    $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
                    if ($nroInterno > 0) {
                        $internosValidos[$nroInterno] = true;
                    }
                }
                $liberados = OrdencompraAnitaLineaSupport::liberarNroInternosInvalidos($oc, $internosValidos);
                if ($liberados !== []) {
                    $acciones[] = 'reasignó '.count($liberados).' penvp_nro_interno inválidos';
                }
                OrdencompraAnitaLineaSupport::asignarClavesLineas($oc);
                $this->cargarRelaciones($oc);

                // Preserva cantentr solo si el interno sigue siendo el mismo; remapear con
                // repararPendmovpFaltanteDesdeRecepciones cuando los internos cambiaron.
                $this->eliminarDetalle($clave, false);
                $this->grabarDetalle($oc, $ctx, $clave);
                $this->restaurarCantentrLineas($clave, $cantentrPreserve);
                $acciones[] = count($lineasAnita) > $esperadas
                    ? 'eliminó líneas duplicadas y regrabó detalle'
                    : 'regrabó pendmovp faltante (Anita='.count($lineasAnita).', ERP='.$esperadas.')';
            }
        }

        $aux = $this->repararRegistrosAnitaFaltantes($oc);
        if (($aux['legcompra'] ?? '') === 'insertado') {
            $acciones[] = 'insertó legcompra';
        }
        if (($aux['pendfecha'] ?? '') === 'insertado') {
            $acciones[] = 'insertó pendfecha';
        }

        if (! $this->existeOccuota($clave) && $oc->ordencompra_comprobantes->isNotEmpty()) {
            try {
                $this->grabarComprobantesCuotas($oc, $ctx, $clave);
                $acciones[] = 'insertó occuota/ocfpagocuota';
            } catch (\Throwable $e) {
                // Restos parciales (ocfpago sin occuota, o viceversa): limpiar y regrabar.
                $this->eliminarComprobantesCuotas($clave);
                $this->grabarComprobantesCuotas($oc, $ctx, $clave);
                $acciones[] = 'regrabó occuota/ocfpagocuota (tras limpieza)';
                Log::warning('OrdencompraAnitaBridge: occuota reparación con limpieza', [
                    'numero' => $numero,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $restantes = $this->diagnosticarSincronizacionAnita($oc)['problemas'];

        return [
            'numero' => $numero,
            'acciones' => $acciones,
            'problemas_restantes' => $restantes,
        ];
    }

    /**
     * Repara OC con cabecera Anita sin pendmovp (o internos ERP que no pertenecen a esta OC):
     * reasigna penvp_nro_interno, alinea líneas de recepción/devolución y regraba detalle
     * con penvp_cantentr neto de recepciones confirmadas.
     *
     * @return array{
     *   numero: int,
     *   acciones: list<string>,
     *   mapa_internos: array<int, int>,
     *   cantentr: array<int, float>,
     *   problemas_restantes: list<string>
     * }
     */
    public function repararPendmovpFaltanteDesdeRecepciones(Ordencompra $oc): array
    {
        if (! $this->habilitado()) {
            throw new \RuntimeException('Escritura OC Anita deshabilitada.');
        }

        $this->cargarRelaciones($oc);
        $this->validarCabeceraMinima($oc);
        $this->fijarEmpresaPath($oc);

        $numero = (int) $oc->numeroordencompra;
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        $acciones = [];

        $lineasAnita = $this->listarPendmovp($clave);
        $internosValidos = [];
        foreach ($lineasAnita as $linea) {
            $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
            if ($nroInterno > 0) {
                $internosValidos[$nroInterno] = true;
            }
        }

        $antesPorOcArt = [];
        foreach ($oc->ordencompra_articulos as $ocArt) {
            $antesPorOcArt[(int) $ocArt->id] = (int) ($ocArt->penvp_nro_interno ?? 0);
        }

        $liberados = OrdencompraAnitaLineaSupport::liberarNroInternosInvalidos($oc, $internosValidos);
        if ($liberados !== []) {
            $acciones[] = 'liberó '.count($liberados).' penvp_nro_interno inválidos';
        }

        OrdencompraAnitaLineaSupport::asignarClavesLineas($oc);
        $this->cargarRelaciones($oc);

        $mapaInternos = [];
        foreach ($oc->ordencompra_articulos as $ocArt) {
            $id = (int) $ocArt->id;
            $nuevo = (int) ($ocArt->penvp_nro_interno ?? 0);
            $mapaInternos[$id] = $nuevo;
            $antes = $antesPorOcArt[$id] ?? 0;
            if ($antes !== $nuevo && $nuevo > 0) {
                $acciones[] = "oc_art {$id}: interno {$antes} → {$nuevo}";
            }
        }

        $alineadas = $this->alinearPenvpRecepcionesConOc($oc, $mapaInternos);
        if ($alineadas > 0) {
            $acciones[] = "alineó {$alineadas} línea(s) de recepción/devolución";
        }

        $cantentrPorInterno = $this->cantentrNetoDesdeRecepcionesConfirmadas($oc);
        $acciones[] = 'cantentr neto: '.json_encode($cantentrPorInterno);

        $ctx = OrdencompraAnitaErpContext::desdeUsuarioId(
            $oc->creousuario_id !== null ? (int) $oc->creousuario_id : null
        );

        if ($this->leerCabeceraPendmaep($clave) === null) {
            $this->insertarPendmaep($oc, $ctx, $clave);
            $acciones[] = 'insertó pendmaep';
        }

        $this->eliminarDetalle($clave, false);
        $this->grabarDetalle($oc, $ctx, $clave);
        $this->restaurarCantentrLineas($clave, $cantentrPorInterno);
        $acciones[] = 'regrabó pendmovp/movpresup con cantentr';

        $estadoAnita = $this->resolverEstadoCabeceraDesdeCantentr($oc, $cantentrPorInterno);
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'update',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'valores' => RecepcionProveedorAnitaEscrituraSupport::penmpEstadoUpdateSet($estadoAnita),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
        ], 'ordencompra pendmaep estado reparacion pendmovp');
        $acciones[] = 'estado cabecera='.$estadoAnita;

        $aux = $this->repararRegistrosAnitaFaltantes($oc);
        if (($aux['legcompra'] ?? '') === 'insertado') {
            $acciones[] = 'insertó legcompra';
        }
        if (($aux['pendfecha'] ?? '') === 'insertado') {
            $acciones[] = 'insertó pendfecha';
        }

        return [
            'numero' => $numero,
            'acciones' => $acciones,
            'mapa_internos' => $mapaInternos,
            'cantentr' => $cantentrPorInterno,
            'problemas_restantes' => $this->diagnosticarSincronizacionAnita($oc)['problemas'],
        ];
    }

    /**
     * @param  array<int, int>  $mapaInternos  ordencompra_articulo.id => penvp_nro_interno
     */
    private function alinearPenvpRecepcionesConOc(Ordencompra $oc, array $mapaInternos): int
    {
        $oc->loadMissing('ordencompra_articulos');
        $datosPorOcArt = [];
        foreach ($oc->ordencompra_articulos as $ocArt) {
            $datosPorOcArt[(int) $ocArt->id] = [
                'penvp_orden' => (int) ($ocArt->penvp_orden ?? 0),
                'penvp_nro_interno' => (int) ($mapaInternos[(int) $ocArt->id] ?? $ocArt->penvp_nro_interno ?? 0),
            ];
        }

        $recepciones = Recepcion_Proveedor::query()
            ->where('ordencompra_id', $oc->id)
            ->with('recepcion_proveedor_articulos')
            ->get();

        $alineadas = 0;
        foreach ($recepciones as $recepcion) {
            foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
                $fuenteId = (int) ($linea->ordencompra_articulo_id ?? 0);
                if ($fuenteId <= 0) {
                    $fuenteId = (int) ($linea->ordencompra_articulo_sustituido_id ?? 0);
                }
                if ($fuenteId <= 0 || ! isset($datosPorOcArt[$fuenteId])) {
                    continue;
                }
                $datos = $datosPorOcArt[$fuenteId];
                $cambios = [];
                if ($datos['penvp_nro_interno'] > 0
                    && (int) ($linea->penvp_nro_interno ?? 0) !== $datos['penvp_nro_interno']) {
                    $cambios['penvp_nro_interno'] = $datos['penvp_nro_interno'];
                }
                if ($datos['penvp_orden'] > 0
                    && (int) ($linea->penvp_orden ?? 0) !== $datos['penvp_orden']) {
                    $cambios['penvp_orden'] = $datos['penvp_orden'];
                }
                if ($cambios !== []) {
                    $linea->update($cambios);
                    $alineadas++;
                }
            }
        }

        return $alineadas;
    }

    /**
     * @return array<int, float> penvp_nro_interno => cantentr neto
     */
    private function cantentrNetoDesdeRecepcionesConfirmadas(Ordencompra $oc): array
    {
        $oc->loadMissing('ordencompra_articulos');
        $netoPorOcArt = [];
        foreach ($oc->ordencompra_articulos as $ocArt) {
            $netoPorOcArt[(int) $ocArt->id] = 0.0;
        }

        $recepciones = Recepcion_Proveedor::query()
            ->where('ordencompra_id', $oc->id)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->with('recepcion_proveedor_articulos')
            ->get();

        foreach ($recepciones as $recepcion) {
            $signo = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION ? -1.0 : 1.0;
            foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
                $fuenteId = (int) ($linea->ordencompra_articulo_id ?? 0);
                if ($fuenteId <= 0) {
                    $fuenteId = (int) ($linea->ordencompra_articulo_sustituido_id ?? 0);
                }
                if ($fuenteId <= 0 || ! array_key_exists($fuenteId, $netoPorOcArt)) {
                    continue;
                }
                $delta = ((float) $linea->cantidad + (float) ($linea->cantidad_rechazada ?? 0)) * $signo;
                $netoPorOcArt[$fuenteId] += $delta;
            }
        }

        $porInterno = [];
        foreach ($oc->ordencompra_articulos as $ocArt) {
            $nro = (int) ($ocArt->penvp_nro_interno ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $neto = max(0.0, (float) ($netoPorOcArt[(int) $ocArt->id] ?? 0));
            $porInterno[$nro] = $neto;
        }

        return $porInterno;
    }

    /**
     * @param  array<int, float>  $cantentrPorInterno
     */
    private function resolverEstadoCabeceraDesdeCantentr(Ordencompra $oc, array $cantentrPorInterno): string
    {
        $oc->loadMissing('ordencompra_articulos');
        if ($oc->ordencompra_articulos->isEmpty()) {
            return '0';
        }

        $todasCompletas = true;
        $algunaEntrada = false;
        foreach ($oc->ordencompra_articulos as $ocArt) {
            $nro = (int) ($ocArt->penvp_nro_interno ?? 0);
            $pedida = (float) ($ocArt->cantidad ?? 0);
            $entr = (float) ($cantentrPorInterno[$nro] ?? 0);
            if ($entr > 0.000001) {
                $algunaEntrada = true;
            }
            if ($entr + 0.000001 < $pedida) {
                $todasCompletas = false;
            }
        }

        if ($todasCompletas && $algunaEntrada) {
            return '2';
        }
        if ($algunaEntrada) {
            return '1';
        }

        return '0';
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function leerCabeceraPendmaep(array $clave): ?object
    {
        $api = new ApiAnita;
        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'campos' => 'penmp_nro,penmp_proveedor,penmp_estado,penmp_fecha,penmp_empresa',
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
            'limit' => 'FIRST 1',
        ], 'ordencompra pendmaep leer');

        return ApiAnita::primeraFilaLista((string) $raw);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return list<object>
     */
    private function listarPendmovp(array $clave): array
    {
        $api = new ApiAnita;
        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.linea'),
            'campos' => 'penvp_nro,penvp_orden,penvp_nro_interno,penvp_proveedor,penvp_cantidad,penvp_cantentr,penvp_articulo',
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmovp($clave),
            'limit' => 'FIRST 200',
        ], 'ordencompra pendmovp listar');

        $decoded = json_decode((string) $raw);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($row) => is_object($row)));
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function existeOccuota(array $clave): bool
    {
        $api = new ApiAnita;
        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cuota'),
            'campos' => 'occ_nro',
            'whereArmado' => OrdencompraAnitaWhereSupport::occuota($clave),
            'limit' => 'FIRST 1',
        ], 'ordencompra occuota existe');

        return ApiAnita::primeraFilaLista((string) $raw) !== null;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function actualizarProveedorPendmaep(array $clave, string $proveedor6): void
    {
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'update',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'valores' => RecepcionProveedorAnitaEscrituraSupport::updateSet([
                'penmp_proveedor' => RecepcionProveedorAnitaEscrituraSupport::proveedorSql($proveedor6),
            ]),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
        ], 'ordencompra pendmaep proveedor pad');
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  array<int, float>  $cantentrPorInterno
     */
    private function restaurarCantentrLineas(array $clave, array $cantentrPorInterno): void
    {
        if ($cantentrPorInterno === []) {
            return;
        }

        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();
        foreach ($cantentrPorInterno as $nroInterno => $cantentr) {
            if ((int) $nroInterno <= 0 || (float) $cantentr <= 0) {
                continue;
            }
            $this->anitaEscritura($api, [
                'acc' => 'update',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.linea'),
                'valores' => RecepcionProveedorAnitaEscrituraSupport::pendmovpCantentrUpdateSet((float) $cantentr),
                'whereArmado' => OrdencompraAnitaWhereSupport::pendmovp($clave)
                    .' AND penvp_nro_interno='.(int) $nroInterno,
            ], 'ordencompra pendmovp cantentr restore '.$nroInterno);
        }
    }

    /**
     * Repara penvp_desc vacío (descripción de línea) y occ_cond_pago = 0 (condición de pago)
     * en OCs ya grabadas en Anita desde el ERP, con UPDATE puntual (sin borrar/reinsertar
     * líneas, para no perder penvp_cantentr/penvp_cantfact de recepciones ya aplicadas).
     *
     * @return array{numero: int, penvp_desc: int, occ_cond_pago: int}
     */
    public function repararDescripcionCondicionpagoAnita(Ordencompra $oc, bool $dryRun = false): array
    {
        if (! $this->habilitado()) {
            throw new \RuntimeException('Escritura OC Anita deshabilitada.');
        }

        $this->cargarRelaciones($oc);
        $this->fijarEmpresaPath($oc);
        $numero = (int) $oc->numeroordencompra;
        if ($numero <= 0) {
            throw new \RuntimeException('La orden de compra no tiene número asignado.');
        }

        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        if (! $this->existePendmaep($clave)) {
            throw new \RuntimeException("La OC #{$numero} no existe en pendmaep (Anita).");
        }

        $ctx = OrdencompraAnitaErpContext::desdeUsuarioId(
            $oc->creousuario_id !== null ? (int) $oc->creousuario_id : null
        );

        $result = ['numero' => $numero, 'penvp_desc' => 0, 'occ_cond_pago' => 0];
        $result['penvp_desc'] = $this->repararPenvpDesc($oc, $ctx, $clave, $dryRun);
        $result['occ_cond_pago'] = $this->repararOccCondPago($oc, $ctx, $clave, $dryRun);

        return $result;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function repararPenvpDesc(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave, bool $dryRun): int
    {
        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();

        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.linea'),
            'campos' => 'penvp_orden, penvp_nro_interno, penvp_desc',
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmovp($clave),
        ], 'ordencompra reparar penvp_desc list');

        $filas = ApiAnita::decodificarListaFilas((string) $raw);
        if ($filas === []) {
            return 0;
        }

        $lineasPorInterno = [];
        $lineasPorOrden = [];
        foreach ($oc->ordencompra_articulos as $linea) {
            $lineasPorInterno[(int) ($linea->penvp_nro_interno ?? 0)] = $linea;
            $lineasPorOrden[(int) ($linea->penvp_orden ?? 0)] = $linea;
        }

        $reparadas = 0;
        foreach ($filas as $fila) {
            if (trim((string) ($fila->penvp_desc ?? '')) !== '') {
                continue;
            }

            $nroInterno = (int) ($fila->penvp_nro_interno ?? 0);
            $orden = (int) ($fila->penvp_orden ?? 0);
            $linea = ($nroInterno > 0 ? ($lineasPorInterno[$nroInterno] ?? null) : null)
                ?? ($orden > 0 ? ($lineasPorOrden[$orden] ?? null) : null);
            if ($linea === null) {
                continue;
            }

            $desc = trim((string) ($linea->detalle ?? ''));
            if ($desc === '') {
                $desc = $ctx->descripcionArticulo((int) $linea->articulo_id);
            }
            if ($desc === '') {
                continue;
            }

            $where = OrdencompraAnitaWhereSupport::pendmovp($clave);
            if ($nroInterno > 0) {
                $where .= ' AND penvp_nro_interno='.$nroInterno;
            } else {
                $where .= ' AND penvp_orden='.$orden;
            }

            if (! $dryRun) {
                $this->anitaEscritura($api, [
                    'acc' => 'update',
                    'sistema' => $sistema,
                    'tabla' => config('ordencompra_anita.tablas.linea'),
                    'valores' => 'penvp_desc = '.RecepcionProveedorAnitaEscrituraSupport::textoSql(substr($desc, 0, 30), 30),
                    'whereArmado' => $where,
                ], 'ordencompra reparar penvp_desc update');
            }
            $reparadas++;
        }

        return $reparadas;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function repararOccCondPago(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave, bool $dryRun): int
    {
        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();

        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.cuota'),
            'campos' => 'occ_nro_cuota, occ_cond_pago',
            'whereArmado' => OrdencompraAnitaWhereSupport::occuota($clave).' ORDER BY occ_nro_cuota',
        ], 'ordencompra reparar occ_cond_pago list');

        $filas = ApiAnita::decodificarListaFilas((string) $raw);
        if ($filas === []) {
            return 0;
        }

        $comprobantes = $oc->ordencompra_comprobantes->sortBy('id')->values();
        $condPagoCabecera = $ctx->condicionpagoCabecera($oc);

        $reparadas = 0;
        foreach ($filas as $fila) {
            if ((int) ($fila->occ_cond_pago ?? 0) > 0) {
                continue;
            }

            $nroCuota = (int) ($fila->occ_nro_cuota ?? 0);
            $comprobante = $comprobantes->get($nroCuota - 1);
            $condPago = $comprobante !== null
                ? $ctx->codigoCondicionpago((int) ($comprobante->condicionpago_id ?? 0))
                : 0;
            if ($condPago <= 0) {
                $condPago = $condPagoCabecera;
            }
            if ($condPago <= 0) {
                continue;
            }

            if (! $dryRun) {
                $this->anitaEscritura($api, [
                    'acc' => 'update',
                    'sistema' => $sistema,
                    'tabla' => config('ordencompra_anita.tablas.cuota'),
                    'valores' => 'occ_cond_pago = '.RecepcionProveedorAnitaEscrituraSupport::enteroSql($condPago),
                    'whereArmado' => OrdencompraAnitaWhereSupport::occuota($clave).' AND occ_nro_cuota='.$nroCuota,
                ], 'ordencompra reparar occ_cond_pago update');
            }
            $reparadas++;
        }

        return $reparadas;
    }

    /**
     * Escribe en Anita solo los comprobantes/cuotas (occuota + ocfpagocuota) y actualiza pendfecha
     * de una OC ya existente en pendmaep. Idempotente: borra occuota/ocfpagocuota previos y reinserta.
     * No toca pendmovp/movpresup (no afecta cantidades recibidas).
     *
     * @return array{numero: int, comprobantes: int}
     */
    public function sincronizarComprobantesCuotasAnita(Ordencompra $oc): array
    {
        if (! $this->habilitado()) {
            throw new \RuntimeException('Escritura OC Anita deshabilitada.');
        }

        $this->cargarRelaciones($oc);
        $this->fijarEmpresaPath($oc);
        $numero = (int) $oc->numeroordencompra;
        if ($numero <= 0) {
            throw new \RuntimeException('La orden de compra no tiene número asignado.');
        }

        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);
        if (! $this->existePendmaep($clave)) {
            throw new \RuntimeException("La OC #{$numero} no existe en pendmaep (Anita).");
        }

        $ctx = OrdencompraAnitaErpContext::desdeUsuarioId(
            $oc->creousuario_id !== null ? (int) $oc->creousuario_id : null
        );

        $this->eliminarComprobantesCuotas($clave);
        $this->grabarComprobantesCuotas($oc, $ctx, $clave);
        $this->grabarPendfecha($oc, $ctx, $clave);

        return [
            'numero' => $numero,
            'comprobantes' => $oc->ordencompra_comprobantes->count(),
        ];
    }

    public function sincronizarBaja(Ordencompra $oc): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $this->fijarEmpresaPath($oc);
        $clave = OrdencompraAnitaWhereSupport::claveDesdeOrdencompra($oc);

        if (! $this->existePendmaep($clave)) {
            return;
        }

        $this->assertSinRecepcionesAplicadas($clave);

        $oc->loadMissing('proveedores');
        $ctx = OrdencompraAnitaErpContext::desdeUsuarioActual();
        $estado = ['cabecera_nueva' => false, 'detalle_grabado' => true, 'numero' => (int) $oc->numeroordencompra];

        try {
            $this->eliminarDetalle($clave);
            $this->eliminarPendfecha($clave, $ctx->codigoProveedor6((int) $oc->proveedor_id));
            $this->eliminarLegcompra((int) $oc->numeroordencompra);
            $this->eliminarPendmaep($clave);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Error al eliminar la orden de compra en Anita: '.$e->getMessage(), 0, $e);
        }
    }

    private function cargarRelaciones(Ordencompra $oc): void
    {
        $oc->loadMissing([
            'empresas', 'centrocostos', 'proveedores', 'requisiciones', 'transportes',
            'condicioncompras', 'condicionentregas', 'condicionpagos',
            'ordencompra_articulos.articulos.categorias',
            'ordencompra_articulos.articulos.impuestos',
            'ordencompra_articulos.articulos.unidadesdemedidas',
            'ordencompra_articulos.monedas',
            'ordencompra_articulos.centrocostos_destino',
            'ordencompra_articulos.partidagastos.presupuestos',
            'ordencompra_articulos.partidagastos.presupuesto_escenarios',
            'ordencompra_articulos.capexs',
            'ordencompra_comprobantes.condicionpagos.condicionpagocuotas',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.formapagos',
        ]);
    }

    private function validarCabeceraMinima(Ordencompra $oc): void
    {
        if ((int) $oc->numeroordencompra <= 0) {
            throw new \RuntimeException('La orden de compra no tiene número asignado.');
        }
        if (empty($oc->proveedor_id)) {
            throw new \RuntimeException('Proveedor obligatorio para grabar en Anita.');
        }
        if (empty($oc->empresa_id) || empty($oc->centrocosto_id)) {
            throw new \RuntimeException('Empresa y centro de costo son obligatorios para Anita.');
        }
        if ($oc->ordencompra_articulos->isEmpty()) {
            throw new \RuntimeException('La orden de compra debe tener al menos un ítem.');
        }

        foreach ($oc->ordencompra_articulos as $linea) {
            if (empty($linea->articulo_id) || (float) ($linea->cantidad ?? 0) <= 0) {
                throw new \RuntimeException('Cada ítem debe tener artículo y cantidad mayor a cero.');
            }
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function existePendmaep(array $clave): bool
    {
        $api = new ApiAnita;
        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'campos' => 'penmp_nro',
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
            'limit' => 'FIRST 1',
        ], 'ordencompra pendmaep existe');

        return ApiAnita::primeraFilaLista((string) $raw) !== null;
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function assertSinRecepcionesAplicadas(array $clave): void
    {
        $api = new ApiAnita;
        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.linea'),
            'campos' => 'penvp_cantentr',
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmovp($clave),
        ], 'ordencompra pendmovp cantentr baja');

        $filas = ApiAnita::decodificarListaFilas((string) $raw);
        foreach ($filas as $fila) {
            if ((float) ($fila->penvp_cantentr ?? 0) > 0) {
                throw new \RuntimeException(
                    'No se puede eliminar la OC #'.$clave['nro'].' en Anita: tiene cantidades recibidas (penvp_cantentr > 0).'
                );
            }
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function insertarPendmaep(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $insert = OrdencompraAnitaEscrituraSupport::pendmaepInsert($oc, $ctx, $clave);
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'insert',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'campos' => $insert['campos'],
            'valores' => $insert['valores'],
        ], 'ordencompra pendmaep insert');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function actualizarPendmaep(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'update',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'valores' => OrdencompraAnitaEscrituraSupport::pendmaepUpdateSet($oc, $ctx, $clave),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
        ], 'ordencompra pendmaep update');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function grabarDetalle(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $codigoProveedor = $ctx->codigoProveedor6((int) $oc->proveedor_id);
        $lineas = $oc->ordencompra_articulos->sortBy([['penvp_orden', 'asc'], ['id', 'asc']]);

        foreach ($lineas as $linea) {
            $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
            if ($nroInterno <= 0) {
                throw new \RuntimeException('Línea OC sin penvp_nro_interno antes de grabar Anita.');
            }

            $insertLinea = OrdencompraAnitaEscrituraSupport::pendmovpInsert($oc, $linea, $ctx, $clave, $codigoProveedor);
            $api = new ApiAnita;
            $this->anitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
                'tabla' => config('ordencompra_anita.tablas.linea'),
                'campos' => $insertLinea['campos'],
                'valores' => $insertLinea['valores'],
            ], 'ordencompra pendmovp insert');

            $insertMovp = OrdencompraAnitaEscrituraSupport::movpresupInsert($oc, $linea, $ctx, $clave);
            $this->anitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
                'tabla' => config('ordencompra_anita.tablas.presupuesto_linea'),
                'campos' => $insertMovp['campos'],
                'valores' => $insertMovp['valores'],
            ], 'ordencompra movpresup insert');

            foreach (OrdencompraAnitaEscrituraSupport::ocvleyInsertsDesdeLinea($linea, $clave) as $insertOcvley) {
                $this->anitaEscritura($api, [
                    'acc' => 'insert',
                    'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
                    'tabla' => config('ordencompra_anita.tablas.leyenda_linea'),
                    'campos' => $insertOcvley['campos'],
                    'valores' => $insertOcvley['valores'],
                ], 'ordencompra ocvley insert');
            }
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function grabarComprobantesCuotas(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $comprobantes = $oc->ordencompra_comprobantes->sortBy('id')->values();
        if ($comprobantes->isEmpty()) {
            return;
        }

        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();
        $nroCuotaOcc = 0;

        foreach ($comprobantes as $comprobante) {
            $nroCuotaOcc++;
            $insertOcc = OrdencompraAnitaEscrituraSupport::occuotaInsert($comprobante, $ctx, $clave, $nroCuotaOcc, $oc);
            $this->anitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.cuota'),
                'campos' => $insertOcc['campos'],
                'valores' => $insertOcc['valores'],
            ], 'ordencompra occuota insert');

            $cuotas = $comprobante->ordencompra_comprobante_cuotas->sortBy('id')->values();
            if ($cuotas->isEmpty()) {
                $cuotasExpandidas = OrdencompraAnitaOcfpagoCuotaExpander::desdeComprobante($comprobante);
                $nroCuotaFpago = 0;
                foreach ($cuotasExpandidas as $cuota) {
                    $nroCuotaFpago++;
                    $insertOcfp = OrdencompraAnitaEscrituraSupport::ocfpagocuotaInsertDesdeArray(
                        $cuota,
                        $clave,
                        $nroCuotaOcc,
                        $nroCuotaFpago,
                        $ctx
                    );
                    $this->anitaEscritura($api, [
                        'acc' => 'insert',
                        'sistema' => $sistema,
                        'tabla' => config('ordencompra_anita.tablas.cuota_fpago'),
                        'campos' => $insertOcfp['campos'],
                        'valores' => $insertOcfp['valores'],
                    ], 'ordencompra ocfpagocuota insert expand');
                }

                continue;
            }

            $nroCuotaFpago = 0;
            foreach ($cuotas as $cuota) {
                $nroCuotaFpago++;
                $insertOcfp = OrdencompraAnitaEscrituraSupport::ocfpagocuotaInsert(
                    $cuota,
                    $ctx,
                    $clave,
                    $nroCuotaOcc,
                    $nroCuotaFpago
                );
                $this->anitaEscritura($api, [
                    'acc' => 'insert',
                    'sistema' => $sistema,
                    'tabla' => config('ordencompra_anita.tablas.cuota_fpago'),
                    'campos' => $insertOcfp['campos'],
                    'valores' => $insertOcfp['valores'],
                ], 'ordencompra ocfpagocuota insert');
            }
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function eliminarComprobantesCuotas(array $clave): void
    {
        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();

        $this->anitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.cuota_fpago'),
            'whereArmado' => OrdencompraAnitaWhereSupport::ocfpagocuota($clave),
        ], 'ordencompra ocfpagocuota delete');

        $this->anitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.cuota'),
            'whereArmado' => OrdencompraAnitaWhereSupport::occuota($clave),
        ], 'ordencompra occuota delete');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function eliminarDetalle(array $clave, bool $incluirCuotas = true): void
    {
        if ($incluirCuotas) {
            $this->eliminarComprobantesCuotas($clave);
        }

        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();

        $this->anitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.leyenda_linea'),
            'whereArmado' => OrdencompraAnitaWhereSupport::ocvley($clave),
        ], 'ordencompra ocvley delete');

        $this->anitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.presupuesto_linea'),
            'whereArmado' => OrdencompraAnitaWhereSupport::movpresup($clave),
        ], 'ordencompra movpresup delete');

        $this->anitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.linea'),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmovp($clave),
        ], 'ordencompra pendmovp delete');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function grabarPendfecha(Ordencompra $oc, OrdencompraAnitaErpContext $ctx, array $clave): void
    {
        $proveedor6 = $ctx->codigoProveedor6((int) $oc->proveedor_id);
        $this->eliminarPendfecha($clave, $proveedor6);

        $fechas = OrdencompraAnitaEscrituraSupport::fechasPendfechaDesdeOc($oc, $ctx);
        $insert = OrdencompraAnitaEscrituraSupport::pendfechaInsert(
            $clave,
            $proveedor6,
            (int) $fechas['fecha_fac'],
            (int) $fechas['fecha_pago']
        );

        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'insert',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.fecha_oc'),
            'campos' => $insert['campos'],
            'valores' => $insert['valores'],
        ], 'ordencompra pendfecha insert');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function eliminarPendfecha(array $clave, string $proveedor6): void
    {
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.fecha_oc'),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendfecha($clave, $proveedor6),
        ], 'ordencompra pendfecha delete');
    }

    private function grabarLegcompraAlta(Ordencompra $oc, OrdencompraAnitaErpContext $ctx): void
    {
        $insert = OrdencompraAnitaEscrituraSupport::legcompraInsert(
            (int) $oc->numeroordencompra,
            $ctx,
            OrdencompraAnitaEscrituraSupport::sectorLegajoCompras(),
            'Alta de OC'
        );

        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'insert',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.historia'),
            'campos' => $insert['campos'],
            'valores' => $insert['valores'],
        ], 'ordencompra legcompra insert alta');
    }

    private function asegurarLegcompra(Ordencompra $oc, OrdencompraAnitaErpContext $ctx): void
    {
        if ($this->existeLegcompra((int) $oc->numeroordencompra)) {
            return;
        }

        $this->grabarLegcompraAlta($oc, $ctx);
    }

    private function existeLegcompra(int $numeroOc): bool
    {
        $api = new ApiAnita;
        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.historia'),
            'campos' => 'legc_id',
            'whereArmado' => OrdencompraAnitaWhereSupport::legcompraPorNumeroOc($numeroOc),
            'limit' => 'FIRST 1',
        ], 'ordencompra legcompra existe');

        return ApiAnita::primeraFilaLista((string) $raw) !== null;
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function existePendfecha(array $clave, string $proveedor6): bool
    {
        $api = new ApiAnita;
        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.fecha_oc'),
            'campos' => 'penpf_nro',
            'whereArmado' => OrdencompraAnitaWhereSupport::pendfecha($clave, $proveedor6),
            'limit' => 'FIRST 1',
        ], 'ordencompra pendfecha existe');

        return ApiAnita::primeraFilaLista((string) $raw) !== null;
    }

    private function eliminarLegcompra(int $numeroOc): void
    {
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.historia'),
            'whereArmado' => OrdencompraAnitaWhereSupport::legcompraPorNumeroOc($numeroOc),
        ], 'ordencompra legcompra delete');
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function eliminarPendmaep(array $clave): void
    {
        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
        ], 'ordencompra pendmaep delete');
    }

    /**
     * @param  array{cabecera_nueva: bool, detalle_grabado: bool, comprobantes_grabados: bool, pendfecha_grabado?: bool, legcompra_grabado?: bool, numero: int}  $estado
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function revertir(array $clave, array $estado): void
    {
        try {
            if ($estado['detalle_grabado'] || ($estado['comprobantes_grabados'] ?? false)) {
                $this->eliminarDetalle($clave);
            }
            if ($estado['pendfecha_grabado'] ?? false) {
                $this->eliminarPendfecha($clave, $this->proveedor6DesdeClave($clave));
            }
            if ($estado['legcompra_grabado'] ?? false) {
                $this->eliminarLegcompra((int) $estado['numero']);
            }
            if ($estado['cabecera_nueva']) {
                $this->eliminarPendmaep($clave);
            }
        } catch (\Throwable $e) {
            Log::error('OrdencompraAnitaBridge: rollback incompleto', [
                'numero_oc' => $estado['numero'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function proveedor6DesdeClave(array $clave): string
    {
        $api = new ApiAnita;
        $raw = $this->anitaEscritura($api, [
            'acc' => 'list',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'campos' => 'penmp_proveedor',
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
            'limit' => 'FIRST 1',
        ], 'ordencompra pendmaep proveedor rollback');

        $fila = ApiAnita::primeraFilaLista((string) $raw);

        return str_pad(trim((string) ($fila->penmp_proveedor ?? '0')), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{cabecera_nueva: bool, detalle_grabado: bool, comprobantes_grabados: bool, numero: int}  $estado
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  array{cabecera: ?object, lineas: list<object>, movpresup: list<object>, ocvley: list<object>, occuota: list<object>, ocfpagocuota: list<object>, pendfecha: ?object}  $backup
     */
    private function revertirConBackup(array $clave, array $estado, array $backup): void
    {
        $numero = $estado['numero'] ?? null;
        $errores = [];

        // Cada paso en try propio: un lock en cabecera no debe impedir restaurar pendmovp
        // (incidente OC 221962: eliminarDetalle + fallo restore cabecera → OC sin líneas).
        $detalleFueTocado = ! empty($estado['detalle_grabado'])
            || ! empty($estado['comprobantes_grabados']);
        $backupTieneDetalle = ($backup['lineas'] ?? []) !== []
            || ($backup['movpresup'] ?? []) !== []
            || ($backup['occuota'] ?? []) !== []
            || ($backup['ocvley'] ?? []) !== [];

        if ($detalleFueTocado || $backupTieneDetalle) {
            try {
                $this->eliminarDetalle($clave);
                $this->restaurarDetalleDesdeBackup($backup, $clave);
            } catch (\Throwable $e) {
                $errores[] = 'detalle: '.$e->getMessage();
            }
        }

        try {
            if ($estado['cabecera_nueva']) {
                $this->eliminarPendmaep($clave);
            } elseif ($backup['cabecera'] !== null) {
                $this->restaurarCabeceraDesdeBackup($backup['cabecera'], $clave);
            }
        } catch (\Throwable $e) {
            $errores[] = 'cabecera: '.$e->getMessage();
        }

        if ($estado['pendfecha_grabado'] ?? false) {
            try {
                $this->eliminarPendfecha($clave, $this->proveedor6DesdeClave($clave));
                if ($backup['pendfecha'] !== null) {
                    $this->restaurarPendfechaDesdeBackup($backup['pendfecha']);
                }
            } catch (\Throwable $e) {
                $errores[] = 'pendfecha: '.$e->getMessage();
            }
        }

        if ($errores !== []) {
            Log::error('OrdencompraAnitaBridge: rollback actualización incompleto', [
                'numero_oc' => $numero,
                'error' => implode(' | ', $errores),
            ]);
        }
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{cabecera: ?object, lineas: list<object>, movpresup: list<object>, ocvley: list<object>, occuota: list<object>, ocfpagocuota: list<object>, pendfecha: ?object}
     */
    private function leerBackupAnita(array $clave): array
    {
        $api = new ApiAnita;
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();
        $whereCab = OrdencompraAnitaWhereSupport::pendmaep($clave);

        $cabRaw = $this->anitaCall($api, [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'campos' => implode(', ', [
                'penmp_proveedor', 'penmp_tipo', 'penmp_letra', 'penmp_sucursal', 'penmp_nro',
                'penmp_fecha', 'penmp_fecha_ent', 'penmp_cond_compra', 'penmp_cond_entrega', 'penmp_cond_pago',
                'penmp_entrega', 'penmp_dto', 'penmp_expreso', 'penmp_cod_mon', 'penmp_cotizacion',
                'penmp_estado', 'penmp_leyenda', 'penmp_requisicion', 'penmp_ccosto', 'penmp_ccosto_dest',
                'penmp_empresa', 'penmp_es_anticipo', 'penmp_usuario_ini', 'penmp_fecha_ing', 'penmp_hora_ing',
                'penmp_estado_aprob', 'penmp_legajo',
            ]),
            'whereArmado' => $whereCab,
            'limit' => 'FIRST 1',
        ]);

        $lineasRaw = $this->anitaCall($api, [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.linea'),
            'campos' => implode(', ', [
                'penvp_proveedor', 'penvp_tipo', 'penvp_letra', 'penvp_sucursal', 'penvp_nro', 'penvp_orden',
                'penvp_articulo', 'penvp_desc', 'penvp_agrupacion', 'penvp_unidad_med', 'penvp_cantidad',
                'penvp_cantentr', 'penvp_cantfact', 'penvp_precio', 'penvp_dto_art', 'penvp_deposito',
                'penvp_tipo_iva', 'penvp_fecha', 'penvp_incl_imp', 'penvp_cod_mon', 'penvp_partida',
                'penvp_fecha_ent', 'penvp_ccosto', 'penvp_requisicion', 'penvp_empresa', 'penvp_nro_interno',
            ]),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmovp($clave),
        ]);

        $movpRaw = $this->anitaCall($api, [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.presupuesto_linea'),
            'campos' => implode(', ', [
                'movp_tipo', 'movp_letra', 'movp_sucursal', 'movp_nro', 'movp_nro_interno', 'movp_partida',
                'movp_presupuesto', 'movp_escenario', 'movp_proyecto', 'movp_mes', 'movp_cod_proyecto',
                'movp_importe', 'movp_articulo', 'movp_fecha', 'movp_cotizacion',
            ]),
            'whereArmado' => OrdencompraAnitaWhereSupport::movpresup($clave),
        ]);

        $occRaw = $this->anitaCall($api, [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.cuota'),
            'campos' => implode(', ', [
                'occ_tipo', 'occ_letra', 'occ_sucursal', 'occ_nro', 'occ_nro_cuota',
                'occ_fecha_vto', 'occ_monto', 'occ_cond_pago', 'occ_medio_pago', 'occ_detalle',
            ]),
            'whereArmado' => OrdencompraAnitaWhereSupport::occuota($clave),
        ]);

        $ocfpRaw = $this->anitaCall($api, [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.cuota_fpago'),
            'campos' => implode(', ', [
                'ocfp_tipo', 'ocfp_letra', 'ocfp_sucursal', 'ocfp_nro', 'ocfp_nro_cuota',
                'ocfp_cuota_fpago', 'ocfp_fecha_vto', 'ocfp_monto',
            ]),
            'whereArmado' => OrdencompraAnitaWhereSupport::ocfpagocuota($clave),
        ]);

        $ocvlRaw = $this->anitaCall($api, [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.leyenda_linea'),
            'campos' => implode(', ', [
                'ocvl_tipo', 'ocvl_letra', 'ocvl_sucursal', 'ocvl_nro', 'ocvl_nro_orden',
                'ocvl_linea', 'ocvl_leyenda',
            ]),
            'whereArmado' => OrdencompraAnitaWhereSupport::ocvley($clave),
        ]);

        $cab = ApiAnita::primeraFilaLista((string) $cabRaw);
        $proveedor6 = str_pad(trim((string) ($cab->penmp_proveedor ?? '0')), 6, '0', STR_PAD_LEFT);
        $penpfRaw = $this->anitaCall($api, [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => config('ordencompra_anita.tablas.fecha_oc'),
            'campos' => implode(', ', [
                'penpf_proveedor', 'penpf_tipo', 'penpf_letra', 'penpf_sucursal', 'penpf_nro',
                'penpf_fecha_fac', 'penpf_fecha_pago',
            ]),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendfecha($clave, $proveedor6),
            'limit' => 'FIRST 1',
        ]);

        return [
            'cabecera' => $cab,
            'lineas' => ApiAnita::decodificarListaFilas((string) $lineasRaw),
            'movpresup' => ApiAnita::decodificarListaFilas((string) $movpRaw),
            'ocvley' => ApiAnita::decodificarListaFilas((string) $ocvlRaw),
            'occuota' => ApiAnita::decodificarListaFilas((string) $occRaw),
            'ocfpagocuota' => ApiAnita::decodificarListaFilas((string) $ocfpRaw),
            'pendfecha' => ApiAnita::primeraFilaLista((string) $penpfRaw),
        ];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    private function restaurarCabeceraDesdeBackup(object $cab, array $clave): void
    {
        $sets = [];
        foreach ((array) $cab as $col => $val) {
            if (! is_string($col) || ! str_starts_with($col, 'penmp_')) {
                continue;
            }
            if (in_array($col, ['penmp_tipo', 'penmp_letra', 'penmp_sucursal', 'penmp_nro'], true)) {
                continue;
            }
            $sets[] = $col.' = '.$this->valorSqlBackup($col, $val);
        }

        if ($sets === []) {
            return;
        }

        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'update',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'valores' => implode(', ', $sets),
            'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
        ], 'ordencompra pendmaep restore backup');
    }

    /**
     * @param  array{cabecera: ?object, lineas: list<object>, movpresup: list<object>, ocvley: list<object>, occuota: list<object>, ocfpagocuota: list<object>, pendfecha: ?object}  $backup
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private function restaurarDetalleDesdeBackup(array $backup, array $clave): void
    {
        $sistema = OrdencompraAnitaNumeracionSupport::sistemaTComp();
        $api = new ApiAnita;

        foreach ($backup['occuota'] as $fila) {
            $cols = [];
            $vals = [];
            foreach ((array) $fila as $col => $val) {
                if (! is_string($col) || ! str_starts_with($col, 'occ_')) {
                    continue;
                }
                $cols[] = $col;
                $vals[] = $this->valorSqlBackup($col, $val);
            }
            if ($cols === []) {
                continue;
            }
            $this->anitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.cuota'),
                'campos' => implode(', ', $cols),
                'valores' => implode(', ', $vals),
            ], 'ordencompra occuota restore backup');
        }

        foreach ($backup['ocfpagocuota'] as $fila) {
            $cols = [];
            $vals = [];
            foreach ((array) $fila as $col => $val) {
                if (! is_string($col) || ! str_starts_with($col, 'ocfp_')) {
                    continue;
                }
                $cols[] = $col;
                $vals[] = $this->valorSqlBackup($col, $val);
            }
            if ($cols === []) {
                continue;
            }
            $this->anitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.cuota_fpago'),
                'campos' => implode(', ', $cols),
                'valores' => implode(', ', $vals),
            ], 'ordencompra ocfpagocuota restore backup');
        }

        foreach ($backup['lineas'] as $fila) {
            $cols = [];
            $vals = [];
            foreach ((array) $fila as $col => $val) {
                if (! is_string($col) || ! str_starts_with($col, 'penvp_')) {
                    continue;
                }
                $cols[] = $col;
                $vals[] = $this->valorSqlBackup($col, $val);
            }
            if ($cols === []) {
                continue;
            }
            $this->anitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.linea'),
                'campos' => implode(', ', $cols),
                'valores' => implode(', ', $vals),
            ], 'ordencompra pendmovp restore backup');
        }

        foreach ($backup['movpresup'] as $fila) {
            $cols = [];
            $vals = [];
            foreach ((array) $fila as $col => $val) {
                if (! is_string($col) || ! str_starts_with($col, 'movp_')) {
                    continue;
                }
                $cols[] = $col;
                $vals[] = $this->valorSqlBackup($col, $val);
            }
            if ($cols === []) {
                continue;
            }
            $this->anitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.presupuesto_linea'),
                'campos' => implode(', ', $cols),
                'valores' => implode(', ', $vals),
            ], 'ordencompra movpresup restore backup');
        }

        foreach ($backup['ocvley'] as $fila) {
            $cols = [];
            $vals = [];
            foreach ((array) $fila as $col => $val) {
                if (! is_string($col) || ! str_starts_with($col, 'ocvl_')) {
                    continue;
                }
                $cols[] = $col;
                $vals[] = $this->valorSqlBackup($col, $val);
            }
            if ($cols === []) {
                continue;
            }
            $this->anitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => $sistema,
                'tabla' => config('ordencompra_anita.tablas.leyenda_linea'),
                'campos' => implode(', ', $cols),
                'valores' => implode(', ', $vals),
            ], 'ordencompra ocvley restore backup');
        }
    }

    private function restaurarPendfechaDesdeBackup(object $fila): void
    {
        $cols = [];
        $vals = [];
        foreach ((array) $fila as $col => $val) {
            if (! is_string($col) || ! str_starts_with($col, 'penpf_')) {
                continue;
            }
            $cols[] = $col;
            $vals[] = $this->valorSqlBackup($col, $val);
        }
        if ($cols === []) {
            return;
        }

        $api = new ApiAnita;
        $this->anitaEscritura($api, [
            'acc' => 'insert',
            'sistema' => OrdencompraAnitaNumeracionSupport::sistemaTComp(),
            'tabla' => config('ordencompra_anita.tablas.fecha_oc'),
            'campos' => implode(', ', $cols),
            'valores' => implode(', ', $vals),
        ], 'ordencompra pendfecha restore backup');
    }

    private function valorSqlBackup(string $columna, mixed $valor): string
    {
        $s = trim((string) $valor);
        if ($s === '' || $s === 'null') {
            if (str_contains($columna, 'fecha') && ! str_contains($columna, 'hora')) {
                return '0';
            }
            if (preg_match('/_(cant|precio|dto|importe|cotizacion|nro|ccosto|partida|presupuesto|escenario|proyecto|mes|deposito|sucursal|empresa|requisicion|expreso|legajo|orden|interno)/', $columna)) {
                return '0';
            }

            return "''";
        }

        if (is_numeric($s) && ! preg_match('/^(penmp_letra|penvp_letra|movp_letra|penvp_incl_imp|penmp_es_anticipo|penmp_entrega|penmp_leyenda|penmp_hora_ing|penmp_estado_aprob|penmp_hora_aprob|penmp_razon_susp|penmp_estado|occ_medio_pago|occ_letra|ocfp_letra)/', $columna)) {
            if (preg_match('/(precio|dto|cant|importe|cotizacion)/', $columna)) {
                return number_format((float) $s, 4, '.', '');
            }

            return (string) (int) $s;
        }

        return "'".str_replace("'", "''", $s)."'";
    }
}
