<?php

namespace App\Http\Controllers\StockcardTransaction;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use App\Barang;
use App\KartuBarang;
use App\KartuBarangTransaksi as headerTrans;
use App\KartuBarangDetilTransaksi as detilTrans;
use Illuminate\Support\Facades\Auth;
// use Mockery\CountValidator\Exception;
use App\LogKartuStok;

// use PDO;
// use function GuzzleHttp\json_decode;

class MutasiBarang extends Controller
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

    public function listJenisTransaksi(){
        $data = DB::table('if_kartu_stok_tipe_transaksi')
        ->where('active',1)
        ->select('kode_tipe_transaksi as kode','tipe_transaksi as tipe','mutasi',DB::raw("isnull(nama_komponen,'') as 'nama_komponen'"))
        ->orderBy('kode_tipe_transaksi')
        ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data tipe transaksi',
            'data' => $data,
        ]);
    }

    public function listJenisTransaksiCentra(){
        $data = DB::table('if_kartu_stok_jenis_transaksi_centra')
        ->select('kode_jenis_transaksi as kode','jenis_transaksi_centra as jenis_transaksi')
        ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data tipe transaksi centra',
            'data' => $data]
        );
    }

    public function listTransaksiTransferStok(Request $request){
        $depo = $request->get('depo');
        $tgl1 = $request->get('tgl1');
        $tgl2 = $request->get('tgl2');
        $data = DB::table('if_kartu_stok_htransaksi as h')
                ->join('if_mlayanan as l','l.kode_mutasi','=','h.kdmut')
                ->join('if_users_api as u','u.id','=','h.inputby')
                ->leftJoin('if_kartu_stok_tipe_transaksi as t','t.kode_tipe_transaksi','=','h.tipe_transaksi')
                ->where('h.kdmut',$depo)
                ->where('h.mutasi','!=','I')
                // ->where(DB::raw("isnull(h.tipe_transaksi,'')"),'!=','')
                ->where('h.active',1)
                ->where(DB::raw("isnull(h.tipe_transaksi,0)"),0)
                ->whereBetween(DB::raw("convert(date,h.tgl_proses_kartu)"),[Carbon::parse($tgl1)->format('Y-m-d'),
                Carbon::parse($tgl2)->format('Y-m-d')])
                ->select('h.id_transaksi','h.mutasi','h.kdmut as kode_unit','l.layanan','h.tgl_proses_kartu',
                    // DB::raw("isnull(t.tipe_transaksi,'Transfer Stok antar Rak') as jenis_transaksi"),
                    DB::raw("case when convert(date,h.tgl_proses_kartu)=convert(date,getdate()) then 'Draft' else 'Completed' end as status_transaksi"),
                    'u.name as nama')
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data transaksi transfer stok',
            'data' => $data,
            ]);
    }

    public function listTransaksiKartuStok(Request $request){
        $depo = $request->get('depo');
        $tgl1 = $request->get('tgl1');
        $tgl2 = $request->get('tgl2');

        $transaksi = DB::table('if_kartu_stok_htransaksi as h')
                    // ->leftJoin('if_htrans as ht',[['h.kode_transaksi_farmasi','=','ht.id_trans'],['ht.active','=',1]])
                    ->leftJoin('if_htrans as ht',function ($join){
                        $join->on('h.kode_transaksi_farmasi','=','ht.id_trans')
                            ->where('ht.active',1);
                    })
                    // ->join('if_kartu_stok_tipe_transaksi as tp',[['tp.kode_tipe_transaksi','=','h.tipe_transaksi'],['tp.active','=',1]])
                    ->join('if_kartu_stok_tipe_transaksi as tp',function ($join){
                        $join->on('tp.kode_tipe_transaksi','=','h.tipe_transaksi')
                            ->where('tp.active',1);
                    })
                    ->join('if_kartu_stok_status_transaksi as st','st.kode_status_transaksi','=','h.status_transaksi')
                    // ->leftJoin('if_kartu_stok_jenis_transaksi_centra as jc',[['jc.kode_jenis_transaksi','=','h.jenis_transaksi_centra'],['jc.active','=',1]])
                    ->leftJoin('if_kartu_stok_jenis_transaksi_centra as jc',function ($join){
                        $join->on('jc.kode_jenis_transaksi','=','h.jenis_transaksi_centra')
                            ->where('jc.active',1);
                    })
                    ->join('if_users_api as u','u.id','=','h.inputby')
                    ->join('if_mlayanan as l','l.kode_mutasi','=','h.kdmut')
                    ->select('h.id_transaksi','h.tgl_proses_kartu as tgl_mutasi','h.kdmut as kode_unit','l.LAYANAN as unit','h.mutasi',
                        DB::raw("isnull(h.kode_transaksi_farmasi,'') as kode_transaksi_farmasi"),
                        DB::raw("isnull(h.nodoc_centra_non_penjualan,'') as no_doc_centra"),
                        DB::raw("isnull(h.tipe_transaksi,0) as kode_tipe_transaksi"),
                        DB::raw("isnull(tp.tipe_transaksi,'') as tipe_transaksi"),
                        DB::raw("isnull(h.jenis_transaksi_centra,0) as kode_jenis_transaksi_centra"),
                        DB::raw("isnull(jc.jenis_transaksi_centra,'') as jenis_transaksi_centra"),
                        DB::raw("isnull(h.status_transaksi,0) as kode_status_transaksi"),
                        DB::raw("isnull(st.kode_status_transaksi,'') as status_transaksi"),
                        DB::raw("case when convert(varchar,h.tgl_proses_kartu,23) = convert(varchar,getdate(),23) then 'Draft' else 'Completed' end as status_editing"))
                    ->where('h.active','=',1)
                    ->where('h.kdmut','=',$depo)
                    ->where(DB::raw('convert(date,h.tgl_proses_kartu)'),'>=',Carbon::parse($tgl1)->format('Y-m-d'))
                    ->where(DB::raw('convert(date,h.tgl_proses_kartu)'),'<=',Carbon::parse($tgl2)->format('Y-m-d'))
                    ->orderBy('id_transaksi')
                    ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data transaksi kartu stok',
            'data' => $transaksi,
            ]);
    }

    public function laporanRincianMyTransaksi(Request $request){
        $depo = $request->get('depo');
        $tgl1 = $request->get('tgl1');
        $tgl2 = $request->get('tgl2');
        $jenis_transaksi = $request->get('jenis_transaksi');

        $user = Auth::user();

        $transaksi = DB::table('if_kartu_stok_htransaksi as h')
                    ->join('if_kartu_stok_dtransaksi as t','h.id_transaksi','=','t.id_transaksi_kartu_stok')
                    ->join('if_kartu_stok_barang as a',function ($join){
                        $join->on('a.id_kartu_stok','=','t.id_kartu_stok')
                            ->on('a.kdmut','=','h.kdmut');
                    })
                    ->join('if_mlemari as l',function ($join){
                        $join->on('l.idlemari','=','a.id_lemari')
                            ->on('a.kdmut','=','l.kdmut');
                    })
                    ->join('if_mbrg_gd as b','b.kdbrg','=','a.kdbrg')
                    ->leftJoin('if_htrans as hh','h.kode_transaksi_farmasi','=','hh.id_trans')
                    ->leftJoin('if_kartu_stok_tipe_transaksi as c','c.kode_tipe_transaksi','=','h.tipe_transaksi')
                    ->select('h.id_transaksi',DB::raw("isnull(b.kdbrg,'') as kode_brg_phc"),
                        DB::raw("isnull(b.kdbrg_centra,'') as kode_brg_centra"),'b.nmbrg as nama_brg','l.nmlemari as rak_brg',
                        't.qty as qty_brg','h.tgl_proses_kartu as tgl_mutasi','h.mutasi',
                        DB::raw("isnull(c.tipe_transaksi,'Transfer Stok') as tipe_transaksi"),
                        DB::raw("case when isnull(h.kode_transaksi_farmasi,'') <> '' then concat('No. Transaksi : ',hh.nomor,
                        ' (',replace(convert(varchar,hh.tgl,103),'/','-'),')') else
                        case when isnull(h.tipe_transaksi,0)=0 then concat('ID Referensi : ',case when h.mutasi = 'O' then 
                        isnull(h.id_transaksi,0)+1 else isnull(h.ref_id_transaksi,0) end) else concat('No Doc. : ',isnull(h.nodoc_centra_non_penjualan,'')) end end as referensi"))
                    ->where('h.active','=',1)
                    ->where('t.active','=',1)
                    ->where('h.inputby','=',$user->id)
                    ->where('h.kdmut','=',$depo)
                    ->where(DB::raw('convert(date,h.tgl_proses_kartu)'),'>=',Carbon::parse($tgl1)->format('Y-m-d'))
                    ->where(DB::raw('convert(date,h.tgl_proses_kartu)'),'<=',Carbon::parse($tgl2)->format('Y-m-d'))
                    ->where(function ($query) use ($jenis_transaksi){
                        if($jenis_transaksi!=0){
                            // jenis spesifik
                            if($jenis_transaksi!=99){
                                $query->where(DB::raw("isnull(h.tipe_transaksi,0)"),'=',$jenis_transaksi);
                            }else{
                                // transfer stok
                                $query->where(DB::raw("isnull(h.tipe_transaksi,0)"),'=',0);
                            }
                        }
                    })
                    ->orderBy('id_transaksi')
                    ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data rincian transaksi kartu stok per user',
            'data' => $transaksi,
            ]);
    }

    public function laporanRincianTransaksi(Request $request){
        $depo = $request->get('depo');
        $tgl1 = $request->get('tgl1');
        $tgl2 = $request->get('tgl2');
        $jenis_transaksi = $request->get('jenis_transaksi');

        $transaksi = DB::table('if_kartu_stok_htransaksi as h')
                    ->join('if_kartu_stok_dtransaksi as t','h.id_transaksi','=','t.id_transaksi_kartu_stok')
                    ->join('if_kartu_stok_barang as a',function ($join){
                        $join->on('a.id_kartu_stok','=','t.id_kartu_stok')
                            ->on('a.kdmut','=','h.kdmut');
                    })
                    ->join('if_mlemari as l',function ($join){
                        $join->on('l.idlemari','=','a.id_lemari')
                            ->on('a.kdmut','=','l.kdmut');
                    })
                    ->join('if_mbrg_gd as b','b.kdbrg','=','a.kdbrg')
                    ->join('if_users_api as u','u.id','=','h.inputby')
                    ->leftJoin('if_htrans as hh','h.kode_transaksi_farmasi','=','hh.id_trans')
                    ->leftJoin('if_kartu_stok_tipe_transaksi as c','c.kode_tipe_transaksi','=','h.tipe_transaksi')
                    ->select('h.id_transaksi',DB::raw("isnull(b.kdbrg,'') as kode_brg_phc"),
                        DB::raw("isnull(b.kdbrg_centra,'') as kode_brg_centra"),'b.nmbrg as nama_brg','l.nmlemari as rak_brg',
                        't.qty as qty_brg','h.tgl_proses_kartu as tgl_mutasi','h.mutasi',
                        DB::raw("isnull(c.tipe_transaksi,'Transfer Stok') as tipe_transaksi"),
                        DB::raw("case when isnull(h.kode_transaksi_farmasi,'') <> '' then concat('No. Transaksi : ',hh.nomor,
                        ' (',replace(convert(varchar,hh.tgl,103),'/','-'),')') else
                        case when isnull(h.tipe_transaksi,0)=0 then concat('ID Referensi : ',case when h.mutasi = 'O' then 
                        isnull(h.id_transaksi,0)+1 else isnull(h.ref_id_transaksi,0) end) else concat('No Doc. : ',isnull(h.nodoc_centra_non_penjualan,'')) end end as referensi"),
                        'u.name as user')
                    ->where('h.active','=',1)
                    ->where('t.active','=',1)
                    // ->where('h.inputby','=',$user->id)
                    ->where('h.kdmut','=',$depo)
                    ->where(DB::raw('convert(date,h.tgl_proses_kartu)'),'>=',Carbon::parse($tgl1)->format('Y-m-d'))
                    ->where(DB::raw('convert(date,h.tgl_proses_kartu)'),'<=',Carbon::parse($tgl2)->format('Y-m-d'))
                    ->where(function ($query) use ($jenis_transaksi){
                        if($jenis_transaksi!=0){
                            // jenis spesifik
                            if($jenis_transaksi!=99){
                                $query->where(DB::raw("isnull(h.tipe_transaksi,0)"),'=',$jenis_transaksi);
                            }else{
                                // transfer stok
                                $query->where(DB::raw("isnull(h.tipe_transaksi,0)"),'=',0);
                            }
                        }
                    })
                    ->orderBy('id_transaksi')
                    ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data rincian transaksi kartu stok',
            'data' => $transaksi,
            ]);
    }

    public function laporanMovementBarang(Request $request){
        $depo = $request->get('depo');
        $tgl1 = $request->get('tgl1');
        $tgl2 = $request->get('tgl2');
        $id_kartu_stok = $request->get('id_kartu_stok');
        $mutasi = $request->get('mutasi'); // I : masuk - O : keluar - X : Total
        
        $data_awal_masuk = '';
        $data_awal_keluar = '';
        $data_masuk = '';
        $data_keluar = '';

        $tglawal = Carbon::parse($tgl1)->format('Y-m-d').' 00:00:00';
        $tglsoakhir = DB::table('if_mglobal')
                    ->where('tipeglobal','TglSOTerakhir')
                    ->value(DB::raw("convert(datetime,valstr)"));

        if(Carbon::parse($tglsoakhir)->format('Y-m-d')==Carbon::parse($tgl1)->format('Y-m-d')){
            $tglawal = Carbon::parse($tglsoakhir)->addSecond()->format('Y-m-d H:i:s');
        }
        
        $data_awal_masuk = DB::table('if_kartu_stok_htransaksi as h')
                            ->join('if_kartu_stok_dtransaksi as t','h.id_transaksi','=','t.id_transaksi_kartu_stok')
                            ->join('if_kartu_stok_barang as bb','bb.id_kartu_stok','=','t.id_kartu_stok')
                            ->join('if_mbrg_gd as bc','bb.kdbrg','=','bc.kdbrg')
                            ->join('if_mlemari as lm','lm.idlemari','=','bb.id_lemari')
                            ->where('h.kdmut',$depo)
                            ->where('t.id_kartu_stok',$id_kartu_stok)
                            ->where('h.active',1)
                            ->where('t.active',1)
                            ->where('h.mutasi','I')
                            ->whereBetween(DB::raw("convert(varchar,h.tgl_proses_kartu,120)"),[
                                Carbon::parse($tglsoakhir)->addSecond()->format('Y-m-d H:i:s'),
                                Carbon::parse($tglawal)->subSecond()->format('Y-m-d H:i:s')
                            ])
                            ->select('bc.kdbrg as kode','bc.nmbrg as nama','lm.nmlemari as rak',DB::raw("'Stok Awal' as keterangan"),
                                DB::raw("case when convert(datetime,'$tglawal') < convert(datetime,'$tglsoakhir') then '$tglsoakhir' else '$tglawal' end as tgl"),
                                DB::raw("'' as tipe_transaksi"),
                                DB::raw("'' as petugas"),DB::raw("sum(t.qty) as awal"),
                                DB::raw('0 as masuk'),DB::raw('0 as keluar'))
                            ->groupBy('bc.kdbrg','bc.nmbrg','lm.nmlemari');

        $data_awal_keluar = DB::table('if_kartu_stok_htransaksi as h')
                            ->join('if_kartu_stok_dtransaksi as t','h.id_transaksi','=','t.id_transaksi_kartu_stok')
                            ->join('if_kartu_stok_barang as bb','bb.id_kartu_stok','=','t.id_kartu_stok')
                            ->join('if_mbrg_gd as bc','bb.kdbrg','=','bc.kdbrg')
                            ->join('if_mlemari as lm','lm.idlemari','=','bb.id_lemari')
                            ->where('h.kdmut',$depo)
                            ->where('t.id_kartu_stok',$id_kartu_stok)
                            ->where('h.active',1)
                            ->where('t.active',1)
                            ->where('h.mutasi','O')
                            ->whereBetween(DB::raw("convert(varchar,h.tgl_proses_kartu,120)"),[
                                Carbon::parse($tglsoakhir)->addSecond()->format('Y-m-d H:i:s'),
                                Carbon::parse($tglawal)->subSecond()->format('Y-m-d H:i:s')
                            ])
                            ->select('bc.kdbrg as kode','bc.nmbrg as nama','lm.nmlemari as rak',DB::raw("'Stok Awal' as keterangan"),
                                DB::raw("case when convert(datetime,'$tglawal') < convert(datetime,'$tglsoakhir') then '$tglsoakhir' else '$tglawal' end as tgl"),
                                DB::raw("'' as tipe_transaksi"),
                                DB::raw("'' as petugas"),DB::raw("sum(t.qty*-1) as awal"),
                                DB::raw('0 as masuk'),DB::raw('0 as keluar'))
                            ->groupBy('bc.kdbrg','bc.nmbrg','lm.nmlemari');

        if($mutasi == 'I' || $mutasi == 'X'){
            $data_masuk = DB::table('if_kartu_stok_htransaksi as h')
                            ->join('if_kartu_stok_dtransaksi as t','h.id_transaksi','=','t.id_transaksi_kartu_stok')
                            ->join('if_users_api as u','u.id','=','h.inputby')
                            ->leftJoin('if_kartu_stok_tipe_transaksi as x','x.kode_tipe_transaksi','=','h.tipe_transaksi')
                            ->leftJoin('if_htrans as hh','hh.id_trans','=','h.kode_transaksi_farmasi')
                            ->join('if_kartu_stok_barang as bb','bb.id_kartu_stok','=','t.id_kartu_stok')
                            ->join('if_mbrg_gd as bc','bb.kdbrg','=','bc.kdbrg')
                            ->join('if_mlemari as lm','lm.idlemari','=','bb.id_lemari')
                            ->where('h.kdmut',$depo)
                            ->where('t.id_kartu_stok',$id_kartu_stok)
                            ->where('h.active',1)
                            ->where('t.active',1)
                            ->where('h.mutasi','I')
                            ->whereBetween(DB::raw("convert(varchar,h.tgl_proses_kartu,120)"),[
                                Carbon::parse($tglawal)->format('Y-m-d H:i:s'),
                                Carbon::parse($tgl2.' '.'23:59:59')->format('Y-m-d H:i:s')
                            ])
                            ->select('bc.kdbrg as kode','bc.nmbrg as nama','lm.nmlemari as rak',
                                DB::raw("case when isnull(h.kode_transaksi_farmasi,'') <> '' then concat('No. Transaksi : ',hh.nomor,
                                ' (',replace(convert(varchar,hh.tgl,103),'/','-'),')') else 
                                case when isnull(h.tipe_transaksi,0)=0 then concat('ID Referensi : ',case when h.mutasi = 'O' then 
                                isnull(h.id_transaksi,0)+1 else isnull(h.ref_id_transaksi,0) end) else concat('No Doc. : ',isnull(h.nodoc_centra_non_penjualan,'')) end end as keterangan"),
                                'h.tgl_proses_kartu as tgl',DB::raw("isnull(x.tipe_transaksi,'Transfer Stok') as tipe_transaksi"),'u.name as petugas',
                                DB::raw('0 as awal'),'t.qty as masuk',DB::raw('0 as keluar'));
        }

        if($mutasi == 'O' || $mutasi == 'X'){
            $data_keluar = DB::table('if_kartu_stok_htransaksi as h')
                            ->join('if_kartu_stok_dtransaksi as t','h.id_transaksi','=','t.id_transaksi_kartu_stok')
                            ->join('if_users_api as u','u.id','=','h.inputby')
                            ->leftJoin('if_kartu_stok_tipe_transaksi as x','x.kode_tipe_transaksi','=','h.tipe_transaksi')
                            ->leftJoin('if_htrans as hh','hh.id_trans','=','h.kode_transaksi_farmasi')
                            ->join('if_kartu_stok_barang as bb','bb.id_kartu_stok','=','t.id_kartu_stok')
                            ->join('if_mbrg_gd as bc','bb.kdbrg','=','bc.kdbrg')
                            ->join('if_mlemari as lm','lm.idlemari','=','bb.id_lemari')
                            ->where('h.kdmut',$depo)
                            ->where('t.id_kartu_stok',$id_kartu_stok)
                            ->where('h.active',1)
                            ->where('t.active',1)
                            ->where('h.mutasi','O')
                            ->whereBetween(DB::raw("convert(varchar,h.tgl_proses_kartu,120)"),[
                                Carbon::parse($tglawal)->format('Y-m-d H:i:s'),
                                Carbon::parse($tgl2.' '.'23:59:59')->format('Y-m-d H:i:s')
                            ])
                            ->select('bc.kdbrg as kode','bc.nmbrg as nama','lm.nmlemari as rak',
                                DB::raw("case when isnull(h.kode_transaksi_farmasi,'') <> '' then concat('No. Transaksi : ',hh.nomor,
                                ' (',replace(convert(varchar,hh.tgl,103),'/','-'),')') else 
                                case when isnull(h.tipe_transaksi,0)=0 then concat('ID Referensi : ',case when h.mutasi = 'O' then 
                                isnull(h.id_transaksi,0)+1 else isnull(h.ref_id_transaksi,0) end) else concat('No Doc. : ',isnull(h.nodoc_centra_non_penjualan,'')) end end as keterangan"),
                                'h.tgl_proses_kartu as tgl',DB::raw("isnull(x.tipe_transaksi,'Transfer Stok') as tipe_transaksi"),'u.name as petugas',
                                DB::raw('0 as awal'),DB::raw('0 as masuk'),'t.qty as keluar');
        }

        $data_all = null;
        if($mutasi == 'I'){
            $data_all = DB::table('if_kartu_stok_barang as k')
                        ->join('if_kartu_stok_barang as bb','bb.id_kartu_stok','=','k.id_kartu_stok')
                        ->join('if_mbrg_gd as bc','bb.kdbrg','=','bc.kdbrg')
                        ->join('if_mlemari as lm','lm.idlemari','=','bb.id_lemari')
                        ->where('k.kdmut',$depo)
                        ->where('k.id_kartu_stok',$id_kartu_stok)
                        ->select('bc.kdbrg as kode','bc.nmbrg as nama','lm.nmlemari as rak',
                            DB::raw("'Stok Awal' as keterangan"),
                            DB::raw("case when convert(datetime,'$tglawal') < convert(datetime,'$tglsoakhir') then '$tglsoakhir' else '$tglawal' end as tgl"),
                            DB::raw("'' as tipe_transaksi"),DB::raw("'' as petugas"),
                            DB::raw("case when convert(datetime,'$tgl1') < convert(datetime,'$tglsoakhir') then 0 else isnull(k.awal,0) end as awal"),
                            DB::raw('0 as masuk'),DB::raw('0 as keluar'))
                        ->unionAll($data_awal_masuk)
                        ->unionAll($data_awal_keluar)
                        ->unionAll($data_masuk);
        }else if($mutasi == 'O'){
            $data_all = DB::table('if_kartu_stok_barang as k')
                        ->join('if_kartu_stok_barang as bb','bb.id_kartu_stok','=','k.id_kartu_stok')
                        ->join('if_mbrg_gd as bc','bb.kdbrg','=','bc.kdbrg')
                        ->join('if_mlemari as lm','lm.idlemari','=','bb.id_lemari')
                        ->where('k.kdmut',$depo)
                        ->where('k.id_kartu_stok',$id_kartu_stok)
                        ->select('bc.kdbrg as kode','bc.nmbrg as nama','lm.nmlemari as rak',
                            DB::raw("'Stok Awal' as keterangan"),
                            DB::raw("case when convert(datetime,'$tglawal') < convert(datetime,'$tglsoakhir') then '$tglsoakhir' else '$tglawal' end as tgl"),
                            DB::raw("'' as tipe_transaksi"),DB::raw("'' as petugas"),
                            DB::raw("case when convert(datetime,'$tgl1') < convert(datetime,'$tglsoakhir') then 0 else isnull(k.awal,0) end as awal"),
                            DB::raw('0 as masuk'),DB::raw('0 as keluar'))
                        ->unionAll($data_awal_masuk)
                        ->unionAll($data_awal_keluar)
                        ->unionAll($data_keluar);
        }else if($mutasi == 'X'){
            $data_all = DB::table('if_kartu_stok_barang as k')
                        ->join('if_kartu_stok_barang as bb','bb.id_kartu_stok','=','k.id_kartu_stok')
                        ->join('if_mbrg_gd as bc','bb.kdbrg','=','bc.kdbrg')
                        ->join('if_mlemari as lm','lm.idlemari','=','bb.id_lemari')
                        ->where('k.kdmut',$depo)
                        ->where('k.id_kartu_stok',$id_kartu_stok)
                        ->select('bc.kdbrg as kode','bc.nmbrg as nama','lm.nmlemari as rak',
                            DB::raw("'Stok Awal' as keterangan"),DB::raw("case when convert(datetime,'$tglawal') < convert(datetime,'$tglsoakhir') then '$tglsoakhir' else '$tglawal' end as tgl"),
                            DB::raw("'' as tipe_transaksi"),DB::raw("'' as petugas"),
                            DB::raw("isnull(k.awal,0) as awal"),
                            DB::raw('0 as masuk'),DB::raw('0 as keluar'))
                        ->unionAll($data_awal_masuk)
                        ->unionAll($data_awal_keluar)
                        ->unionAll($data_masuk)
                        ->unionAll($data_keluar);
        }

        $data = DB::table(DB::raw("({$data_all->toSql()}) as sub"))
                ->mergeBindings($data_all)
                ->select('kode','nama','rak','keterangan','tgl','tipe_transaksi','petugas',
                    DB::raw("isnull(sum(awal),0) as awal"),DB::raw("isnull(sum(masuk),0) as masuk"),
                    DB::raw("isnull(sum(keluar),0) as keluar"),DB::raw("isnull(sum(awal+masuk-keluar),0) as akhir"))
                ->groupBy('kode','nama','rak','keterangan','tgl','tipe_transaksi','petugas')
                ->orderBy('tgl')
                ->get();

        $stok_awal = 0;
        foreach($data as $dt){
            $dt->tgl = Carbon::parse($dt->tgl)->format('Y-m-d H:i:s');
            if($dt->keterangan=='Stok Awal'){
                $stok_awal = (int)$dt->awal+(int)$dt->masuk-(int)$dt->keluar;
            }else{
                $dt->awal = $stok_awal;
                $stok_awal = (int)$dt->awal+(int)$dt->masuk-(int)$dt->keluar;
                $dt->akhir = $stok_awal;
            }
        }
        
        $data->push([
            'kode' => $data[0]->kode,
            'nama' => $data[0]->nama,
            'rak' => $data[0]->rak,
            'keterangan' => 'Stok Akhir',
            'tgl' => Carbon::parse($tgl2.' '.'23:59:59')->format('Y-m-d H:i:s'),
            'tipe_transaksi' => '',
            'petugas' => '',
            'awal' => $stok_awal,
            'masuk' => 0,
            'keluar' => 0,
            'akhir' => $stok_awal,

        ]);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data movement barang',
            'data' => $data,
            ]);
    }

    // public function listTransaksiKartuStokAll(Request $request){
    //     $depo = $request->get('depo');
    //     $tgl1 = $request->get('tgl1');
    //     $tgl2 = $request->get('tgl2');

    //     $transaksi = DB::table('if_kartu_stok_htransaksi as h')
    //                 // ->leftJoin('if_htrans as ht',[['h.kode_transaksi_farmasi','=','ht.id_trans'],['ht.active','=',1]])
    //                 ->leftJoin('if_htrans as ht',function ($join){
    //                     $join->on('h.kode_transaksi_farmasi','=','ht.id_trans')
    //                         ->where('ht.active',1);
    //                 })
    //                 // ->join('if_kartu_stok_tipe_transaksi as tp',[['tp.kode_tipe_transaksi','=','h.tipe_transaksi'],['tp.active','=',1]])
    //                 ->join('if_kartu_stok_tipe_transaksi as tp',function ($join){
    //                     $join->on('tp.kode_tipe_transaksi','=','h.tipe_transaksi')
    //                         ->where('tp.active',1);
    //                 })
    //                 ->join('if_kartu_stok_status_transaksi as st','st.kode_status_transaksi','=','h.status_transaksi')
    //                 // ->leftJoin('if_kartu_stok_jenis_transaksi_centra as jc',[['jc.kode_jenis_transaksi','=','h.jenis_transaksi_centra'],['jc.active','=',1]])
    //                 ->leftJoin('if_kartu_stok_jenis_transaksi_centra as jc',function ($join){
    //                     $join->on('jc.kode_jenis_transaksi','=','h.jenis_transaksi_centra')
    //                         ->where('jc.active',1);
    //                 })
    //                 ->join('if_users_api as u','u.id','=','h.inputby')
    //                 ->join('if_mlayanan as l','l.kode_mutasi','=','h.kdmut')
    //                 ->select('h.id_transaksi','h.tgl_proses_kartu as tgl_mutasi','h.kdmut as kode_unit','l.LAYANAN as unit','h.mutasi',
    //                     DB::raw("isnull(h.kode_transaksi_farmasi,'') as kode_transaksi_farmasi"),
    //                     DB::raw("isnull(h.nodoc_centra_non_penjualan,'') as no_doc_centra"),
    //                     DB::raw("isnull(h.tipe_transaksi,0) as kode_tipe_transaksi"),
    //                     DB::raw("isnull(tp.tipe_transaksi,'') as tipe_transaksi"),
    //                     DB::raw("isnull(h.jenis_transaksi_centra,0) as kode_jenis_transaksi_centra"),
    //                     DB::raw("isnull(jc.jenis_transaksi_centra,'') as jenis_transaksi_centra"),
    //                     DB::raw("isnull(h.status_transaksi,0) as kode_status_transaksi"),
    //                     DB::raw("isnull(st.kode_status_transaksi,'') as status_transaksi"),
    //                     DB::raw("case when convert(varchar,h.tgl_proses_kartu,23) = convert(varchar,getdate(),23) then 'Draft' else 'Completed' end as status_editing"))
    //                 ->where('h.active','=',1)
    //                 ->where('h.kdmut','=',$depo)
    //                 ->where(DB::raw('convert(date,h.tgl_proses_kartu)'),'>=',Carbon::parse($tgl1)->format('Y-m-d'))
    //                 ->where(DB::raw('convert(date,h.tgl_proses_kartu)'),'<=',Carbon::parse($tgl2)->format('Y-m-d'))
    //                 ->orderBy('id_transaksi')
    //                 ->paginate(20);

    //     return response()->json([
    //         'code' => 200,
    //         'status' => 'success',
    //         'message' => 'data transaksi kartu stok',
    //         'data' => $transaksi,
    //         ]);
    // }

    // public function listTransaksiKartuStokFilter(Request $request){
    //     $depo = $request->get('depo');
    //     $tgl1 = $request->get('tgl1');
    //     $tgl2 = $request->get('tgl2');
    //     $filter = $request->get('query');

    //     $transaksi = DB::table('if_kartu_stok_htransaksi as h')
    //                 // ->leftJoin('if_htrans as ht',[['h.kode_transaksi_farmasi','=','ht.id_trans'],['ht.active','=',1]])
    //                 ->leftJoin('if_htrans as ht',function ($join){
    //                     $join->on('h.kode_transaksi_farmasi','=','ht.id_trans')
    //                         ->where('ht.active',1);
    //                 })
    //                 // ->join('if_kartu_stok_tipe_transaksi as tp',[['tp.kode_tipe_transaksi','=','h.tipe_transaksi'],['tp.active','=',1]])
    //                 ->join('if_kartu_stok_tipe_transaksi as tp',function ($join){
    //                     $join->on('tp.kode_tipe_transaksi','=','h.tipe_transaksi')
    //                         ->where('tp.active',1);
    //                 })
    //                 ->join('if_kartu_stok_status_transaksi as st','st.kode_status_transaksi','=','h.status_transaksi')
    //                 // ->leftJoin('if_kartu_stok_jenis_transaksi_centra as jc',[['jc.kode_jenis_transaksi','=','h.jenis_transaksi_centra'],['jc.active','=',1]])
    //                 ->leftJoin('if_kartu_stok_jenis_transaksi_centra as jc',function ($join){
    //                     $join->on('jc.kode_jenis_transaksi','=','h.jenis_transaksi_centra')
    //                         ->where('jc.active',1);
    //                 })
    //                 ->join('if_users_api as u','u.id','=','h.inputby')
    //                 ->join('if_mlayanan as l','l.kode_mutasi','=','h.kdmut')
    //                 ->select('h.id_transaksi','h.tgl_proses_kartu as tgl_mutasi','h.kdmut as kode_unit','l.LAYANAN as unit','h.mutasi',
    //                     DB::raw("isnull(h.kode_transaksi_farmasi,'') as kode_transaksi_farmasi"),
    //                     DB::raw("isnull(h.nodoc_centra_non_penjualan,'') as no_doc_centra"),
    //                     DB::raw("isnull(h.tipe_transaksi,0) as kode_tipe_transaksi"),
    //                     DB::raw("isnull(tp.tipe_transaksi,'') as tipe_transaksi"),
    //                     DB::raw("isnull(h.jenis_transaksi_centra,0) as kode_jenis_transaksi_centra"),
    //                     DB::raw("isnull(jc.jenis_transaksi_centra,'') as jenis_transaksi_centra"),
    //                     DB::raw("isnull(h.status_transaksi,0) as kode_status_transaksi"),
    //                     DB::raw("isnull(st.kode_status_transaksi,'') as status_transaksi"),
    //                     DB::raw("case when convert(varchar,h.tgl_proses_kartu,23) = convert(varchar,getdate(),23) then 'Draft' else 'Completed' end as status_editing"))
    //                 ->where('h.active','=',1)
    //                 ->where('h.kdmut','=',$depo)
    //                 ->where(DB::raw('convert(date,h.tgl_proses_kartu)'),'>=',Carbon::parse($tgl1)->format('Y-m-d'))
    //                 ->where(DB::raw('convert(date,h.tgl_proses_kartu)'),'<=',Carbon::parse($tgl2)->format('Y-m-d'))
    //                 ->orderBy('id_transaksi')
    //                 ->paginate(20);

    //     return response()->json([
    //         'code' => 200,
    //         'status' => 'success',
    //         'message' => 'data transaksi kartu stok',
    //         'data' => $transaksi,
    //         ]);
    // }

    // public function rincianTransaksiFarmasi(Request $request){
    //     $id_transaksi = $request->get('id_transaksi');

    //     $data = DB::table('if_htrans as h')
    //                 ->join('if_trans as t','h.id_trans','=','t.id_trans')
    //                 ->join('if_mbrg_gd as b','t.kdbrg','=','b.kdbrg')
    //                 ->select('h.id_trans as id_transaksi','t.kdbrg as kode_barang_phc',DB::raw("isnull(b.kdbrg_centra,'') as kode_barang_centra"),
    //                     't.no','t.id',DB::raw("case when t.id=0 then 'racikan' else 'non racikan' end as jenis"),
    //                     DB::raw("case isnull(h.mutasi,'') when 'I' then isnull(kt.mnama,'') when 'O' then case when h.kdot<='400' then 'RESEP' else ot.mnama end else '' end as jenis_transaksi"),
    //                     'b.nmbrg as obat','t.jumlah as qty')
    //                 ->where('h.active','=',1)
    //                 ->where('h.id_trans','=',$id_transaksi)
    //                 ->orderBy(['no','id'])
    //                 ->get();

    //     return response()->json([
    //         'code' => 200,
    //         'status' => 'success',
    //         'message' => 'data rincian transaksi',
    //         'data' => $data,]
    //     );
    // }

    public function saveRegistrasiBarang(Request $request){
        $code = 401;
        $message = '';
        $status = 'error';
        $kdmut = $request->kdmut;
        $kdbrg = $request->kdbrg;
        $idlemari = $request->idlemari;
        $stok = $request->stok;
        $device_info = $request->device_info;
        $cek_valid_data = 0;

        $user = Auth::user();

        try{
            DB::beginTransaction();
            if($kdmut==''){
                throw new \Exception('kode mutasi tidak boleh kosong');
            }else{
                $cek_valid_data = DB::table('if_mlayanan')
                                ->where('kode_mutasi',$kdmut)
                                ->where('active',1)
                                ->count();
                if($cek_valid_data==0){
                    throw new \Exception('kode mutasi tidak ada dalam sistem');
                }else{
                    if($idlemari==0){
                        throw new \Exception('rak obat tidak boleh kosong');
                    }
                    $cek_valid_data = DB::table('if_mlemari')
                                ->where('kdmut',$kdmut)
                                ->where('idlemari',$idlemari)
                                ->where('aktif',1)
                                ->count();
                    if($cek_valid_data==0){
                        throw new \Exception('kode rak tidak ada pada depo ini');
                    }else{
                        $cek_valid_data = DB::table('if_mbrg as a')
                                            ->join('if_mlayanan as b','a.tipeif','=','b.idlayanan')
                                            ->where('a.active',1)
                                            ->where('b.active',1)
                                            ->where('b.kode_mutasi',$kdmut)
                                            ->where('a.kdbrg',$kdbrg)
                                            ->count();
                        if($cek_valid_data==0){
                            throw new \Exception('obat tidak terdaftar pada layanan ini');
                        }else{
                            $cek_valid_data = DB::table('if_mbrg as a')
                                            ->join('if_mlayanan as b','a.tipeif','=','b.idlayanan')
                                            ->where('a.active',1)
                                            ->where('b.active',1)
                                            ->where('b.kode_mutasi',$kdmut)
                                            ->where('a.kdbrg',$kdbrg)
                                            ->where(DB::raw("isnull(a.brgak,0)"),$stok)
                                            ->count();
                            if($cek_valid_data==0){
                                throw new \Exception('stok tidak sesuai dengan data aktual');
                            }else{
                                $kartu = new KartuBarang;
                                $kartu->kdmut = $kdmut;
                                $kartu->kdbrg = $kdbrg;
                                $kartu->id_lemari = $idlemari;
                                $kartu->awal = (int)$stok;
                                $kartu->masuk = 0;
                                $kartu->keluar = 0;
                                $kartu->active = 1;
                                if($kartu->save()){
                                    DB::commit();
                                    $code = 200;
                                    $status = 'success';
                                    $message = 'registrasi obat berhasil';
                                }else{
                                    DB::rollBack();
                                    $message = 'ada kesalahan proses penyimpanan';
                                }
                            }
                        }
                    }
                }
            }
        }catch(\Exception $ex){
            DB::rollBack();
            $message = $ex->getMessage();
        }

        $this->logUser($user->id,'/master','registrasi obat kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }

    public function cancelProsesKartuStok(Request $request){
        $kode_transaksi = $request->kode_transaksi;
        $device_info = $request->device_info;

        $user = Auth::user();
        DB::update('update if_htrans set sedang_proses_kartu_stok=0 where id_trans=?', [$kode_transaksi]);
        
        $this->logUser($user->id,'/transaksi','cancel proses kartu stok',json_encode($request->all()),'batal pemrosesan berhasil',$device_info);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'batal pemrosesan berhasil',
            'data' => null,       
        ]);
    }

    public function cekExistingTransaksiKartuStok($kode_transaksi_kartu_stok){
        $kode_transaksi = $kode_transaksi_kartu_stok;
        
        $code = 401;
        $status = 'error';
        $message = '';
        $jumlah_data = DB::table('if_kartu_stok_htransaksi')
                ->where('id_transaksi',$kode_transaksi)
                // ->where(DB::raw("convert(date,tgl_proses_kartu)"),'>=',date('Y-m-d'))
                ->where('active',1)
                ->count();
        if($jumlah_data>0){
            $jumlah_data = DB::table('if_kartu_stok_htransaksi')
                        ->where('id_transaksi',$kode_transaksi)
                        ->where(DB::raw("convert(date,tgl_proses_kartu)"),'>=',date('Y-m-d'))
                        ->where('active',1)
                        ->count();
            if($jumlah_data){
                $status = 'success';
                $code = 200;
            }else{
                $message = 'transaksi tidak dapat dihapus. Status transaksi : completed';
            }
        }else{
            $message = 'transaksi tidak ada dalam sistem';
        }

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => null,       
        ]);
    }

    // public function cekExistingTransaksiKartuStok2($kode_transaksi_kartu_stok){
    //     $kode_transaksi = $kode_transaksi_kartu_stok;
        
    //     $status = 'error';
        
    //     $jumlah_data = DB::table('if_kartu_stok_htransaksi')
    //             ->where('id_transaksi',$kode_transaksi)
    //             // ->where(DB::raw("convert(date,tgl_proses_kartu)"),'>=',date('Y-m-d'))
    //             ->where('active',1)
    //             ->count();
    //     if($jumlah_data>0){
    //         $jumlah_data = DB::table('if_kartu_stok_htransaksi')
    //                     ->where('id_transaksi',$kode_transaksi)
    //                     ->where(DB::raw("convert(date,tgl_proses_kartu)"),'>=',date('Y-m-d'))
    //                     ->where('active',1)
    //                     ->count();
    //         if($jumlah_data){
    //             $status = 'success';
    //         }
    //     }

    //     return $status;
    // }

    public function hapusTransaksiKartuStok(Request $request){
        $kode_transaksi = $request->kode_transaksi_kartu_stok;
        $device_info = $request->device_info;
        $code = 401;
        $message = '';
        $status = 'error';
        $user = Auth::user();
        $cek_data = $this->cekExistingTransaksiKartuStok($kode_transaksi);
        
        $cek_data = $cek_data->getContent();
        $cek_data = json_decode($cek_data);
        
        if($cek_data->status == 'success'){
            try{
                DB::beginTransaction();
                $kode_transaksi_farmasi = DB::table('if_kartu_stok_htransaksi')
                                        ->where('id_transaksi',$kode_transaksi)
                                        ->value(DB::raw("isnull(kode_transaksi_farmasi,'') as kode_transaksi_farmasi"));

                $ref_id_transaksi_kartu_stok = DB::table('if_kartu_stok_htransaksi')
                                            ->where(DB::raw("isnull(ref_id_transaksi,0)"),$kode_transaksi)
                                            ->value('id_transaksi');
                
                if($kode_transaksi_farmasi!=''){
                    DB::update('update if_htrans set proses_kartu_stok = 0 where id_trans=?', [$kode_transaksi_farmasi]);
                }

                $mutasi = DB::table('if_kartu_stok_htransaksi')
                        ->where('id_transaksi',$kode_transaksi)
                        ->value('mutasi');
                
                $rincian_transaksi = DB::table('if_kartu_stok_dtransaksi')
                                    ->where('id_transaksi_kartu_stok',$kode_transaksi)
                                    ->select('id_kartu_stok','qty')
                                    ->get();

                DB::update('update if_kartu_stok_htransaksi set active=0,deleteby=?,deletedate=? where id_transaksi=?',
                        [$user->id,date('Y-m-d H:m:s'),$kode_transaksi]);

                DB::update('update if_kartu_stok_dtransaksi set active=0,deleteby=:user_delete,deletedate=:tgl_proses where id_transaksi_kartu_stok=:id_transaksi', 
                        ['user_delete' => $user->id,'tgl_proses' => date('Y-m-d H:m:s'),'id_transaksi' => $kode_transaksi]);
                
                foreach($rincian_transaksi as $rincian){
                    $id_kartu_stok = $rincian->id_kartu_stok;
                    $qty = $rincian->qty;
                    $kartu = KartuBarang::find($id_kartu_stok);
                    if($mutasi=='I'){
                        $kartu->MASUK = $kartu->MASUK - (int)($qty);
                    }else{
                        $kartu->KELUAR = $kartu->KELUAR - (int)($qty);
                    }
                    $kartu->ACTIVE = 1;
                    $kartu->save();
                }

                if($ref_id_transaksi_kartu_stok!=''){
                    // mutasi masuk transaksi transfer stok
                    $mutasi = DB::table('if_kartu_stok_htransaksi')
                            ->where('id_transaksi',$ref_id_transaksi_kartu_stok)
                            ->value('mutasi');
                
                    $rincian_transaksi = DB::table('if_kartu_stok_dtransaksi')
                                    ->where('id_transaksi_kartu_stok',$ref_id_transaksi_kartu_stok)
                                    ->select('id_kartu_stok','qty')
                                    ->get();

                    DB::update('update if_kartu_stok_htransaksi set active=0,deleteby=:user_delete,deletedate=:tgl_proses 
                            where id_transaksi=:id_transaksi', 
                            ['user_delete' => $user->id,'tgl_proses' => date('Y-m-d H:m:s'),'id_transaksi' => $ref_id_transaksi_kartu_stok]);
                    DB::update('update if_kartu_stok_dtransaksi set active=0,deleteby=:user_delete,deletedate=:tgl_proses 
                            where id_transaksi_kartu_stok=:id_transaksi', 
                            ['user_delete' => $user->id,'tgl_proses' => date('Y-m-d H:m:s'),'id_transaksi' => $ref_id_transaksi_kartu_stok]);
                    
                    foreach($rincian_transaksi as $rincian){
                        $id_kartu_stok = $rincian->id_kartu_stok;
                        $qty = $rincian->qty;
                        $kartu = KartuBarang::find($id_kartu_stok);
                        if($mutasi=='I'){
                            $kartu->MASUK = $kartu->MASUK - (int)($qty);
                        }else{
                            $kartu->KELUAR = $kartu->KELUAR - (int)($qty);
                        }
                        $kartu->ACTIVE = 1;
                        $kartu->save();
                    }
                }

                DB::commit();
                $code = 200;
                $message = 'hapus transaksi berhasil';
                $status = 'success';
            }catch(\Exception $ex){
                $message = $ex->getMessage();
                DB::rollBack();
            }
        }else{
            $message = $cek_data->message;
        }
         
        $this->logUser($user->id,'/transaksi','hapus transaksi kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => null, 
        ]);
    }

    // public function validasi($idtransaksi,$kdmut){
    //     $ada_transaksi=DB::table('if_htrans')->where(['id_transaksi' => $idtransaksi,'active' => 1])->count();
    //     $code = 0;
    //     $message = '';
    //     if ($ada_transaksi>0){
    //         $ada_transaksi = DB::table('if_htrans as h')
    //             ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
    //             ->where('h.id_trans','=',$idtransaksi)
    //             ->where('h.active','=',1)
    //             ->where('l.kode_mutasi','=',$kdmut)
    //             ->count();
    //         if($ada_transaksi>0){
    //             $ada_transaksi = DB::table('if_htrans')
    //                             ->where(['id_transaksi' => $idtransaksi,'active'=>1,'proses_kartu_stok'=>1])
    //                             ->count();
    //             if($ada_transaksi>0){
    //                 $ada_transaksi = DB::table('if_kartu_stok_htransaksi')
    //                                 ->where(['kode_transaksi_farmasi' => $idtransaksi,'active'=>1])
    //                                 ->first();
    //                 if(!empty($ada_transaksi)){
    //                     $message = "transaksi ini telah diproses kartu stok oleh ".
    //                                 $ada_transaksi[0]->MODIBY==null || $ada_transaksi[0]->MODIBY=="" ? $ada_transaksi[0]->INPUTBY : $ada_transaksi[0]->MODIBY. 
    //                                 "(".Carbon::parse($ada_transaksi[0]->TGL_PROSES_KARTU)->format('d-m-Y')." ".
    //                                 Carbon::parse($ada_transaksi[0]->TGL_PROSES_KARTU)->format('H:m:s').")";
    //                 }else{
    //                     $ada_transaksi = DB::table('if_htrans')
    //                                     ->where(['id_trans' => $idtransaksi,'active' => 1,'sedang_proses_kartu_stok' => 1])
    //                                     ->count();
    //                     if($ada_transaksi>0){
    //                         $message = "transaksi ini sedang diproses kartu stok";
    //                     }else{
    //                         DB::update('update if_htrans set sedang_proses_kartu_stok = 1 where id_transaksi = ?', [$idtransaksi]);
    //                         $code = 1;
    //                     }
    //                 }
    //             }
    //         }else{
    //             $message = "transaksi tidak ada pada layanan ini";
    //         }
    //     }else{
    //         $message = "transaksi tidak ditemukan !";
    //     }

    //     return json_encode([
    //         'code' => $code,
    //         'message' => $message,
    //     ]);
    // }

    public function saveTransferStok(Request $request){
        $message = '';
        $status = 'error';
        $code = 401;

        $depo = $request->depo;
        $arr_transaksi = json_decode($request->transaksi,true);

        $user = Auth::user();
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            DB::beginTransaction();
            try{
                $headKeluar = new headerTrans;
                $headKeluar->kdmut = $depo;
                $headKeluar->tgl_proses_kartu = date('Y-m-d H:i:s');
                $headKeluar->mutasi = 'O';
                $headKeluar->status_transaksi = 2; //closed
                $headKeluar->active = 1;
                $headKeluar->inputby = $user->id;
                if($headKeluar->save()){
                    // kirim barang
                    foreach($arr_transaksi as $detil){
                        $barang = explode(' ~ ',$detil['item']);
                        $lemari_asal = explode(' ~ ',$detil['lemari_kirim']);
                        $id_kartu = KartuBarang::where('kdmut',$depo)
                                        ->where('kdbrg',$barang[0])
                                        ->where('active',1)
                                        ->where('id_lemari',$lemari_asal[0])
                                        ->value('id_kartu_stok');
    
                        $master_barang = KartuBarang::find($id_kartu);
                        if(((int)$master_barang->AWAL + (int)$master_barang->MASUK - (int)$master_barang->KELUAR)<(int)$detil['qty']){
                            throw new \Exception('qty barang melebihi stok');
                        }else{
                            $detilKeluar = new detilTrans;
                            $detilKeluar->id_transaksi_kartu_stok = $headKeluar->id_transaksi;
                            
                            $detilKeluar->id_kartu_stok = $id_kartu;
                            $detilKeluar->qty = (int)$detil['qty'];
                            $detilKeluar->active = 1;
                            $detilKeluar->inputby = $user->id;
                            if($detilKeluar->save()){
                                $master_barang->KELUAR = (int)$master_barang->KELUAR + (int)$detil['qty'];
                                $master_barang->save();
                                // return $master_barang;
                                // die();
                            }
                        }
                    }
                    //terima barang
                    $headMasuk = new headerTrans;
                    $headMasuk->kdmut = $depo;
                    $headMasuk->tgl_proses_kartu = date('Y-m-d H:i:s');
                    $headMasuk->mutasi = 'I';
                    $headMasuk->status_transaksi = 2; //closed
                    $headMasuk->active = 1;
                    $headMasuk->inputby = $user->id;
                    $headMasuk->ref_id_transaksi = $headKeluar->id_transaksi;
                    if ($headMasuk->save()){
                        foreach($arr_transaksi as $detil){
                            $barang = explode(' ~ ',$detil['item']);
                            $lemari_tujuan = explode(' ~ ',$detil['lemari_tujuan']);
                            $id_kartu = KartuBarang::where('kdmut',$depo)
                                            ->where('kdbrg',$barang[0])
                                            ->where('active',1)
                                            ->where('id_lemari',$lemari_tujuan[0])
                                            ->value('id_kartu_stok');
                            if ($id_kartu==null){
                                // create master kartu stok
                                $kartu = new KartuBarang;
                                $kartu->kdmut = $depo;
                                $kartu->kdbrg = $barang[0];
                                $kartu->id_lemari = $lemari_tujuan[0];
                                $kartu->awal = 0;
                                $kartu->masuk = 0;
                                $kartu->keluar = 0;
                                $kartu->active = 1;
                                if ($kartu->save()){
                                    $id_kartu = $kartu->ID_KARTU_STOK;
                                }
                            }
                            $master_barang = KartuBarang::find($id_kartu);
                            
                            $detilMasuk = new detilTrans;
                            $detilMasuk->id_transaksi_kartu_stok = $headMasuk->id_transaksi;
                            
                            $detilMasuk->id_kartu_stok = $id_kartu;
                            $detilMasuk->qty = (int)$detil['qty'];
                            $detilMasuk->active = 1;
                            $detilMasuk->inputby = $user->id;
                            if($detilMasuk->save()){
                                $master_barang->MASUK = (int)$master_barang->MASUK + (int)$detil['qty'];
                                $master_barang->save();
                            }
                            
                        }
                    }
                }
                DB::commit();
                $status = "success";
                $message = "transaksi berhasil disimpan";
            }catch(\Exception $ex){
                $message = $ex->getMessage();
                DB::rollback();
            }
        }else{
            $message = "user ini tidak memiliki akses !";
        }

        $this->logUser($user->id,'/transfer-stok','simpan transaksi transfer stok kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => 200,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }

    public function saveMutasiFarmasi(Request $request){
        $message = '';
        $status = 'error';
        $code = 401;

        $depo = $request->depo;
        $id_transaksi = $request->id_transaksi;
        $tgl_transaksi_farmasi = $request->tgl_transaksi_farmasi;
        $jenis_transaksi = $request->jenis_transaksi; // 1 : resep ; 2 : retur
        $mutasi = $request->mutasi;
        $arr_transaksi = json_decode($request->transaksi,true);
        $status_valid = true;

        // return $arr_transaksi;

        $user = Auth::user();
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            // validasi data
            $id_kartu = '';
            if ($id_transaksi=='' || $id_transaksi==null){
                $message = 'tidak ada data yang diproses';
                $status_valid = false;
            }else{
                foreach($arr_transaksi as $detil){
                    $kode_brg = $detil['kode_brg_phc'];
                    $lemari = $detil['lemari'];
                    $jumlah = (int)$detil['jumlah'];
                    
                    if($lemari==''){
                        $message = 'Rak obat atas kode '.$kode_brg.' belum ditentukan';
                        $status_valid = false;
                        break;
                    }
                    $cek_lemari = DB::table('if_kartu_stok_barang as a')
                            ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                            ->where('a.kdmut',$depo)
                            ->where('a.kdbrg',$kode_brg)
                            ->where('b.nmlemari',$lemari)
                            ->where('a.active',1)
                            ->where('b.aktif',1)
                            ->count();
                    if($cek_lemari==0){
                        $message = 'kode barang '.$kode_brg.' tidak ada pada rak '.$lemari;
                        $status_valid = false;
                        break;
                    }
                    
                    if($jenis_transaksi==1){
                        $stok = DB::table('if_kartu_stok_barang as a')
                        ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                        ->where('a.kdmut',$depo)
                        ->where('a.kdbrg',$kode_brg)
                        ->where('b.nmlemari',$lemari)
                        ->where('a.active',1)
                        ->where('b.aktif',1)
                        ->value(DB::raw("a.awal+a.masuk-a.keluar as akhir"));        
                        if($jumlah>$stok){
                            $message = 'qty atas kode barang '.$kode_brg.' melebihi stok';
                            $status_valid = false;
                            break;
                        }
                    }
                }

                if($status_valid){
                    DB::beginTransaction();
                    try{
                        $headKeluar = new headerTrans;
                        $headKeluar->kdmut = $depo;
                        $headKeluar->kode_transaksi_farmasi = $id_transaksi;
                        $headKeluar->tgl_transaksi = Carbon::parse($tgl_transaksi_farmasi)->format('Y-m-d');
                        $headKeluar->tgl_proses_kartu = date('Y-m-d H:i:s');
                        $headKeluar->mutasi = $mutasi;
                        $headKeluar->status_transaksi = 2; //closed
                        $headKeluar->tipe_transaksi = $jenis_transaksi;
                        $headKeluar->active = 1;
                        $headKeluar->inputby = $user->id;
                        if($headKeluar->save()){
                            foreach($arr_transaksi as $detil){
                                $id_kartu = DB::table('if_kartu_stok_barang as a')
                                            ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                                            ->where('a.kdmut',$depo)
                                            ->where('a.kdbrg',$detil['kode_brg_phc'])
                                            ->where('b.nmlemari',$detil['lemari'])
                                            ->where('a.active',1)
                                            ->where('b.aktif',1)
                                            ->value('a.id_kartu_stok');
            
                                $master_barang = KartuBarang::find($id_kartu);
                                
                                $detilKeluar = new detilTrans;
                                $detilKeluar->id_transaksi_kartu_stok = $headKeluar->id_transaksi;
                                $detilKeluar->id_kartu_stok = $id_kartu;
                                $detilKeluar->qty = (int)$detil['jumlah'];
                                $detilKeluar->active = 1;
                                $detilKeluar->inputby = $user->id;
                                if($detilKeluar->save()){
                                    if($mutasi=='I'){
                                        $master_barang->MASUK = (int)$master_barang->MASUK + (int)$detil['jumlah'];
                                    }else{
                                        $master_barang->KELUAR = (int)$master_barang->KELUAR + (int)$detil['jumlah'];
                                    }
                                    $master_barang->save();
                                }
                            }
                            DB::update('update if_htrans set sedang_proses_kartu_stok=0,proses_kartu_stok=1 where id_trans=?', [$id_transaksi]);
                        }
                        DB::commit();
                        $status = "success";
                        $message = "transaksi berhasil disimpan";
                    }catch(\Exception $ex){
                        $message = $ex->getMessage();
                        DB::rollback();
                    }
                }

            }
        }else{
            $message = "user ini tidak memiliki akses !";
        }

        $this->logUser($user->id,'/transaksi','simpan transaksi resep kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => 200,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }

    public function saveDKOGudang(Request $request){
        $message = '';
        $status = 'error';
        $code = 401;

        $depo = $request->depo;
        $id_transaksi = $request->id_transaksi;
        $tgl_transaksi_farmasi = $request->tgl_transaksi_dko;
        $mutasi = $request->mutasi;
        $jenis_transaksi = $request->jenis_transaksi;
        $arr_transaksi = json_decode($request->transaksi,true);
        $status_valid = true;
        
        $user = Auth::user();
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            // validasi data
            $id_kartu = '';
            if ($id_transaksi=='' || $id_transaksi==null){
                $message = 'tidak ada data yang diproses';
                $status_valid = false;
            }else{
                foreach($arr_transaksi as $detil){
                    $kode_brg = $detil['kode_barang_phc'];
                    $lemari = $detil['lemari'];
                    $jumlah = (int)$detil['qty'];
                    
                    if($lemari==''){
                        $message = 'Rak obat atas kode '.$kode_brg.' belum ditentukan';
                        $status_valid = false;
                        break;
                    }
                    // cek barang sudah ada rak ato belum
                    $cek_lemari = DB::table('if_kartu_stok_barang as a')
                            ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                            ->where('a.kdmut',$depo)
                            ->where('a.kdbrg',$kode_brg)
                            // ->where('b.nmlemari',$lemari)
                            ->where('a.active',1)
                            ->where('b.aktif',1)
                            ->count();
                    if($cek_lemari>0){
                        $cek_lemari = DB::table('if_kartu_stok_barang as a')
                                    ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                                    ->where('a.kdmut',$depo)
                                    ->where('a.kdbrg',$kode_brg)
                                    ->where('b.nmlemari',$lemari)
                                    ->where('a.active',1)
                                    ->where('b.aktif',1)
                                    ->count();
                        // rak tidak sesuai
                        if ($cek_lemari==0){
                            $message = 'rak atas kode barang '.$kode_brg.' tidak sesuai';
                            $status_valid = false;
                            break;
                        }
                    }
                }

                if($status_valid){
                    DB::beginTransaction();
                    try{
                        $headKeluar = new headerTrans;
                        $headKeluar->kdmut = $depo;
                        $headKeluar->kode_transaksi_farmasi = $id_transaksi;
                        $headKeluar->tgl_transaksi = Carbon::parse($tgl_transaksi_farmasi)->format('Y-m-d');
                        $headKeluar->tgl_proses_kartu = date('Y-m-d H:i:s');
                        $headKeluar->mutasi = $mutasi;
                        $headKeluar->status_transaksi = 2; //closed
                        $headKeluar->tipe_transaksi = $jenis_transaksi;
                        $headKeluar->active = 1;
                        $headKeluar->inputby = $user->id;
                        if($headKeluar->save()){
                            foreach($arr_transaksi as $detil){
                                $id_kartu = DB::table('if_kartu_stok_barang as a')
                                            ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                                            ->where('a.kdmut',$depo)
                                            ->where('a.kdbrg',$detil['kode_barang_phc'])
                                            ->where('b.nmlemari',$detil['lemari'])
                                            ->where('a.active',1)
                                            ->where('b.aktif',1)
                                            ->value('a.id_kartu_stok');

                                if($id_kartu==null){
                                    $idlemari = DB::table('if_mlemari')
                                                ->where('nmlemari',$detil['lemari'])
                                                ->where('aktif',1)
                                                ->where('kdmut',$depo)
                                                ->value('idlemari');
                                    if($idlemari==null){
                                        throw new \Exception('id rak tidak ditemukan');
                                    }

                                    $kartu = new KartuBarang;
                                    $kartu->kdmut = $depo;
                                    $kartu->kdbrg = $detil['kode_barang_phc'];
                                    $kartu->id_lemari = $idlemari;
                                    $kartu->awal = 0;
                                    $kartu->masuk = 0;
                                    $kartu->keluar = 0;
                                    $kartu->active = 1;
                                    if ($kartu->save()){
                                        $id_kartu = $kartu->ID_KARTU_STOK;
                                    }
                                }
            
                                $master_barang = KartuBarang::find($id_kartu);
                                
                                $detilMasuk = new detilTrans;
                                $detilMasuk->id_transaksi_kartu_stok = $headKeluar->id_transaksi;
                                $detilMasuk->id_kartu_stok = $id_kartu;
                                $detilMasuk->qty = (int)$detil['qty'];
                                $detilMasuk->active = 1;
                                $detilMasuk->inputby = $user->id;
                                if($detilMasuk->save()){
                                    $master_barang->MASUK = (int)$master_barang->MASUK + (int)$detil['qty'];
                                    $master_barang->save();
                                }
                            }
                            DB::update('update if_htrans set sedang_proses_kartu_stok=0,proses_kartu_stok=1 where id_trans=?', [$id_transaksi]);
                        }
                        DB::commit();
                        $status = "success";
                        $message = "transaksi berhasil disimpan";
                    }catch(\Exception $ex){
                        $message = $ex->getMessage();
                        DB::rollback();
                    }
                }

            }
        }else{
            $message = "user ini tidak memiliki akses !";
        }

        $this->logUser($user->id,'/transaksi','simpan transaksi DKO kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => 200,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }

    public function savePembelianLangsung(Request $request){
        $message = '';
        $status = 'error';
        $code = 401;

        $depo = $request->depo;
        $id_transaksi = $request->id_transaksi;
        $tgl_transaksi_farmasi = $request->tgl_transaksi;
        $mutasi = $request->mutasi;
        $jenis_transaksi = $request->jenis_transaksi;
        $arr_transaksi = json_decode($request->transaksi,true);
        $status_valid = true;
        
        $user = Auth::user();
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            // validasi data
            $id_kartu = '';
            if ($id_transaksi=='' || $id_transaksi==null){
                $message = 'tidak ada data yang diproses';
                $status_valid = false;
            }else{
                foreach($arr_transaksi as $detil){
                    $kode_brg = $detil['kode_barang_phc'];
                    $lemari = $detil['lemari'];
                    $jumlah = (int)$detil['qty'];
                    
                    if($lemari==''){
                        $message = 'Rak obat atas kode '.$kode_brg.' belum ditentukan';
                        $status_valid = false;
                        break;
                    }
                    // cek barang sudah ada rak ato belum
                    $cek_lemari = DB::table('if_kartu_stok_barang as a')
                            ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                            ->where('a.kdmut',$depo)
                            ->where('a.kdbrg',$kode_brg)
                            // ->where('b.nmlemari',$lemari)
                            ->where('a.active',1)
                            ->where('b.aktif',1)
                            ->count();
                    if($cek_lemari>0){
                        $cek_lemari = DB::table('if_kartu_stok_barang as a')
                                    ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                                    ->where('a.kdmut',$depo)
                                    ->where('a.kdbrg',$kode_brg)
                                    ->where('b.nmlemari',$lemari)
                                    ->where('a.active',1)
                                    ->where('b.aktif',1)
                                    ->count();
                        // rak tidak sesuai
                        if ($cek_lemari==0){
                            $message = 'rak atas kode barang '.$kode_brg.' tidak sesuai';
                            $status_valid = false;
                            break;
                        }
                    }
                }

                if($status_valid){
                    DB::beginTransaction();
                    try{
                        $headKeluar = new headerTrans;
                        $headKeluar->kdmut = $depo;
                        $headKeluar->kode_transaksi_farmasi = $id_transaksi;
                        $headKeluar->tgl_transaksi = Carbon::parse($tgl_transaksi_farmasi)->format('Y-m-d');
                        $headKeluar->tgl_proses_kartu = date('Y-m-d H:i:s');
                        $headKeluar->mutasi = $mutasi;
                        $headKeluar->status_transaksi = 2; //closed
                        $headKeluar->tipe_transaksi = $jenis_transaksi;
                        $headKeluar->active = 1;
                        $headKeluar->inputby = $user->id;
                        if($headKeluar->save()){
                            foreach($arr_transaksi as $detil){
                                $id_kartu = DB::table('if_kartu_stok_barang as a')
                                            ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                                            ->where('a.kdmut',$depo)
                                            ->where('a.kdbrg',$detil['kode_barang_phc'])
                                            ->where('b.nmlemari',$detil['lemari'])
                                            ->where('a.active',1)
                                            ->where('b.aktif',1)
                                            ->value('a.id_kartu_stok');

                                if($id_kartu==null){
                                    $idlemari = DB::table('if_mlemari')
                                                ->where('nmlemari',$detil['lemari'])
                                                ->where('aktif',1)
                                                ->where('kdmut',$depo)
                                                ->value('idlemari');
                                    if($idlemari==null){
                                        throw new \Exception('id rak tidak ditemukan');
                                    }

                                    $kartu = new KartuBarang;
                                    $kartu->kdmut = $depo;
                                    $kartu->kdbrg = $detil['kode_barang_phc'];
                                    $kartu->id_lemari = $idlemari;
                                    $kartu->awal = 0;
                                    $kartu->masuk = 0;
                                    $kartu->keluar = 0;
                                    $kartu->active = 1;
                                    if ($kartu->save()){
                                        $id_kartu = $kartu->ID_KARTU_STOK;
                                    }
                                }
            
                                $master_barang = KartuBarang::find($id_kartu);
                                
                                $detilMasuk = new detilTrans;
                                $detilMasuk->id_transaksi_kartu_stok = $headKeluar->id_transaksi;
                                $detilMasuk->id_kartu_stok = $id_kartu;
                                $detilMasuk->qty = (int)$detil['qty'];
                                $detilMasuk->active = 1;
                                $detilMasuk->inputby = $user->id;
                                if($detilMasuk->save()){
                                    $master_barang->MASUK = (int)$master_barang->MASUK + (int)$detil['qty'];
                                    $master_barang->save();
                                }
                            }
                            DB::update('update if_htrans set sedang_proses_kartu_stok=0,proses_kartu_stok=1 where id_trans=?', [$id_transaksi]);
                        }
                        DB::commit();
                        $status = "success";
                        $message = "transaksi berhasil disimpan";
                    }catch(\Exception $ex){
                        $message = $ex->getMessage();
                        DB::rollback();
                    }
                }

            }
        }else{
            $message = "user ini tidak memiliki akses !";
        }

        $this->logUser($user->id,'/transaksi','simpan transaksi pembelian langsung kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => 200,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }

    public function saveMutasiAntarUnit(Request $request){
        $message = '';
        $status = 'error';
        $code = 401;

        $depo = $request->depo;
        $id_transaksi = $request->id_transaksi;
        $tgl_transaksi_farmasi = $request->tgl_transaksi_farmasi;
        $jenis_transaksi = $request->jenis_transaksi; // 5 : mutasi masuk antar unit ; 6 : mutasi keluar antar unit
        $mutasi = $request->mutasi;
        $arr_transaksi = json_decode($request->transaksi,true);
        $status_valid = true;

        // return $arr_transaksi;

        $user = Auth::user();
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            // validasi data
            $id_kartu = '';
            if ($id_transaksi=='' || $id_transaksi==null){
                $message = 'tidak ada data yang diproses';
                $status_valid = false;
            }else{
                foreach($arr_transaksi as $detil){
                    $kode_brg = $detil['kode_brg_phc'];
                    $lemari = $detil['lemari'];
                    $jumlah = (int)$detil['jumlah'];
                    
                    if($lemari==''){
                        $message = 'Rak obat atas kode '.$kode_brg.' belum ditentukan';
                        $status_valid = false;
                        break;
                    }
                    $cek_lemari = DB::table('if_kartu_stok_barang as a')
                            ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                            ->where('a.kdmut',$depo)
                            ->where('a.kdbrg',$kode_brg)
                            ->where('b.nmlemari',$lemari)
                            ->where('a.active',1)
                            ->where('b.aktif',1)
                            ->count();
                    if($cek_lemari==0){
                        $message = 'kode barang '.$kode_brg.' tidak ada pada rak '.$lemari;
                        $status_valid = false;
                        break;
                    }
                    
                    if($mutasi=='O'){
                        $stok = DB::table('if_kartu_stok_barang as a')
                            ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                            ->where('a.kdmut',$depo)
                            ->where('a.kdbrg',$kode_brg)
                            ->where('b.nmlemari',$lemari)
                            ->where('a.active',1)
                            ->where('b.aktif',1)
                            ->value(DB::raw("a.awal+a.masuk-a.keluar as akhir"));
                        
                        if($jumlah>$stok){
                            $message = 'qty atas kode barang '.$kode_brg.' melebihi stok';
                            $status_valid = false;
                            break;
                        }
                    }
                }

                if($status_valid){
                    DB::beginTransaction();
                    try{
                        $headKeluar = new headerTrans;
                        $headKeluar->kdmut = $depo;
                        $headKeluar->kode_transaksi_farmasi = $id_transaksi;
                        $headKeluar->tgl_transaksi = Carbon::parse($tgl_transaksi_farmasi)->format('Y-m-d');
                        $headKeluar->tgl_proses_kartu = date('Y-m-d H:i:s');
                        $headKeluar->mutasi = $mutasi;
                        $headKeluar->status_transaksi = 2; //closed
                        $headKeluar->tipe_transaksi = $jenis_transaksi;
                        $headKeluar->active = 1;
                        $headKeluar->inputby = $user->id;
                        if($headKeluar->save()){
                            foreach($arr_transaksi as $detil){
                                $id_kartu = DB::table('if_kartu_stok_barang as a')
                                            ->join('if_mlemari as b','b.idlemari','=','a.id_lemari')
                                            ->where('a.kdmut',$depo)
                                            ->where('a.kdbrg',$detil['kode_brg_phc'])
                                            ->where('b.nmlemari',$detil['lemari'])
                                            ->where('a.active',1)
                                            ->where('b.aktif',1)
                                            ->value('a.id_kartu_stok');
            
                                $master_barang = KartuBarang::find($id_kartu);
                                
                                $detilKeluar = new detilTrans;
                                $detilKeluar->id_transaksi_kartu_stok = $headKeluar->id_transaksi;
                                $detilKeluar->id_kartu_stok = $id_kartu;
                                $detilKeluar->qty = (int)$detil['jumlah'];
                                $detilKeluar->active = 1;
                                $detilKeluar->inputby = $user->id;
                                if($detilKeluar->save()){
                                    if($mutasi=='I'){
                                        $master_barang->MASUK = (int)$master_barang->MASUK + (int)$detil['jumlah'];
                                    }else{
                                        $master_barang->KELUAR = (int)$master_barang->KELUAR + (int)$detil['jumlah'];
                                    }
                                    $master_barang->save();
                                }
                            }
                            DB::update('update if_htrans set sedang_proses_kartu_stok=0,proses_kartu_stok=1 where id_trans=?', [$id_transaksi]);
                        }
                        DB::commit();
                        $status = "success";
                        $message = "transaksi berhasil disimpan";
                    }catch(\Exception $ex){
                        $message = $ex->getMessage();
                        DB::rollback();
                    }
                }

            }
        }else{
            $message = "user ini tidak memiliki akses !";
        }

        $this->logUser($user->id,'/transaksi','simpan transaksi mutasi antar unit kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => 200,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }
}