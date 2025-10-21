<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Cliente_Premio_Archivo_Uif extends Model
{
    protected $fillable = ['cliente_premio_uif_id', 'nombrearchivo'];
    protected $table = 'cliente_premio_archivo_uif';

	public function cliente_premio_uifs()
	{
    	return $this->belongsTo(Cliente_Premio_Uif::class, 'cliente_premio_uif_id', 'id');
	}

    public static function setFoto($foto, $actual = false)
    {
        if ($foto) {
            if ($actual) {
                Storage::disk('public')->delete("imagenes/fotos_uif/$actual");
            }
            $imageName = Str::random(20) . '.jpg';
            $imagen = Image::make($foto)->encode('jpg', 75);
            $imagen->resize(300, 300, function ($constraint) {
                $constraint->upsize();
            });
            Storage::disk('public')->put("imagenes/fotos_uif/$imageName", $imagen->stream());
            return $imageName;
        } else {
            return false;
        }
    }

}
