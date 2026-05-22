<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use App\ApiAnita;

class Pais extends Model
{
    protected $fillable = ['nombre', 'codigo', 'codigo_afip'];

    protected $table = 'pais';

    protected $keyField = 'id';

    protected $keyFieldAnita = 'pais_pais';

    /**
     * Código Informix (pais_pais) para clim_pais y FKs Anita.
     */
    public function codigoAnita(): ?string
    {
        $codigo = trim((string) ($this->codigo ?? ''));

        return $codigo !== '' ? $codigo : null;
    }

    public function sincronizarConAnita(): void
    {
        $this->sincronizarCodigosDesdeAnita();
    }

    /**
     * Trae / actualiza países desde Informix (pais_pais, pais_desc, pais_cod_afip).
     */
    public function sincronizarCodigosDesdeAnita(): void
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => $this->table,
            'campos' => 'pais_pais, pais_desc, pais_cod_afip',
            'orderBy' => 'pais_pais',
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita)) {
            return;
        }

        foreach ($dataAnita as $row) {
            $codigoAnita = trim((string) ($row->pais_pais ?? ''));
            $nombre = trim((string) ($row->pais_desc ?? ''));
            $codigoAfip = trim((string) ($row->pais_cod_afip ?? ''));

            if ($codigoAnita === '' || $nombre === '') {
                continue;
            }

            $pais = self::query()
                ->where('codigo', $codigoAnita)
                ->orWhereRaw('TRIM(nombre) = ?', [$nombre])
                ->first();

            if ($pais) {
                $pais->update([
                    'nombre' => $nombre,
                    'codigo' => $codigoAnita,
                    'codigo_afip' => $codigoAfip !== '' ? $codigoAfip : null,
                ]);
            } else {
                self::create([
                    'nombre' => $nombre,
                    'codigo' => $codigoAnita,
                    'codigo_afip' => $codigoAfip !== '' ? $codigoAfip : null,
                ]);
            }
        }
    }

    public function traerRegistroDeAnita($key): void
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => $this->table,
            'campos' => 'pais_pais, pais_desc, pais_cod_afip',
            'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$key."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return;
        }

        $row = $dataAnita[0];
        $codigoAnita = trim((string) ($row->pais_pais ?? $key));
        $nombre = trim((string) ($row->pais_desc ?? ''));
        $codigoAfip = trim((string) ($row->pais_cod_afip ?? ''));

        $pais = self::query()
            ->where('codigo', $codigoAnita)
            ->orWhere('id', $key)
            ->first();

        $payload = [
            'nombre' => $nombre,
            'codigo' => $codigoAnita,
            'codigo_afip' => $codigoAfip !== '' ? $codigoAfip : null,
        ];

        if ($pais) {
            $pais->update($payload);
        } else {
            self::create($payload);
        }
    }

    public function guardarAnita($request, $id): void
    {
        $apiAnita = new ApiAnita();
        $codigoAnita = trim((string) ($request->codigo ?? $id));
        $codigoAfip = trim((string) ($request->codigo_afip ?? ''));

        $data = [
            'tabla' => $this->table,
            'acc' => 'insert',
            'campos' => 'pais_pais, pais_desc, pais_cod_afip',
            'valores' => " '".$codigoAnita."', '".$request->nombre."', '".$codigoAfip."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function actualizarAnita($request, $id): void
    {
        $apiAnita = new ApiAnita();
        $codigoAnita = trim((string) ($request->codigo ?? $id));
        $codigoAfip = trim((string) ($request->codigo_afip ?? ''));

        $data = [
            'acc' => 'update',
            'tabla' => $this->table,
            'valores' => " pais_desc = '".$request->nombre."', pais_cod_afip = '".$codigoAfip."' ",
            'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$codigoAnita."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function eliminarAnita($id): void
    {
        $pais = self::find($id);
        $codigoAnita = $pais?->codigoAnita() ?? $id;

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'delete',
            'tabla' => $this->table,
            'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$codigoAnita."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }
}
