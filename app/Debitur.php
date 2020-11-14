<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Debitur extends Model
{
    protected $table = "rirj_mdebitur";
    protected $primaryKey = "KDDEBT";
    protected $keyType = "string";
    public $incrementing = false;
    public $timestamps=false;
    protected $fillable = ['kddebt','ndmebt','alamat','kota','koderek','koderek2','tglmks','tglaw',
                        'tglak','tipeadmin','admin','stt_excess','tipe_debitur','kontak','telp','std_obat',
                        'if_excess_valid','resep_jalan_tagih_bapel','auto_dinas','auto_verif_apm',
                        'status_aktif','stt_online','acc_id','partner_id','trading_partner_id','customer_id',
                        'v_claim','cek_dinas'];
}
