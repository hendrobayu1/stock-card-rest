<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Lemari extends Model
{
    protected $table = "if_mlemari";
    const CREATED_AT = "crtdt";
    const UPDATED_AT = "updatedt";
    protected $primaryKey = "idlemari";
    protected $fillable = ["idlemari","kdmut","nmlemari","keterangan","crtusr","aktif"];

    public function kartuBarang(){
        return $this->hasMany('App\KartuBarang','id_lemari','idlemari');
    }
}
