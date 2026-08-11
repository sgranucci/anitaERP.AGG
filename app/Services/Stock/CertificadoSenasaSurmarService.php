<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\CertificadoSenasaSurmar;
use App\Models\Stock\CertificadoSenasaSurmarArticulo;
use App\Models\Stock\CertificadoSenasaSurmarCliente;
use App\Models\Stock\CertificadoSenasaSurmarEtiqueta;
use App\Models\Stock\Stock_Etiqueta;
use App\Models\Ventas\Camion;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Transporte;
use App\Services\Arca\ArcaWsremcarneService;
use App\Support\Stock\CertificadoSenasaSurmarListadoFiltros;
use App\Support\Stock\CertificadoSenasaSurmarRemitoPayloadBuilder;
use App\Support\Stock\CertificadoSenasaSurmarXmlBuilder;
use App\Support\Stock\SurmarSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Certificado SENASA Surmar + remito cárnico AFIP (a-certsan / remito_electronico).
 * Borrador + grabado provisorio por ítem con aperturas stock_etiqueta.
 */
class CertificadoSenasaSurmarService
{
    public function __construct(
        private readonly ArcaWsremcarneService $wsremcarne,
        private readonly CertificadoSenasaSurmarRemitoPayloadBuilder $remitoBuilder,
        private readonly CertificadoSenasaSurmarXmlBuilder $xmlBuilder,
    ) {
    }

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros = [], bool $paginar = true)
    {
        $query = CertificadoSenasaSurmar::query()
            ->withCount('articulos')
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->orderByDesc('id');

        if (CertificadoSenasaSurmarListadoFiltros::tieneCriteriosAplicados($filtros)) {
            CertificadoSenasaSurmarListadoFiltros::aplicar($query, $filtros);
        }

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function buscar(int $id): CertificadoSenasaSurmar
    {
        return CertificadoSenasaSurmar::query()
            ->with([
                'camion',
                'transporte',
                'cliente',
                'articulos.articulo',
                'articulos.etiquetas.etiqueta',
                'clientes',
                'destinos',
                'etiquetas',
            ])
            ->whereKey($id)
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function iniciar(array $data): CertificadoSenasaSurmar
    {
        $empresaId = SurmarSupport::EMPRESA_ID;
        SurmarSupport::abortSiNoSurmar($empresaId);

        $camionId = (int) ($data['camion_id'] ?? 0);
        $transporteId = (int) ($data['transporte_id'] ?? 0);
        $clienteId = (int) ($data['cliente_id'] ?? 0);
        $fecha = (string) ($data['fecha'] ?? now()->toDateString());

        if ($camionId <= 0 || ! Camion::query()->whereKey($camionId)->exists()) {
            throw ValidationException::withMessages(['camion_id' => 'Camión inválido.']);
        }
        if ($transporteId > 0 && ! Transporte::query()->whereKey($transporteId)->exists()) {
            throw ValidationException::withMessages(['transporte_id' => 'Transporte inválido.']);
        }
        if ($clienteId <= 0 || ! Cliente::query()->whereKey($clienteId)->exists()) {
            throw ValidationException::withMessages(['cliente_id' => 'Cliente inválido.']);
        }

        return DB::transaction(function () use ($data, $empresaId, $camionId, $transporteId, $clienteId, $fecha) {
            $serie = strtoupper(substr((string) ($data['serie'] ?? 'A'), 0, 1)) ?: 'A';
            $numero = $this->siguienteNumero($empresaId, $serie);
            $puntoEmision = (int) ($data['punto_emision']
                ?? config('arca_wsremcarne.defaults.punto_emision', 1));
            $idReq = $this->siguienteIdReq($empresaId, $puntoEmision);

            $camion = Camion::query()->find($camionId);
            $cliente = Cliente::query()->find($clienteId);
            $transporte = $transporteId > 0 ? Transporte::query()->find($transporteId) : null;

            $cert = CertificadoSenasaSurmar::create([
                'empresa_id' => $empresaId,
                'numero' => $numero,
                'serie' => $serie,
                'fecha' => $fecha,
                'estado' => CertificadoSenasaSurmar::ESTADO_BORRADOR,
                'camion_id' => $camionId,
                'transporte_id' => $transporteId > 0 ? $transporteId : null,
                'cliente_id' => $clienteId,
                'precinto' => trim((string) ($data['precinto'] ?? '')),
                'origen' => (string) ($data['origen'] ?? config('senasa.origen_default')),
                'procedencia' => (string) ($data['procedencia'] ?? config('senasa.procedencia_default')),
                'opcion' => 1,
                'cantidad_precinto' => (int) ($data['cantidad_precinto'] ?? $camion->cantidad_precinto ?? 0),
                'establecimiento_nro' => (string) ($data['establecimiento_nro'] ?? config('senasa.establecimiento')),
                'establecimiento_destino' => (int) ($data['establecimiento_destino'] ?? 0) ?: null,
                'temperatura' => isset($data['temperatura']) ? (float) $data['temperatura'] : null,
                'abre_por_localidad' => (bool) ($data['abre_por_localidad'] ?? false),
                'genera_web' => array_key_exists('genera_web', $data) ? (bool) $data['genera_web'] : true,
                'genera_remito' => array_key_exists('genera_remito', $data) ? (bool) $data['genera_remito'] : true,
                'observacion' => (string) ($data['observacion'] ?? ''),
                'punto_emision' => $puntoEmision,
                'id_req' => $idReq,
                'tipo_movimiento' => (string) config('arca_wsremcarne.defaults.tipo_movimiento', 'ENV'),
                'categoria_emisor' => (int) config('arca_wsremcarne.defaults.categoria_emisor', 3),
                'tipo_receptor' => (string) config('arca_wsremcarne.defaults.tipo_receptor', 'MI'),
                'categoria_receptor' => (int) config('arca_wsremcarne.defaults.categoria_receptor', 2),
                'cuit_titular' => (string) config('arca_wsremcarne.cuit_titular_default', '30505150372'),
                'cuit_receptor' => preg_replace('/\D+/', '', (string) ($cliente->numerodocumento ?? '')) ?: null,
                'cuit_transportista' => preg_replace('/\D+/', '', (string) ($transporte->nroinscripcion ?? '')) ?: null,
                'cuit_conductor' => preg_replace('/\D+/', '', (string) ($camion->cuit_chofer ?? $transporte->cuit_chofer ?? '')) ?: null,
                'dominio_vehiculo' => (string) ($camion->dominio ?? ''),
                'dominio_acoplado' => (string) ($camion->dominio_acoplado ?? ''),
                'distancia_km' => (float) config('arca_wsremcarne.defaults.distancia_km', 1),
                'cod_dom_origen' => (int) ($data['cod_dom_origen'] ?? 0) ?: null,
                'cod_dom_destino' => (int) ($data['cod_dom_destino'] ?? 0) ?: null,
                'nro_cert_interno' => $numero,
                'usuario_id' => Auth::id(),
            ]);

            CertificadoSenasaSurmarCliente::create([
                'certificado_senasa_surmar_id' => $cert->id,
                'linea' => 1,
                'cliente_id' => $clienteId,
                'codigo_cliente' => (string) ($cliente->codigo ?? ''),
            ]);

            return $cert;
        });
    }

    /**
     * Graba/actualiza línea + asocia etiquetas (AJAX workbench).
     *
     * @param  array<string, mixed>  $data
     * @return array{linea: array<string, mixed>}
     */
    public function guardarLineaProvisoria(int $certId, array $data): array
    {
        $cert = $this->buscar($certId);
        if (! $cert->esEditable()) {
            throw ValidationException::withMessages(['estado' => 'El certificado no está en borrador.']);
        }

        $articuloId = (int) ($data['articulo_id'] ?? 0);
        $articulo = Articulo::query()->find($articuloId);
        if (! $articulo) {
            throw ValidationException::withMessages(['articulo_id' => 'Artículo inválido.']);
        }

        $etiquetaIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($data['etiqueta_ids'] ?? [])
        ))));
        if ($etiquetaIds === []) {
            throw ValidationException::withMessages(['etiqueta_ids' => 'Debe asociar al menos una etiqueta.']);
        }

        $etiquetas = Stock_Etiqueta::query()
            ->where('empresa_id', SurmarSupport::EMPRESA_ID)
            ->whereIn('id', $etiquetaIds)
            ->get();
        if ($etiquetas->count() !== count($etiquetaIds)) {
            throw ValidationException::withMessages(['etiqueta_ids' => 'Alguna etiqueta no existe o no es Surmar.']);
        }
        foreach ($etiquetas as $eti) {
            if ((int) $eti->articulo_id !== $articuloId) {
                throw ValidationException::withMessages([
                    'etiqueta_ids' => 'La etiqueta #'.$eti->id.' no corresponde al artículo.',
                ]);
            }
            if ($eti->estado === SurmarSupport::ESTADO_ANULADA) {
                throw ValidationException::withMessages([
                    'etiqueta_ids' => 'La etiqueta #'.$eti->id.' está anulada.',
                ]);
            }
        }

        $kilos = isset($data['kilos'])
            ? (float) $data['kilos']
            : (float) $etiquetas->sum('peso_neto');
        $cajas = isset($data['cajas'])
            ? (float) $data['cajas']
            : (float) max(1, $etiquetas->count());
        $piezas = isset($data['piezas'])
            ? (float) $data['piezas']
            : (float) $etiquetas->sum('cant_pieza');
        if ($kilos <= 0) {
            throw ValidationException::withMessages(['kilos' => 'Los kilos deben ser mayores a 0.']);
        }

        $hora = now()->format('H:i');

        $linea = DB::transaction(function () use ($cert, $data, $articulo, $etiquetas, $kilos, $cajas, $piezas, $hora) {
            $lineaId = (int) ($data['linea_id'] ?? 0);
            if ($lineaId > 0) {
                $linea = CertificadoSenasaSurmarArticulo::query()
                    ->where('certificado_senasa_surmar_id', $cert->id)
                    ->whereKey($lineaId)
                    ->firstOrFail();
                CertificadoSenasaSurmarEtiqueta::query()
                    ->where('certificado_senasa_surmar_articulo_id', $linea->id)
                    ->delete();
            } else {
                $maxLinea = (int) CertificadoSenasaSurmarArticulo::query()
                    ->where('certificado_senasa_surmar_id', $cert->id)
                    ->max('linea');
                $linea = new CertificadoSenasaSurmarArticulo([
                    'certificado_senasa_surmar_id' => $cert->id,
                    'linea' => $maxLinea + 1,
                ]);
            }

            $linea->fill([
                'articulo_id' => $articulo->id,
                'sku' => (string) $articulo->sku,
                'kilos' => $kilos,
                'cajas' => $cajas,
                'piezas' => $piezas,
                'tropa' => isset($data['tropa']) ? (int) $data['tropa'] : null,
                'grupocarne' => (int) ($articulo->grupocarne ?? 0) ?: null,
                'tipocarne' => (int) ($articulo->tipocarne ?? 0) ?: null,
                'cert_tercero' => trim((string) ($data['cert_tercero'] ?? '')) ?: null,
                'partida' => isset($data['partida']) ? (int) $data['partida'] : null,
                'hora_piqueo' => $hora,
            ]);
            $linea->save();

            foreach ($etiquetas as $eti) {
                CertificadoSenasaSurmarEtiqueta::create([
                    'empresa_id' => SurmarSupport::EMPRESA_ID,
                    'certificado_senasa_surmar_id' => $cert->id,
                    'certificado_senasa_surmar_articulo_id' => $linea->id,
                    'etiqueta_id' => $eti->id,
                    'articulo_id' => $eti->articulo_id,
                    'cant_pieza' => (float) $eti->cant_pieza,
                    'peso_bruto' => (float) $eti->peso_bruto,
                    'peso_neto' => (float) $eti->peso_neto,
                    'lote_proveedor' => $eti->lote_proveedor,
                    'hora_piqueo' => $hora,
                ]);
            }

            $this->recalcularTotalesCabecera($cert->id);

            return $linea->fresh(['articulo', 'etiquetas.etiqueta']);
        });

        return ['linea' => $this->lineaPayload($linea)];
    }

    public function eliminarLinea(int $certId, int $lineaId): void
    {
        $cert = $this->buscar($certId);
        if (! $cert->esEditable()) {
            throw ValidationException::withMessages(['estado' => 'El certificado no está en borrador.']);
        }

        DB::transaction(function () use ($cert, $lineaId) {
            $linea = CertificadoSenasaSurmarArticulo::query()
                ->where('certificado_senasa_surmar_id', $cert->id)
                ->whereKey($lineaId)
                ->firstOrFail();
            CertificadoSenasaSurmarEtiqueta::query()
                ->where('certificado_senasa_surmar_articulo_id', $linea->id)
                ->delete();
            $linea->delete();
            $this->recalcularTotalesCabecera($cert->id);
        });
    }

    public function confirmar(int $certId): CertificadoSenasaSurmar
    {
        $cert = $this->buscar($certId);
        if (! $cert->esEditable()) {
            throw ValidationException::withMessages(['estado' => 'El certificado no está en borrador.']);
        }
        if ($cert->articulos->isEmpty()) {
            throw ValidationException::withMessages(['articulos' => 'Debe cargar al menos un ítem.']);
        }

        return DB::transaction(function () use ($cert) {
            $cert = $this->buscar($cert->id);

            if ($cert->genera_remito) {
                if (! $cert->id_req) {
                    $cert->id_req = $this->siguienteIdReq(
                        (int) $cert->empresa_id,
                        (int) ($cert->punto_emision ?: config('arca_wsremcarne.defaults.punto_emision', 1))
                    );
                    $cert->save();
                }

                $payload = $this->remitoBuilder->build($cert);
                try {
                    $resp = $this->wsremcarne->generarRemito((int) $cert->empresa_id, $payload);
                } catch (\Throwable $e) {
                    $cert->mensaje_afip = mb_substr($e->getMessage(), 0, 2000);
                    $cert->resultado_afip = 'R';
                    $cert->save();
                    throw ValidationException::withMessages([
                        'remito' => 'AFIP remito cárnico: '.$e->getMessage(),
                    ]);
                }

                $cert->cod_remito = (string) ($resp['cod_remito'] ?? '');
                $cert->cod_autorizacion = (string) ($resp['cod_autorizacion'] ?? '');
                $cert->estado_afip = (string) ($resp['estado'] ?? '');
                $cert->resultado_afip = (string) ($resp['resultado'] ?? 'A');
                $cert->fecha_emision_afip = $this->parseFechaAfip($resp['fecha_emision'] ?? null);
                $cert->fecha_vto_afip = $this->parseFechaAfip($resp['fecha_vencimiento'] ?? null);
                $cert->mensaje_afip = null;

                if (! empty($resp['qr'])) {
                    $qrRel = 'senasa/surmar/qr_'.$cert->id.'.bin';
                    Storage::disk('local')->put($qrRel, is_string($resp['qr']) ? $resp['qr'] : (string) $resp['qr']);
                    $cert->qr_path = $qrRel;
                }
                $cert->save();
            }

            if ($cert->genera_web) {
                $xml = $this->xmlBuilder->build($cert->fresh(['articulos.articulo.codigosenasas.envasesenasas', 'destinos', 'camion', 'cliente']));
                if ($xml !== '') {
                    $rel = 'senasa/surmar/certSURMAR_'.$cert->serie.$cert->numero.'.xml';
                    Storage::disk('local')->put($rel, $xml);
                    $cert->xml_path = $rel;
                    $cert->save();
                }
            }

            $cert->estado = CertificadoSenasaSurmar::ESTADO_CONFIRMADO;
            $cert->save();

            return $cert->fresh(['articulos.etiquetas', 'camion', 'cliente']);
        });
    }

    public function anular(int $certId): CertificadoSenasaSurmar
    {
        $cert = $this->buscar($certId);
        if ($cert->estado === CertificadoSenasaSurmar::ESTADO_ANULADO) {
            return $cert;
        }
        if ($cert->estado === CertificadoSenasaSurmar::ESTADO_CONFIRMADO && $cert->cod_remito) {
            throw ValidationException::withMessages([
                'estado' => 'Certificado con remito AFIP autorizado: anulación AFIP no implementada en fase 1. Contacte soporte.',
            ]);
        }

        $cert->estado = CertificadoSenasaSurmar::ESTADO_ANULADO;
        $cert->save();

        return $cert;
    }

    /** @return array<string, mixed> */
    public function lineaPayload(CertificadoSenasaSurmarArticulo $linea): array
    {
        $linea->loadMissing(['articulo', 'etiquetas.etiqueta']);

        return [
            'id' => $linea->id,
            'linea' => $linea->linea,
            'articulo_id' => $linea->articulo_id,
            'sku' => $linea->sku,
            'descripcion' => $linea->articulo->descripcion ?? '',
            'kilos' => (float) $linea->kilos,
            'cajas' => (float) $linea->cajas,
            'piezas' => (float) $linea->piezas,
            'tropa' => $linea->tropa,
            'grupocarne' => $linea->grupocarne,
            'tipocarne' => $linea->tipocarne,
            'cod_tipo_prod' => $linea->codTipoProdAfip(),
            'hora_piqueo' => $linea->hora_piqueo,
            'etiquetas' => $linea->etiquetas->map(fn (CertificadoSenasaSurmarEtiqueta $e) => [
                'id' => $e->id,
                'etiqueta_id' => $e->etiqueta_id,
                'peso_neto' => (float) $e->peso_neto,
                'lote_proveedor' => $e->lote_proveedor,
                'hora_piqueo' => $e->hora_piqueo,
            ])->values()->all(),
        ];
    }

    /**
     * Resolver etiqueta por ID ERP o barcode Anita (nint-nap / sku-nint-nap).
     *
     * @return array<string, mixed>
     */
    public function resolverEtiqueta(string|int $codigo): array
    {
        $raw = trim((string) $codigo);
        $resolved = \App\Support\Stock\Surmar\SurmarEtiquetaLookupSupport::resolver(
            $raw,
            SurmarSupport::EMPRESA_ID,
            soloDisponible: false,
            rechazarAnulada: true,
        );
        $p = $resolved['payload'];

        return [
            'etiqueta_id' => $p['etiqueta_id'],
            'articulo_id' => $p['articulo_id'],
            'sku' => $p['sku'],
            'descripcion' => $p['descripcion'],
            'peso_neto' => $p['peso_neto'],
            'peso_bruto' => $p['peso_bruto'],
            'cant_pieza' => $p['cant_pieza'],
            'lote_proveedor' => $p['lote_proveedor'],
            'estado' => $p['estado'],
            'grupocarne' => $p['grupocarne'],
            'tipocarne' => $p['tipocarne'],
            'anita_nro_interno' => $p['anita_nro_interno'],
            'anita_nro_apertura' => $p['anita_nro_apertura'],
        ];
    }

    private function recalcularTotalesCabecera(int $certId): void
    {
        $sumKilos = (float) CertificadoSenasaSurmarArticulo::query()
            ->where('certificado_senasa_surmar_id', $certId)
            ->sum('kilos');
        $sumCajas = (float) CertificadoSenasaSurmarArticulo::query()
            ->where('certificado_senasa_surmar_id', $certId)
            ->sum('cajas');
        $sumPiezas = (float) CertificadoSenasaSurmarArticulo::query()
            ->where('certificado_senasa_surmar_id', $certId)
            ->sum('piezas');

        CertificadoSenasaSurmar::query()->whereKey($certId)->update([
            'cantidad_caja' => (int) round($sumCajas),
            'cantidad_bulto' => (int) round($sumPiezas > 0 ? $sumPiezas : $sumKilos),
        ]);
    }

    private function siguienteNumero(int $empresaId, string $serie): int
    {
        $max = (int) CertificadoSenasaSurmar::query()
            ->where('empresa_id', $empresaId)
            ->where('serie', $serie)
            ->max('numero');

        return $max + 1;
    }

    private function siguienteIdReq(int $empresaId, int $puntoEmision): int
    {
        try {
            $ult = $this->wsremcarne->consultarUltimoRemitoEmitido($empresaId, $puntoEmision);
            $id = (int) ($ult['id_req'] ?? 0);
            if ($id > 0) {
                return $id + 1;
            }
            $nro = (int) preg_replace('/\D+/', '', (string) ($ult['nro_remito'] ?? $ult['cod_remito'] ?? '')) ?: 0;
            if ($nro > 0) {
                return $nro + 1;
            }
        } catch (\Throwable) {
            // Fallback local si AFIP no responde en el alta de borrador
        }

        $max = (int) CertificadoSenasaSurmar::query()
            ->where('empresa_id', $empresaId)
            ->where('punto_emision', $puntoEmision)
            ->max('id_req');

        return max(1, $max + 1);
    }

    private function parseFechaAfip(mixed $valor): ?string
    {
        $s = trim((string) ($valor ?? ''));
        if ($s === '') {
            return null;
        }
        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
