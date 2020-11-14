<?php

namespace App\Http\Controllers\UDDTransaction;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\UDDHtransTemp as TempHeader;
use App\UDDTransTemp as TempDetil;
use App\UDDTransDetilTemp as TempDetilPemberian;
// use App\UDDTransDetilTemp;
// use App\UDDTransTemp;
use DateTime;

class TransaksiUDDController extends Controller
{
    public function getShift(){
        $shift = 0;
        $jam = Carbon::now()->format('H:i:s');
        $jam_shift_3 = DB::table('if_shift')
                        ->where('shift',3)
                        ->first();
        if($jam>=$jam_shift_3->sAwal || $jam<=$jam_shift_3->sAkhir){
            $shift=3;
        }else{
            $shift = DB::table('if_shift')
                        ->where('sAwal','<=',$jam)
                        ->where('sAkhir','>=',$jam)
                        ->value('shift');
        }

        $tgl_shift = Carbon::now()->format('Y-m-d');
        $tgl_cetak = $tgl_shift;
        if($shift==3 && $jam<='07:00:00'){
            $tgl_shift = Carbon::parse($tgl_shift)->subDay();
        }

        return json_encode([
            'tgl_cetak' => $tgl_cetak,
            'tgl_shift' => $tgl_shift,
            'jam' => $jam,
            'shift' => $shift,
        ]);
    }

