<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Casi todos los comprobantes entran por el import de Anita y no por el legajo, así que la precarga y
 * su scan quedaban esperando una factura que ya estaba cargada: la COM seguía reservada y el sector
 * veía como pendiente algo que ya estaba hecho.
 *
 * Este servicio cierra ese hueco. Dada una factura ya cargada, busca la precarga que la estaba
 * esperando (por clave fiscal: empresa + código AFIP + letra + sucursal + número + CUIT), la vincula al
 * comprobante, le traspasa las COM para que la reserva tenga un solo dueño y la deja GENERADA.
 *
 * GENERADA y no ANULADA a propósito: dice la verdad ("esta precarga se convirtió en esa factura"),
 * la saca de los pendientes y, como el scan sigue matcheando con ella, tampoco se rematerializa.
 */
class ComprobanteProveedorCierrePrecargaLegajoService
{
    /**
     * @return array{precarga_id: int, coms: list<int>}|null  null si no había precarga esperando.
     */
    public function cerrar(Comprobante_Proveedor $comprobante, int $usuarioId): ?array
    {
        $precarga = $this->precargaEsperando($comprobante);
        if ($precarga === null) {
            return null;
        }

        $conflicto = $this->comsEnConflicto($precarga, $comprobante);

        // Savepoint propio: si falla a mitad del traspaso, no queda la COM en los dos lados.
        return DB::transaction(function () use ($precarga, $comprobante, $usuarioId, $conflicto): array {
            $coms = $this->traspasarComsAlComprobante($precarga, $comprobante);

            $comprobante->precarga_comprobante_proveedor_id = (int) $precarga->id;
            $comprobante->save();

            $precarga->estado = PrecargaComprobanteEstados::GENERADA;
            $precarga->save();

            $this->registrarEnHistoria($precarga, $comprobante, $coms, $conflicto, $usuarioId);

            if ($conflicto !== []) {
                Log::warning('comprobante_proveedor.cierre_precarga_legajo_com_en_conflicto', [
                    'comprobante_proveedor_id' => (int) $comprobante->id,
                    'precarga_id' => (int) $precarga->id,
                    'coms_de_la_precarga' => $conflicto,
                ]);
            }

            return ['precarga_id' => (int) $precarga->id, 'coms' => $coms, 'conflicto' => $conflicto];
        });
    }

