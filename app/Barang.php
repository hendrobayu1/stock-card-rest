<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $table = "if_mbrg_gd";
    protected $primaryKey = "kdbrg";
    const CREATED_AT = "inputdate";
    const UPDATED_AT = "modidate";
    public $fillable = ["kdbrg"];

    public function kartubarang(){
        return $this->hasMany('App\KartuBarang','kdbrg','kdbrg');
    }
}
