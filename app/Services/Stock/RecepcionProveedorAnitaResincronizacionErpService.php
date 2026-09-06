<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;
use App\Support\Stock\RecepcionProveedorAnitaWhereSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Re-sincroniza en Anita COM originadas en anitaERP (excluye ANITA_IMPORT).
 * Cubre cabecera recepmae faltante, claves fuera de sucursal y borradores con huérfanos ERP.
 */
class RecepcionProveedorAnitaResincronizacionErpService
{
    /** Recepciones del incidente sucursal virtual 991 / migración junio 2026. */
    private const IDS_INCIDENTE = [
        63246, 63247, 63248, 63249, 63255, 63256, 63257, 63258, 63259,
    ];

    public function __construct(
        private readonly RecepcionProveedorAnitaBridgeService $anitaBridge,
        private readonly RecepcionProveedorAsientoService $asientoService,
    ) {}

    public function contar(?int $id = null, bool $soloReparacionDetalleRef = false): int
    {
        return $soloReparacionDetalleRef
            ? $this->candidatasReparacionDetalleRef($id)->count()
            : $this->candidatas($id)->count();
    }

    /**
     * @return array{procesadas: int, erp_claves_corregidas: int, resincronizadas: int, borrador_limpiadas: int, reparadas_detalle_ref: int, omitidas: int, errores: int}
     */
    public function ejecutar(bool $dryRun = false, ?int $id = null, ?callable $onError = null): array
    {
        return $this->ejecutarResincronizacionCompleta($dryRun, $id, $onError);
    }

    /**
     * @return array{procesadas: int, erp_claves_corregidas: int, resincronizadas: int, borrador_limpiadas: int, reparadas_detalle_ref: int, omitidas: int, errores: int}
     */
    public function ejecutarResincronizacionCompleta(bool $dryRun = false, ?int $id = null, ?callable $onError = null): array
    {
        $stats = [
            'procesadas' => 0,
            'erp_claves_corregidas' => 0,
            'resincronizadas' => 0,
            'borrador_limpiadas' => 0,
            'reparadas_detalle_ref' => 0,
            'omitidas' => 0,
            'errores' => 0,
        ];

        foreach ($this->candidatas($id) as $recepcion) {
            $stats['procesadas']++;
            try {
                $resultado = $this->procesarUna($recepcion, $dryRun);
                if ($resultado === 'omitida') {
                    $stats['omitidas']++;
                } else {
                    $stats['erp_claves_corregidas'] += $resultado['claves_corregidas'];
                    if ($resultado['resincronizada']) {
                        $stats['resincronizadas']++;
                    }
                    if ($resultado['borrador_limpiada']) {
                        $stats['borrador_limpiadas']++;
                    }
                }
            } catch (\Throwable $e) {
                $stats['errores']++;
                Log::error('RecepcionProveedorAnitaResincronizacionErp: fallo', [
                    'recepcion_id' => $recepcion->id,
                    'numerorecepcion' => $recepcion->numerorecepcion,
                    'mensaje' => $e->getMessage(),
                ]);
                if ($onError !== null) {
                    $onError($recepcion, $e);
                }
            }
        }

        return $stats;
    }

    /**
     * @return array{procesadas: int, erp_claves_corregidas: int, resincronizadas: int, borrador_limpiadas: int, reparadas_detalle_ref: int, omitidas: int, errores: int}
     */
    public function ejecutarReparacionDetalleRef(bool $dryRun = false, ?int $id = null, ?callable $onError = null): array
    {
        $stats = [
            'procesadas' => 0,
            'erp_claves_corregidas' => 0,
            'resincronizadas' => 0,
            'borrador_limpiadas' => 0,
            'reparadas_detalle_ref' => 0,
            'omitidas' => 0,
            'errores' => 0,
        ];

        foreach ($this->candidatasReparacionDetalleRef($id) as $recepcion) {
            $stats['procesadas']++;
            try {
                if ($dryRun) {
                    $stats['reparadas_detalle_ref']++;

                    continue;
                }

                $this->anitaBridge->repararDetallePreservandoCabecera($recepcion->fresh([
                    'proveedores', 'empresas', 'ordencompras',
                    'recepcion_proveedor_articulos.articulos.categorias',
                    'recepcion_proveedor_articulos.articulos.impuestos',
                    'recepcion_proveedor_articulos.centrocostos',
                ]));
                $stats['reparadas_detalle_ref']++;
            } catch (\Throwable $e) {
                $stats['errores']++;
                Log::error('RecepcionProveedorAnitaResincronizacionErp: fallo reparación detalle REF', [
                    'recepcion_id' => $recepcion->id,
                    'numerorecepcion' => $recepcion->numerorecepcion,
                    'mensaje' => $e->getMessage(),
                ]);
                if ($onError !== null) {
                    $onError($recepcion, $e);
                }
            }
        }

        return $stats;
    }