    public function getJasaResep($jumlah,$debitur,$jenis_resep,$kdbrg,$status_naik_kelas_bpjs,$tipeif){
        $debitur_baru = $debitur;
        if($status_naik_kelas_bpjs==1){
            $debitur_baru='999';
        }
        $jasa_r = 0;
        $user_oplos_kemo = DB::table('if_mbrg')
                            ->where('tipeif',$tipeif)
                            ->where('kdbrg',$kdbrg)
                            ->where('active',1)
                            ->value(DB::raw("isnull(user_oplos_kemo,'')"));
        if($user_oplos_kemo==''){
            if($debitur_baru!='926' && $debitur_baru!='310' && $debitur_baru!='804'){
                $data_jasa_resep =  DB::table('if_margindeb as d')
                                    ->join('if_marginhead as head','head.idmargin','=','d.idmargin')
                                    ->join('if_mjasa_resep as mbrg',function ($join) use($jenis_resep){
                                        $join->on('mbrg.id_margin','=','head.idmargin')
                                            ->on('mbrg.jenis_obat',$jenis_resep);
                                    })
                                    ->where('d.kddeb',$debitur_baru)
                                    ->selectRaw("(mbrg.nominal_jasa * (case when mbrg.kali_qty=1 then $jumlah else 1 end)) + 
                                    case when '$debitur_baru' in ('804','910') and $jenis_resep=2 then (75*$jumlah) else 0 end as jasa_resep")
                                    ->first();
                $jasa_r = $data_jasa_resep->jasa_resep;
            }
        }
        return $jasa_r;
    }

    public function getHargaJual($jumlah,$debitur,$kdbrg,$status_naik_kelas_bpjs,$tipeif,$isjalan){
        $debitur_baru = $debitur;
        if($status_naik_kelas_bpjs==1){
            $debitur_baru='999';
        }
        $hjual = 0;
        $jumlah_data = DB::table('if_mbrg_gd')
                        ->where('kdbrg',$kdbrg)
                        ->where('active',1)
                        ->count();
        if($jumlah_data>0){
            $status_harga = DB::table('if_mbrg')
                            ->where('tipeif',$tipeif)
                            ->where('kdbrg',$kdbrg)
                            ->where('active',1)
                            ->value(DB::raw("isnull(statharga,'')"));
            if($status_harga!='F'){
                if($debitur_baru=='103'||$debitur_baru=='498'){
                    $hjual = DB::table('if_mbrg_gd_guper')
                            ->where('tipe',1)
                            ->where('kdbrg',$kdbrg)
                            ->value(DB::raw("isnull(hrata,0)"));
                }else{
                    $hjual = DB::table('if_mbrg_gd_guper')
                            ->where('tipe',1)
                            ->where('kdbrg',$kdbrg)
                            ->value(DB::raw("case when '$debitur_baru' in ('910','804','926','310') then hrata else convert(int,round(isnull(hna,0),0)) end"));

                    $idmargin = DB::table('if_margindeb')
                                ->where('kddeb',$debitur_baru)
                                ->value('idmargin');

                    $jenis_barang = DB::table('if_mbrg_gd as a')
                                    ->join('if_mjenis as b','a.jenis','=','b.kdjenis')
                                    ->where('a.kdbrg',$kdbrg)
                                    ->where('a.active',1)
                                    ->first('b.grup','a.jenis');

                    if($idmargin!=0){
                        if($debitur_baru=='910'){
                            // inhealth pelindo
                            $jumlah_data = DB::table('if_std_obat as s')
                                            ->join('rirj_mdebitur as d','d.std_obat','=','s.kode')
                                            ->join('if_std_detobat as sd','sd.kode','=','s.kode')
                                            ->where('sd.kdbrg',$kdbrg)
                                            ->where('d.kddebt','804')
                                            ->count();
                            if($jumlah_data>0){
                                $idmargin = DB::table('if_margindeb')
                                            ->where('kddeb','804')
                                            ->value('idmargin');
                            }
                        }

                        if($debitur_baru!='310'){
                            // bukan kimia farma
                            $het = DB::table('if_mbrg_gd_guper')
                                    ->where('tipe',1)
                                    ->where('kdbrg',$kdbrg)
                                    ->value(DB::raw("isnull(het,0)"));
                            if($debitur_baru=='926'){
                                // jika bpjs
                                if($jenis_barang->jenis=='D5' || $jenis_barang->jenis=='F8' || 
                                    $kdbrg=='*D0280' || $kdbrg=='*D0281' || $kdbrg=='*M0296' || $kdbrg=='*E0197'){
                                        // bpjs kemo & dianel
                                        $hjual = $hjual*1.1;
                                }else{
                                    $hjual = DB::table('if_marginbrg')
                                            ->where('idmargin',$idmargin)
                                            ->where('jenis',$jenis_barang->grup)
                                            ->where('hppaw','<=',$hjual)
                                            ->where('hppak','>=',$hjual)
                                            ->value(DB::raw("$hjual*((case when $isjalan=1 then mg_jalan else mg_inap end)+1)*1.1"));
                                }
                            }else{
                                if($debitur_baru=='949' && $jenis_barang->jenis=='A3'){
                                    // harga jual bpjs tk jenis alkes rehab medis
                                    $hjual = DB::table('if_mbrg_gd_guper')
                                            ->where('tipe',1)
                                            ->where('kdbrg',$kdbrg)
                                            ->value(DB::raw("isnull(hrata,0)"));
                                    $hjual = $hjual*1.12*1.1;
                                }else{
                                    // non bpjs
                                    $hjual = DB::table('if_marginbrg')
                                            ->where('idmargin',$idmargin)
                                            ->where('jenis',$jenis_barang->grup)
                                            ->where('hppaw','<=',$hjual)
                                            ->where('hppak','>=',$hjual)
                                            ->value(DB::raw("$hjual*((case when $isjalan=1 then mg_jalan else mg_inap end)+1)*1.1"));
                                }
                            }
                            if($hjual > $het && $het != 0){
                                $hjual = $het;
                            }
                        }else{
                            // kimia farma
                            if($jenis_barang->grup=='K'){
                                $hjual = $hjual*1.1;
                            }else{
                                $hjual = $hjual*1.1*1.1;
                            }
                        }
                        $hjual = floor($hjual);
                    }
                }
            }else{
                $hjual = DB::table('if_mbrg')
                        ->where('tipeif',$tipeif)
                        ->where('kdbrg',$kdbrg)
                        ->where('active',1)
                        ->value('hrata');
            }
        }
        
        return $hjual;
    }

    public function getIDUnitLayanan($depo){
        return DB::table('if_mlayanan')
                ->where('active',1)
                ->where('kode_mutasi',$depo)
                ->value('unit_id');
    }

    public function previewTransaksiBaru(Request $request){
        $code = 401;
        $message = '';
        $status = 'error';
        $data = [];

        $depo = $request->depo;
        $register = $request->register;
        $kamar = $request->kamar;
        $dokter = $request->dokter;
        $ip_komp = $request->ip_komp;
        $device_info = $request->device_info;
        $detil = json_decode($request->detil,true);

        $data_shift = json_decode($this->getShift());
        
        $tgl_shift = $data_shift->tgl_shift;
        $tgl_cetak = $data_shift->tgl_cetak;
        $jam = $data_shift->jam;
        $shift = $data_shift->shift;
        $idunit = '';
        $tipeif = 0;
        $debitur = '';
        
        if(count($detil)>0 && $register!='' && $kamar!='' && $kamar!='' && $depo!=''){
            $jml_data = DB::table('ri_masterpx as a')
                        ->whereRaw("isnull(a.sts_batal,0)=0")
                        ->whereRaw("a.tgl_plng is null")
                        ->where('a.noreg',$register)
                        ->where('a.kmr_skrg',$kamar)
                        ->count();
            if($jml_data==0){
                $message = 'data pasien tidak sesuai';
            }else{
                $idunit = $this->getIDUnitLayanan($depo);
                $tipeif = DB::table('if_mlayanan')
                            ->where('kode_mutasi',$depo)
                            ->where('unit_id',$idunit)
                            ->where('active',1)
                            ->value('idlayanan');
                $debitur = DB::table('ri_masterpx')
                            ->where('noreg',$register)
                            ->value('kdebi');

                foreach($detil as $detil_barang){
                    if($detil_barang->kdbrg == '' || $detil_barang->jumlah == '' || $detil_barang->jumlah == '0'){
                        $message = 'inputan barang dengan kode '.$detil_barang->kdbrg.' tidak sesuai';
                        break;
                    }else{
                        if($detil->kdsigna==''){
                            // racikan
                            $racikan_valid=0;
                            $detil_collect = collect($detil);
                            $detil_racikan = $detil_collect->where('nomor','>',$detil_barang->nomor)->sortBy('nomor')->values()->all();
                            foreach($detil_racikan as $detil_barang_racikan){
                                $jml_data = DB::table('if_msigna')
                                            ->where('kdsigna',$detil_barang_racikan->kdbrg)
                                            ->count();
                                if($jml_data>0 && $detil_barang_racikan->kdsigna != ''){
                                    $racikan_valid=1;
                                    break;
                                }
                            }
                            if($racikan_valid==0){
                                $message = 'bentuk akhir racikan atas barang '.$detil_barang->kdbrg.' belum ditentukan';
                                break;
                            }
                        }else{
                            // non racikan atau mf
                            $jml_data = DB::table('if_msigna')
                                            ->where('kdsigna',$detil_barang->kdbrg)
                                            ->count();
                            if($jml_data>0){
                                // mf
                                $detil_collect = collect($detil);
                                $jml_data = $detil_collect->where('nomor','<',$detil_barang->nomor)
                                                ->where('kdsigna','')
                                                ->count();
                                if($jml_data==0){
                                    $message = 'bahan racikan atas kode '.$detil_barang->kdbrg.' tidak ada';
                                    break;
                                }
                            }else{
                                // non racikan
                                $jml_data = DB::table('if_mbrg as a')
                                            ->join('if_mbrg_gd as b','a.kdbrg','=','b.kdbrg')
                                            ->join('if_mlayanan as c','a.tipeif','=','c.idlayanan')
                                            ->where('c.kode_mutasi',$depo)
                                            ->where('b.kdbrg',$detil_barang->kdbrg)
                                            ->where('b.active',1)
                                            ->where('a.active',1)
                                            ->count();
                                if($jml_data==0){
                                    $message = 'inputan barang atas kode '.$detil_barang->kdbrg.' tidak valid';
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if($message==''){
                DB::beginTransaction();
                try{
                    DB::delete('delete from if_udd_trans_detil_temp where id_trans in 
                    (select id_trans from if_udd_htrans_temp where noreg=:noreg and idunit=:idunit)', ['noreg'=>$register,'idunit'=>$idunit]);

                    DB::delete('delete from if_udd_trans_temp where id_trans in 
                    (select id_trans from if_udd_htrans_temp where noreg=:noreg and idunit=:idunit)', ['noreg'=>$register,'idunit'=>$idunit]);

                    DB::delete('delete from if_udd_htrans_temp where noreg=:noreg and idunit=:idunit', ['noreg'=>$register,'idunit'=>$idunit]);

                    $prefix_transaksi = DB::table('if_mglobal')
                                        ->where('tipeglobal','KodeTrans')
                                        ->where('valstr2',$idunit)
                                        ->value('valstr');

                    $kode_transaksi = DB::table('if_udd_htrans_temp')
                                        ->whereRaw("convert(date,tgl)=convert(date,getdate())")
                                        ->where('idunit',$idunit)
                                        ->where('id_trans','like',$prefix_transaksi.'%')
                                        ->orderByDesc('id_trans')
                                        ->value(DB::raw("concat($prefix_transaksi, 
                                        convert(varchar,year(getdate())), 
                                        right('0'+convert(varchar,month(getdate())),2), 
                                        right('0'+convert(varchar,day(getdate())),2),
                                        '/',
                                        right('000000' + convert(varchar,convert(int,isnull(max(substring(id_trans,11,6)),0))+1),6)
                                        )"));

                    $nomor_antrian = DB::table('if_udd_htrans_temp')
                                        ->where('idunit',$idunit)
                                        ->where('noreg',$register)
                                        ->value(DB::raw("isnull(max(antrian),0)+1"));
                    $temp_header = new TempHeader();
                    $temp_header->id_trans = $kode_transaksi;
                    $temp_header->noreg = $register;
                    $temp_header->tgl = $tgl_cetak;
                    $temp_header->tglshift = $tgl_shift;
                    $temp_header->jam = $jam;
                    $temp_header->jaga = $shift;
                    $temp_header->antrian = $nomor_antrian;
                    $temp_header->kdkamar = $kamar;
                    $temp_header->kddok = $dokter;
                    $temp_header->tipeif = $tipeif;
                    $temp_header->active = 1;
                    $temp_header->inputby = '';
                    $temp_header->inputdate = new DateTime();
                    $temp_header->ip_komp = $ip_komp;
                    $temp_header->idunit = $idunit;
                    if($temp_header->save()){
                        $id = 0;
                        foreach($detil as $detil_barang){

                            $bentuk_obat=0;
                            $jml_if=0;
                            $jml_if2=0;
                            $stok_if_ak=0;
                            $jasa=0;
                            $hari=0;
                            $hari=1;
                            $jasa = 0;
                            $kdsigna = '';
                            
                            if ($detil_barang->kdsigna=''){
                                // bahan racikan
                                $id +=1;
                            }else{
                                $jml_data = DB::table('if_msigna')
                                            ->where('kdsigna',$detil_barang->kdbrg)
                                            ->count();
                                if($jml_data>0){
                                    // mf
                                    $id +=1;
                                    $bentuk_obat = 2;
                                }else{
                                    // non racikan
                                    $id = 0;
                                    $bentuk_obat = 0;
                                }
                            }

                            // hitung qty
                            if($detil_barang->tipe_qty=='1'){
                                $jml_if = ceil((float)$detil_barang->jumlah);
                            }else if($detil_barang->tipe_qty=='2'){
                                $jml_if = ceil((float)$detil_barang->jumlah/100);
                            }else if($detil_barang->tipe_qty=='7'){
                                if($detil_barang->kdsigna==''){
                                    $data_master_sediaan = DB::table('if_mbrg_gd')
                                                            ->where('kdbrg',$detil_barang->kdbrg)
                                                            ->value(DB::raw("isnull(dosis,0)"));
                                    if($data_master_sediaan==0){
                                        $jml_if = 0;
                                    }else{
                                        $jml_if = ceil((float)$detil_barang->jumlah/$data_master_sediaan/1000);
                                    }
                                }else{
                                    $jml_if = ceil((float)$detil_barang->jumlah);
                                }
                            }else{
                                if($detil_barang->kdsigna==''){
                                    $data_master_sediaan = DB::table('if_mbrg_gd')
                                                            ->where('kdbrg',$detil_barang->kdbrg)
                                                            ->value(DB::raw("isnull(dosis,0)"));
                                    if($data_master_sediaan==0){
                                        $jml_if = 0;
                                    }else{
                                        $jml_if = ceil((float)$detil_barang->jumlah/$data_master_sediaan);
                                    }
                                }else{
                                    $jml_if = ceil((float)$detil_barang->jumlah);
                                }
                            }

                            // cek stok akhir bulan
                            $jml_data = DB::table('if_msigna')
                                        ->where('kdsigna',$detil_barang->kdbrg)
                                        ->count();

                            if($jml_data>0 && $detil_barang->kdsigna != ''){
                                // mf
                                $stok_if_ak = 9999;
                                $jml_if2 = $jml_if;
                            }else{
                                // cari stok akhir barang
                                $stok_if_ak = DB::table('if_mbrg')
                                            ->where('active',1)
                                            ->where('tipeif',$tipeif)
                                            ->where('kdbrg',$detil_barang->kdbrg)
                                            ->value('brgak');
                                if($stok_if_ak>=$jml_if){
                                    $jml_if2 = $jml_if;
                                }else{
                                    $jml_if2 = $stok_if_ak < 0 ? 0 : $stok_if_ak;
                                }
                            }

                            $status_naik_kelas=0;
                            if($debitur=='926'){
                                $status_naik_kelas = DB::table('ri_masterpx_bpjs')
                                                    ->where('noreg',$register)
                                                    ->value(DB::raw("isnull(statusnaikkelas,0)"));
                                if($status_naik_kelas>1){
                                    $status_naik_kelas=1;
                                }
                            }
                            
                            if($detil_barang->kdsigna!=''){
                                $jasa = $this->getJasaResep($jml_if,$debitur,$bentuk_obat,$detil_barang->kdbrg,$status_naik_kelas,$tipeif);
                            }

                            $temp_detil = new TempDetil();
                            $temp_detil->id_trans = $kode_transaksi;
                            $temp_detil->no = $detil_barang->no;
                            $temp_detil->id = $detil_barang->id;
                            $temp_detil->kdbrg = $detil_barang->kdbrg;
                            if(DB::table('if_mbrg_gd_guper')->where('tipe',1)->where('kdbrg',$detil_barang->kdbrg)->count()>0){
                                $temp_detil->harga = DB::table('if_mbrg_gd_guper')
                                                    ->where('tipe',1)
                                                    ->where('kdbrg',$detil_barang->kdbrg)
                                                    ->value('hrata');
                                $temp_detil->hbiji = DB::table('if_mbrg_gd_guper')
                                                    ->where('tipe',1)
                                                    ->where('kdbrg',$detil_barang->kdbrg)
                                                    ->value('hbiji');
                            }else{
                                $temp_detil->harga = 0;
                                $temp_detil->hbiji = 0;
                            }

                            if($detil_barang->kdsigna!=''){
                                if(DB::table('if_msigna')
                                ->where('kdsigna',$detil_barang->kdsigna)->count()>0){
                                    $jmlperhari = DB::table('if_msigna')
                                        ->where('kdsigna',$detil_barang->kdsigna)
                                        ->value('perhari');
                                    if($jmlperhari!=0){
                                        $hari = ceil((float)$jml_if/(float)$jmlperhari);
                                    }
                                    $kdsigna = $detil_barang->kdsigna;
                                }else{
                                    $kdsigna = '999999';
                                }
                            }

                            $temp_detil->hjual = $jasa;
                            $temp_detil->hjual = $this->getHargaJual($jml_if,$debitur,$detil_barang->kdbrg,$status_naik_kelas,$tipeif,0);
                            $temp_detil->disc = 0;
                            $temp_detil->tipe_qty = $detil_barang->tipe_qty;
                            $temp_detil->jumlah = $detil_barang->jumlah;
                            $temp_detil->jml_if = $jml_if;
                            $temp_detil->jml_if2 = $jml_if2;
                            $temp_detil->stok_if_ak = $stok_if_ak;
                            $temp_detil->signa = $kdsigna;
                            $temp_detil->signa2 = $detil_barang->signa2;
                            $temp_detil->hari = $hari;
                            $temp_detil->ketqty = $detil_barang->keterangan;
                            $temp_detil->jumlah_seper = $detil_barang->jumlah_seper;
                            $temp_detil->active = 1;
                            $temp_detil->inputby = '';
                            $temp_detil->inputdate = new DateTime();
                            $temp_detil->save();
                            
                            $jenis_barang_udd = '';
                            $frek1d = 0;
                            $frek1d_temp = 0;
                            $jumlah_pemberian = 0;
                            if($id==0){
                                //non racikan
                                $jenis_barang_udd = DB::table('if_mbrg_gd')
                                                    ->where('kdbrg',$detil_barang->kdbrg)
                                                    ->where('active',1)
                                                    ->value(DB::raw("isnull(jenis_obat_udd,'')"));
                                if($jenis_barang_udd==''){
                                    throw new \Exception('jenis barang atas kode '.$detil_barang->kdbrg.' belum ditentukan');
                                }

                                if(DB::table('if_msigna')
                                    ->where('kdsigna',$detil_barang->kdsigna)
                                    ->count()>0){
                                        $frek1d = DB::table('if_udd_jam_pemberian_signa')
                                                    ->where('kdsigna',$detil_barang->kdsigna)
                                                    ->where('jenis_obat_udd',$jenis_barang_udd)
                                                    ->max('idx_pemberian');
                                }

                                if($frek1d!=0){
                                    $frek1d_temp = $frek1d;
                                    $frek1d = DB::table('if_udd_jam_pemberian_signa')
                                                ->where('active',1)
                                                ->where('kdsigna',$detil_barang->kdsigna)
                                                ->where('jenis_obat_udd',$jenis_barang_udd)
                                                ->where(function ($query) {
                                                    $query->where('jam_pemberian',date('H:i:s'))
                                                        ->orWhere('hari_sama',0);
                                                })
                                                ->count();
                                    if($frek1d==$frek1d_temp){
                                        if((int)$jml_if % $frek1d != 0){
                                            throw new \Exception('jumlah obat atas kode barang '.$detil_barang->kdbrg.' tidak sesuai dengan jam pemberian signa');
                                        }
                                    }
                                    if($frek1d==0){
                                        $jumlah_pemberian=1;
                                    }else{
                                        $jumlah_pemberian = (float)$jml_if/(float)$frek1d;
                                    }
                                    $data_rincian_pemberian = DB::table('if_udd_jam_pemberian_signa')
                                                            ->where('active',1)
                                                            ->where('kdsigna',$detil_barang->kdsigna)
                                                            ->where('jenis_obat_udd',$jenis_barang_udd)
                                                            ->where(function ($query){
                                                                $query->where('jam_pemberian',date('H:i:s'))
                                                                    ->orWhere('hari_sama',0);
                                                            })
                                                            ->select('idx_pemberian','jam_pemberian','hari_sama')
                                                            ->orderBy('idx_pemberian')
                                                            ->get();
                                    foreach($data_rincian_pemberian as $data_rincian){
                                        $detil_pemberian = new TempDetilPemberian();
                                        $detil_pemberian->id_trans = $kode_transaksi;
                                        $detil_pemberian->idx_pemberian = $data_rincian->idx_pemberian;
                                        $detil_pemberian->jadwal_jam_pemberian = $data_rincian->jam_pemberian;
                                        $detil_pemberian->no = $detil_barang->no;
                                        $detil_pemberian->id = $detil_barang->id;
                                        $detil_pemberian->kdbrg = $detil_barang->kdbrg;
                                        $detil_pemberian->jumlah = round($jumlah_pemberian,2);
                                        $detil_pemberian->active = 1;
                                        $detil_pemberian->inputby = '';
                                        $detil_pemberian->inputdate = new DateTime();
                                        if($data_rincian->hari_sama==0){
                                            $detil_pemberian->jadwal_tgl_pemberian = Carbon::now()->addDay()->format('Y-m-d');
                                        }else{
                                            $detil_pemberian->jadwal_tgl_pemberian = Carbon::now()->format('Y-m-d');
                                        }
                                        $detil_pemberian->save();
                                    }
                                }else{
                                    $detil_pemberian = new TempDetilPemberian();
                                    $detil_pemberian->id_trans = $kode_transaksi;
                                    $detil_pemberian->jadwal_tgl_pemberian = Carbon::now()->format('Y-m-d');
                                    $detil_pemberian->idx_pemberian = 1;
                                    $detil_pemberian->jadwal_jam_pemberian = $jenis_barang_udd=='5'?Carbon::now()->addMinute()->format('H:i:s'):Carbon::now()->format('H:i:s');
                                    $detil_pemberian->no = $detil_barang->no;
                                    $detil_pemberian->id = $detil_barang->id;
                                    $detil_pemberian->kdbrg = $detil_barang->kdbrg;
                                    $detil_pemberian->jumlah = $jml_if;
                                    $detil_pemberian->active = 1;
                                    $detil_pemberian->inputby = '';
                                    $detil_pemberian->inputdate = new DateTime();
                                    $detil_pemberian->save();
                                }
                            }else{
                                //racikan
                                $detil_pemberian = new TempDetilPemberian();
                                $detil_pemberian->id_trans = $kode_transaksi;
                                $detil_pemberian->jadwal_tgl_pemberian = Carbon::now()->format('Y-m-d');
                                $detil_pemberian->idx_pemberian = 1;
                                $detil_pemberian->jadwal_jam_pemberian = Carbon::now()->format('H:i:s');
                                $detil_pemberian->no = $detil_barang->no;
                                $detil_pemberian->id = $detil_barang->id;
                                $detil_pemberian->kdbrg = $detil_barang->kdbrg;
                                $detil_pemberian->jumlah = $jml_if;
                                $detil_pemberian->active = 1;
                                $detil_pemberian->inputby = '';
                                $detil_pemberian->inputdate = new DateTime();
                                $detil_pemberian->save();
                            }
                            if($detil_barang->kdsigna!='' && 
                                DB::table('if_msigna')->where('kdsigna',$detil_barang->kdbrg)->count()>0){
                                $detil_racikan = TempDetil::where('id_trans',$kode_transaksi)
                                                ->where('no','<',$detil_barang->no)
                                                ->orderByDesc('no')
                                                ->select('kdbrg','no','signa','tipe_qty')
                                                ->get();
                                foreach($detil_racikan  as $detil_barang_racikan){
                                    if($detil_barang_racikan->signa!=''){
                                        break;
                                    }else{
                                        if(DB::table('if_msigna')
                                            ->where('kdsigna',$detil_barang->kdbrg)
                                            ->where('signa','like','%DTD%')
                                            ->count()>0){
                                            // dtd
                                            $jml = TempDetil::where('id_trans',$kode_transaksi)
                                                    ->where('no',$detil_barang_racikan->no)
                                                    ->value('jumlah');
                                            $jml = (float)$jml*(float)$detil_barang->jumlah;
                                            if($detil_barang_racikan->tipe_qty==1 || $detil_barang_racikan->tipe_qty==2){
                                                $jml = ceil($jml);
                                            }else if($detil_barang_racikan->tipe_qty==7){
                                                $dosis = DB::table('if_mbrg_gd')
                                                        ->where('kdbrg',$detil_barang_racikan->kdbrg)
                                                        ->value(DB::raw("isnull(dosis,0)"));
                                                if($dosis==0){
                                                    $jml = 0;
                                                }else{
                                                    $jml = ceil((float)$jml/$dosis/1000);
                                                }
                                            }else{
                                                $dosis = DB::table('if_mbrg_gd')
                                                        ->where('kdbrg',$detil_barang_racikan->kdbrg)
                                                        ->value(DB::raw("isnull(dosis,0)"));
                                                if($dosis==0){
                                                    $jml = 0;
                                                }else{
                                                    $jml = ceil((float)$jml/$dosis);
                                                }
                                            }
                                            $jml2 = $jml;
                                            $stok_akhir = DB::table('if_mbrg')
                                                        ->where('tipeif',$tipeif)
                                                        ->where('kdbrg',$detil_barang_racikan->kdbrg)
                                                        ->value('brgak');
                                            if($stok_akhir<$jml2){
                                                $jml2 = $stok_akhir;
                                            }
                                            DB::update('update if_udd_trans_temp set jml_if=:jml_if,jml_if2=:jml_if2 
                                            where id_trans=:id_trans and no=:no', 
                                            ['jml_if'=>$jml,'jml_if2'=>$jml2,
                                            'id_trans'=>$kode_transaksi,'no'=>$detil_barang_racikan->no]);

                                            DB::update('update if_udd_trans)_detil_temp set jumlah=:jml_if 
                                            where id_trans=:id_trans and no=:no', 
                                            ['jml_if'=>$jml,'id_trans'=>$kode_transaksi,'no'=>$detil_barang_racikan->no]);
                                        }
                                        // end dtd
                                    }
                                    // end hitung racikan
                                }
                                // end loop
                            }
                            // end cek racikan
                        }
                        // end loop detil inputan
                    }
                    // end save
                    DB::commit();
                    $code = 200;
                    $message = 'preview sukses';
                    $status = 'success';
                    $header_transaksi = DB::table('if_udd_htrans_temp')
                                        ->where('id_trans',$kode_transaksi)
                                        ->where('active',1)
                                        ->first('id_trans','noreg','tgl','tglshift','jam','jaga','antrian','kdkamar',
                                        'kddok','tipeif','idunit');
                    $detil_transaksi = DB::table('if_udd_trans_temp')
                                        ->where('id_trans',$kode_transaksi)
                                        ->where('active',1)
                                        ->select('no','id','kdbrg','harga','hbiji','hjual',
                                            'disc','tipe_qty','jumlah','jml_if','jml_if2','signa','signa2',
                                            'hari','jasa','ketqty','stok_if_ak','jumlah_seper')
                                        ->get();
                    $detil_transaksi_pemberian = DB::table('if_udd_trans_detil_temp')
                                                ->where('id_trans',$kode_transaksi)
                                                ->where('active',1)
                                                ->select('idx_pemberian','jadwal_tgl_pemberian','jadwal_jam_pemberian',
                                                'no','id','kdbrg','jumlah')
                                                ->get();
                    $header_transaksi->detil_transaksi = $detil_transaksi;
                    $header_transaksi->detil_pemberian = $detil_transaksi_pemberian;
                    $data = $header_transaksi;
                }
                catch(\Exception $ex){
                    DB::rollBack();
                    $message = $ex->getMessage();
                }
            }
        }else{
            $message = 'tidak ada data yang diproses';
        }

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function saveTransaksi(Request $request){
        $code = 401;
        $status = 'error';
        $message = '';
        $data = null;

        $kode_transaksi = $request->kode_transaksi;
        $detil_pemberian = json_decode($request->detil_pemberian,true);
        $user_proses = $request->user_proses;
        $device_info = $request->device_info;
        
        $validasi = Validator::make($request->all,[
            'kode_transaksi' => 'required',
            'detil_pemberian' => 'required',
            'user_proses' => 'required',
        ]);

        if($validasi->fails()){
            $message = $validasi->errors();
        }else{
            if (DB::table('if_udd_htrans_temp')
                ->where('active',1)
                ->where('id_trans',$kode_transaksi)
                ->count()>0){
                    $collect_detil_pemberian = collect($detil_pemberian);
                    $data_temporary = DB::table('if_udd_trans_temp')
                                    ->where('id_trans',$kode_transaksi)
                                    ->select('*')
                                    ->orderBy('no')
                                    ->get();
                    foreach($data_temporary as $tmp){
                        $jumlah = $collect_detil_pemberian->where('nomor',$tmp->no)
                                                ->sum('jumlah');
                        if($jumlah!=$tmp->jml_if){
                            $message = 'jumlah obat dengan kode '.$tmp->kdbrg.' tidak sama dengan jumlah obat setelah pembagian';
                            break;
                        }
                    }
                    if($message==''){
                        try{
                            DB::beginTransaction();
                            
                            DB::commit();
                        }
                        catch(\Exception $ex){
                            DB::rollBack();
                            $message = $ex->getMessage();
                        }
                    }
            }else{
                $message = 'kode transaksi tidak ada dalam sistem';
            }
        }
    }
}