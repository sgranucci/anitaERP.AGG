<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Illuminate\Support\Facades\Log;

/**
 * Re-sincroniza en Anita solo cabeceras COM con recm_terminal=ERP (origen anitaERP).
 * No toca recepciones hechas en Anita (`recm_terminal` vacío, REF u otro valor distinto de ERP).
 */
class RecepcionProveedorAnitaResincronizacionErpService
{
    /** Recepciones del incidente sucursal virtual 991 / migración junio 2026. */
    private const IDS_INCIDENTE = [
        63246, 63247, 63248, 63249, 63255, 63256, 63257, 63258, 63259,
    ];

    public function __construct(
        private readonly RecepcionProveedorAnitaBridgeService $anitaBridge,
    ) {
    }

    public function contar(?int $id = null): int
    {
        return $this->query($id)->count();
    }

    /**
     * @return array{procesadas: int, erp_claves_corregidas: int, resincronizadas: int, borrador_limpiadas: int, omitidas: int, errores: int}
     */
    public function ejecutar(bool $dryRun = false, ?int $id = null, ?callable $onError = null): array
    {
        $stats = [
            'procesadas' => 0,
            'erp_claves_corregidas' => 0,
            'resincronizadas' => 0,
            'borrador_limpiadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
        ];

        foreach ($this->query($id)->get() as $recepcion) {
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

        $this->anitaBridge->sincronizarRecepcion($recepcion->fresh([
            'proveedores', 'empresas', 'ordencompras',
            'recepcion_proveedor_articulos.articulos.categorias',
            'recepcion_proveedor_articulos.articulos.impuestos',
            'recepcion_proveedor_articulos.centrocostos',
        ]));

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

    private function query(?int $id = null)
    {
        $query = Recepcion_Proveedor::query()
            ->with('empresas')
            ->whereIn('id', self::IDS_INCIDENTE);

        if ($id !== null && $id > 0) {
            $query->whereKey($id);
        }

        return $query->orderBy('id');
    }
}
