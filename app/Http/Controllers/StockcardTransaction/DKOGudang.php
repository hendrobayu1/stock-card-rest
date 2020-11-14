<?php

namespace App\Http\Controllers\StockcardTransaction;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\LogKartuStok;

class DKOGudang extends Controller
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

    public function listDKOGudang(Request $request){
        $depo = $request->get('depo');
        $tgl1 = $request->get('tgl1');
        $tgl2 = $request->get('tgl2');

        $user = Auth::user();
        $transaksi = null;
        $code = 401;
        $message = '';
        $status = 'error';
        if($user && $user->id != 353){
            $transaksi = DB::table('if_htrans as a')
                    ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                    ->where('a.active',1)
                    ->where('d.active',1)
                    ->where('d.kode_mutasi',$depo)
                    ->where('a.mutasi','=','I')
                    ->where('a.kdin','=','901')
                    ->where(DB::raw("isnull(a.proses_kartu_stok,0)"),'=',0)
                    ->whereBetween(DB::raw("convert(date,a.tgl)"),[Carbon::parse($tgl1)->format('Y-m-d'),Carbon::parse($tgl2)->format('Y-m-d')])
                    ->select('a.id_trans as id_transaksi',DB::raw("convert(date,a.tgl) as tgl"),'a.jam','a.nomor','a.inputby as petugas',DB::raw("'' as details"))
                    ->orderBy('tgl')
                    ->orderBy('nomor')
                    ->get();

            // $detils = DB::table('if_htrans as a')
            //             ->join('if_trans as b','a.id_trans','=','b.id_trans')
            //             ->join('if_mbrg_gd as c','b.kdbrg','=','c.kdbrg')
            //             ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
            //             ->where('a.active',1)
            //             ->where('c.active',1)
            //             ->where('d.active',1)
            //             ->where('d.kode_mutasi',$depo)
            //             ->where('a.mutasi','=','I')
            //             ->where('a.kdin','=','901')
            //             ->whereBetween(DB::raw("convert(date,a.tgl)"),[Carbon::parse($tgl1)->format('Y-m-d'),Carbon::parse($tgl2)->format('Y-m-d')])
            //             ->select('h.id_trans as id_transaksi','b.kdbrg as kode_barang_phc',DB::raw("isnull(c.kdbrg_centra,'') as kode_barang_centra"),
            //                 'b.no','c.nmbrg as obat','b.jumlah as qty',DB::raw("'' as lemari"))
            //             ->orderBy('id_transaksi')
            //             ->orderBy('no')
            //             ->get();

            // $collect_details = collect($detils);
            // foreach($transaksi as $trans){
            //     $rincian_per_transaksi = $collect_details->where('id_transaksi',$trans->id_transaksi)->values(
            //         ["id_transaksi","kode_barang_phc","kode_barang_centra","no","obat","qty","lemari"]);
            //     $trans->details = $rincian_per_transaksi;
            // }
            $code = 200;
            $status = 'success';
            $message = 'data DKO gudang';
        }else{
            $message = 'user tidak memiliki akses';
        }

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $transaksi
        ]);
    }

    public function cariDKOperKode(Request $request){
        $depo = $request->depo;
        $kode_transaksi = $request->kode_transaksi;
        $nomor_dko = $request->nomor_dko;
        $tgl_transaksi = $request->tgl_dko;

        $user = Auth::user();
        $data = null;
        $code = 401;
        $message = '';
        $status = 'error';
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            $jumlah_data = 0;

            if($kode_transaksi!=''){
                //cek kode transaksi
                $jumlah_data = DB::table('if_htrans as a')
                            ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                            ->where('a.active',1)
                            ->where('d.active',1)
                            ->where('d.kode_mutasi',$depo)
                            ->where('a.mutasi','=','I')
                            ->where('a.kdin','=','901')
                            ->where('a.id_trans','=',$kode_transaksi)
                            ->count();

                if($jumlah_data>0){
                    //cek existing data
                    $jumlah_data = DB::table('if_htrans as a')
                                ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                                ->where('a.active',1)
                                ->where('d.active',1)
                                ->where('d.kode_mutasi',$depo)
                                ->where('a.mutasi','=','I')
                                ->where('a.kdin','=','901')
                                ->where('a.id_trans','=',$kode_transaksi)
                                ->whereRaw("isnull(a.sedang_proses_kartu_stok,0) = 1")
                                ->count();

                    if($jumlah_data>0){
                        $message = 'DKO ini sedang proses kartu stok';
                    }else{
                        $jumlah_data = DB::table('if_htrans as a')
                                    ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                                    ->where('a.active',1)
                                    ->where('d.active',1)
                                    ->where('d.kode_mutasi',$depo)
                                    ->where('a.mutasi','=','I')
                                    ->where('a.kdin','=','901')
                                    ->where('a.id_trans','=',$kode_transaksi)
                                    ->whereRaw("isnull(a.proses_kartu_stok,0) = 1")
                                    ->count();
                        if($jumlah_data>0){
                            $message = 'DKO ini telah diproses kartu stok';
                        }
                    }
                }else{
                    $message = 'transaksi DKO tidak ditemukan';
                }
            }else{
                //cek nomor & tgl DKO
                $jumlah_data =  DB::table('if_htrans as a')
                            ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                            ->where('a.active',1)
                            ->where('d.active',1)
                            ->where('d.kode_mutasi',$depo)
                            ->where('a.mutasi','=','I')
                            ->where('a.kdin','=','901')
                            ->where('a.nomor','=',$nomor_dko)
                            ->where(DB::raw("convert(date,a.tgl)"),'=',Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                            ->count();

                if($jumlah_data>0){
                    //cek existing DKO

                    $jumlah_data = DB::table('if_htrans as a')
                            ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                            ->where('a.active',1)
                            ->where('d.active',1)
                            ->where('d.kode_mutasi',$depo)
                            ->where('a.mutasi','=','I')
                            ->where('a.kdin','=','901')
                            ->where('a.nomor','=',$nomor_dko)
                            ->where(DB::raw("convert(date,a.tgl)"),'=',Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                            ->whereRaw("isnull(a.sedang_proses_kartu_stok,0) = 1")
                            ->count();
                    if($jumlah_data>0){
                        $message = 'DKO ini sedang proses kartu stok';
                    }else{
                        $jumlah_data = DB::table('if_htrans as a')
                                    ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                                    ->where('a.active',1)
                                    ->where('d.active',1)
                                    ->where('d.kode_mutasi',$depo)
                                    ->where('a.mutasi','=','I')
                                    ->where('a.kdin','=','901')
                                    ->where('a.nomor','=',$nomor_dko)
                                    ->where(DB::raw("convert(date,a.tgl)"),'=',Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                                    ->whereRaw("isnull(a.proses_kartu_stok,0) = 1")
                                    ->count();
                        if($jumlah_data>0){
                            $message = 'DKO ini telah diproses kartu stok';
                        }
                    }
                }else{
                    $message = 'transaksi DKO tidak ditemukan';
                }
            }

            if($message==''){
                if($kode_transaksi!=''){
                    //cari berdasarkan kode transaksi
                    $data = DB::table('if_htrans as a')
                            ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                            ->where('a.active',1)
                            ->where('d.active',1)
                            ->where('d.kode_mutasi',$depo)
                            ->where('a.mutasi','=','I')
                            ->where('a.kdin','=','901')
                            ->where('a.id_trans','=',$kode_transaksi)
                            ->select('a.id_trans as id_transaksi',DB::raw("convert(date,a.tgl) as tgl"),'a.jam','a.nomor','a.inputby as user_proses',DB::raw("'' as details"))
                            ->first();
                }else{
                    //cari berdasarkan nomor & tgl transaksi
                    $data = DB::table('if_htrans as a')
                            ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                            ->where('a.active',1)
                            ->where('d.active',1)
                            ->where('d.kode_mutasi',$depo)
                            ->where('a.mutasi','=','I')
                            ->where('a.kdin','=','901')
                            ->where('a.nomor','=',$nomor_dko)
                            ->where(DB::raw("convert(date,a.tgl)"),'=',Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                            ->select('a.id_trans as id_transaksi',DB::raw("convert(date,a.tgl) as tgl"),'a.jam','a.nomor','a.inputby as user_proses',DB::raw("'' as details"))
                            ->first();
                }

                $data_kode_transaksi = $data->id_transaksi;

                DB::update('update if_htrans set sedang_proses_kartu_stok=1 where id_trans=?', [$data_kode_transaksi]);

                $rincian = DB::table('if_htrans as a')
                            ->join('if_trans as b','a.id_trans','=','b.id_trans')
                            ->join('if_mbrg_gd as c','b.kdbrg','=','c.kdbrg')
                            ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                            ->where('a.active',1)
                            ->where('c.active',1)
                            ->where('d.active',1)
                            ->where('d.kode_mutasi',$depo)
                            ->where('a.mutasi','=','I')
                            ->where('a.kdin','=','901')
                            ->where('a.id_trans','=',$data_kode_transaksi)
                            ->select('a.id_trans as id_transaksi','b.kdbrg as kode_barang_phc',DB::raw("isnull(c.kdbrg_centra,'') as kode_barang_centra"),
                                'b.no','c.nmbrg as obat','b.jumlah as qty',DB::raw("'-' as lemari"))
                            ->orderBy('id_transaksi')
                            ->orderBy('no')
                            ->get();
                foreach($rincian as $rinci){
                    if($rinci->kode_barang_phc!=null){
                        $lemari_name = DB::table('if_kartu_stok_barang as b')
                                        ->join('if_mlemari as l',function ($join){
                                            $join->on('l.idlemari','=','b.id_lemari')
                                                ->on('b.kdmut','=','l.kdmut');
                                        })
                                        ->where('b.kdbrg',$rinci->kode_barang_phc)
                                        ->where('b.kdmut',$depo)
                                        ->where('b.active',1)
                                        ->where('l.aktif',1)
                                        ->value('l.nmlemari');

                        if($lemari_name!=null && $lemari_name!=''){
                            $rinci->lemari = $lemari_name;
                        }
                    }
                }

                $data->rincian = $rincian;

                $message = 'data transaksi DKO';
                $code = 200;
                $status = 'success';

            }
        }else{
            $message = 'user tidak memiliki akses';
        }

        $this->logUser($user->id,'/transaksi','proses DKO kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
