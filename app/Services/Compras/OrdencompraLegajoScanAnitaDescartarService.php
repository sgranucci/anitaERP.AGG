<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Ordencompra_Legajo_Scan_Anita_Descartado;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Al borrar una precarga nacida de Scan Anita, la saca del legajo (ERP + best-effort Anita)
 * para que no se rematerialice ni reaparezca en Asignar COM / bandeja.
 */
class OrdencompraLegajoScanAnitaDescartarService
{
    public function descartarSiCorresponde(Precarga_Comprobante_Proveedor $precarga): void
    {
        if ((string) ($precarga->origen_entrada ?? '') !== PrecargaComprobanteOrigenEntrada::SCAN_ANITA) {
            return;
        }

        $oc = $this->resolverOrdencompra($precarga);
        if ($oc === null) {
            Log::info('bandeja.scan_anita_descartar.sin_oc', [
                'precarga_id' => (int) $precarga->id,
                'numeroordencompra' => (string) ($precarga->numeroordencompra ?? ''),
            ]);

            return;
        }

        $documentoId = $this->resolverDocumentoId($oc, $precarga);
        if ($documentoId <= 0) {
            Log::info('bandeja.scan_anita_descartar.sin_documento', [
                'precarga_id' => (int) $precarga->id,
                'ordencompra_id' => (int) $oc->id,
                'letra' => (string) ($precarga->letra ?? ''),
                'sucursal' => (int) ($precarga->sucursal ?? 0),
                'numero' => (int) ($precarga->numerocomprobante ?? 0),
            ]);

            return;
        }

        $this->registrarDescarte($oc, $precarga, $documentoId, 'Se eliminó la factura del legajo');
        $this->desvincularEnAnita($oc, $documentoId);
        OrdencompraLegajoAnitaScanFacturaSupport::forgetCache();
    }

