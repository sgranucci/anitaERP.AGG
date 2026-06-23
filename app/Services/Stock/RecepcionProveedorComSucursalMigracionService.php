<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Illuminate\Support\Facades\Log;

/**
 * Corrige recepciones COM grabadas con sucursal virtual 99x (991…) volviendo a código empresa.
 */
class RecepcionProveedorComSucursalMigracionService
{
    public function __construct(
        private readonly RecepcionProveedorAnitaBridgeService $anitaBridge,
    ) {
    }

    public function contarCandidatas(?int $id = null): int
    {
        return $this->queryCandidatas($id)->count();
    }

    /**
     * @return array{candidatas: int, erp_actualizadas: int, anita_resincronizadas: int, omitidas: int, errores: int}
     */
    public function ejecutar(bool $dryRun = false, ?int $id = null, ?callable $onError = null): array
    {
        $stats = [
            'candidatas' => 0,
            'erp_actualizadas' => 0,
            'anita_resincronizadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
        ];

        $query = $this->queryCandidatas($id);
        $stats['candidatas'] = $query->count();

        foreach ($query->get() as $recepcion) {
            try {
                $resultado = $this->migrarUna($recepcion, $dryRun);
                if ($resultado === 'omitida') {
                    $stats['omitidas']++;
                } else {
                    if ($resultado['erp']) {
                        $stats['erp_actualizadas']++;
                    }
                    if ($resultado['anita']) {
                        $stats['anita_resincronizadas']++;
                    }
                }
            } catch (\Throwable $e) {
                $stats['errores']++;
                Log::error('RecepcionProveedorComSucursalMigracion: fallo', [
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
     * @return 'omitida'|array{erp: bool, anita: bool}
     */
    private function migrarUna(Recepcion_Proveedor $recepcion, bool $dryRun): string|array
    {
        $recepcion->loadMissing('empresas');
        $sucursalCorrecta = RecepcionProveedorAnitaClaveSupport::sucursalEmpresa($recepcion);
        $sucursalAlmacenada = (int) ($recepcion->anita_sucursal ?? 0);

        if ($sucursalAlmacenada === $sucursalCorrecta) {
            return 'omitida';
        }

        if (! RecepcionProveedorAnitaClaveSupport::esSucursalVirtualLegacy($sucursalAlmacenada)
            && $sucursalAlmacenada !== $sucursalCorrecta) {
            // Sucursal distinta pero no virtual: no tocar automáticamente.
            return 'omitida';
        }

        $claveLegacy = RecepcionProveedorAnitaClaveSupport::claveDesdeAtributosAlmacenados($recepcion);
        $claveNueva = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);

        if ($dryRun) {
            return ['erp' => true, 'anita' => $recepcion->estado === RecepcionProveedorEstados::CONFIRMADA];
        }

        $revertirPendmovp = $recepcion->estado === RecepcionProveedorEstados::CONFIRMADA;
        if ($sucursalAlmacenada > 0 && $sucursalAlmacenada !== $sucursalCorrecta) {
            $this->anitaBridge->revertirClaveComEnAnita($recepcion, $claveLegacy, $revertirPendmovp);
        }

        RecepcionProveedorAnitaClaveSupport::asignarEnRecepcion($recepcion, $claveNueva);
        $recepcion->refresh();

        $resincronizoAnita = false;
        if ($recepcion->estado === RecepcionProveedorEstados::CONFIRMADA) {
            $this->anitaBridge->sincronizarRecepcion($recepcion->fresh([
                'proveedores', 'empresas', 'ordencompras',
                'recepcion_proveedor_articulos.articulos.categorias',
                'recepcion_proveedor_articulos.articulos.impuestos',
                'recepcion_proveedor_articulos.centrocostos',
            ]));
            $resincronizoAnita = true;
        }

        return ['erp' => true, 'anita' => $resincronizoAnita];
    }

    private function queryCandidatas(?int $id = null)
    {
        $query = Recepcion_Proveedor::query()
            ->with('empresas')
            ->whereNotNull('anita_sucursal')
            ->where('anita_sucursal', '>=', 90);

        if ($id !== null && $id > 0) {
            $query->whereKey($id);
        }

        return $query->orderBy('id');
    }
}
