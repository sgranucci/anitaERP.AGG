<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Documento_Fiscal;
use App\Support\Compras\ProveedorDocumentoFiscalSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Proveedor_Documento_FiscalRepository implements Proveedor_Documento_FiscalRepositoryInterface
{
    public function __construct(
        private Proveedor_Documento_Fiscal $model,
    ) {}

    public function listarPorProveedor(int $proveedorId): Collection
    {
        return $this->model->newQuery()
            ->where('proveedor_id', $proveedorId)
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('id')
            ->get()
            ->sortBy(static function (Proveedor_Documento_Fiscal $doc) {
                return match (strtoupper((string) $doc->tipo)) {
                    ProveedorDocumentoFiscalSupport::TIPO_CUIT => 0,
                    ProveedorDocumentoFiscalSupport::TIPO_CM05 => 1,
                    default => 2,
                };
            })
            ->values();
    }

    /**
     * Último documento por tipo (el más reciente por vencimiento/id).
     *
     * @return array{CUIT: ?Proveedor_Documento_Fiscal, CM05: ?Proveedor_Documento_Fiscal}
     */
    public function vigentesPorTipo(int $proveedorId): array
    {
        $docs = $this->listarPorProveedor($proveedorId);
        $out = [
            ProveedorDocumentoFiscalSupport::TIPO_CUIT => null,
            ProveedorDocumentoFiscalSupport::TIPO_CM05 => null,
        ];
        foreach ($docs as $doc) {
            $tipo = strtoupper((string) $doc->tipo);
            if (! array_key_exists($tipo, $out)) {
                continue;
            }
            if ($out[$tipo] === null) {
                $out[$tipo] = $doc;
            }
        }

        return $out;
    }

    /**
     * @return list<array{tipo: string, etiqueta: string, estado: string, documento: ?Proveedor_Documento_Fiscal, mensaje: string}>
     */
    public function avisosPortal(int $proveedorId): array
    {
        $vigentes = $this->vigentesPorTipo($proveedorId);
        $avisos = [];
        foreach (ProveedorDocumentoFiscalSupport::tipos() as $tipo) {
            $doc = $vigentes[$tipo] ?? null;
            $etiqueta = ProveedorDocumentoFiscalSupport::etiquetaTipo($tipo);
            if ($doc === null) {
                $avisos[] = [
                    'tipo' => $tipo,
                    'etiqueta' => $etiqueta,
                    'estado' => 'faltante',
                    'documento' => null,
                    'mensaje' => 'Debe presentar '.$etiqueta.'.',
                ];
                continue;
            }
            $estado = $doc->estadoVigencia();
            if ($estado === 'vencido') {
                $avisos[] = [
                    'tipo' => $tipo,
                    'etiqueta' => $etiqueta,
                    'estado' => 'vencido',
                    'documento' => $doc,
                    'mensaje' => $etiqueta.' vencido el '
                        .optional($doc->fecha_vencimiento)->format('d/m/Y')
                        .'. Debe presentar uno vigente.',
                ];
            } elseif ($estado === 'proximo') {
                $avisos[] = [
                    'tipo' => $tipo,
                    'etiqueta' => $etiqueta,
                    'estado' => 'proximo',
                    'documento' => $doc,
                    'mensaje' => $etiqueta.' vence el '
                        .optional($doc->fecha_vencimiento)->format('d/m/Y')
                        .'. Conviene renovarlo.',
                ];
            } elseif ($estado === 'sin_fecha') {
                $avisos[] = [
                    'tipo' => $tipo,
                    'etiqueta' => $etiqueta,
                    'estado' => 'sin_fecha',
                    'documento' => $doc,
                    'mensaje' => $etiqueta.' sin fecha de vencimiento cargada. Complete el vencimiento.',
                ];
            }
        }

        return $avisos;
    }

    public function crearDesdeUpload(
        int $proveedorId,
        string $tipo,
        UploadedFile $archivo,
        ?string $fechaVencimiento,
        ?int $anioEjercicio,
        string $origen,
    ): Proveedor_Documento_Fiscal {
        $tipo = strtoupper($tipo);
        if (! ProveedorDocumentoFiscalSupport::esTipoValido($tipo)) {
            throw new \InvalidArgumentException('Tipo de documento fiscal inválido.');
        }

        $dir = ProveedorDocumentoFiscalSupport::directorioProveedor($proveedorId);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = strtolower($archivo->getClientOriginalExtension() ?: 'pdf');
        $base = Str::slug(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'documento';
        $nombre = $proveedorId.'-'.$tipo.'-'.now()->format('YmdHis').'-'.$base.'.'.$ext;
        $archivo->move($dir, $nombre);

        return $this->model->create([
            'proveedor_id' => $proveedorId,
            'tipo' => $tipo,
            'nombrearchivo' => $nombre,
            'fecha_vencimiento' => $fechaVencimiento ?: null,
            'anio_ejercicio' => $tipo === ProveedorDocumentoFiscalSupport::TIPO_CM05
                ? ($anioEjercicio ?: (int) now()->year)
                : null,
            'origen' => $origen,
            'presento_usuario_id' => Auth::id(),
        ]);
    }

    public function findDelProveedor(int $id, int $proveedorId): Proveedor_Documento_Fiscal
    {
        $doc = $this->model->newQuery()
            ->whereKey($id)
            ->where('proveedor_id', $proveedorId)
            ->first();
        if ($doc === null) {
            throw new ModelNotFoundException('Documento fiscal no encontrado.');
        }

        return $doc;
    }

    public function eliminar(int $id, int $proveedorId): void
    {
        $doc = $this->findDelProveedor($id, $proveedorId);
        $path = ProveedorDocumentoFiscalSupport::directorioProveedor($proveedorId)
            .DIRECTORY_SEPARATOR.$doc->nombrearchivo;
        if (is_file($path)) {
            @unlink($path);
        }
        $doc->delete();
    }

    public function sincronizarDesdeRequest(int $proveedorId, $request): void
    {
        $idsConservar = collect($request->input('documento_fiscal_ids_existentes', []))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $existentes = $this->listarPorProveedor($proveedorId);
        foreach ($existentes as $doc) {
            if (! in_array((int) $doc->id, $idsConservar, true)) {
                $this->eliminar((int) $doc->id, $proveedorId);
            }
        }

        $tipos = $request->input('documento_fiscal_tipos', []);
        $vencimientos = $request->input('documento_fiscal_vencimientos', []);
        $anios = $request->input('documento_fiscal_anios', []);
        $archivos = $request->file('documento_fiscal_archivos', []);

        if (! is_array($tipos)) {
            return;
        }

        foreach ($tipos as $i => $tipo) {
            $archivo = $archivos[$i] ?? null;
            if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
                continue;
            }
            $this->crearDesdeUpload(
                $proveedorId,
                (string) $tipo,
                $archivo,
                $vencimientos[$i] ?? null,
                isset($anios[$i]) && $anios[$i] !== '' ? (int) $anios[$i] : null,
                ProveedorDocumentoFiscalSupport::ORIGEN_ABM,
            );
        }
    }
}
