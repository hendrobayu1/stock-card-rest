<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UDDTransTemp extends Model
{
    protected $table = 'if_udd_trans_temp';
    // protected $primaryKey = "ID_TRANS";
    // protected $keyType = "string";
    public $incrementing = false;
    public $timestamps=false;
    protected $fillable = ['id_trans','no','id','kdbrg','harga','hbiji','hjual',
                        'disc','tipe_qty','jumlah','jml_if','jml_if2','signa','signa2',
                        'hari','jasa','ketqty','stok_if_ak','jumlah_seper','active',
                        'inputby','inputdate'];
}
