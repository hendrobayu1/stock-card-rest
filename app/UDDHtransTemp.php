<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UDDHtransTemp extends Model
{
    protected $table = 'if_udd_htrans_temp';
    protected $primaryKey = "ID_TRANS";
    protected $keyType = "string";
    public $incrementing = false;
    public $timestamps=false;
    protected $fillable = ['id_trans','noreg','tgl','tglshift','jam','jaga','antrian','kdkamar',
                        'kddok','tipeif','active','inputby','inputdate','ip_komp',
                        'idunit'];
}