    /**
     * Igual que cerrar() pero sin propagar errores: el import no se cae por no poder cerrar un legajo,
     * la factura ya quedó bien cargada y lo único que se pierde es el cierre.
     */
    public function cerrarSinFallar(Comprobante_Proveedor $comprobante, int $usuarioId): void
    {
        try {
            $resultado = $this->cerrar($comprobante, $usuarioId);
            if ($resultado !== null) {
                Log::info('comprobante_proveedor.cierre_precarga_legajo', [
                    'comprobante_proveedor_id' => (int) $comprobante->id,
                    'precarga_id' => $resultado['precarga_id'],
                    'coms_traspasadas' => $resultado['coms'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('comprobante_proveedor.cierre_precarga_legajo_fallo', [
                'comprobante_proveedor_id' => (int) $comprobante->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function precargaEsperando(Comprobante_Proveedor $comprobante): ?Precarga_Comprobante_Proveedor
    {
        $cuit = ComprobanteProveedorUnicidadSupport::cuitDesdeComprobante($comprobante);
        $codigoAfip = ComprobanteProveedorUnicidadSupport::codigoAfipDesdeTipoId(
            (int) $comprobante->tipotransaccion_compra_id
        );
        if ($cuit === '' || $codigoAfip === '') {
            return null;
        }

        $precarga = ComprobanteProveedorUnicidadSupport::findDuplicadoPrecargaPorAfip(
            (int) $comprobante->empresa_id,
            $codigoAfip,
            (string) $comprobante->letra,
            (int) $comprobante->sucursal,
            (int) $comprobante->numerocomprobante,
            $cuit,
        );
        if ($precarga === null) {
            return null;
        }
        if (strtoupper(trim((string) $precarga->estado)) === PrecargaComprobanteEstados::GENERADA) {
            return null;
        }

        return $precarga;
    }

    /**
     * COM que la precarga reclama cuando el comprobante ya tiene las suyas: no se traspasan (las del
     * comprobante contabilizado son la verdad) pero tampoco se borran solas, porque significa que
     * alguien asignó una COM distinta a la que terminó consumiendo la factura. Se deja anotado.
     *
     * @return list<int>
     */
    private function comsEnConflicto(
        Precarga_Comprobante_Proveedor $precarga,
        Comprobante_Proveedor $comprobante,
    ): array {
        $delComprobante = Comprobante_Proveedor_Recepcion::query()
            ->where('comprobante_proveedor_id', (int) $comprobante->id)
            ->pluck('recepcion_proveedor_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($delComprobante === []) {
            return [];
        }

        $deLaPrecarga = Precarga_Comprobante_Proveedor_Recepcion::query()
            ->where('precarga_comprobante_proveedor_id', (int) $precarga->id)
            ->pluck('recepcion_proveedor_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_diff($deLaPrecarga, $delComprobante));
    }

    /**
     * Mueve las COM de la precarga al comprobante para que quede un solo dueño de la reserva.
     *
     * @return list<int>
     */
    private function traspasarComsAlComprobante(
        Precarga_Comprobante_Proveedor $precarga,
        Comprobante_Proveedor $comprobante,
    ): array {
        $yaAsignadas = Comprobante_Proveedor_Recepcion::query()
            ->where('comprobante_proveedor_id', (int) $comprobante->id)
            ->exists();
        if ($yaAsignadas) {
            return [];
        }

        $filas = Precarga_Comprobante_Proveedor_Recepcion::query()
            ->where('precarga_comprobante_proveedor_id', (int) $precarga->id)
            ->orderBy('orden')
            ->get();

        $movidas = [];
        $orden = 0;
        foreach ($filas as $fila) {
            $recepcionId = (int) $fila->recepcion_proveedor_id;
            if ($recepcionId <= 0) {
                continue;
            }
            $orden++;
            Comprobante_Proveedor_Recepcion::query()->create([
                'comprobante_proveedor_id' => (int) $comprobante->id,
                'recepcion_proveedor_id' => $recepcionId,
                'orden' => $orden,
            ]);
            $fila->delete();
            $movidas[] = $recepcionId;
        }

        return $movidas;
    }

    /**
     * @param  list<int>  $coms
     * @param  list<int>  $conflicto
     */
    private function registrarEnHistoria(
        Precarga_Comprobante_Proveedor $precarga,
        Comprobante_Proveedor $comprobante,
        array $coms,
        array $conflicto,
        int $usuarioId,
    ): void {
        $oc = Ordencompra::query()
            ->where('empresa_id', (int) $precarga->empresa_id)
            ->where('numeroordencompra', $precarga->numeroordencompra)
            ->first();
        if ($oc === null || ! $oc->sector_legajocompra_id) {
            return;
        }

        $leyenda = 'La factura '.$precarga->letra.'-'.$precarga->sucursal.'-'.$precarga->numerocomprobante
            .' ya estaba cargada en el ERP (comprobante #'.$comprobante->id.', origen '
            .($comprobante->origen_entrada ?: 'sin origen').'), así que la precarga #'.$precarga->id
            .' del legajo quedó GENERADA y vinculada a ese comprobante.';
        if ($coms !== []) {
            $leyenda .= ' Las COM '.implode(', ', $coms).' pasaron de la precarga al comprobante.';
        }
        if ($conflicto !== []) {
            $leyenda .= ' ATENCION: la precarga reclamaba las COM '.implode(', ', $conflicto)
                .' pero el comprobante consumió otras, así que esa asignación estaba mal y quedó'
                .' retenida. Revisar a qué factura corresponden esas COM.';
        }

        Ordencompra_Historia::query()->create([
            'ordencompra_id' => (int) $oc->id,
            'sector_legajocompra_id' => (int) $oc->sector_legajocompra_id,
            'fecha' => now(),
            'observacion' => 'Precarga cerrada: la factura ya estaba en el ERP',
            'leyenda' => mb_substr($leyenda, 0, 1000),
            'creousuario_id' => $usuarioId > 0 ? $usuarioId : 1,
        ]);
    }
}