    /**
     * @return Collection<int, Recepcion_Proveedor>
     */
    private function candidatasReparacionDetalleRef(?int $id = null): Collection
    {
        $candidatas = collect();

        foreach ($this->queryBase($id)->orderBy('id')->cursor() as $recepcion) {
            if ($this->requiereReparacionDetalleRef($recepcion)) {
                $candidatas->push($recepcion);
            }
        }

        return $candidatas;
    }

    public function requiereReparacionDetalleRef(Recepcion_Proveedor $recepcion): bool
    {
        return $this->requiereReparacionDetalleIncompleto($recepcion);
    }

    /**
     * Cabecera Anita presente y vinculada, pero recepmov/stkmov con menos ítems que el ERP.
     * REF: solo regrava detalle. ERP con documentoid correcto: idem.
     * ERP con documentoid huérfano: lo toma requiereResincronizacion (resync completo).
     */
    public function requiereReparacionDetalleIncompleto(Recepcion_Proveedor $recepcion): bool
    {
        if ($recepcion->origen_carga === 'ANITA_IMPORT') {
            return false;
        }

        if ($recepcion->estado !== RecepcionProveedorEstados::CONFIRMADA) {
            return false;
        }

        if ((int) $recepcion->numerorecepcion <= 0) {
            return false;
        }

        if (! $this->anitaBridge->detalleComIncompletoEnAnita($recepcion)) {
            return false;
        }

        $cabecera = $this->anitaBridge->cabeceraRecepmaeVinculadaDocumento($recepcion);
        if ($cabecera === null) {
            return false;
        }

        $terminal = trim((string) ($cabecera->recm_terminal ?? ''));
        if (RecepcionProveedorAnitaWhereSupport::esTerminalProtegidoAnita($terminal)) {
            return true;
        }

        // ERP: detalle-only solo si el documentoid ya apunta al ERP (si no, resync completo).
        return (int) ($cabecera->recm_documentoid ?? 0) === (int) $recepcion->id;
    }

    /**
     * @return Collection<int, Recepcion_Proveedor>
     */
    private function candidatas(?int $id = null): Collection
    {
        $candidatas = collect();

        foreach ($this->queryBase($id)->orderBy('id')->cursor() as $recepcion) {
            if ($this->requiereResincronizacion($recepcion)) {
                $candidatas->push($recepcion);
            }
        }

        return $candidatas;
    }

    public function requiereResincronizacion(Recepcion_Proveedor $recepcion): bool
    {
        $recepcion->loadMissing('empresas');

        if ($recepcion->origen_carga === 'ANITA_IMPORT') {
            return false;
        }

        $cabecerasErp = $this->anitaBridge->listarRecepmaeErpPorDocumento((int) $recepcion->id);

        if ($recepcion->estado === RecepcionProveedorEstados::BORRADOR) {
            return $cabecerasErp !== [];
        }

        if ($recepcion->estado !== RecepcionProveedorEstados::CONFIRMADA) {
            return false;
        }

        if ((int) $recepcion->numerorecepcion <= 0) {
            return false;
        }

        $cabeceraVinculada = $this->anitaBridge->cabeceraRecepmaeVinculadaDocumento($recepcion);
        if (
            $cabeceraVinculada !== null
            && RecepcionProveedorAnitaWhereSupport::esTerminalProtegidoAnita(
                trim((string) ($cabeceraVinculada->recm_terminal ?? ''))
            )
        ) {
            // REF / vacío: Anita ya tocó referencias; no re-sync completo (solo detalle si falta).
            return false;
        }

        // Terminal ERP sin documentoid apuntando a este id: hay que regrabar cabecera + detalle.
        if ($cabecerasErp === []) {
            return true;
        }

        $claveCorrecta = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);

