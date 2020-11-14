<?php

namespace App\Http\Controllers\InpatientData;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\LogKartuStok;

class InfoPasienRIController extends Controller
{
    public function logUser($userid,$route,$process_name,$params,$response_message,$device_info){
        try{
            LogKartuStok::create([
                'userid' => $userid,
                'route' => $route,
                'process_name' => $process_name,
                'params' => $params,
                'response_message' => $response_message,
                'device_info' => $device_info,
                'created_date' => date('Y-m-d H:i:s'),
            ]);
            return true;
        }catch(\Exception $ex){
            return false;
        }
    }

    public function getListRuanganRawatInapPasien(Request $request){
        $idunit = $request->idunit;
        
        $data_ruang_buka_register = DB::table('ri_bukaregisterkrs as a')
                                    ->join('ri_masterpx mp',function ($join){
                                        $join->on('mp.noreg','=','a.noreg')
                                            ->where('a.active',1)
                                            ->where(DB::raw('convert(varchar,a.tanggal1,120)'),'<=',Carbon::now()->format('Y-m-d H:i:s'))
                                            ->where(DB::raw('convert(varchar,a.tanggal2,120)'),'>=',Carbon::now()->format('Y-m-d H:i:s'));
                                    })
                                    ->join('ri_mkamar as k',function ($join){
                                        $join->on('k.kd_kmr','=','mp.kmr_skrg')
                                            ->on('mp.unit_id','=','k.unit_id');
                                    })
                                    ->join('ri_mrperawatan as r','r.rp','=','k.rp')
                                    ->where('mp.unit_id',$idunit)
                                    ->where(DB::raw("isnull(mp.sts_batal,0)"),'=',0)
                                    ->select('r.rp as kode','r.nm_ruang as ruang')
                                    ->groupBy('r.rp','r.nm_ruang');

        $data_ruang = DB::table('ri_masterpx as mp')
                    ->join('ri_mkamar as k',function ($join){
                        $join->on('k.kd_kmr','=','mp.kmr_skrg')
                            ->on('mp.unit_id','=','k.unit_id');
                    })
                    ->join('ri_mrperawatan as r','r.rp','=','k.rp')
                    ->whereRaw("mp.tgl_plng is null")
                    ->where('mp.unit_id',$idunit)
                    ->where(DB::raw("isnull(mp.sts_batal,0)"),'=',0)
                    ->select('r.rp as kode','r.nm_ruang as ruang')
                    ->groupBy('r.rp','r.nm_ruang')
                    ->unionAll($data_ruang_buka_register);

        $data_ruang_all = DB::table(DB::raw("({$data_ruang->toSql()}) as ruang"))
                        ->mergeBindings($data_ruang)
                        ->select('kode','ruang')
                        ->groupBy('kode','ruang')
                        ->orderBy('ruang')
                        ->get();
        
        return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'data ruang rawat inap pasien aktif',
                'data' => $data_ruang_all,
            ]);
    }

    public function getListPasienRawatInapAktif(Request $request){
        $idunit = $request->idunit;
        $kode_ruang = $request->kode_ruang;
        
        $data_pasien_ri = DB::table('ri_masterpx as a')
                            ->join('ri_mkamar as b',function ($join){
                                $join->on('a.kmr_skrg','=','b.kd_kmr')
                                    ->on('a.unit_id','=','b.unit_id');
                            })
                            ->join('rirj_masterpx as c','c.no_peserta','=','a.nopeserta')
                            ->join('ri_mrperawatan as d','d.rp','=','b.rp')
                            ->join('rirj_mdebitur as e','e.kddebt','=','a.kdebi')
                            ->join('rirj_mdebt_dinas as f',function ($join){
                                $join->on('f.kddebt','=','a.kdebi')
                                    ->on('f.kddin','=','a.kdini');
                            })
                            ->select('a.noreg as register','a.kbuku as rm','c.nama',
                                DB::raw("dbo.GetAgeYear(c.tgl_lhr,convert(date,getdate())) as usia"),'c.sex','b.nm_kmr as kamar',
                                'e.nmdebt as debitur','f.nmdin as dinas',DB::raw("'' as kode_dpjp"),DB::raw("'' as dpjp"))
                            ->where('a.unit_id','=',$idunit)
                            ->whereRaw("a.tgl_plng is null")
                            ->where(DB::raw("isnull(a.sts_batal,0)"),'=',0)
                            ->where('d.rp','=',$kode_ruang)
                            ->get();
        foreach($data_pasien_ri as $data){
            $data_dokter = DB::table('ri_masterpx_dokterdpjp as dpjp')
                            ->join('dr_mdokter as d','d.kddok','=','dpjp.kddok')
                            ->where('noreg',$data->register)
                            ->where('dokteraktif',1)
                            ->where('dokterutama',1)
                            ->select('d.kddok as kode','d.nmdok as dokter')
                            ->groupBy('d.kddok','d.nmdok')
                            ->get();
            if(count($data_dokter)>0){
                $data->kode_dpjp = $data_dokter[0]->kode;
                $data->dpjp = $data_dokter[0]->dokter;
            }else{
                $data_dokter = DB::table('ri_masterpx as dpjp')
                            ->join('dr_mdokter as d','d.kddok','=','dpjp.pengi')
                            ->where('noreg',$data->register)
                            ->select('d.kddok as kode',
                            DB::raw("concat(d.nmdok,' (dokter pengirim)') as dokter"))
                            ->groupBy('d.kddok',DB::raw("concat(d.nmdok,' (dokter pengirim)')"))
                            ->get();
                if(count($data_dokter)>0){
                    $data->kode_dpjp = $data_dokter[0]->kode;
                    $data->dpjp = $data_dokter[0]->dokter;
                }
            }
        }
        //Carbon::parse(DB::raw("convert(date,c.tgl_lhr)"))->diff(Carbon::now())->format('%y Tahun %m Bulan'))
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data pasien aktif per ruang',
            'data' => $data_pasien_ri,
        ]);
    }

    public function cariRegisterRawatInap(Request $request){
        $idunit = $request->idunit;
        $register = $request->register;
        
        $data = null;
        $code = 401;
        $message = '';
        $status = 'error';
        $jumlah_data = 0;
        $data_pasien_ri = [];

        $device_info = $request->device_info;
        $user = Auth::user();

        if($register != ''){
            //cek register
            $jumlah_data = DB::table('ri_masterpx')
                        ->whereRaw("isnull(sts_batal,0)=0")
                        ->where('noreg',$register)
                        ->where('unit_id',$idunit)
                        ->count();
            if($jumlah_data>0){
                //cek status register
                $jumlah_data = DB::table('ri_masterpx')
                            ->whereRaw("isnull(sts_batal,0)=0")
                            ->where('noreg',$register)
                            ->where('unit_id',$idunit)
                            ->whereRaw("tgl_plng is not null")
                            ->count();
                if($jumlah_data>0){
                    $jumlah_data = DB::table('ri_bukaregisterkrs')
                                    ->where('active',1)
                                    ->where(DB::raw('convert(varchar,tanggal1,120)'),'<=',Carbon::now()->format('Y-m-d H:i:s'))
                                    ->where(DB::raw('convert(varchar,tanggal2,120)'),'>=',Carbon::now()->format('Y-m-d H:i:s'))
                                    ->count();
                    if($jumlah_data<1){
                        $message = 'pasien telah krs';
                    }
                }
            }else{
                $message = 'register tidak ditemukan';
            }
        }else{
            $message = 'register tidak boleh kosong';
        }

        if($message==''){
            $code = 200;
            $status = 'success';
            $message = 'data pasien rawat inap';
            $data_pasien_ri = DB::table('ri_masterpx as a')
                            ->join('ri_mkamar as b',function ($join){
                                $join->on('a.kmr_skrg','=','b.kd_kmr')
                                    ->on('a.unit_id','=','b.unit_id');
                            })
                            ->join('rirj_masterpx as c','c.no_peserta','=','a.nopeserta')
                            ->join('ri_mrperawatan as d','d.rp','=','b.rp')
                            ->join('rirj_mdebitur as e','e.kddebt','=','a.kdebi')
                            ->join('rirj_mdebt_dinas as f',function ($join){
                                $join->on('f.kddebt','=','a.kdebi')
                                    ->on('f.kddin','=','a.kdini');
                            })
                            ->select('a.noreg as register','a.kbuku as rm','c.nama',
                                DB::raw("dbo.GetAgeYear(c.tgl_lhr,convert(date,getdate())) as usia"),'c.sex','b.nm_kmr as kamar',
                                'e.nmdebt as debitur','f.nmdin as dinas',
                                DB::raw("'' as kode_dpjp"),DB::raw("'' as dpjp"),DB::raw("0 as udd_hari_ini"))
                            ->where('a.unit_id','=',$idunit)
                            ->whereRaw("a.tgl_plng is null")
                            ->where(DB::raw("isnull(a.sts_batal,0)"),'=',0)
                            ->where('a.noreg','=',$register)
                            ->first();
            
            if(count($data_pasien_ri)>0){
                $data_dokter = DB::table('ri_masterpx_dokterdpjp as dpjp')
                            ->join('dr_mdokter as d','d.kddok','=','dpjp.kddok')
                            ->where('noreg',$data_pasien_ri[0]->register)
                            ->where('dokteraktif',1)
                            ->where('dokterutama',1)
                            ->select('d.kddok as kode','d.nmdok as dokter')
                            ->groupBy('d.kddok','d.nmdok')
                            ->get();
                if(count($data_dokter)>0){
                    $data_pasien_ri->kode_dpjp = $data_dokter[0]->kode;
                    $data_pasien_ri->dpjp = $data_dokter[0]->dokter;
                }else{
                    $data_dokter = DB::table('ri_masterpx as dpjp')
                                ->join('dr_mdokter as d','d.kddok','=','dpjp.pengi')
                                ->where('noreg',$data_pasien_ri[0]->register)
                                ->select('d.kddok as kode',
                                DB::raw("concat(d.nmdok,' (dokter pengirim)') as dokter"))
                                ->groupBy('d.kddok',DB::raw("concat(d.nmdok,' (dokter pengirim)')"))
                                ->get();
                    if(count($data_dokter)>0){
                        $data_pasien_ri->kode_dpjp = $data_dokter[0]->kode;
                        $data_pasien_ri->dpjp = $data_dokter[0]->dokter;
                    }
                }
                $jumlah_data = DB::table('if_udd_htrans')
                                ->where('active',1)
                                ->where('noreg',$data_pasien_ri[0]->register)
                                ->where(DB::raw("convert(date,tgl)"),'=',Carbon::now()->format('Y-m-d'))
                                ->count();
                $data_pasien_ri->udd_hari_ini = $jumlah_data;
            }
        }

        $this->logUser($user->id,'/proses-register-rawat-inap','proses register rawat inap',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data_pasien_ri,
        ]);
    }
}
