<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class KartuBarang extends Model
{
    protected $table = "if_kartu_stok_barang";
    protected $primaryKey = "ID_KARTU_STOK";
    public $incrementing = true;
    public $timestamps=false;
    protected $guards=[];

    public function detiltransaksi(){
        return $this->hasMany('App\KartuBarangDetilTransaksi','id_kartu_stok','id_kartu_stok');
    }

    public function barang(){
        return $this->belongsTo('App\Barang');
    }
    
    public function lemari(){
        return $this->belongsTo('App\Lemari',);
    }
}