        return $this->cabecerasFueraDeSucursal($cabecerasErp, $claveCorrecta) !== [];
    }

    /**
     * Re-sincroniza recepmae/recepmov y ctamov para una COM CONFIRMADA (uso job post-commit / reparación).
     */
    public function repararRecepcionConfirmada(int $recepcionId): void
    {
        $recepcion = Recepcion_Proveedor::query()
            ->with('empresas')
            ->whereKey($recepcionId)
            ->firstOrFail();

        if ($recepcion->estado !== RecepcionProveedorEstados::CONFIRMADA) {
            throw new \RuntimeException('Solo se puede reparar Anita en recepciones CONFIRMADA.');
        }

        $resultado = $this->procesarUna($recepcion, false);
        if ($resultado === 'omitida') {
            throw new \RuntimeException('La recepción no es candidata a re-sincronización Anita.');
        }
    }

    /**
     * @return 'omitida'|array{claves_corregidas: int, resincronizada: bool, borrador_limpiada: bool}
     */
    private function procesarUna(Recepcion_Proveedor $recepcion, bool $dryRun): string|array
    {
        $recepcion->loadMissing('empresas');
        $claveCorrecta = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $cabecerasErp = $this->anitaBridge->listarRecepmaeErpPorDocumento((int) $recepcion->id);

        if ($cabecerasErp === [] && $recepcion->estado !== RecepcionProveedorEstados::CONFIRMADA) {
            return 'omitida';
        }

        if ($dryRun) {
            return [
                'claves_corregidas' => count($this->cabecerasFueraDeSucursal($cabecerasErp, $claveCorrecta)),
                'resincronizada' => $recepcion->estado === RecepcionProveedorEstados::CONFIRMADA,
                'borrador_limpiada' => $recepcion->estado === RecepcionProveedorEstados::BORRADOR && $cabecerasErp !== [],
            ];
        }

        RecepcionProveedorAnitaClaveSupport::asignarEnRecepcion($recepcion, $claveCorrecta);
        $recepcion->refresh();

        $estadoAnulada = (string) config('recepcion_proveedor.anita.recepcion_estado_anulada', '3');
        $clavesCorregidas = 0;

        if ($recepcion->estado === RecepcionProveedorEstados::BORRADOR) {
            foreach ($cabecerasErp as $cabecera) {
                $claveCab = $this->claveDesdeCabeceraAnita($cabecera);
                $this->anitaBridge->revertirClaveComEnAnita($recepcion, $claveCab, false);
                $clavesCorregidas++;
            }

            return [
                'claves_corregidas' => $clavesCorregidas,
                'resincronizada' => false,
                'borrador_limpiada' => $clavesCorregidas > 0,
            ];
        }

        foreach ($this->cabecerasFueraDeSucursal($cabecerasErp, $claveCorrecta) as $cabecera) {
            $claveCab = $this->claveDesdeCabeceraAnita($cabecera);
            $revertirPendmovp = trim((string) ($cabecera->recm_estado ?? '')) !== $estadoAnulada;
            $this->anitaBridge->revertirClaveComEnAnita($recepcion, $claveCab, $revertirPendmovp);
            $clavesCorregidas++;
        }

        if ($cabecerasErp === [] && $this->anitaBridge->tieneDetalleComEnAnita($recepcion)) {
            $this->anitaBridge->ajustarPendmovpRecepcion($recepcion->fresh([
                'ordencompras',
                'recepcion_proveedor_articulos.articulos',
            ]), -1);
        }

        $this->anitaBridge->sincronizarRecepcion($recepcion->fresh([
            'proveedores', 'empresas', 'ordencompras',
            'recepcion_proveedor_articulos.articulos.categorias',
            'recepcion_proveedor_articulos.articulos.impuestos',
            'recepcion_proveedor_articulos.centrocostos',
        ]));

        if ((int) $recepcion->asiento_id > 0) {
            $this->asientoService->reconciliarCtamovConErp($recepcion->fresh([
                'asientos',
                'empresas',
                'proveedores',
                'ordencompras',
                'recepcion_proveedor_articulos.articulos.categorias',
                'recepcion_proveedor_articulos.articulos.impuestos',
                'recepcion_proveedor_articulos.centrocostos',
            ]));
        }

        return [
            'claves_corregidas' => $clavesCorregidas,
            'resincronizada' => true,
            'borrador_limpiada' => false,
        ];
    }

    /**
     * @param  array<int, object>  $cabecerasErp
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $claveCorrecta
     * @return array<int, object>
     */
    private function cabecerasFueraDeSucursal(array $cabecerasErp, array $claveCorrecta): array
    {
        $fuera = [];
        foreach ($cabecerasErp as $cabecera) {
            if ((int) ($cabecera->recm_sucursal ?? 0) !== (int) $claveCorrecta['sucursal']) {
                $fuera[] = $cabecera;
            }
        }

        return $fuera;
    }

    /** @return array{tipo: string, letra: string, sucursal: int, nro: int} */
    private function claveDesdeCabeceraAnita(object $cabecera): array
    {
        $cfg = config('recepcion_proveedor.anita');

        return [
            'tipo' => (string) ($cabecera->recm_tipo ?? $cfg['recepcion_tipo']),
            'letra' => (string) ($cabecera->recm_letra ?? RecepcionProveedorAnitaClaveSupport::letraCom()),
            'sucursal' => (int) ($cabecera->recm_sucursal ?? 0),
            'nro' => (int) ($cabecera->recm_nro ?? 0),
        ];
    }

    /** @return Builder<Recepcion_Proveedor> */
    private function queryBase(?int $id = null): Builder
    {
        $query = Recepcion_Proveedor::query()
            ->with('empresas')
            ->where('origen_carga', '!=', 'ANITA_IMPORT')
            ->where(function (Builder $q): void {
                $q->whereIn('id', self::IDS_INCIDENTE)
                    ->orWhere(function (Builder $q2): void {
                        $q2->where('estado', RecepcionProveedorEstados::CONFIRMADA)
                            ->where('numerorecepcion', '>', 0);
                    })
                    ->orWhere(function (Builder $q3): void {
                        $q3->where('estado', RecepcionProveedorEstados::BORRADOR);
                    });
            });

        if ($id !== null && $id > 0) {
            $query->whereKey($id);
        }

        return $query;
    }
}
