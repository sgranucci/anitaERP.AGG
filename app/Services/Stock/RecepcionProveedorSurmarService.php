<?php

namespace App\Services\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Proveedor;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Estado;
use App\Models\Stock\RecepcionProveedorArticuloSurmar;
use App\Models\Stock\Stock_Etiqueta;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Stock\Unidadmedida;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Support\Stock\ArticuloMovimientoCantidadSignoSupport;
use App\Support\Stock\RecepcionProveedorDiferenciaSupport;
use App\Support\Stock\RecepcionProveedorSurmarListadoFiltros;
use App\Support\Stock\Surmar\RecepcionProveedorSurmarOcSupport;
use App\Support\Stock\Surmar\SurmarEtiquetaFechaVtoSupport;
use App\Support\Stock\Surmar\SurmarUnidadmedidaSeparaSupport;
use App\Support\Stock\SurmarEtiquetaZplSupport;
use App\Support\Stock\SurmarSupport;
use Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Recepción Surmar: cabecera en BORRADOR + grabado provisorio por ítem (como Anita a-stock.c COM).
 * Referencia OC (igual AGG / carga_referencia+asigna_pedido). Cada línea se persiste al cerrarla y emite etiqueta.
 */
class RecepcionProveedorSurmarService
{
    /** Altas operativas desde el workbench Surmar. */
    public const ORIGEN_CARGA = 'SURMAR';

