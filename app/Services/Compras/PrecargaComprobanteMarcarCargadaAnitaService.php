<?php

namespace App\Services\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorAnitaCompraExistenciaSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Marca una precarga ERP como ya cargada en Anita (tabla compra),
 * para sacarla de Pendientes sin generar un comprobante duplicado.
 */
class PrecargaComprobanteMarcarCargadaAnitaService
{
    public function __construct(
        private readonly PrecargaComprobanteAnitaSyncService $anitaSync,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $filaAnita
     * @return array{precarga_id: int, nro_interno: int|null, mensaje: string, ya_estaba: bool}
     */
    public function marcar(int $precargaId, ?array $filaAnita = null): array
    {
        $precarga = Precarga_Comprobante_Proveedor::query()->find($precargaId);
        if (! $precarga) {
            throw new RuntimeException('No existe la precarga #'.$precargaId.'.');
        }

        return $this->marcarModelo($precarga, $filaAnita);
    }

    /**
     * @param  array<string, mixed>|null  $filaAnita
     * @return array{precarga_id: int, nro_interno: int|null, mensaje: string, ya_estaba: bool}
     */
    public function marcarModelo(Precarga_Comprobante_Proveedor $precarga, ?array $filaAnita = null): array
    {
        $precargaId = (int) $precarga->id;

        if ((string) $precarga->estado === PrecargaComprobanteEstados::CARGADA_ANITA) {
            $nro = (int) ($precarga->anita_nro_interno ?? 0);

            return [
                'precarga_id' => $precargaId,
                'nro_interno' => $nro > 0 ? $nro : null,
                'mensaje' => 'La precarga #'.$precargaId.' ya estaba marcada como cargada en Anita'
                    .($nro > 0 ? ' (nro. interno '.$nro.').' : '.'),
                'ya_estaba' => true,
            ];
        }

        if (! PrecargaComprobanteEstados::puedeMarcarCargadaAnita((string) $precarga->estado)) {
            throw new RuntimeException(
                'La precarga #'.$precargaId.' está en estado '.$precarga->estado
                .' y no se puede marcar como cargada en Anita.'
            );
        }

        if ($precarga->comprobante_proveedor()->exists()) {
            throw new RuntimeException(
                'La precarga #'.$precargaId.' ya tiene un comprobante generado. '
                .'No se marca como cargada en Anita.'
            );
        }

        $fila = $filaAnita;
        if (! is_array($fila) || $fila === []) {
            $fila = ComprobanteProveedorAnitaCompraExistenciaSupport::buscar(
                (int) $precarga->empresa_id,
                (int) $precarga->proveedor_id,
                (int) $precarga->tipotransaccion_compra_id,
                (string) $precarga->letra,
                (int) $precarga->sucursal,
                (int) $precarga->numerocomprobante,
            );
        }

        if ($fila === null) {
            throw new RuntimeException(
                'No se encontró la factura en Anita (tabla compra). '
                .'Solo se puede marcar la precarga si ya está cargada allí.'
            );
        }

        $nroInterno = (int) ($fila['com_nro_interno'] ?? 0);

        $precarga->estado = PrecargaComprobanteEstados::CARGADA_ANITA;
        $precarga->anita_nro_interno = $nroInterno > 0 ? $nroInterno : null;
        $precarga->save();

        $avisoAnitaPrecarga = $this->quitarPrecargaEnAnita($precargaId);

        $mensaje = 'Precarga #'.$precargaId.' marcada como ya cargada en Anita'
            .($nroInterno > 0 ? ' (nro. interno '.$nroInterno.')' : '')
            .'. Salió de Pendientes.';
        if ($avisoAnitaPrecarga !== null) {
            $mensaje .= ' '.$avisoAnitaPrecarga;
        }

        return [
            'precarga_id' => $precargaId,
            'nro_interno' => $nroInterno > 0 ? $nroInterno : null,
            'mensaje' => $mensaje,
            'ya_estaba' => false,
        ];
    }

    /**
     * Revisa pendientes y marca las que ya existen en Anita compra.
     *
     * @param  list<int>|null  $empresaIds
     * @return array{marcadas: int, sin_match: int, errores: int, mensaje: string}
     */
    public function detectarYMarcarPendientes(int $limite = 40, ?array $empresaIds = null): array
    {
        $limite = max(1, min(80, $limite));

        $query = Precarga_Comprobante_Proveedor::query()
            ->where('estado', PrecargaComprobanteEstados::PENDIENTE);
        if (is_array($empresaIds) && $empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds);
        }

        $pendientes = $query
            ->orderBy('id')
            ->limit($limite)
            ->get();

        $marcadas = 0;
        $sinMatch = 0;
        $errores = 0;

        foreach ($pendientes as $precarga) {
            if ($precarga->comprobante_proveedor()->exists()) {
                $sinMatch++;

                continue;
            }

            try {
                $fila = ComprobanteProveedorAnitaCompraExistenciaSupport::buscar(
                    (int) $precarga->empresa_id,
                    (int) $precarga->proveedor_id,
                    (int) $precarga->tipotransaccion_compra_id,
                    (string) $precarga->letra,
                    (int) $precarga->sucursal,
                    (int) $precarga->numerocomprobante,
                );
                if ($fila === null) {
                    $sinMatch++;

                    continue;
                }

                $this->marcarModelo($precarga, $fila);
                $marcadas++;
            } catch (\Throwable $e) {
                $errores++;
                Log::warning('precarga.detectar_cargada_anita_error', [
                    'precarga_id' => $precarga->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $revisadas = $pendientes->count();
        $mensaje = 'Se revisaron '.$revisadas.' precarga(s) pendiente(s) contra Anita: '
            .$marcadas.' marcada(s) como ya cargada(s)'
            .($sinMatch > 0 ? ', '.$sinMatch.' sin factura en Anita' : '')
            .($errores > 0 ? ', '.$errores.' con error de consulta' : '')
            .'.';

        return [
            'marcadas' => $marcadas,
            'sin_match' => $sinMatch,
            'errores' => $errores,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * Consulta Anita sin abortar el alta si el bridge falla.
     *
     * @return array{mensaje: string, nro_interno: int|null, fila: array<string, mixed>}|null
     */
    public function avisoSiYaExisteEnAnita(Precarga_Comprobante_Proveedor $precarga): ?array
    {
        if ((string) $precarga->estado === PrecargaComprobanteEstados::CARGADA_ANITA) {
            $nro = (int) ($precarga->anita_nro_interno ?? 0);

            return [
                'mensaje' => 'Esta precarga ya está marcada como cargada en Anita'
                    .($nro > 0 ? ' (nro. interno '.$nro.')' : '')
                    .'. No se puede generar el comprobante desde el ERP.',
                'nro_interno' => $nro > 0 ? $nro : null,
                'fila' => [],
                'ya_marcada' => true,
            ];
        }

        try {
            $fila = ComprobanteProveedorAnitaCompraExistenciaSupport::buscar(
                (int) $precarga->empresa_id,
                (int) $precarga->proveedor_id,
                (int) $precarga->tipotransaccion_compra_id,
                (string) $precarga->letra,
                (int) $precarga->sucursal,
                (int) $precarga->numerocomprobante,
            );
        } catch (\Throwable $e) {
            Log::warning('precarga.aviso_anita_compra_error', [
                'precarga_id' => $precarga->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($fila === null) {
            return null;
        }

        $nroInterno = (int) ($fila['com_nro_interno'] ?? 0);

        return [
            'mensaje' => ComprobanteProveedorAnitaCompraExistenciaSupport::mensajeDuplicado(
                $fila,
                (string) $precarga->letra,
                (int) $precarga->sucursal,
                (int) $precarga->numerocomprobante,
            ),
            'nro_interno' => $nroInterno > 0 ? $nroInterno : null,
            'fila' => $fila,
            'ya_marcada' => false,
        ];
    }

    private function quitarPrecargaEnAnita(int $precargaId): ?string
    {
        try {
            if (! $this->anitaSync->existsCabeceraEnAnita($precargaId)) {
                return null;
            }

            $this->anitaSync->deleteCabecera($precargaId);

            return null;
        } catch (\Throwable $e) {
            Log::warning('precarga.quitar_anita_al_marcar_cargada', [
                'precarga_id' => $precargaId,
                'error' => $e->getMessage(),
            ]);

            return 'La precarga de Anita no se pudo borrar (el comprobante en compra sí existe).';
        }
    }
}
