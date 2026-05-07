<?php

namespace App\Repositories\Compras;

use App\ApiAnita;
use App\Models\Compras\Listaprecio_Proveedor;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Moneda;
use App\Models\Compras\Condicionpago;
use App\Models\Compras\Condicionentrega;
use App\Models\Compras\Condicioncompra;
use App\Models\Stock\Articulo;
use App\Models\Compras\Listaprecio_Proveedor_Articulo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Auth;
use Exception;

class Listaprecio_ProveedorRepository implements Listaprecio_ProveedorRepositoryInterface
{
    protected $model;
    protected $tableAnita = ['listapmae', 'listapmov'];
    protected $keyField = 'id';
    protected $keyFieldAnita = 'lispm_nro';

    public function __construct(Listaprecio_Proveedor $model)
    {
        $this->model = $model;
    }

    public function create(array $data)
    {
        $lista = $this->model->create($data);

        // Graba anita (cabecera + movimientos) con lógica de negocio
        $anita = $this->guardarAnitaDesdeErp((int) $lista->id);
        if (isset($anita['error'])) {
            throw new Exception('Error en grabacion anita. '.$anita['mensaje']);
        }

        return $lista;
    }

    public function update(array $data, $id)
    {
        $ok = $this->model->findOrFail($id)->update($data);

        // Actualiza anita (cabecera + movimientos) con lógica de negocio
        $anita = $this->actualizarAnitaDesdeErp((int) $id);
        if (isset($anita['error'])) {
            throw new Exception('Error en actualización anita. '.$anita['mensaje']);
        }

        return $ok;
    }