    /** Histórico importado desde Anita (`config('recepcion_anita_surmar.origen_carga')`). */
    public const ORIGEN_ANITA_IMPORT = 'ANITA_IMPORT';

    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
    ) {
    }

    /** @return list<string> */
    public static function origenesListado(): array
    {
        $import = (string) config('recepcion_anita_surmar.origen_carga', self::ORIGEN_ANITA_IMPORT);

        return array_values(array_unique([self::ORIGEN_CARGA, $import]));
    }

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros = [], bool $paginar = true)
    {
        SurmarSupport::abortSiNoSurmar(SurmarSupport::EMPRESA_ID);

        $query = Recepcion_Proveedor::query()
            ->select([
                'recepcion_proveedor.*',
                'empresa.nombre as nombreempresa',
                'proveedor.nombre as nombreproveedor',
                'ordencompra.numeroordencompra as numeroordencompra',
            ])
            ->withCount('recepcion_proveedor_articulos')
            ->join('empresa', 'empresa.id', '=', 'recepcion_proveedor.empresa_id')
            ->join('proveedor', 'proveedor.id', '=', 'recepcion_proveedor.proveedor_id')
            ->leftJoin('ordencompra', 'ordencompra.id', '=', 'recepcion_proveedor.ordencompra_id')
            ->where('recepcion_proveedor.empresa_id', SurmarSupport::EMPRESA_ID)
            ->whereIn('recepcion_proveedor.origen_carga', self::origenesListado())
            ->orderByDesc('recepcion_proveedor.id');

        if (RecepcionProveedorSurmarListadoFiltros::tieneCriteriosAplicados($filtros)) {
            RecepcionProveedorSurmarListadoFiltros::aplicar($query, $filtros);
        }

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function buscar(int $id): Recepcion_Proveedor
    {
        SurmarSupport::abortSiNoSurmar(SurmarSupport::EMPRESA_ID);

        $recepcion = Recepcion_Proveedor::query()
            ->with([
                'proveedores',
                'depositos',
                'empresas',
                'ordencompras',
                'recepcion_proveedor_articulos' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
                'recepcion_proveedor_articulos.articulos',
                'recepcion_proveedor_articulos.unidadesmedida',
            ])
            ->whereKey($id)
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->whereIn('origen_carga', self::origenesListado())
            ->firstOrFail();

        return $recepcion;
    }

    /**
     * Alta de cabecera provisoria con OC de referencia (como carga_referencia en a-stock.c / AGG).
     *
     * @param  array<string, mixed>  $data
     */
    public function iniciar(array $data): Recepcion_Proveedor
    {
        $empresaId = SurmarSupport::EMPRESA_ID;
        SurmarSupport::abortSiNoSurmar($empresaId);
        $ordencompraId = (int) ($data['ordencompra_id'] ?? 0);
        $depositoId = (int) ($data['deposito_id'] ?? 0);
        $fecha = (string) ($data['fecha'] ?? now()->toDateString());

        if ($ordencompraId <= 0) {
            throw ValidationException::withMessages(['ordencompra_id' => 'Debe indicar la orden de compra.']);
        }

        try {
            $ocData = RecepcionProveedorSurmarOcSupport::resolver($ordencompraId, true);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['ordencompra_id' => $e->getMessage()]);
        }

        /** @var Ordencompra $oc */
        $oc = $ocData['cabecera'];
        $proveedorId = (int) $oc->proveedor_id;
        if ($proveedorId <= 0 || ! Proveedor::query()->whereKey($proveedorId)->exists()) {
            throw ValidationException::withMessages(['proveedor_id' => 'Proveedor de la OC inválido.']);
        }
        if ($depositoId <= 0 || ! Depmae::query()->whereKey($depositoId)->exists()) {
            throw ValidationException::withMessages(['deposito_id' => 'Depósito inválido.']);
        }
        if ($ocData['lineas'] === []) {
            throw ValidationException::withMessages(['ordencompra_id' => 'La OC no tiene líneas pendientes de recepción.']);
        }

        return DB::transaction(function () use ($data, $empresaId, $proveedorId, $depositoId, $fecha, $oc) {
            $recepcion = $this->repository->create([
                'ordencompra_id' => $oc->id,
                'tipo' => Recepcion_Proveedor::TIPO_RECEPCION,
                'empresa_id' => $empresaId,
                'proveedor_id' => $proveedorId,
                'deposito_id' => $depositoId,
                'centrocosto_id' => (int) ($oc->centrocosto_id ?? 0) ?: null,
                'fecha' => $fecha,
                'moneda_id' => (int) ($data['moneda_id'] ?? 1),
                'cotizacion' => (float) ($data['cotizacion'] ?? 1),
                'estado' => Recepcion_Proveedor::ESTADO_BORRADOR,
                'observacion' => (string) ($data['observacion'] ?? ''),
                'certificado_senasa' => trim((string) ($data['certificado_senasa'] ?? '')) ?: null,
                'tropa' => ($t = (int) ($data['tropa'] ?? 0)) > 0 ? $t : null,
                'temperatura_ingreso' => isset($data['temperatura_ingreso']) && $data['temperatura_ingreso'] !== ''
                    ? (float) $data['temperatura_ingreso']
                    : null,
                'destino_senasa' => trim((string) ($data['destino_senasa'] ?? 'Consumo interno')) ?: 'Consumo interno',
                'camara' => trim((string) ($data['camara'] ?? '')) ?: null,
                'nro_establecimiento' => ($n = (int) ($data['nro_establecimiento'] ?? 0)) > 0 ? $n : null,
                'origen_carga' => self::ORIGEN_CARGA,
                'creousuario_id' => Auth::id(),
                'anita_tipo' => 'COM',
            ]);

            Recepcion_Proveedor_Estado::create([
                'recepcion_proveedor_id' => $recepcion->id,
                'estado' => Recepcion_Proveedor::ESTADO_BORRADOR,
                'fecha' => now(),
                'usuario_id' => Auth::id(),
                'observacion' => 'Inicio recepción Surmar OC '.$oc->numeroordencompra.' (provisorio)',
            ]);

            return $recepcion->fresh(['ordencompras', 'proveedores']);
        });
    }

    /**
     * Actualiza cabecera (fecha/depósito/obs + datos SENASA Anita carga_certificado).
     *
     * @param  array<string, mixed>  $data
     */
    public function actualizarEncabezado(int $recepcionId, array $data): Recepcion_Proveedor
    {
        $recepcion = $this->buscar($recepcionId);
        $this->assertBorrador($recepcion);

        $depositoId = (int) ($data['deposito_id'] ?? $recepcion->deposito_id);
        if ($depositoId <= 0 || ! Depmae::query()->whereKey($depositoId)->exists()) {
            throw ValidationException::withMessages(['deposito_id' => 'Depósito inválido.']);
        }

        $nroEst = (int) ($data['nro_establecimiento'] ?? 0);
        if ($nroEst > 10000) {
            throw ValidationException::withMessages([
                'nro_establecimiento' => 'Establecimiento erróneo (Anita: máximo 10000).',
            ]);
        }

        $recepcion->update([
            'fecha' => (string) ($data['fecha'] ?? $recepcion->fecha?->format('Y-m-d')),
            'deposito_id' => $depositoId,
            'observacion' => (string) ($data['observacion'] ?? $recepcion->observacion ?? ''),
            'certificado_senasa' => trim((string) ($data['certificado_senasa'] ?? '')) ?: null,
            'tropa' => ($t = (int) ($data['tropa'] ?? 0)) > 0 ? $t : null,
            'temperatura_ingreso' => isset($data['temperatura_ingreso']) && $data['temperatura_ingreso'] !== ''
                ? (float) $data['temperatura_ingreso']
                : null,
            'destino_senasa' => trim((string) ($data['destino_senasa'] ?? '')) ?: 'Consumo interno',
            'camara' => trim((string) ($data['camara'] ?? '')) ?: null,
            'nro_establecimiento' => $nroEst > 0 ? $nroEst : null,
        ]);

        $certificado = trim((string) ($recepcion->fresh()->certificado_senasa ?? ''));
        if ($certificado !== '') {
            // Anita: el certificado de «Datos adicionales» se usa como lote en cada ítem.
            $lineas = RecepcionProveedorArticuloSurmar::query()
                ->where('recepcion_proveedor_id', $recepcion->id)
                ->get(['id', 'stock_etiqueta_id']);
            foreach ($lineas as $linea) {
                $linea->update([
                    'lote_proveedor' => $certificado,
                    'certificado' => $certificado,
                ]);
                if ((int) ($linea->stock_etiqueta_id ?? 0) > 0) {
                    Stock_Etiqueta::query()->whereKey($linea->stock_etiqueta_id)->update([
                        'lote_proveedor' => $certificado,
                    ]);
                }
            }
        }

        return $recepcion->fresh(['ordencompras', 'proveedores', 'depositos']);
    }

    /** @return list<array<string, mixed>> */
    public function lineasOcPendientes(Recepcion_Proveedor $recepcion): array
    {
        $ocId = (int) ($recepcion->ordencompra_id ?? 0);
        if ($ocId <= 0) {
            return [];
        }

        try {
            $oc = RecepcionProveedorSurmarOcSupport::cargarOc($ocId);
        } catch (\Throwable) {
            return [];
        }

        return RecepcionProveedorSurmarOcSupport::armarLineasPendientes($oc);
    }

    /**
     * Graba una línea de recepción + una etiqueta (Anita: una unidad por Imprime).
     * cant_unid_separa = total del lote impreso en la etiqueta; nro_apertura = esta unidad.
     * El proceso secuencial (unidad 1..N) lo maneja el modal sin cerrar.
     *
     * @param  array<string, mixed>  $data
     * @return array{lineas: list<RecepcionProveedorArticuloSurmar>, linea: RecepcionProveedorArticuloSurmar, etiqueta: Stock_Etiqueta, zpl: string, zpls: list<string>, nro_apertura: int, cant_unid_separa: int, proxima_apertura: int}
     */
    public function guardarLineaProvisoria(int $recepcionId, array $data): array
    {
        $recepcion = $this->buscar($recepcionId);
        $this->assertBorrador($recepcion);

        $ocLinea = $this->resolverLineaOcParaGrabar($recepcion, $data);
        $articuloId = (int) ($ocLinea['articulo_id'] ?? $data['articulo_id'] ?? 0);
        $articulo = Articulo::query()->whereKey($articuloId)->first();
        if (! $articulo) {
            throw ValidationException::withMessages(['articulo_id' => 'Artículo inválido.']);
        }

        $pesos = $this->resolverPesosLinea($data);
        $lote = $this->resolverLoteLinea($recepcion, $data);
        // Fecha impresa / base de vto = día de emisión (no fecha de OC ni cabecera vieja).
        $fechaEmision = now();
        $fechaVto = SurmarEtiquetaFechaVtoSupport::resolver(
            isset($data['fecha_vto']) ? (string) $data['fecha_vto'] : null,
            $fechaEmision,
            (int) ($articulo->vencimientoendia ?? 0)
        );
        $separa = $this->resolverSepara($data, $articulo, $ocLinea);
        $cantUnid = max(1, min(50, (int) ($data['cant_unid_separa'] ?? 1)));
        $cantPieza = round((float) ($data['cant_pieza'] ?? 1), 4);
        $precio = (float) ($data['precio'] ?? $ocLinea['precio'] ?? 0);
        $certificadoCabecera = trim((string) ($recepcion->certificado_senasa ?? ''));
        $certificadoLinea = $certificadoCabecera !== '' ? $certificadoCabecera : $lote;
        $nroEstablecimiento = (int) ($recepcion->nro_establecimiento ?? 0) ?: null;
        $umdId = $this->resolverUnidadmedidaId($articulo, $data, $ocLinea);
        $ocArtId = (int) ($ocLinea['ordencompra_articulo_id'] ?? 0);
        $nroExplicit = (int) ($data['nro_apertura'] ?? 0);
        $nroApertura = $nroExplicit > 0
            ? $nroExplicit
            : $this->proximaApertura($recepcion->id, $ocArtId, $articuloId);

        return DB::transaction(function () use (
            $recepcion, $articulo, $data, $ocLinea, $pesos, $lote, $fechaVto, $fechaEmision, $separa, $cantUnid,
            $cantPieza, $precio, $certificadoLinea, $nroEstablecimiento, $umdId, $nroApertura, $ocArtId, $articuloId
        ) {
            $result = $this->crearLineaYEtiqueta(
                $recepcion,
                $articulo,
                $data,
                $ocLinea,
                $pesos,
                $lote,
                $fechaVto,
                $fechaEmision,
                $separa,
                $cantUnid,
                $nroApertura,
                $cantPieza,
                $precio,
                $certificadoLinea,
                $nroEstablecimiento,
                $umdId
            );

            $recepcion->touch();

            return [
                'lineas' => [$result['linea']],
                'linea' => $result['linea'],
                'etiqueta' => $result['etiqueta'],
                'zpl' => $result['zpl'],
                'zpls' => [$result['zpl']],
                'nro_apertura' => $nroApertura,
                'cant_unid_separa' => $cantUnid,
                'proxima_apertura' => $this->proximaApertura($recepcion->id, $ocArtId, $articuloId),
            ];
        });
    }

    /**
     * Actualiza datos de una línea provisoria y su etiqueta (Anita «Modifica»).
     *
     * @param  array<string, mixed>  $data
     * @return array{linea: RecepcionProveedorArticuloSurmar, etiqueta: Stock_Etiqueta, zpl: string, preview: array<string, mixed>}
     */
    public function actualizarLineaProvisoria(int $recepcionId, int $lineaId, array $data): array
    {
        $recepcion = $this->buscar($recepcionId);
        $this->assertBorrador($recepcion);

        $linea = RecepcionProveedorArticuloSurmar::query()
            ->where('recepcion_proveedor_id', $recepcion->id)
            ->whereKey($lineaId)
            ->firstOrFail();

        $articulo = Articulo::query()->whereKey($linea->articulo_id)->firstOrFail();
        $pesos = $this->resolverPesosLinea($data);
        $lote = $this->resolverLoteLinea($recepcion, $data);
        $fechaEmision = now();
        $fechaVto = SurmarEtiquetaFechaVtoSupport::resolver(
            isset($data['fecha_vto']) ? (string) $data['fecha_vto'] : null,
            $fechaEmision,
            (int) ($articulo->vencimientoendia ?? 0)
        );
        $separa = $this->resolverSepara($data, $articulo, [
            'unidadmedida_id' => $linea->unidadmedida_id,
        ]);
        $cantUnid = max(1, (int) ($data['cant_unid_separa'] ?? $linea->cant_unid_separa ?? 1));
        $nroApertura = max(1, (int) ($data['nro_apertura'] ?? $linea->nro_apertura ?? 1));
        $cantPieza = round((float) ($data['cant_pieza'] ?? $linea->cant_pieza ?? 1), 4);
        $certificadoCabecera = trim((string) ($recepcion->certificado_senasa ?? ''));
        $certificadoLinea = $certificadoCabecera !== '' ? $certificadoCabecera : $lote;

        return DB::transaction(function () use (
            $recepcion, $linea, $articulo, $pesos, $lote, $fechaVto, $fechaEmision, $separa, $cantUnid,
            $nroApertura, $cantPieza, $certificadoLinea
        ) {
            $linea->update([
                'cantidad' => $pesos['neto'],
                'cantidad_stock' => $pesos['neto'],
                'lote_proveedor' => $lote,
                'certificado' => $certificadoLinea,
                'fecha_vto' => $fechaVto,
                'peso_bruto' => $pesos['bruto'],
                'peso_tara' => $pesos['tara'],
                'peso_neto' => $pesos['neto'],
                'cant_pieza' => $cantPieza,
                'separa_unidadmedida_id' => $separa,
                'cant_unid_separa' => $cantUnid,
                'nro_apertura' => $nroApertura,
            ]);

            $etiquetaId = (int) ($linea->stock_etiqueta_id ?? 0);
            if ($etiquetaId <= 0) {
                throw ValidationException::withMessages(['linea' => 'La línea no tiene etiqueta asociada.']);
            }

            Stock_Etiqueta::query()->whereKey($etiquetaId)->update([
                'lote_proveedor' => $lote,
                'fecha_vto' => $fechaVto,
                'fecha_emision' => $fechaEmision->format('Y-m-d'),
                'hora_emision' => now()->format('H:i'),
                'cant_pieza' => $cantPieza,
                'peso_bruto' => $pesos['bruto'],
                'peso_neto' => $pesos['neto'],
                'separa_unidadmedida_id' => $separa,
                'cant_unid_separa' => $cantUnid,
                'anita_nro_apertura' => $nroApertura,
                'descripcion_snapshot' => mb_substr((string) $articulo->descripcion, 0, 60),
            ]);

            $recepcion->touch();

            $etiqueta = Stock_Etiqueta::query()
                ->with(['articulos', 'unidadesmedida', 'separaUnidadmedida'])
                ->findOrFail($etiquetaId);
            $zpl = $this->zplDesdeEtiqueta($etiqueta, $recepcion);

            return [
                'linea' => $linea->fresh(['articulos', 'unidadesmedida', 'separaUnidadmedida', 'stock_etiqueta']),
                'etiqueta' => $etiqueta,
                'zpl' => $zpl,
                'preview' => $this->previewDesdeEtiqueta($etiqueta, $recepcion),
            ];
        });
    }

    /** @return array{bruto: float, tara: float, neto: float} */
    private function resolverPesosLinea(array $data): array
    {
        $pesoBruto = round((float) ($data['peso_bruto'] ?? 0), 4);
        $pesoTara = round((float) ($data['peso_tara'] ?? 0), 4);
        if ($pesoTara < 0) {
            $pesoTara = 0;
        }
        if ($pesoBruto > 0) {
            $pesoNeto = round($pesoBruto - $pesoTara, 4);
        } else {
            $pesoNeto = round((float) ($data['peso_neto'] ?? 0), 4);
            $pesoBruto = round($pesoNeto + $pesoTara, 4);
        }
        if ($pesoNeto <= 0) {
            throw ValidationException::withMessages([
                'peso_neto' => $pesoBruto > 0 && $pesoTara >= $pesoBruto
                    ? 'Peso neto inválido: la tara no puede ser mayor o igual al bruto.'
                    : 'Peso neto debe ser mayor a 0.',
            ]);
        }

        return ['bruto' => $pesoBruto, 'tara' => $pesoTara, 'neto' => $pesoNeto];
    }

    private function resolverLoteLinea(Recepcion_Proveedor $recepcion, array $data): string
    {
        $lote = trim((string) ($data['lote_proveedor'] ?? ''));
        $certificadoCabecera = trim((string) ($recepcion->certificado_senasa ?? ''));
        $certificadoEnviado = trim((string) ($data['certificado'] ?? ''));
        if ($lote === '' && $certificadoCabecera !== '') {
            $lote = $certificadoCabecera;
        }
        if ($lote === '') {
            $lote = $certificadoEnviado;
        }
        if ($lote === '') {
            throw ValidationException::withMessages([
                'lote_proveedor' => 'Debe ingresar el lote (o cargar el certificado SENASA en el encabezado).',
            ]);
        }

        // Si el operador cargó el certificado en pantalla y aún no guardó el encabezado,
        // persistirlo para que el próximo ítem / recarga lo herede.
        if ($certificadoCabecera === '' && $certificadoEnviado !== '') {
            $recepcion->update(['certificado_senasa' => $certificadoEnviado]);
            $recepcion->certificado_senasa = $certificadoEnviado;
        }

        return $lote;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $ocLinea
     */
    private function resolverSepara(array $data, Articulo $articulo, array $ocLinea): int
    {
        $separa = (int) ($data['separa_unidadmedida_id'] ?? 0);
        if ($separa > 0 && SurmarUnidadmedidaSeparaSupport::existe($separa)) {
            return $separa;
        }

        return SurmarUnidadmedidaSeparaSupport::idDefault();
    }

    private function proximaApertura(int $recepcionId, int $ordencompraArticuloId, ?int $articuloId = null): int
    {
        $q = RecepcionProveedorArticuloSurmar::query()
            ->where('recepcion_proveedor_id', $recepcionId);
        if ($ordencompraArticuloId > 0) {
            $q->where('ordencompra_articulo_id', $ordencompraArticuloId);
        } elseif ($articuloId && $articuloId > 0) {
            $q->where('articulo_id', $articuloId)->whereNull('ordencompra_articulo_id');
        }
        $max = (int) ($q->max('nro_apertura') ?? 0);

        return $max + 1;
    }

    /**
     * @param  array{bruto: float, tara: float, neto: float}  $pesos
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $ocLinea
     * @return array{linea: RecepcionProveedorArticuloSurmar, etiqueta: Stock_Etiqueta, zpl: string}
     */
    private function crearLineaYEtiqueta(
        Recepcion_Proveedor $recepcion,
        Articulo $articulo,
        array $data,
        array $ocLinea,
        array $pesos,
        string $lote,
        $fechaVto,
        $fechaEmision,
        int $separa,
        int $cantUnid,
        int $nroApertura,
        float $cantPieza,
        float $precio,
        string $certificadoLinea,
        ?int $nroEstablecimiento,
        int $umdId
    ): array {
        $recepcion->loadMissing('proveedores');
        $ahora = now();
        $horaCarga = $ahora->format('H:i');
        $fechaEmisionYmd = $fechaEmision instanceof \Carbon\CarbonInterface
            ? $fechaEmision->format('Y-m-d')
            : (string) ($fechaEmision ?: $ahora->toDateString());
        $orden = (int) (RecepcionProveedorArticuloSurmar::query()
            ->where('recepcion_proveedor_id', $recepcion->id)
            ->max('orden') ?? 0) + 1;

        $linea = RecepcionProveedorArticuloSurmar::create([
            'recepcion_proveedor_id' => $recepcion->id,
            'ordencompra_articulo_id' => $ocLinea['ordencompra_articulo_id'] ?? null,
            'penvp_orden' => $ocLinea['penvp_orden'] ?? null,
            'penvp_nro_interno' => $ocLinea['penvp_nro_interno'] ?? null,
            'tipo_linea' => ($ocLinea['ordencompra_articulo_id'] ?? null)
                ? RecepcionProveedorDiferenciaSupport::TIPO_OC
                : RecepcionProveedorDiferenciaSupport::TIPO_EXTRA,
            'orden' => $orden,
            'articulo_id' => $articulo->id,
            'cantidad' => $pesos['neto'],
            'cantidad_stock' => $pesos['neto'],
            'cantidad_oc' => (float) ($ocLinea['cantidad_pendiente'] ?? $ocLinea['cantidad_oc'] ?? 0),
            'unidadmedida_id' => $umdId,
            'coeficienteconversion' => 1,
            'precio' => $precio,
            'precio_ordencompra' => (float) ($ocLinea['precio_ordencompra'] ?? $precio),
            'precio_stock' => $precio,
            'moneda_id' => $recepcion->moneda_id,
            'cotizacion' => $recepcion->cotizacion,
            'descuento' => 0,
            'deposito_id' => $recepcion->deposito_id,
            'centrocosto_id' => $recepcion->centrocosto_id,
            'detalle' => (string) ($data['detalle'] ?? $ocLinea['detalle'] ?? ''),
            'estado' => 'ACTIVA',
            'lote_proveedor' => $lote,
            'certificado' => $certificadoLinea,
            'fecha_vto' => $fechaVto,
            'peso_bruto' => $pesos['bruto'],
            'peso_tara' => $pesos['tara'],
            'peso_neto' => $pesos['neto'],
            'cant_pieza' => $cantPieza,
            'separa_unidadmedida_id' => $separa,
            'cant_unid_separa' => $cantUnid,
            'nro_apertura' => $nroApertura,
            'hora_piqueo' => $horaCarga,
            'piqueado_at' => $ahora,
        ]);

        $etiqueta = Stock_Etiqueta::create([
            'empresa_id' => SurmarSupport::EMPRESA_ID,
            'articulo_id' => $articulo->id,
            'deposito_id' => $recepcion->deposito_id,
            'unidadmedida_id' => $umdId,
            'separa_unidadmedida_id' => $separa,
            'cant_unid_separa' => $cantUnid,
            'estado' => SurmarSupport::ESTADO_DISPONIBLE,
            'origen_tipo' => SurmarSupport::ORIGEN_COM,
            'origen_id' => $recepcion->id,
            'origen_linea_id' => $linea->id,
            'lote_proveedor' => $lote,
            'fecha_vto' => $fechaVto,
            'fecha_emision' => $fechaEmisionYmd,
            'hora_emision' => $horaCarga,
            'cant_pieza' => $cantPieza,
            'peso_bruto' => $pesos['bruto'],
            'peso_neto' => $pesos['neto'],
            'nro_establecimiento' => $nroEstablecimiento,
            'descripcion_snapshot' => mb_substr((string) $articulo->descripcion, 0, 60),
            'anita_proveedor' => mb_substr(
                (string) ($recepcion->proveedores->fantasia ?? $recepcion->proveedores->nombre ?? ''),
                0,
                40
            ),
            'anita_tipo' => 'COM',
            'anita_orden' => $orden,
            'anita_nro_apertura' => $nroApertura,
            'usuario_id' => Auth::id(),
        ]);

        $linea->update(['stock_etiqueta_id' => $etiqueta->id]);

        $etiqueta = $etiqueta->fresh(['articulos', 'unidadesmedida', 'separaUnidadmedida']);
        $zpl = $this->zplDesdeEtiqueta($etiqueta, $recepcion);

        return [
            'linea' => $linea->fresh(['articulos', 'unidadesmedida', 'separaUnidadmedida', 'stock_etiqueta']),
            'etiqueta' => $etiqueta,
            'zpl' => $zpl,
        ];
    }

    public function eliminarLineaProvisoria(int $recepcionId, int $lineaId): void
    {
        $recepcion = $this->buscar($recepcionId);
        $this->assertBorrador($recepcion);

        DB::transaction(function () use ($recepcion, $lineaId) {
            $linea = RecepcionProveedorArticuloSurmar::query()
                ->where('recepcion_proveedor_id', $recepcion->id)
                ->whereKey($lineaId)
                ->firstOrFail();

            $etiquetaId = (int) ($linea->stock_etiqueta_id ?? 0);
            $linea->delete();

            if ($etiquetaId > 0) {
                Stock_Etiqueta::query()
                    ->whereKey($etiquetaId)
                    ->where('origen_tipo', SurmarSupport::ORIGEN_COM)
                    ->where('origen_id', $recepcion->id)
                    ->update(['estado' => SurmarSupport::ESTADO_ANULADA]);
            }

            $recepcion->touch();
        });
    }

    public function confirmar(int $id): Recepcion_Proveedor
    {
        $recepcion = $this->buscar($id);
        $this->assertBorrador($recepcion);

        $lineas = RecepcionProveedorArticuloSurmar::query()
            ->where('recepcion_proveedor_id', $recepcion->id)
            ->get();

        if ($lineas->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Debe cargar al menos un ítem antes de confirmar.']);
        }

        return DB::transaction(function () use ($recepcion, $lineas) {
            $movId = $this->generarMovimientoStock($recepcion, $lineas);

            foreach ($lineas as $linea) {
                if ((int) ($linea->stock_etiqueta_id ?? 0) <= 0) {
                    continue;
                }
                Stock_Etiqueta::query()->whereKey($linea->stock_etiqueta_id)->update([
                    'articulo_movimiento_id' => $linea->articulo_movimiento_id,
                    'deposito_id' => $linea->deposito_id ?: $recepcion->deposito_id,
                ]);
            }

            $recepcion->update([
                'estado' => Recepcion_Proveedor::ESTADO_CONFIRMADA,
                'movimientostock_id' => $movId,
            ]);

            Recepcion_Proveedor_Estado::create([
                'recepcion_proveedor_id' => $recepcion->id,
                'estado' => Recepcion_Proveedor::ESTADO_CONFIRMADA,
                'fecha' => now(),
                'usuario_id' => Auth::id(),
                'observacion' => 'Confirmación Surmar — stock generado',
            ]);

            return $recepcion->fresh();
        });
    }

    public function anular(int $id): Recepcion_Proveedor
    {
        $recepcion = $this->buscar($id);
        if ($recepcion->estado === Recepcion_Proveedor::ESTADO_ANULADA) {
            return $recepcion;
        }
        if ($recepcion->estado === Recepcion_Proveedor::ESTADO_CONFIRMADA) {
            throw ValidationException::withMessages(['estado' => 'Recepción confirmada: anulación Surmar pendiente de reverso de stock.']);
        }

        return DB::transaction(function () use ($recepcion) {
            Stock_Etiqueta::query()
                ->where('origen_tipo', SurmarSupport::ORIGEN_COM)
                ->where('origen_id', $recepcion->id)
                ->update(['estado' => SurmarSupport::ESTADO_ANULADA]);

            $recepcion->update(['estado' => Recepcion_Proveedor::ESTADO_ANULADA]);
            Recepcion_Proveedor_Estado::create([
                'recepcion_proveedor_id' => $recepcion->id,
                'estado' => Recepcion_Proveedor::ESTADO_ANULADA,
                'fecha' => now(),
                'usuario_id' => Auth::id(),
                'observacion' => 'Anulación borrador Surmar',
            ]);

            return $recepcion->fresh();
        });
    }

    public function eliminarBorrador(int $id): void
    {
        $recepcion = $this->buscar($id);
        $this->assertBorrador($recepcion);

        DB::transaction(function () use ($recepcion) {
            $recepcionId = (int) $recepcion->id;

            $etiquetaIds = RecepcionProveedorArticuloSurmar::query()
                ->where('recepcion_proveedor_id', $recepcionId)
                ->pluck('stock_etiqueta_id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->unique()
                ->values()
                ->all();

            // Rompe FK articulo → etiqueta antes de borrar etiquetas.
            RecepcionProveedorArticuloSurmar::query()
                ->where('recepcion_proveedor_id', $recepcionId)
                ->update(['stock_etiqueta_id' => null]);

            RecepcionProveedorArticuloSurmar::query()
                ->where('recepcion_proveedor_id', $recepcionId)
                ->delete();

            if ($etiquetaIds !== []) {
                // Hijas que referencian la etiqueta (certificado SENASA / consumo / movimiento).
                if (Schema::hasTable('certificado_senasa_surmar_etiqueta')) {
                    DB::table('certificado_senasa_surmar_etiqueta')
                        ->whereIn('etiqueta_id', $etiquetaIds)
                        ->delete();
                }
                if (Schema::hasTable('stock_etiqueta_consumo')) {
                    DB::table('stock_etiqueta_consumo')
                        ->whereIn('etiqueta_id', $etiquetaIds)
                        ->delete();
                }
                if (Schema::hasTable('stock_etiqueta_movimiento')) {
                    DB::table('stock_etiqueta_movimiento')
                        ->whereIn('etiqueta_id', $etiquetaIds)
                        ->delete();
                }

                Stock_Etiqueta::query()
                    ->whereIn('id', $etiquetaIds)
                    ->where('empresa_id', SurmarSupport::EMPRESA_ID)
                    ->update(['etiqueta_origen_id' => null]);

                Stock_Etiqueta::query()
                    ->whereIn('id', $etiquetaIds)
                    ->where('empresa_id', SurmarSupport::EMPRESA_ID)
                    ->delete();
            }

            Recepcion_Proveedor_Estado::query()
                ->where('recepcion_proveedor_id', $recepcionId)
                ->delete();

            if (Schema::hasTable('recepcion_proveedor_archivo')) {
                DB::table('recepcion_proveedor_archivo')
                    ->where('recepcion_proveedor_id', $recepcionId)
                    ->delete();
            }
            if (Schema::hasTable('recepcion_proveedor_parte_unica')) {
                DB::table('recepcion_proveedor_parte_unica')
                    ->where('recepcion_proveedor_id', $recepcionId)
                    ->delete();
            }
            if (Schema::hasTable('recepcion_proveedor_token')) {
                DB::table('recepcion_proveedor_token')
                    ->where('recepcion_proveedor_id', $recepcionId)
                    ->delete();
            }
            if (Schema::hasTable('comprobante_proveedor_recepcion')) {
                DB::table('comprobante_proveedor_recepcion')
                    ->where('recepcion_proveedor_id', $recepcionId)
                    ->delete();
            }
            if (Schema::hasTable('ordencompra_articulo_precio_historia')) {
                DB::table('ordencompra_articulo_precio_historia')
                    ->where('recepcion_proveedor_id', $recepcionId)
                    ->update([
                        'recepcion_proveedor_id' => null,
                        'recepcion_proveedor_articulo_id' => null,
                    ]);
            }

            $recepcion->delete();
        });
    }

    public function zplEtiqueta(int $etiquetaId): string
    {
        $etiqueta = Stock_Etiqueta::query()
            ->with(['articulos', 'unidadesmedida'])
            ->whereKey($etiquetaId)
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->firstOrFail();

        $recepcion = null;
        if ($etiqueta->origen_tipo === SurmarSupport::ORIGEN_COM && $etiqueta->origen_id) {
            $recepcion = Recepcion_Proveedor::query()->find($etiqueta->origen_id);
        }

        return $this->zplDesdeEtiqueta($etiqueta, $recepcion);
    }

    /** @return array<string, mixed> */
    public function lineaPayload(RecepcionProveedorArticuloSurmar $linea): array
    {
        $art = $linea->articulos;
        $linea->loadMissing(['separaUnidadmedida', 'stock_etiqueta']);

        return [
            'id' => $linea->id,
            'orden' => $linea->orden,
            'articulo_id' => $linea->articulo_id,
            'codigo' => $art->sku ?? '',
            'descripcion' => $art->descripcion ?? $linea->detalle,
            'lote_proveedor' => $linea->lote_proveedor,
            'certificado' => $linea->certificado,
            'fecha_vto' => optional($linea->fecha_vto)->format('Y-m-d'),
            'peso_bruto' => (float) $linea->peso_bruto,
            'peso_tara' => (float) ($linea->peso_tara ?? 0),
            'peso_neto' => (float) $linea->peso_neto,
            'cant_pieza' => (float) $linea->cant_pieza,
            'separa_unidadmedida_id' => (int) ($linea->separa_unidadmedida_id ?? 0),
            'separa_abreviatura' => SurmarUnidadmedidaSeparaSupport::abreviatura((int) ($linea->separa_unidadmedida_id ?? 0)),
            'cant_unid_separa' => (int) ($linea->cant_unid_separa ?? 1),
            'nro_apertura' => (int) ($linea->nro_apertura ?? 1),
            'hora_piqueo' => $linea->hora_piqueo,
            'piqueado_at' => optional($linea->piqueado_at)->format('d/m/Y H:i:s'),
            'stock_etiqueta_id' => $linea->stock_etiqueta_id,
            'ordencompra_articulo_id' => $linea->ordencompra_articulo_id,
            'tipo_linea' => (string) ($linea->tipo_linea ?: (
                $linea->ordencompra_articulo_id
                    ? RecepcionProveedorDiferenciaSupport::TIPO_OC
                    : RecepcionProveedorDiferenciaSupport::TIPO_EXTRA
            )),
            'cantidad_oc' => (float) ($linea->cantidad_oc ?? 0),
            'precio' => (float) ($linea->precio ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolverLineaOcParaGrabar(Recepcion_Proveedor $recepcion, array $data): array
    {
        $ocArtId = (int) ($data['ordencompra_articulo_id'] ?? 0);
        $ocId = (int) ($recepcion->ordencompra_id ?? 0);
        $articuloId = (int) ($data['articulo_id'] ?? 0);

        // Artículo fuera de la OC (EXTRA): solo requiere articulo_id.
        if ($ocArtId <= 0) {
            if ($articuloId <= 0) {
                throw ValidationException::withMessages([
                    'articulo_id' => 'Seleccione un artículo (línea OC o consulta de artículos).',
                ]);
            }

            return [
                'ordencompra_articulo_id' => null,
                'penvp_orden' => null,
                'penvp_nro_interno' => null,
                'articulo_id' => $articuloId,
                'precio' => (float) ($data['precio'] ?? 0),
                'precio_ordencompra' => 0.0,
                'cantidad_oc' => 0.0,
                'cantidad_pendiente' => 0.0,
                'unidadmedida_id' => (int) ($data['unidadmedida_id'] ?? 0) ?: null,
                'detalle' => (string) ($data['detalle'] ?? ''),
                'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_EXTRA,
            ];
        }

        if ($ocId <= 0) {
            throw ValidationException::withMessages(['ordencompra_id' => 'La recepción no tiene OC vinculada.']);
        }

        $ocArt = Ordencompra_Articulo::query()
            ->with('articulos')
            ->whereKey($ocArtId)
            ->where('ordencompra_id', $ocId)
            ->first();

        if (! $ocArt) {
            throw ValidationException::withMessages([
                'ordencompra_articulo_id' => 'La línea no pertenece a la OC de esta recepción.',
            ]);
        }

        $pendientes = RecepcionProveedorSurmarOcSupport::armarLineasPendientes(
            RecepcionProveedorSurmarOcSupport::cargarOc($ocId)
        );
        foreach ($pendientes as $pend) {
            if ((int) $pend['ordencompra_articulo_id'] === $ocArtId) {
                $pend['tipo_linea'] = RecepcionProveedorDiferenciaSupport::TIPO_OC;

                return $pend;
            }
        }

        // Permite otra etiqueta de la misma línea OC en este borrador (saldo OC = COM confirmadas).
        $art = $ocArt->articulos;

        return [
            'ordencompra_articulo_id' => (int) $ocArt->id,
            'penvp_orden' => (int) ($ocArt->penvp_orden ?? 0) ?: null,
            'penvp_nro_interno' => (int) ($ocArt->penvp_nro_interno ?? 0) ?: null,
            'articulo_id' => (int) $ocArt->articulo_id,
            'precio' => (float) ($ocArt->precio ?? 0),
            'precio_ordencompra' => (float) ($ocArt->precio ?? 0),
            'cantidad_oc' => (float) ($ocArt->cantidad ?? 0),
            'cantidad_pendiente' => (float) ($ocArt->cantidad ?? 0),
            'unidadmedida_id' => (int) ($art->unidadmedida_id ?? 0) ?: null,
            'detalle' => (string) ($ocArt->detalle ?? ''),
            'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
        ];
    }

    private function assertBorrador(Recepcion_Proveedor $recepcion): void
    {
        if ($recepcion->estado !== Recepcion_Proveedor::ESTADO_BORRADOR) {
            throw ValidationException::withMessages(['estado' => 'La recepción ya no está en estado provisorio.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $ocLinea
     */
    private function resolverUnidadmedidaId(Articulo $articulo, array $data, array $ocLinea): int
    {
        $umdId = (int) ($data['unidadmedida_id'] ?? $ocLinea['unidadmedida_id'] ?? $articulo->unidadmedida_id ?? 0);
        if ($umdId > 0) {
            return $umdId;
        }

        $kgId = (int) (Unidadmedida::query()->where('abreviatura', 'KG')->value('id') ?? 0);
        if ($kgId > 0) {
            return $kgId;
        }

        throw ValidationException::withMessages([
            'unidadmedida_id' => 'El artículo no tiene unidad de medida y no hay KG configurada.',
        ]);
    }

    /** @param \Illuminate\Support\Collection<int, RecepcionProveedorArticuloSurmar> $lineas */
    private function generarMovimientoStock(Recepcion_Proveedor $recepcion, $lineas): int
    {
        $tipoStock = Tipotransaccion_Stock::where('abreviatura', 'RCING')->first();
        if (! $tipoStock) {
            throw new \RuntimeException('Tipo transacción stock RCING no configurado.');
        }

        $signoDb = (int) $tipoStock->getRawOriginal('signo');
        $concepto = 'Recepción Surmar '.$recepcion->numerorecepcion;

        $mov = MovimientoStock::create([
            'fecha' => $recepcion->fecha->format('Y-m-d'),
            'fechajornada' => $recepcion->fecha->format('Y-m-d'),
            'tipotransaccion_stock_id' => $tipoStock->id,
            'codigo' => substr((string) $recepcion->numerorecepcion, 0, 10),
            'leyenda' => $concepto,
            'estado' => 'A',
            'usuario_id' => Auth::id(),
        ]);

        foreach ($lineas as $linea) {
            $cantidad = (float) $linea->cantidad;
            if ($cantidad <= 0.000001) {
                continue;
            }

            $cantidadFirmada = ArticuloMovimientoCantidadSignoSupport::cantidadFirmadaSignoStock(
                $cantidad,
                $signoDb
            );

            $am = Articulo_Movimiento::create([
                'fecha' => $recepcion->fecha->format('Y-m-d'),
                'fechajornada' => $recepcion->fecha->format('Y-m-d'),
                'tipotransaccion_stock_id' => $tipoStock->id,
                'movimientostock_id' => $mov->id,
                'articulo_id' => $linea->articulo_id,
                'concepto' => $concepto,
                'cantidad' => $cantidadFirmada,
                'precio' => (float) ($linea->precio_stock ?: $linea->precio),
                'costo' => (float) ($linea->precio_stock ?: $linea->precio),
                'descuento' => 0,
                'moneda_id' => $linea->moneda_id,
                'incluyeimpuesto' => 'N',
                'deposito_id' => $linea->deposito_id ?: $recepcion->deposito_id,
                'lote' => 0,
            ]);

            $linea->update(['articulo_movimiento_id' => $am->id]);
        }

        return (int) $mov->id;
    }

    private function zplDesdeEtiqueta(Stock_Etiqueta $etiqueta, ?Recepcion_Proveedor $recepcion): string
    {
        return SurmarEtiquetaZplSupport::generar($this->datosEtiquetaParaZpl($etiqueta, $recepcion));
    }

    /** @return array<string, mixed> */
    public function previewDesdeEtiqueta(Stock_Etiqueta $etiqueta, ?Recepcion_Proveedor $recepcion): array
    {
        return SurmarEtiquetaZplSupport::datosPreview($this->datosEtiquetaParaZpl($etiqueta, $recepcion));
    }

    /** @return array<string, mixed> */
    private function datosEtiquetaParaZpl(Stock_Etiqueta $etiqueta, ?Recepcion_Proveedor $recepcion): array
    {
        $etiqueta->loadMissing(['articulos', 'unidadesmedida', 'separaUnidadmedida']);
        $art = $etiqueta->articulos;

        if (! $recepcion
            && $etiqueta->origen_tipo === SurmarSupport::ORIGEN_COM
            && (int) ($etiqueta->origen_id ?? 0) > 0
        ) {
            $recepcion = Recepcion_Proveedor::query()
                ->with('proveedores')
                ->find((int) $etiqueta->origen_id);
        }

        $proveedorNombre = '';
        if ($recepcion) {
            $recepcion->loadMissing('proveedores');
            $proveedorNombre = (string) ($recepcion->proveedores->fantasia ?? $recepcion->proveedores->nombre ?? '');
        }
        if ($proveedorNombre === '' && trim((string) ($etiqueta->anita_proveedor ?? '')) !== '') {
            $proveedorNombre = trim((string) $etiqueta->anita_proveedor);
        }

        $umdSepara = SurmarUnidadmedidaSeparaSupport::abreviatura(
            (int) ($etiqueta->separa_unidadmedida_id ?? 0)
        );
        if ($umdSepara === 'UN' && $etiqueta->relationLoaded('separaUnidadmedida') && $etiqueta->separaUnidadmedida) {
            $umdSepara = trim((string) ($etiqueta->separaUnidadmedida->abreviatura ?? 'UN')) ?: 'UN';
        }

        return [
            'id' => (int) $etiqueta->id,
            'codigo_articulo' => (string) ($art->sku ?? ''),
            'descripcion' => (string) ($etiqueta->descripcion_snapshot ?: ($art->descripcion ?? '')),
            'proveedor' => $proveedorNombre,
            'peso_bruto' => (float) $etiqueta->peso_bruto,
            'peso_neto' => (float) $etiqueta->peso_neto,
            'cant_pieza' => (float) $etiqueta->cant_pieza,
            'umd_separa' => $umdSepara,
            'cant_unid_separa' => (int) ($etiqueta->cant_unid_separa ?? 1),
            'nro_apertura' => (int) ($etiqueta->anita_nro_apertura ?? 1),
            'lote' => (string) $etiqueta->lote_proveedor,
            'fecha' => optional($etiqueta->fecha_emision)->format('d/m/Y'),
            'fecha_vto' => optional($etiqueta->fecha_vto)->format('d/m/Y'),
        ];
    }
}