    /**
     * Descarte explícito desde la bandeja: el operador elige el scan y explica por qué.
     * No requiere que exista precarga (el caso típico es un scan mal vinculado que nunca
     * debió entrar al legajo).
     */
    public function descartarManual(Ordencompra $oc, int $documentoId, string $motivo): void
    {
        $fila = OrdencompraLegajoAnitaScanFacturaSupport::filaDeOc($oc, $documentoId);
        if ($fila === null) {
            throw ValidationException::withMessages([
                'documento_id' => 'La factura escaneada no pertenece a este legajo.',
            ]);
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Indique el motivo del descarte.',
            ]);
        }

        DB::transaction(function () use ($oc, $documentoId, $fila, $motivo) {
            $precarga = $this->precargaVigenteDelScan($oc, $fila);
            if ($precarga !== null) {
                // Si ya se cargó al ERP no se descarta: hay que anular el comprobante primero.
                $this->assertPrecargaNoEstaEnElErp($precarga);
                $precarga->delete();
            }

            Ordencompra_Legajo_Scan_Anita_Descartado::query()->updateOrCreate(
                [
                    'ordencompra_id' => (int) $oc->id,
                    'documento_id' => $documentoId,
                ],
                [
                    'empresa_id' => (int) $oc->empresa_id,
                    'numeroordencompra' => (string) $oc->numeroordencompra,
                    'letra' => strtoupper(trim((string) ($fila['cletra'] ?? ''))) ?: null,
                    'sucursal' => (int) ($fila['isucursal'] ?? 0) ?: null,
                    'numerocomprobante' => (int) ($fila['inumero'] ?? 0) ?: null,
                    'precarga_id_origen' => $precarga ? (int) $precarga->id : null,
                    'motivo' => mb_substr($motivo, 0, 255),
                    'user_id' => Auth::id() ? (int) Auth::id() : null,
                    // Un descarte nuevo pisa una reversión anterior del mismo documento.
                    'revertido_at' => null,
                    'revertido_user_id' => null,
                    'revertido_motivo' => null,
                ]
            );

            $this->registrarHistoria(
                $oc,
                'Descarte de factura escaneada de Anita',
                'Se descartó el documento Anita #'.$documentoId.' ('.$this->etiquetaFila($fila).') del legajo. Motivo: '.$motivo
            );
        });

        $this->desvincularEnAnita($oc, $documentoId);
        OrdencompraLegajoAnitaScanFacturaSupport::forgetCache();
    }

    /**
     * Deshace un descarte: el scan vuelve a aparecer en el legajo y se re-vincula en Anita.
     * El registro no se borra, queda como rastro con quién y por qué lo revirtió.
     */
    public function revertir(Ordencompra $oc, int $documentoId, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Indique el motivo para deshacer el descarte.',
            ]);
        }

        DB::transaction(function () use ($oc, $documentoId, $motivo) {
            $descarte = Ordencompra_Legajo_Scan_Anita_Descartado::query()
                ->where('ordencompra_id', (int) $oc->id)
                ->where('documento_id', $documentoId)
                ->lockForUpdate()
                ->first();

            if ($descarte === null || ! $descarte->estaVigente()) {
                throw ValidationException::withMessages([
                    'documento_id' => 'La factura escaneada no está descartada en este legajo.',
                ]);
            }

            $descarte->fill([
                'revertido_at' => now(),
                'revertido_user_id' => Auth::id() ? (int) Auth::id() : null,
                'revertido_motivo' => mb_substr($motivo, 0, 255),
            ])->save();

            $this->registrarHistoria(
                $oc,
                'Reversión de descarte de factura escaneada',
                'Se deshizo el descarte del documento Anita #'.$documentoId
                    .'. La factura vuelve al legajo. Motivo: '.$motivo
            );
        });

        $this->revincularEnAnita($oc, $documentoId);
        OrdencompraLegajoAnitaScanFacturaSupport::forgetCache();
    }

    /**
     * @return list<array{documento_id: int, etiqueta: string, motivo: string, usuario: string, fecha: string}>
     */
    public function descartadosVigentesDeOc(Ordencompra $oc): array
    {
        $rows = Ordencompra_Legajo_Scan_Anita_Descartado::query()
            ->vigente()
            ->where('ordencompra_id', (int) $oc->id)
            ->orderByDesc('id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $etiqueta = trim(implode('-', array_filter([
                strtoupper((string) ($row->letra ?? '')),
                (string) ($row->sucursal ?? ''),
                (string) ($row->numerocomprobante ?? ''),
            ], static fn ($v) => (string) $v !== '')));

            $out[] = [
                'documento_id' => (int) $row->documento_id,
                'etiqueta' => $etiqueta !== '' ? $etiqueta : ('doc #'.(int) $row->documento_id),
                'motivo' => (string) ($row->motivo ?? ''),
                'usuario' => (string) (DB::table('usuario')->where('id', (int) $row->user_id)->value('nombre') ?? ''),
                'fecha' => $row->created_at ? $row->created_at->format('d/m/Y H:i') : '',
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ordencompraIds
     * @return array<int, array<int, true>> mapa ocId => [documentoId => true]
     */
    public function documentoIdsDescartadosPorOcIds(array $ordencompraIds): array
    {
        $ordencompraIds = array_values(array_filter(array_map('intval', $ordencompraIds), static fn (int $id) => $id > 0));
        if ($ordencompraIds === []) {
            return [];
        }

        $out = [];
        foreach ($ordencompraIds as $ocId) {
            $out[$ocId] = [];
        }

        $rows = Ordencompra_Legajo_Scan_Anita_Descartado::query()
            ->vigente()
            ->whereIn('ordencompra_id', $ordencompraIds)
            ->get(['ordencompra_id', 'documento_id']);

        foreach ($rows as $row) {
            $ocId = (int) $row->ordencompra_id;
            $docId = (int) $row->documento_id;
            if ($ocId > 0 && $docId > 0) {
                $out[$ocId][$docId] = true;
            }
        }

        return $out;
    }

    public function estaDescartado(Ordencompra $oc, int $documentoId): bool
    {
        if ($documentoId <= 0 || (int) $oc->id <= 0) {
            return false;
        }

        return Ordencompra_Legajo_Scan_Anita_Descartado::query()
            ->vigente()
            ->where('ordencompra_id', (int) $oc->id)
            ->where('documento_id', $documentoId)
            ->exists();
    }

    private function resolverOrdencompra(Precarga_Comprobante_Proveedor $precarga): ?Ordencompra
    {
        $nro = trim((string) ($precarga->numeroordencompra ?? ''));
        if ($nro === '') {
            return null;
        }

        $query = Ordencompra::query()->where('numeroordencompra', $nro);
        $empresaId = (int) ($precarga->empresa_id ?? 0);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->with('empresas:id,codigo,nombre')->first();
    }

    private function resolverDocumentoId(Ordencompra $oc, Precarga_Comprobante_Proveedor $precarga): int
    {
        $scans = OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOcIncluyendoDescartados($oc);
        $docId = OrdencompraLegajoAnitaScanFacturaSupport::documentoIdCompatible(
            $scans,
            (string) ($precarga->letra ?? ''),
            (int) ($precarga->sucursal ?? 0),
            (int) ($precarga->numerocomprobante ?? 0),
            '',
            0
        );

        return (int) ($docId ?? 0);
    }

    private function registrarDescarte(
        Ordencompra $oc,
        Precarga_Comprobante_Proveedor $precarga,
        int $documentoId,
        string $motivo,
    ): void {
        Ordencompra_Legajo_Scan_Anita_Descartado::query()->updateOrCreate(
            [
                'ordencompra_id' => (int) $oc->id,
                'documento_id' => $documentoId,
            ],
            [
                'empresa_id' => (int) ($precarga->empresa_id ?: $oc->empresa_id),
                'numeroordencompra' => (string) $oc->numeroordencompra,
                'letra' => strtoupper(trim((string) ($precarga->letra ?? ''))) ?: null,
                'sucursal' => (int) ($precarga->sucursal ?? 0) ?: null,
                'numerocomprobante' => (int) ($precarga->numerocomprobante ?? 0) ?: null,
                'precarga_id_origen' => (int) $precarga->id,
                'motivo' => mb_substr(trim($motivo), 0, 255) ?: null,
                'user_id' => Auth::id() ? (int) Auth::id() : null,
                'revertido_at' => null,
                'revertido_user_id' => null,
                'revertido_motivo' => null,
            ]
        );
    }

    private function precargaVigenteDelScan(Ordencompra $oc, array $fila): ?Precarga_Comprobante_Proveedor
    {
        $letra = strtoupper(trim((string) ($fila['cletra'] ?? '')));
        $sucursal = (int) ($fila['isucursal'] ?? 0);
        $numero = (int) ($fila['inumero'] ?? 0);
        if ($numero <= 0) {
            return null;
        }

        return Precarga_Comprobante_Proveedor::query()
            ->where('numeroordencompra', (string) $oc->numeroordencompra)
            ->where('empresa_id', (int) $oc->empresa_id)
            ->where('numerocomprobante', $numero)
            ->when($letra !== '', fn ($q) => $q->where('letra', $letra))
            ->when($sucursal > 0, fn ($q) => $q->where('sucursal', $sucursal))
            // Una precarga anulada no es vigente: devolverla hacía que el descarte la borrara en duro
            // (este modelo no tiene softdeletes) y se perdía el registro de la anulación.
            ->whereRaw("UPPER(TRIM(COALESCE(estado, ''))) != ?", [PrecargaComprobanteEstados::ANULADA])
            ->lockForUpdate()
            ->first();
    }

    /**
     * Una vez que la factura entró al ERP el descarte del scan dejaría el comprobante huérfano:
     * el camino correcto es anular el comprobante, no esconder el scan que lo originó.
     */
    /**
     * Descartar el scan borra la precarga, así que no se puede hacer si esa precarga respalda un
     * comprobante ya cargado. En cambio, si la factura entró al ERP por otra vía (import de Anita,
     * carga sin OC), la precarga es un duplicado y descartar el scan es justamente lo que corresponde:
     * si no, al reabrir el legajo el scan la vuelve a materializar.
     */
    private function assertPrecargaNoEstaEnElErp(Precarga_Comprobante_Proveedor $precarga): void
    {
        $comprobantes = DB::table('comprobante_proveedor')
            ->where('empresa_id', (int) $precarga->empresa_id)
            ->where('proveedor_id', (int) $precarga->proveedor_id)
            ->where('letra', (string) $precarga->letra)
            ->where('sucursal', (int) $precarga->sucursal)
            ->where('numerocomprobante', (int) $precarga->numerocomprobante)
            ->get(['id', 'precarga_comprobante_proveedor_id', 'ordencompra_id']);

        if ($comprobantes->isEmpty()) {
            return;
        }

        $ocDelLegajo = $this->resolverOrdencompra($precarga);
        $ocIdDelLegajo = $ocDelLegajo !== null ? (int) $ocDelLegajo->id : 0;

        foreach ($comprobantes as $cp) {
            $naceDeEstaPrecarga = (int) ($cp->precarga_comprobante_proveedor_id ?? 0) === (int) $precarga->id;
            $esDelMismoLegajo = $ocIdDelLegajo > 0 && (int) ($cp->ordencompra_id ?? 0) === $ocIdDelLegajo;
            if ($naceDeEstaPrecarga || $esDelMismoLegajo) {
                throw ValidationException::withMessages([
                    'documento_id' => 'La factura ya está cargada en el ERP desde este legajo (comprobante #'
                        .(int) $cp->id.'). Anule el comprobante antes de descartar el scan.',
                ]);
            }
        }
    }

    private function etiquetaFila(array $fila): string
    {
        $partes = array_filter([
            strtoupper(trim((string) ($fila['cletra'] ?? ''))),
            (string) ((int) ($fila['isucursal'] ?? 0) ?: ''),
            (string) ((int) ($fila['inumero'] ?? 0) ?: ''),
        ], static fn (string $v) => $v !== '');

        return $partes === [] ? 'sin numerar' : implode('-', $partes);
    }

    private function registrarHistoria(Ordencompra $oc, string $observacion, string $leyenda): void
    {
        $userId = Auth::id() ? (int) Auth::id() : null;
        if ($userId === null) {
            return;
        }

        Ordencompra_Historia::query()->create([
            'ordencompra_id' => (int) $oc->id,
            'sector_legajocompra_id' => $oc->sector_legajocompra_id ? (int) $oc->sector_legajocompra_id : null,
            'fecha' => now(),
            'observacion' => $observacion,
            'leyenda' => mb_substr($leyenda, 0, 1000),
            'creousuario_id' => $userId,
        ]);
    }

    /**
     * Best-effort inverso de desvincularEnAnita: devuelve el scan a la OC (iotid = nro OC).
     */
    private function revincularEnAnita(Ordencompra $oc, int $documentoId): void
    {
        $nroOc = (int) preg_replace('/\D+/', '', (string) $oc->numeroordencompra);
        if ($documentoId <= 0 || $nroOc <= 0) {
            return;
        }

        try {
            (new ApiAnita)->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'scanfactura',
                'sistema' => 'base_admin',
                'valores' => " iotid = '".$nroOc."' ",
                'whereArmado' => ' WHERE idocumentoid = '.(int) $documentoId.' AND iotid = 0 ',
            ], 'scanfactura revincular OC', 'bandeja.scan_anita_descartar.revertir_anita', true);
        } catch (\Throwable $e) {
            Log::warning('bandeja.scan_anita_descartar.revertir_anita_fallo', [
                'ordencompra_id' => (int) $oc->id,
                'documento_id' => $documentoId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Best-effort: corta el vínculo scanfactura → OC en Anita (iotid = 0).
     * El descarte ERP alcanza para no rematerializar aunque esto falle.
     */
    private function desvincularEnAnita(Ordencompra $oc, int $documentoId): void
    {
        $nroOc = (int) preg_replace('/\D+/', '', (string) $oc->numeroordencompra);
        if ($documentoId <= 0 || $nroOc <= 0) {
            return;
        }

        try {
            (new ApiAnita)->apiCallEscritura([
                'acc' => 'update',
                'tabla' => 'scanfactura',
                'sistema' => 'base_admin',
                'valores' => " iotid = '0' ",
                'whereArmado' => ' WHERE idocumentoid = '.(int) $documentoId
                    .' AND iotid = '.(int) $nroOc.' ',
            ], 'scanfactura desvincular OC', 'bandeja.scan_anita_descartar.anita', true);
        } catch (\Throwable $e) {
            Log::warning('bandeja.scan_anita_descartar.anita_fallo', [
                'ordencompra_id' => (int) $oc->id,
                'documento_id' => $documentoId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