    public function find($id)
    {
        if (null == $row = $this->model->with([
            'proveedores',
            'condicionpagos',
            'condicionentregas',
            'condicioncompras',
            'monedas',
            'usuarios',
        ])->with(['listaprecio_proveedor_articulos' => function ($q) {
            $q->orderBy('fechavigencia', 'desc')->orderBy('id', 'desc');
        }, 'listaprecio_proveedor_articulos.articulos',
            'listaprecio_proveedor_articulos.usuarioultcambio',
            'listaprecio_proveedor_archivos',
            'listaprecio_proveedor_estados.usuarios',
        ])->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $row;
    }

    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            $lp = $this->model->find($id);
            if (! $lp) {
                return false;
            }
            DB::table('listaprecio_proveedor_articulo')->where('listaprecio_proveedor_id', $id)->delete();
            DB::table('listaprecio_proveedor_estado')->where('listaprecio_proveedor_id', $id)->delete();
            DB::table('listaprecio_proveedor_archivo')->where('listaprecio_proveedor_id', $id)->delete();

            return (bool) $lp->delete();
        });
    }

    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        $apiAnita = new ApiAnita();

        $data = [
            'acc' => 'list',
            'sistema' => 'compras',
            'campos' => "$this->keyFieldAnita as $this->keyField",
            'tabla' => $this->tableAnita[0],
            'orderBy' => $this->keyFieldAnita,
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = $this->model->select('id')->get();
        $idsLocal = $datosLocal->pluck('id')->all();

        foreach ($dataAnita as $value) {
            $idLocal = (int) $value->{$this->keyField};

            if (! in_array($idLocal, $idsLocal, true)) {
                $this->traerRegistroDeAnita((int) $value->{$this->keyField}, true);
            } else {
                $this->traerRegistroDeAnita((int) $value->{$this->keyField}, false);
            }
        }
    }

    private function traerRegistroDeAnita(int $nroLista, bool $fl_crea_registro)
    {
        $apiAnita = new ApiAnita();

        $data = [
            'acc' => 'list',
            'tabla' => $this->tableAnita[0],
            'sistema' => 'compras',
            'campos' => '
                lispm_nro,
                lispm_proveedor,
                lispm_fecha,
                lispm_cond_entrega,
                lispm_cond_pago,
                lispm_cond_compra,
                lispm_nombre_prov,
                lispm_estado,
                lispm_nombre_lista,
                lispm_cod_mon,
                lispm_usuario
            ',
            'whereArmado' => " WHERE $this->keyFieldAnita = '".$nroLista."' ",
        ];
        $cab = json_decode($apiAnita->apiCall($data));
        if (! $cab || count($cab) === 0) {
            return;
        }
        $cab = $cab[0];

        // Mapeos (si no existe algo, queda null y no bloquea el alta)
        $proveedorCodigo = ltrim((string) $cab->lispm_proveedor, '0');
        $proveedor = Proveedor::where('codigo', $proveedorCodigo)->first();

        $condPago = Condicionpago::where('codigo', (string) $cab->lispm_cond_pago)->first();
        $condEntrega = Condicionentrega::where('codigo', (string) $cab->lispm_cond_entrega)->first();
        $condCompra = Condicioncompra::where('codigo', (string) $cab->lispm_cond_compra)->first();

        $moneda = null;
        if (isset($cab->lispm_cod_mon)) {
            $moneda = Moneda::where('codigo', (string) $cab->lispm_cod_mon)->first();
            if (! $moneda) {
                $moneda = Moneda::where('id', (int) $cab->lispm_cod_mon)->first();
            }
        }

        $fecha = $this->anitaIntToDate((int) $cab->lispm_fecha);

        $arrCab = [
            'proveedor_id' => $proveedor?->id,
            'fecha' => $fecha,
            'nombre' => rtrim((string) ($cab->lispm_nombre_lista ?? '')),
            'observaciones' => '',
            'condicionpago_id' => $condPago?->id,
            'condicionentrega_id' => $condEntrega?->id,
            'condicioncompra_id' => $condCompra?->id,
            'moneda_id' => $moneda?->id,
            'estado' => $this->mapEstadoAnitaToErp((string) ($cab->lispm_estado ?? '')),
            'creousuario_id' => Auth::user()->id,
        ];

        DB::transaction(function () use ($arrCab, $nroLista, $fl_crea_registro, $apiAnita) {
            if ($fl_crea_registro) {
                $listaprecio_proveedor = $this->model->create($arrCab);
            } else {
                unset($arrCab['id']);
                $listaprecio_proveedor = $this->model->findOrFail((int) $nroLista)->update($arrCab);
                DB::table('listaprecio_proveedor_articulo')->where('listaprecio_proveedor_id', (int) $nroLista)->delete();
            }

            $dataMov = [
                'acc' => 'list',
                'tabla' => $this->tableAnita[1],
                'sistema' => 'compras',
                'campos' => '
                    listpv_nro,
                    listpv_nro_orden,
                    listpv_fecha,
                    listpv_articulo,
                    listpv_precio,
                    listpv_proveedor,
                    lispv_desc,
                    lispv_art_prov,
                    lispv_descuento
                ',
                'whereArmado' => " WHERE listpv_nro = '".$nroLista."' ",
                'orderBy' => 'listpv_nro_orden',
            ];
            $movs = json_decode($apiAnita->apiCall($dataMov));

            foreach ($movs as $m) {
                $sku = ltrim((string) $m->listpv_articulo, '0');
                $art = Articulo::where('sku', $sku)->first();
                if (! $art) {
                    continue;
                }

                $row = [
                    'listaprecio_proveedor_id' => $listaprecio_proveedor->id,
                    'articulo_id' => $art->id,
                    'precio' => (float) ($m->listpv_precio ?? 0),
                    'descuento' => (float) ((string) ($m->lispv_descuento ?? 0)),
                    'articulo_proveedor' => substr(rtrim((string) ($m->lispv_art_prov ?? '')), 0, 100),
                    'fechavigencia' => $this->anitaIntToDate((int) ($m->listpv_fecha ?? 0)),
                    'usuarioultcambio_id' => Auth::user()->id,
                ];
                Listaprecio_Proveedor_Articulo::create($row);
            }
        });
    }

    private function guardarAnitaDesdeErp(int $listaprecio_proveedor_id): array
    {
        $lista = $this->model->with(['proveedores', 'condicionpagos', 'condicionentregas', 'condicioncompras', 'monedas'])
            ->findOrFail($listaprecio_proveedor_id);

        $apiAnita = new ApiAnita();

        $proveedorCodigo = $lista->proveedores?->codigo ?? '';
        $proveedorCodigo = str_pad((string) $proveedorCodigo, 6, '0', STR_PAD_LEFT);

        $fecha = Carbon::parse($lista->fecha)->format('Ymd');

        $condEntrega = $lista->condicionentregas?->codigo ?? 0;
        $condPago = $lista->condicionpagos?->codigo ?? 0;
        $condCompra = $lista->condicioncompras?->codigo ?? 0;

        $monedaCodigo = $lista->monedas?->codigo ?? ($lista->monedas?->abreviatura ?? '0');
        $monedaCodigo = (string) (is_string($monedaCodigo) && $monedaCodigo !== '' ? substr($monedaCodigo, 0, 1) : '0');

        $estado = substr((string) ($lista->estado ?? 'A'), 0, 1);
        $usuario = substr((string) (Auth::user()->nombre ?? Auth::user()->name ?? ''), 0, 15);

        $dataCab = [
            'tabla' => $this->tableAnita[0],
            'acc' => 'insert',
            'sistema' => 'compras',
            'campos' => '
                lispm_nro,
                lispm_proveedor,
                lispm_fecha,
                lispm_cond_entrega,
                lispm_cond_pago,
                lispm_cond_compra,
                lispm_nombre_prov,
                lispm_estado,
                lispm_nombre_lista,
                lispm_cod_mon,
                lispm_usuario
            ',
            'valores' => "
                '".$listaprecio_proveedor_id."',
                '".$proveedorCodigo."',
                '".$fecha."',
                '".$condEntrega."',
                '".$condPago."',
                '".$condCompra."',
                '".substr(preg_replace('([^A-Za-z0-9 ])', '', (string) ($lista->proveedores?->nombre ?? '')), 0, 30)."',
                '".$estado."',
                '".substr(preg_replace('([^A-Za-z0-9 ])', '', (string) ($lista->nombre ?? '')), 0, 30)."',
                '".$monedaCodigo."',
                '".$usuario."'
            ",
        ];
        $resp = $apiAnita->apiCall($dataCab);
        if (strpos((string) $resp, 'Error') !== false) {
            return ['error' => 'Error listapmae', 'mensaje' => $resp];
        }

        return $this->grabarMovimientosAnitaDesdeErp($listaprecio_proveedor_id);
    }

    private function actualizarAnitaDesdeErp(int $listaprecio_proveedor_id): array
    {
        $apiAnita = new ApiAnita();

        // Si no existe en anita, lo crea (misma lógica que otros módulos)
        $dataChk = [
            'acc' => 'list',
            'tabla' => $this->tableAnita[0],
            'sistema' => 'compras',
            'campos' => 'lispm_nro',
            'whereArmado' => " WHERE lispm_nro = '".$listaprecio_proveedor_id."' ",
        ];
        $existe = json_decode($apiAnita->apiCall($dataChk));
        if (! $existe || count($existe) === 0) {
            return $this->guardarAnitaDesdeErp($listaprecio_proveedor_id);
        }

        $lista = $this->model->with(['proveedores', 'condicionpagos', 'condicionentregas', 'condicioncompras', 'monedas'])
            ->findOrFail($listaprecio_proveedor_id);

        $proveedorCodigo = $lista->proveedores?->codigo ?? '';
        $proveedorCodigo = str_pad((string) $proveedorCodigo, 6, '0', STR_PAD_LEFT);

        $fecha = Carbon::parse($lista->fecha)->format('Ymd');

        $condEntrega = $lista->condicionentregas?->codigo ?? 0;
        $condPago = $lista->condicionpagos?->codigo ?? 0;
        $condCompra = $lista->condicioncompras?->codigo ?? 0;

        $monedaCodigo = $lista->monedas?->codigo ?? ($lista->monedas?->abreviatura ?? '0');
        $monedaCodigo = (string) (is_string($monedaCodigo) && $monedaCodigo !== '' ? substr($monedaCodigo, 0, 1) : '0');

        $estado = substr((string) ($lista->estado ?? 'A'), 0, 1);
        $usuario = substr((string) (Auth::user()->nombre ?? Auth::user()->name ?? ''), 0, 15);

        $dataCab = [
            'acc' => 'update',
            'tabla' => $this->tableAnita[0],
            'sistema' => 'compras',
            'valores' => "
                lispm_proveedor = '".$proveedorCodigo."',
                lispm_fecha = '".$fecha."',
                lispm_cond_entrega = '".$condEntrega."',
                lispm_cond_pago = '".$condPago."',
                lispm_cond_compra = '".$condCompra."',
                lispm_nombre_prov = '".substr(preg_replace('([^A-Za-z0-9 ])', '', (string) ($lista->proveedores?->nombre ?? '')), 0, 30)."',
                lispm_estado = '".$estado."',
                lispm_nombre_lista = '".substr(preg_replace('([^A-Za-z0-9 ])', '', (string) ($lista->nombre ?? '')), 0, 30)."',
                lispm_cod_mon = '".$monedaCodigo."',
                lispm_usuario = '".$usuario."'
            ",
            'whereArmado' => " WHERE lispm_nro = '".$listaprecio_proveedor_id."' ",
        ];
        $resp = $apiAnita->apiCall($dataCab);
        if (strpos((string) $resp, 'Error') !== false) {
            return ['error' => 'Error listapmae', 'mensaje' => $resp];
        }

        // Borra movimientos y regraba
        $dataDel = [
            'acc' => 'delete',
            'tabla' => $this->tableAnita[1],
            'sistema' => 'compras',
            'whereArmado' => " WHERE listpv_nro = '".$listaprecio_proveedor_id."' ",
        ];
        $respDel = $apiAnita->apiCall($dataDel);
        if (strpos((string) $respDel, 'Error') !== false) {
            return ['error' => 'Error listapmov delete', 'mensaje' => $respDel];
        }

        return $this->grabarMovimientosAnitaDesdeErp($listaprecio_proveedor_id);
    }

    private function grabarMovimientosAnitaDesdeErp(int $listaprecio_proveedor_id): array
    {
        $lista = $this->model->with(['proveedores'])->findOrFail($listaprecio_proveedor_id);
        $apiAnita = new ApiAnita();

        $proveedorCodigo = $lista->proveedores?->codigo ?? '';
        $proveedorCodigo = str_pad((string) $proveedorCodigo, 6, '0', STR_PAD_LEFT);

        $lineas = Listaprecio_Proveedor_Articulo::where('listaprecio_proveedor_id', $listaprecio_proveedor_id)
            ->with(['articulos'])
            ->orderBy('fechavigencia', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        $orden = 0;
        foreach ($lineas as $ln) {
            $fecha = Carbon::parse($ln->fechavigencia)->format('Ymd');
            $sku = $ln->articulos?->sku ?? '';
            $sku = str_pad((string) $sku, 13, '0', STR_PAD_LEFT);

            $desc = $ln->articulos?->descripcion ?? '';

            $dataMov = [
                'tabla' => $this->tableAnita[1],
                'acc' => 'insert',
                'sistema' => 'compras',
                'campos' => '
                    listpv_nro,
                    listpv_nro_orden,
                    listpv_fecha,
                    listpv_articulo,
                    listpv_precio,
                    listpv_proveedor,
                    lispv_desc,
                    lispv_art_prov,
                    lispv_descuento
                ',
                'valores' => "
                    '".$listaprecio_proveedor_id."',
                    '".$orden."',
                    '".$fecha."',
                    '".$sku."',
                    '".((float) $ln->precio)."',
                    '".$proveedorCodigo."',
                    '".substr(preg_replace('([^A-Za-z0-9 ])', '', (string) $desc), 0, 30)."',
                    '".substr((string) ($ln->articulo_proveedor ?? ''), 0, 30)."',
                    '".((float) ($ln->descuento ?? 0))."'
                ",
            ];

            $resp = $apiAnita->apiCall($dataMov);
            if (strpos((string) $resp, 'Error') !== false) {
                return ['error' => 'Error listapmov', 'mensaje' => $resp];
            }
            $orden++;
        }

        return ['success' => true];
    }

    private function anitaIntToDate(int $yyyymmdd): string
    {
        if ($yyyymmdd < 19000101) {
            return date('Y-m-d');
        }
        $s = (string) $yyyymmdd;
        if (strlen($s) !== 8) {
            return date('Y-m-d');
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    private function mapEstadoAnitaToErp(string $estado): string
    {
        $e = strtoupper(substr(trim($estado), 0, 1));
        if ($e === 'I') {
            return 'Inactiva';
        }
        if ($e === 'A') {
            return 'Activa';
        }

        return 'Activa';
    }
}
