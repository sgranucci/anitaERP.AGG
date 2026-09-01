<?php

namespace App\Services\Ventas;

use App\Models\Stock\Codigosenasa;
use App\Models\Ventas\Camion;
use App\Models\Ventas\CertificadoSanitario;
use App\Models\Ventas\CertificadoSanitarioArticulo;
use App\Models\Ventas\CertificadoSanitarioCliente;
use App\Models\Ventas\CertificadoSanitarioDestino;
use App\Models\Ventas\Transporte;
use App\Repositories\Stock\CodigosenasaRepositoryInterface;
use App\Support\Ventas\CertificadoSanitarioListadoFiltros;
use App\Support\Ventas\CertificadoSanitario\CertificadoSanitarioAnitaNumeracionSupport;
use App\Support\Ventas\CertificadoSanitario\CertificadoSanitarioDestinoAnitaSupport;
use App\Support\Ventas\CertificadoSanitario\CertificadoSanitarioOrigenSupport;
use App\Support\Ventas\CertificadoSanitario\CertificadoSanitarioWebXmlBuilder;
use App\Support\Ventas\CertificadoSanitario\PedidoCertificadoLinea;
use App\Support\Ventas\CertificadoSanitario\PedidoCertificadoListado;
use App\Support\Ventas\CertificadoSanitario\PedidoCertificadoSource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class CertificadoSanitarioService
{
    public function __construct(
        private PedidoCertificadoSource $pedidoSource,
        private CertificadoSanitarioWebXmlBuilder $xmlBuilder,
        private CodigosenasaRepositoryInterface $codigosenasaRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, PedidoCertificadoLinea>
     */
    public function previewLineas(array $filtros): Collection
    {
        return $this->pedidoSource->listarLineas($filtros);
    }

    public function previewConsulta(array $filtros): PedidoCertificadoListado
    {
        return $this->pedidoSource->listar($filtros);
    }

    /**
     * @param  array<string, mixed>|string  $filtros
     * @return LengthAwarePaginator<int, CertificadoSanitario>|Collection<int, CertificadoSanitario>
     */
    public function listar(array|string $filtros = [], bool $paginar = true)
    {
        $filtros = $this->normalizarFiltrosListado($filtros);
        $query = CertificadoSanitario::query()
            ->select('certificado_sanitario.*')
            ->with(['camion', 'transporte'])
            ->withSum('articulos as kilos_total', 'cantidad')
            ->withSum('articulos as cajas_total', 'cajas')
            ->orderByDesc('certificado_sanitario.id');
        $this->aplicarFiltrosListado($query, $filtros);

        $nombreEmpresa = (string) config('app.empresa');
        $asignarEmpresa = static function (CertificadoSanitario $row) use ($nombreEmpresa): CertificadoSanitario {
            $row->nombreempresa = $nombreEmpresa;

            return $row;
        };

        if ($paginar) {
            $pagina = $query->paginate(10);
            $pagina->getCollection()->transform($asignarEmpresa);

            return $pagina;
        }

        return $query->get()->transform($asignarEmpresa);
    }

    /**
     * Totales de kilos/cajas del filtro completo (no solo la página visible).
     *
     * @param  array<string, mixed>|string  $filtros
     * @return array{certificados: int, kilos: float, cajas: float}
     */
    public function totalesListado(array|string $filtros = []): array
    {
        $filtros = $this->normalizarFiltrosListado($filtros);
        $ids = CertificadoSanitario::query()->select('certificado_sanitario.id');
        $this->aplicarFiltrosListado($ids, $filtros);

        $row = DB::table('certificado_sanitario_articulo')
            ->whereIn('certificado_sanitario_id', $ids)
            ->selectRaw('COUNT(DISTINCT certificado_sanitario_id) as certificados, COALESCE(SUM(cantidad), 0) as kilos, COALESCE(SUM(cajas), 0) as cajas')
            ->first();

        return [
            'certificados' => (int) ($row->certificados ?? 0),
            'kilos' => (float) ($row->kilos ?? 0),
            'cajas' => (float) ($row->cajas ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>|string  $filtros
     * @return array<string, mixed>
     */
    private function normalizarFiltrosListado(array|string $filtros): array
    {
        if (is_string($filtros)) {
            return array_merge(CertificadoSanitarioListadoFiltros::filtrosVacios(), [
                'valor' => $filtros,
                'busqueda' => $filtros,
            ]);
        }

        return $filtros;
    }

    /**
     * @param  Builder<\App\Models\Ventas\CertificadoSanitario>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosListado(Builder $query, array $filtros): void
    {
        $query
            ->leftJoin('camion', 'camion.id', '=', 'certificado_sanitario.camion_id')
            ->leftJoin('transporte', 'transporte.id', '=', 'certificado_sanitario.transporte_id');

        if (CertificadoSanitarioListadoFiltros::tieneCriteriosAplicados($filtros)) {
            CertificadoSanitarioListadoFiltros::aplicar($query, $filtros);
        }
    }

    /**
     * @param  array{
     *   fecha: string,
     *   camion_id: int,
     *   temperatura?: float|null,
     *   nro_remito?: int|null,
     *   precinto?: string|null,
     *   cantidad_precinto?: int|null,
     *   establecimiento_destino?: int|null,
     *   abre_por_localidad?: bool,
     *   transporte_id?: int|null,
     *   zonavta_id?: int|null,
     *   cliente_id?: int|null,
     *   transporte_desde?: int|null,
     *   transporte_hasta?: int|null,
     *   genera_web?: bool
     * }  $input
     * @return list<CertificadoSanitario>
     */
    public function generar(array $input): array
    {
        $fecha = Carbon::parse($input['fecha'])->startOfDay();
        $abre = (bool) ($input['abre_por_localidad'] ?? false);
        $generaWeb = array_key_exists('genera_web', $input) ? (bool) $input['genera_web'] : true;

        $this->codigosenasaRepository->sincronizarConAnita();

        $listado = $this->pedidoSource->listar($input);
        if ($listado->omitidosSinSenasa->isNotEmpty()) {
            $skus = $listado->omitidosSinSenasa
                ->map(static fn ($o) => $o->sku)
                ->unique()
                ->values()
                ->all();
            throw new RuntimeException(
                'No se puede generar el certificado: hay artículos sin código SENASA. '
                .'Cárguelos en el ABM de artículos y vuelva a consultar los pedidos: '
                .implode(', ', $skus)
            );
        }

        $lineas = $listado->lineas;
        if ($lineas->isEmpty()) {
            throw new RuntimeException('No hay pedidos con artículos SENASA para la fecha/filtros indicados.');
        }

        // 3353 (Lazzarano) lleva amparo en el XML; 9066 (jamón crudo) no:
        // Anita deja certa_cert_terc vacío y el elaborador va en codigoProducto.
        $faltanOrigen = CertificadoSanitarioOrigenSupport::skusTerceroConAmparoFaltante($lineas);
        if ($faltanOrigen !== []) {
            throw new RuntimeException(
                'Productos de otro establecimiento sin certificado de origen (amparo). '
                .'Carguelo en Anita/recepción o genere el certificado allí primero: '
                .implode(', ', $faltanOrigen)
            );
        }

        $camion = Camion::query()->findOrFail((int) $input['camion_id']);
        $grupos = $lineas->groupBy(fn (PedidoCertificadoLinea $l) => $l->claveAgrupacion($abre));

        $creados = [];
        DB::transaction(function () use ($grupos, $fecha, $input, $camion, $abre, $generaWeb, &$creados) {
            foreach ($grupos as $clave => $lineasGrupo) {
                /** @var Collection<int, PedidoCertificadoLinea> $lineasGrupo */
                $creados[] = $this->persistirGrupo($lineasGrupo, $fecha, $input, $camion, $abre, $generaWeb);
            }
        });

        return $creados;
    }

    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     * @param  array<string, mixed>  $input
     */
    private function persistirGrupo(
        Collection $lineas,
        Carbon $fecha,
        array $input,
        Camion $camion,
        bool $abre,
        bool $generaWeb,
    ): CertificadoSanitario {
        $patagonico = $lineas->contains(fn (PedidoCertificadoLinea $l) => $this->esPatagonico($l));
        if (CertificadoSanitarioAnitaNumeracionSupport::estaHabilitada()) {
            $num = CertificadoSanitarioAnitaNumeracionSupport::reservar($patagonico);
            $serie = $num['serie'];
            $numero = $num['numero'];
            $nroInterno = $num['nro_interno'];
            $nroPatagonico = $num['nro_patagonico'];
        } else {
            $serie = $this->siguienteSerie();
            $numero = $this->siguienteNumero($serie);
            $nroInterno = $patagonico ? null : $numero;
            $nroPatagonico = $patagonico ? $numero : null;
        }

        $primera = $lineas->first();
        $transporteId = $primera->transporteId;
        if (! $transporteId && $primera->codigoTransporte) {
            $transporteId = Transporte::query()->where('codigo', (string) (int) $primera->codigoTransporte)->value('id');
        }

        $cert = CertificadoSanitario::create([
            'numero' => $numero,
            'serie' => $serie,
            'fecha' => $fecha->toDateString(),
            'camion_id' => $camion->id,
            'precinto' => trim((string) ($input['precinto'] ?? '')),
            'origen' => (string) config('senasa.origen_default'),
            'opcion' => 1,
            'cantidad_bulto' => (int) round($lineas->sum('piezas')),
            'cantidad_caja' => (int) round($lineas->sum('cajas')),
            'cantidad_precinto' => (int) ($input['cantidad_precinto'] ?? $camion->cantidad_precinto ?? 0),
            'procedencia' => (string) config('senasa.procedencia_default'),
            'ptr' => $patagonico ? '0' : '1',
            'certif_sanitario' => ' ',
            'establecimiento_nro' => (string) config('senasa.establecimiento'),
            'transporte_id' => $transporteId,
            'nro_cert_interno' => $nroInterno,
            'nro_cert_patagonico' => $nroPatagonico,
            'establecimiento_destino' => (int) ($input['establecimiento_destino'] ?? 0) ?: null,
            'temperatura' => isset($input['temperatura']) ? (float) $input['temperatura'] : null,
            'nro_remito' => isset($input['nro_remito']) ? (int) $input['nro_remito'] : null,
            'abre_por_localidad' => $abre,
            'genera_web' => $generaWeb,
            'usuario_id' => Auth::id(),
        ]);

        $lineaArt = 1;
        $arts = $lineas->groupBy(fn (PedidoCertificadoLinea $l) => $l->sku);
        foreach ($arts as $sku => $items) {
            $certTercero = $items
                ->map(fn (PedidoCertificadoLinea $l) => trim($l->certificadoOrigen))
                ->first(fn (string $v) => $v !== '');
            CertificadoSanitarioArticulo::create([
                'certificado_sanitario_id' => $cert->id,
                'linea' => $lineaArt++,
                'articulo_id' => $items->first()->articuloId,
                'sku' => (string) $sku,
                'cantidad' => (float) $items->sum('kilos'),
                'cajas' => (float) $items->sum('cajas'),
                'piezas' => (float) $items->sum('piezas'),
                'cert_tercero' => $certTercero ?: null,
                'partida' => null,
            ]);
        }

        $lineaCli = 1;
        foreach ($lineas->unique(fn (PedidoCertificadoLinea $l) => $l->codigoCliente) as $cli) {
            CertificadoSanitarioCliente::create([
                'certificado_sanitario_id' => $cert->id,
                'linea' => $lineaCli++,
                'cliente_id' => $cli->clienteId,
                'codigo_cliente' => $cli->codigoCliente,
            ]);
        }

        $lineaDest = 1;
        foreach ($lineas->unique(fn (PedidoCertificadoLinea $l) => (string) ($l->codigoZona ?? $l->zonavtaId)) as $dest) {
            $anitaDest = CertificadoSanitarioDestinoAnitaSupport::porCodigoZona($dest->codigoZona);
            CertificadoSanitarioDestino::create([
                'certificado_sanitario_id' => $cert->id,
                'linea' => $lineaDest++,
                'zonavta_id' => $dest->zonavtaId,
                'codigo_destino' => $dest->codigoZona,
                'localidad' => $anitaDest['localidad'] ?? $dest->localidadNombre,
                'provincia' => $anitaDest['provincia'] ?? $dest->provinciaNombre,
                'patagonico' => $anitaDest['patagonico'] ?? $this->esPatagonico($dest),
            ]);
        }

        $cert->load(['camion', 'destinos']);

        if ($generaWeb) {
            $this->guardarXmls($cert, $lineas);
        }

        return $cert->fresh(['camion', 'articulos', 'clientes', 'destinos', 'transporte']);
    }

    /**
     * Regenera XML WEB si el certificado se marcó para generar y el archivo no está en disco
     * (o el proceso web no puede leerlo: directorios 0700 creados desde CLI),
     * o si faltan amparos de terceros en líneas SENASA,
     * o si el maestro SENASA cambió (jamones crudos 9066 / frío),
     * o si el destino no coincide con la tabla Anita destino (zona, no cliente).
     */
    public function regenerarXmlsSiFaltan(CertificadoSanitario $cert): CertificadoSanitario
    {
        if (! $cert->genera_web) {
            return $cert;
        }

        $this->codigosenasaRepository->sincronizarConAnita();
        $cert->unsetRelation('articulos');

        $cert->loadMissing([
            'camion',
            'destinos',
            'clientes.cliente.localidades',
            'clientes.cliente.provincias',
            'articulos.articulo.codigosenasas.envasesenasas',
            'articulos.articulo.lineas',
            'articulos.articulo.mventas',
            'transporte',
        ]);

        $necesitaAmparo = $this->completarCertTerceroFaltantes($cert);
        $destinoAlineado = $this->alinearDestinosConAnita($cert);
        $lineas = $this->lineasDesdeCertificado($cert->fresh([
            'camion',
            'destinos',
            'clientes.cliente.localidades',
            'clientes.cliente.provincias',
            'articulos.articulo.codigosenasas.envasesenasas',
            'articulos.articulo.lineas',
            'articulos.articulo.mventas',
            'transporte',
        ]));
        if ($lineas->isEmpty()) {
            return $cert;
        }

        $xmlFalta = ! $this->xmlLegible($cert->xml_frio) && ! $this->xmlLegible($cert->xml_sin_frio);
        $xmlSinTagOrigen = $this->xmlTerceroSinTagOrigen($cert, $lineas);
        $xmlMaestroViejo = $this->xmlDesactualizadoRespectoMaestro($cert, $lineas);
        $xmlCantidadCajas = $this->xmlCantidadNoEsPiezas($cert, $lineas);
        $xmlDestinoViejo = $this->xmlLugarDestinoDesactualizado($cert);
        if (
            ! $necesitaAmparo
            && ! $destinoAlineado
            && ! $xmlFalta
            && ! $xmlSinTagOrigen
            && ! $xmlMaestroViejo
            && ! $xmlCantidadCajas
            && ! $xmlDestinoViejo
        ) {
            return $cert;
        }

        $this->guardarXmls($cert, $lineas);

        return $cert->fresh(['camion', 'transporte', 'articulos']);
    }

    /**
     * Completa cert_tercero vacío en productos de tercero (prefijo ≠ establecimiento propio).
     *
     * @return bool true si actualizó alguna línea
     */
    private function completarCertTerceroFaltantes(CertificadoSanitario $cert): bool
    {
        $actualizo = false;
        foreach ($cert->articulos as $item) {
            if (trim((string) ($item->cert_tercero ?? '')) !== '') {
                continue;
            }
            $prefijo = trim((string) ($item->articulo?->codigosenasas?->prefijo ?? ''));
            if (! CertificadoSanitarioOrigenSupport::esProductoTercero($prefijo)) {
                continue;
            }
            $origen = CertificadoSanitarioOrigenSupport::resolverParaSku((string) $item->sku, $prefijo);
            if ($origen === '') {
                continue;
            }
            $item->cert_tercero = $origen;
            $item->save();
            $actualizo = true;
        }

        return $actualizo;
    }

    /**
     * XML ya generado antes del fix: hay líneas con amparo pero el XML no tiene el tag.
     *
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     */
    private function xmlTerceroSinTagOrigen(CertificadoSanitario $cert, Collection $lineas): bool
    {
        $necesitaTag = $lineas->contains(
            fn (PedidoCertificadoLinea $l) => trim($l->certificadoOrigen) !== ''
        );
        if (! $necesitaTag) {
            return false;
        }

        foreach ([$cert->xml_frio, $cert->xml_sin_frio] as $path) {
            if (! $this->xmlLegible($path)) {
                continue;
            }
            $contenido = (string) Storage::disk('local')->get($path);
            if ($contenido !== '' && ! str_contains($contenido, 'certificadoDeOrigen')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Maestro SENASA (prefijo/registro/frío) distinto al que quedó grabado en el XML.
     *
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     */
    private function xmlDesactualizadoRespectoMaestro(CertificadoSanitario $cert, Collection $lineas): bool
    {
        $hayFrio = $lineas->contains(
            fn (PedidoCertificadoLinea $l) => Codigosenasa::codigoFrio($l->llevafrio) === 'S'
        );
        $haySinFrio = $lineas->contains(
            fn (PedidoCertificadoLinea $l) => Codigosenasa::codigoFrio($l->llevafrio) === 'N'
        );
        if ($hayFrio && ! $this->xmlLegible($cert->xml_frio)) {
            return true;
        }
        if ($haySinFrio && ! $this->xmlLegible($cert->xml_sin_frio)) {
            return true;
        }
        if (! $haySinFrio && $this->xmlLegible($cert->xml_sin_frio)) {
            return true;
        }
        if (! $hayFrio && $this->xmlLegible($cert->xml_frio)) {
            return true;
        }

        $xmls = '';
        foreach ([$cert->xml_frio, $cert->xml_sin_frio] as $path) {
            if ($this->xmlLegible($path)) {
                $xmls .= (string) Storage::disk('local')->get($path);
            }
        }
        if ($xmls === '') {
            return true;
        }

        foreach ($lineas as $l) {
            $codigo = CertificadoSanitarioWebXmlBuilder::codigoProducto($l);
            if ($codigo !== '' && ! str_contains($xmls, '>'.$codigo.'<')) {
                return true;
            }
        }

        return false;
    }

    /**
     * XML viejo: se:cantidad era cajas; ahora son piezas (unidades).
     *
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     */
    private function xmlCantidadNoEsPiezas(CertificadoSanitario $cert, Collection $lineas): bool
    {
        foreach (['S', 'N'] as $frio) {
            $nuevo = $this->xmlBuilder->build($cert, $lineas, $frio, $cert->camion);
            $path = $frio === 'S' ? $cert->xml_frio : $cert->xml_sin_frio;
            if ($nuevo === '') {
                continue;
            }
            if (! $this->xmlLegible($path)) {
                return true;
            }
            $viejo = (string) Storage::disk('local')->get($path);
            if ($this->cantidadesDelXml($nuevo) !== $this->cantidadesDelXml($viejo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<float>
     */
    private function cantidadesDelXml(string $xml): array
    {
        preg_match_all('/<se:cantidad>([^<]+)<\/se:cantidad>/', $xml, $m);

        return array_map(static fn ($v) => (float) $v, $m[1] ?? []);
    }

    private function piezasDesdeArticuloGuardado(CertificadoSanitarioArticulo $item): float
    {
        $guardadas = (float) ($item->piezas ?? 0);
        if ($guardadas > 0) {
            return $guardadas;
        }

        $ux = (float) ($item->articulo?->unidadesxenvase ?? 0);
        $cajas = (float) ($item->cajas ?? 0);
        if ($ux > 0 && $cajas > 0) {
            return $cajas * $ux;
        }

        return $cajas;
    }

    /**
     * @return Collection<int, PedidoCertificadoLinea>
     */
    private function lineasDesdeCertificado(CertificadoSanitario $cert): Collection
    {
        $cli = $cert->clientes->first();
        $cliente = $cli?->cliente;
        $loc = $cliente?->localidades;
        $prov = $cliente?->provincias;
        $dest = $cert->destinos->first();
        $transporte = $cert->transporte;

        $out = collect();
        foreach ($cert->articulos as $item) {
            $art = $item->articulo;
            $cods = $art?->codigosenasas;
            if (! $cods) {
                continue;
            }

            $out->push(new PedidoCertificadoLinea(
                codigoPedido: (string) $cert->numero,
                origen: 'erp',
                codigoCliente: (string) ($cli?->codigo_cliente ?? $cliente?->codigo ?? ''),
                clienteId: $cliente?->id,
                transporteId: $cert->transporte_id,
                codigoTransporte: $transporte?->codigo !== null ? (string) $transporte->codigo : null,
                zonavtaId: $dest?->zonavta_id,
                codigoZona: $dest?->codigo_destino,
                sku: (string) ($item->sku ?? ''),
                articuloNombre: trim((string) ($art->descripcion ?? $art->nombre ?? '')),
                articuloId: $item->articulo_id ? (int) $item->articulo_id : null,
                kilos: (float) $item->cantidad,
                cajas: (float) $item->cajas,
                piezas: $this->piezasDesdeArticuloGuardado($item),
                codigosenasaId: (int) $cods->id,
                llevafrio: Codigosenasa::codigoFrio($cods->llevafrio ?? 'N'),
                registroSenasa: trim((string) ($cods->registro ?? '')),
                prefijoSenasa: trim((string) ($cods->prefijo ?? '')),
                envasesenasaId: $cods->envasesenasa_id ? (int) $cods->envasesenasa_id : null,
                envaseNombre: trim((string) ($cods->envasesenasas->nombre ?? '')),
                marca: trim((string) ($art->mventas->nombre ?? $art->lineas->nombre ?? $art->nombre ?? '')),
                vencimientoEnDias: (int) ($art->vencimientoendia ?? 0),
                pesoAprox: (float) ($art->peso ?? 0),
                localidadSenasaCodigo: $loc && $loc->codigosenasa ? (int) $loc->codigosenasa : null,
                clienteNombre: trim((string) ($cliente->nombre ?? '')),
                clienteDireccion: trim((string) ($cliente->domicilio ?? $cliente->direccion ?? '')),
                clienteCp: trim((string) ($cliente->codigopostal ?? '')),
                clienteTelefono: trim((string) ($cliente->telefono ?? '')),
                localidadNombre: trim((string) ($dest?->localidad ?? $loc?->nombre ?? '')),
                provinciaNombre: trim((string) ($dest?->provincia ?? $prov?->nombre ?? '')),
                certificadoOrigen: trim((string) ($item->cert_tercero ?? '')),
            ));
        }

        return CertificadoSanitarioDestinoAnitaSupport::enriquecerLineas(
            CertificadoSanitarioOrigenSupport::enriquecerLineas($out)
        );
    }

    /**
     * XML con lugarDestino distinto al de destinos (ya alineados a Anita).
     */
    private function xmlLugarDestinoDesactualizado(CertificadoSanitario $cert): bool
    {
        $esperado = $this->xmlBuilder->lugarDestinoDesdeDestinos($cert);
        if ($esperado === '') {
            return false;
        }

        foreach ([$cert->xml_frio, $cert->xml_sin_frio] as $path) {
            if (! $this->xmlLegible($path)) {
                continue;
            }
            $xml = (string) Storage::disk('local')->get($path);
            if ($xml === '') {
                continue;
            }
            if (! preg_match('/<se:lugarDestino>([^<]*)<\/se:lugarDestino>/', $xml, $m)) {
                return true;
            }
            if (trim((string) html_entity_decode($m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8')) !== $esperado) {
                return true;
            }
        }

        return false;
    }

    /**
     * p-certsan.c / certsan.fc: localidad del destino = tabla destino (zona), no el cliente.
     */
    private function alinearDestinosConAnita(CertificadoSanitario $cert): bool
    {
        $cambio = false;
        foreach ($cert->destinos as $destino) {
            if (CertificadoSanitarioDestinoAnitaSupport::aplicarADestino($destino)) {
                $cambio = true;
            }
        }
        if ($cambio) {
            $cert->unsetRelation('destinos');
            $cert->load('destinos');
        }

        return $cambio;
    }

    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     */
    private function guardarXmls(CertificadoSanitario $cert, Collection $lineas): void
    {
        $base = trim((string) config('senasa.xml_storage_path'), '/');
        $dir = $base.'/'.$cert->fecha->format('Y/m');
        $this->asegurarDirectorioXml($dir);

        $viejos = [$cert->xml_frio, $cert->xml_sin_frio];
        $cert->xml_frio = null;
        $cert->xml_sin_frio = null;

        foreach (['S', 'N'] as $frio) {
            $contenido = $this->xmlBuilder->build($cert, $lineas, $frio, $cert->camion);
            if ($contenido === '') {
                continue;
            }
            $nombre = sprintf('certsan%d%s.xml', $cert->numero, $frio);
            $path = $dir.'/'.$nombre;
            Storage::disk('local')->put($path, $contenido);
            @chmod(Storage::disk('local')->path($path), 0664);
            if ($frio === 'S') {
                $cert->xml_frio = $path;
            } else {
                $cert->xml_sin_frio = $path;
            }
        }
        $cert->save();

        foreach ($viejos as $viejo) {
            if (! $viejo || $viejo === $cert->xml_frio || $viejo === $cert->xml_sin_frio) {
                continue;
            }
            if (Storage::disk('local')->exists($viejo)) {
                Storage::disk('local')->delete($viejo);
            }
        }
    }

    public function xmlLegible(?string $path): bool
    {
        return (bool) ($path && Storage::disk('local')->exists($path));
    }

    /**
     * ZIP con el XML adentro: SENASA no acepta el XML suelto.
     */
    public function descargarXmlZip(string $pathRelativo): BinaryFileResponse
    {
        if (! $this->xmlLegible($pathRelativo)) {
            throw new RuntimeException('XML no disponible para este certificado.');
        }

        $xmlNombre = basename($pathRelativo);
        $zipNombre = pathinfo($xmlNombre, PATHINFO_FILENAME).'.zip';
        $tmp = tempnam(sys_get_temp_dir(), 'senasa_xml_');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal para el ZIP.');
        }
        @unlink($tmp);
        $zipPath = $tmp.'.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP para SENASA.');
        }
        $zip->addFromString($xmlNombre, Storage::disk('local')->get($pathRelativo));
        $zip->close();
        @chmod($zipPath, 0664);

        return response()->download($zipPath, $zipNombre, [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    private function asegurarDirectorioXml(string $dirRelativo): void
    {
        Storage::disk('local')->makeDirectory($dirRelativo);
        $abs = Storage::disk('local')->path($dirRelativo);
        $root = rtrim(Storage::disk('local')->path(''), DIRECTORY_SEPARATOR);
        $cursor = $abs;
        while ($cursor !== $root && str_starts_with($cursor, $root.DIRECTORY_SEPARATOR)) {
            @chmod($cursor, 0775);
            $cursor = dirname($cursor);
        }
    }

    private function siguienteSerie(): string
    {
        // Serie 'A' por defecto (Anita usa numerador SER). Extensible luego.
        return 'A';
    }

    private function siguienteNumero(string $serie): int
    {
        $max = (int) CertificadoSanitario::query()->where('serie', $serie)->max('numero');

        return $max + 1;
    }

    private function esPatagonico(PedidoCertificadoLinea $l): bool
    {
        $dest = CertificadoSanitarioDestinoAnitaSupport::porCodigoZona($l->codigoZona);
        if ($dest !== null) {
            return $dest['patagonico'];
        }

        $prov = mb_strtoupper($l->provinciaNombre);
        foreach (['NEUQUEN', 'NEUQUÉN', 'RIO NEGRO', 'RÍO NEGRO', 'CHUBUT', 'SANTA CRUZ', 'TIERRA DEL FUEGO'] as $p) {
            if ($prov !== '' && str_contains($prov, $p)) {
                return true;
            }
        }

        return false;
    }
}
