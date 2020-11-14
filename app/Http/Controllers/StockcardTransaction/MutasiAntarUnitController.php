<?php

namespace App\Http\Controllers\StockcardTransaction;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\LogKartuStok;

class MutasiAntarUnitController extends Controller
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

    public function listTransaksiMutasiFarmasi(Request $request){
        $depo = $request->get('depo');
        $tgl1 = $request->get('tgl1');
        $tgl2 = $request->get('tgl2');
        $jenis_transaksi = $request->get('jenis_transaksi'); //5 : barang masuk dari unit lain - 6 : barang keluar ke unit lain

        $transaksi = DB::table('if_htrans as h')
                    ->leftJoin('if_mketin as kt','h.kdin','=','kt.mkode')
                    ->leftJoin('if_mketot as ot','ot.mkode','=','h.kdot')
                    ->join('if_mlayanan as l','l.idlayanan','=','h.tipeif')
                    ->select('h.id_trans as id_transaksi','h.mutasi',DB::raw("convert(date,h.tgl) as tgl"),'h.jam','h.nomor','h.inputby as petugas',DB::raw("'' as details"),
                        DB::raw("case when h.mutasi='I' then isnull(kt.mnama,'') else isnull(ot.mnama,'') end as keterangan"))
                    ->where('h.active','=',1)
                    ->where('l.kode_mutasi','=',$depo)
                    // ->where('h.kdot','<=','400')
                    // ->where('h.kddeb','!=','')
                    ->where(DB::raw('convert(date,h.tgl)'),'>=',Carbon::parse($tgl1)->format('Y-m-d'))
                    ->where(DB::raw('convert(date,h.tgl)'),'<=',Carbon::parse($tgl2)->format('Y-m-d'))
                    ->where(DB::raw('isnull(h.proses_kartu_stok,0)'),'=',0)
                    ->where(function ($query) use ($jenis_transaksi){
                        if($jenis_transaksi==5){
                            // mutasi masuk antar unit
                            $query->where(DB::raw("isnull(kt.mutasi_antar_unit,0)"),'=',1);
                        }else if($jenis_transaksi==6){
                            // mutasi keluar antar unit
                            $query->where(DB::raw("isnull(ot.mutasi_antar_unit,0)"),'=',1);
                        }
                    })
                    ->orderBy('tgl','asc')
                    ->orderBy('jam','asc')
                    ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data transaksi mutasi antar unit',
            'data' => $transaksi,]
        );
    }

    public function cariTransaksiperKode(Request $request){
        $depo = $request->depo;
        $kode_transaksi = $request->kode_transaksi;
        $nomor_transaksi = $request->nomor_transaksi;
        $tgl_transaksi = $request->tgl_transaksi;
        $jenis_transaksi = $request->jenis_transaksi; // 5 : barang masuk dari unit lain - 6 : barang keluar ke unit lain
        $data = null;
        $code = 401;
        $message = '';
        $status = 'error';
        $jumlah_data = 0;

        $device_info = $request->device_info;
        $user = Auth::user();

        if($kode_transaksi!=''){
            //cek kode transaksi
            $jumlah_data = DB::table('if_htrans as h')
                        ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                        ->leftJoin('if_mketin as kt','h.kdin','=','kt.mkode')
                        ->leftJoin('if_mketot as ot','ot.mkode','=','h.kdot')
                        ->where('l.kode_mutasi',$depo)
                        ->where('h.id_trans',$kode_transaksi)
                        ->where('h.active',1)
                        ->where(function ($query) use ($jenis_transaksi){
                            if($jenis_transaksi==5){
                                // mutasi masuk antar unit
                                $query->where(DB::raw("isnull(kt.mutasi_antar_unit,0)"),'=',1);
                            }else if($jenis_transaksi==6){
                                // mutasi keluar antar unit
                                $query->where(DB::raw("isnull(ot.mutasi_antar_unit,0)"),'=',1);
                            }
                        })
                        ->count();
            if($jumlah_data>0){
                //cek existing data
                $jumlah_data = DB::table('if_htrans as h')
                            ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                            ->where('l.kode_mutasi',$depo)
                            ->where('id_trans',$kode_transaksi)
                            ->where('h.active',1)
                            ->whereRaw("isnull(sedang_proses_kartu_stok,0) = 1")
                            ->count();
                if($jumlah_data>0){
                    $message = 'transaksi ini sedang proses kartu stok';
                }else{
                    $jumlah_data = DB::table('if_htrans as h')
                                ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                                ->where('l.kode_mutasi',$depo)
                                ->where('id_trans',$kode_transaksi)
                                ->where('h.active',1)
                                ->whereRaw("isnull(proses_kartu_stok,0) = 1")
                                ->count();
                    if($jumlah_data>0){
                        $message = 'transaksi ini telah diproses kartu stok';
                    }
                }
            }else{
                $message = 'transaksi tidak ditemukan';
            }
        }else{
            //cek nomor & tgl transaksi
            $jumlah_data = DB::table('if_htrans as h')
                        ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                        ->leftJoin('if_mketin as kt','h.kdin','=','kt.mkode')
                        ->leftJoin('if_mketot as ot','ot.mkode','=','h.kdot')
                        ->where('l.kode_mutasi',$depo)
                        ->where('h.nomor',$nomor_transaksi)
                        ->where(DB::raw("convert(date,tgl)"),Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                        ->where('h.active',1)
                        ->where(function ($query) use ($jenis_transaksi){
                            if($jenis_transaksi==5){
                                // mutasi masuk antar unit
                                $query->where(DB::raw("isnull(kt.mutasi_antar_unit,0)"),'=',1);
                            }else if($jenis_transaksi==6){
                                // mutasi keluar antar unit
                                $query->where(DB::raw("isnull(ot.mutasi_antar_unit,0)"),'=',1);
                            }
                        })
                        ->count();
            if($jumlah_data>0){
                //cek existing resep
                $jumlah_data = DB::table('if_htrans as h')
                        ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                        ->where('l.kode_mutasi',$depo)
                        ->where('h.nomor',$nomor_transaksi)
                        ->where(DB::raw("convert(date,tgl)"),Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                        ->where('h.active',1)
                        ->whereRaw("isnull(sedang_proses_kartu_stok,0) = 1")
                        ->count();
                if($jumlah_data>0){
                    $message = 'transaksi ini sedang proses kartu stok';
                }else{
                    $jumlah_data = DB::table('if_htrans as h')
                                ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                                ->where('l.kode_mutasi',$depo)
                                ->where('h.nomor',$nomor_transaksi)
                                ->where(DB::raw("convert(date,tgl)"),Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                                ->where('h.active',1)
                                ->whereRaw("isnull(proses_kartu_stok,0) = 1")
                                ->count();
                    if($jumlah_data>0){
                        $message = 'transaksi ini telah diproses kartu stok';
                    }
                }
            }else{
                $message = 'transaksi tidak ditemukan';
            }
        }

        if($message==''){
            if($kode_transaksi!=''){
                //cari berdasarkan kode transaksi
                $data = DB::table('if_htrans as h')
                    ->leftJoin('if_mketin as kt','h.kdin','=','kt.mkode')
                    ->leftJoin('if_mketot as ot','ot.mkode','=','h.kdot')
                    ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                    ->where('l.kode_mutasi',$depo)
                    ->where('h.id_trans',$kode_transaksi)
                    ->where('h.active',1)
                    ->where(function ($query) use ($jenis_transaksi){
                        if($jenis_transaksi==5){
                            // mutasi masuk antar unit
                            $query->where(DB::raw("isnull(kt.mutasi_antar_unit,0)"),'=',1);
                        }else if($jenis_transaksi==6){
                            // mutasi keluar antar unit
                            $query->where(DB::raw("isnull(ot.mutasi_antar_unit,0)"),'=',1);
                        }
                    })
                    ->select('h.id_trans as id_transaksi','h.mutasi',DB::raw("convert(date,h.tgl) as tgl"),'h.jam','h.nomor','h.inputby as petugas',
                        DB::raw("case when h.mutasi='I' then isnull(kt.mnama,'') else isnull(ot.mnama,'') end as keterangan"))->first();
            }else{
                //cari berdasarkan nomor & tgl transaksi
                $data = DB::table('if_htrans as h')
                    ->leftJoin('if_mketin as kt','h.kdin','=','kt.mkode')
                    ->leftJoin('if_mketot as ot','ot.mkode','=','h.kdot')
                    ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                    ->where('l.kode_mutasi',$depo)
                    ->where('h.nomor',$nomor_transaksi)
                    ->where(DB::raw("convert(date,h.tgl)"),Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                    ->where('h.active',1)
                    ->where(function ($query) use ($jenis_transaksi){
                        if($jenis_transaksi==5){
                            // mutasi masuk antar unit
                            $query->where(DB::raw("isnull(kt.mutasi_antar_unit,0)"),'=',1);
                        }else if($jenis_transaksi==6){
                            // mutasi keluar antar unit
                            $query->where(DB::raw("isnull(ot.mutasi_antar_unit,0)"),'=',1);
                        }
                    })
                    ->select('h.id_trans as id_transaksi','h.mutasi',DB::raw("convert(date,h.tgl) as tgl"),'h.jam','h.nomor','h.inputby as petugas',
                        DB::raw("case when h.mutasi='I' then isnull(kt.mnama,'') else isnull(ot.mnama,'') end as keterangan"))->first();
            }

            $data_kode_transaksi = $data->id_transaksi;
            $data_tgl_transaksi = $data->tgl;

            DB::update('update if_htrans set sedang_proses_kartu_stok=1 where id_trans=?', [$data_kode_transaksi]);

            $rincian_transaksi = DB::table('if_htrans as h')
                            ->join('if_trans as t','h.id_trans','=','t.id_trans')
                            ->join('if_mbrg_gd as b','b.kdbrg','=','t.kdbrg')
                            ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                            ->where('l.kode_mutasi',$depo)
                            ->where('h.active',1)
                            ->where('h.id_trans',$data_kode_transaksi)
                            ->select('t.kdbrg as kode_brg_phc',DB::raw("isnull(b.kdbrg_centra,'') as kode_centra"),
                                'b.nmbrg as nama_barang',DB::raw("case when h.mutasi='I' then t.jumlah else t.jumlah end as jumlah"),
                                DB::raw("'-' as lemari"))
                            ->get();

            foreach($rincian_transaksi as $rinci){
                if($rinci->kode_brg_phc!=null){
                    $lemari_name = DB::table('if_kartu_stok_barang as b')
                                    ->join('if_mlemari as l',function ($join){
                                        $join->on('l.idlemari','=','b.id_lemari')
                                            ->on('b.kdmut','=','l.kdmut');
                                    })
                                    ->where('b.kdbrg',$rinci->kode_brg_phc)
                                    ->where('b.kdmut',$depo)
                                    ->where('b.active',1)
                                    ->where('l.aktif',1)
                                    ->value('l.nmlemari');

                    if($lemari_name!=null && $lemari_name!=''){
                        $rinci->lemari = $lemari_name;
                    }
                }
            }

            $data->rincian_obat = $rincian_transaksi;
            $message = 'data transaksi mutasi antar unit';
            $code = 200;
            $status = 'success';
        }

        $this->logUser($user->id,'/transaksi','proses mutasi antar unit kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }
}