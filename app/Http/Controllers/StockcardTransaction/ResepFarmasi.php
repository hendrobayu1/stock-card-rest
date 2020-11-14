<?php

namespace App\Http\Controllers\StockcardTransaction;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\LogKartuStok;

class ResepFarmasi extends Controller
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

    public function listTransaksiFarmasi(Request $request){
        $depo = $request->get('depo');
        $tgl1 = $request->get('tgl1');
        $tgl2 = $request->get('tgl2');
        $jenis_transaksi = $request->get('jenis_transaksi'); //1 : resep - 2 : retur

        $transaksi = DB::table('if_htrans as h')
                    ->leftJoin('if_mketin as kt','h.kdin','=','kt.mkode')
                    ->leftJoin('if_mketot as ot','ot.mkode','=','h.kdot')
                    ->join('if_mlayanan as l','l.idlayanan','=','h.tipeif')
                    ->select('h.id_trans as id_transaksi',
                        DB::raw("isnull(h.nota,'') as nota"),
                        'h.nomor','h.antrian as no_antrian',
                        DB::raw('convert(date,h.tgl) as tgl'),'h.jam',
                        DB::raw("case isnull(h.mutasi,'') when 'I' then 'IN' when 'O' then 'OUT' else '' end as mutasi"),
                        DB::raw("case isnull(h.mutasi,'') when 'I' then isnull(kt.mnama,'') when 'O' then case when h.kdot<='400' then 'RESEP' else ot.mnama end else '' end as jenis_transaksi"),
                        'h.norm as rm',DB::raw("rtrim(isnull(h.nmpx,'')) as pasien"),
                        DB::raw("rtrim(isnull(h.kddeb,'')) as kode_debitur"),
                        DB::raw("rtrim(isnull(h.nmdeb,'')) as debitur"),
                        DB::raw("rtrim(isnull(h.kddin,'')) as kode_dinas"),
                        DB::raw("rtrim(isnull(h.nmdin,'')) as dinas"),
                        DB::raw("rtrim(isnull(h.kdklin,'')) as kode_ruangan"),
                        DB::raw("rtrim(isnull(h.nmklin,'')) as ruangan"),
                        DB::raw("rtrim(isnull(h.kddok,'')) as kode_dokter"),
                        DB::raw("rtrim(isnull(h.nmdok,'')) as dokter"),DB::raw("'' as details"))
                    ->where('h.active','=',1)
                    ->where('l.kode_mutasi','=',$depo)
                    ->where('h.kdot','<=','400')
                    ->where('h.kddeb','!=','')
                    ->where(DB::raw('convert(date,h.tgl)'),'>=',Carbon::parse($tgl1)->format('Y-m-d'))
                    ->where(DB::raw('convert(date,h.tgl)'),'<=',Carbon::parse($tgl2)->format('Y-m-d'))
                    ->where(DB::raw('isnull(h.proses_kartu_stok,0)'),'=',0)
                    ->where(function ($query) use ($jenis_transaksi){
                        if($jenis_transaksi==1){
                            // resep
                            $query->where(DB::raw("isnull(h.resepaw,0)"),'=',0);
                        }else if($jenis_transaksi==2){
                            // retur
                            $query->where(DB::raw("isnull(h.resepaw,0)"),'!=',0);
                        }
                    })
                    ->orderBy('tgl','asc')
                    ->orderBy('jam','asc')
                    ->get();
        // $detils = DB::table('if_htrans as h')
        //             ->join('if_trans as t','h.id_trans','=','t.id_trans')
        //             ->join('if_mbrg_gd as b','t.kdbrg','=','b.kdbrg')
        //             ->join('if_mlayanan as l','l.idlayanan','=','h.tipeif')
        //             ->select('h.id_trans as id_transaksi','t.kdbrg as kode_barang_phc',DB::raw("isnull(b.kdbrg_centra,'') as kode_barang_centra"),
        //                 't.no','t.id',DB::raw("case when t.id=0 then 'racikan' else 'non racikan' end as jenis"),
        //                 'b.nmbrg as obat','t.jumlah as qty')
        //             ->where('h.active','=',1)
        //             ->where('l.kode_mutasi','=',$depo)
        //             ->where('h.kdot','<=','400')
        //             ->where('h.kddeb','!=','')
        //             ->where(DB::raw('convert(date,h.tgl)'),'>=',Carbon::parse($tgl1)->format('Y-m-d'))
        //             ->where(DB::raw('convert(date,h.tgl)'),'<=',Carbon::parse($tgl2)->format('Y-m-d'))
        //             ->where(DB::raw('isnull(h.proses_kartu_stok,0)'),'=',0)
        //             ->where(function ($query) use ($jenis_transaksi){
        //                 if($jenis_transaksi==1){
        //                     // resep
        //                     $query->where(DB::raw("isnull(h.resepaw,0)"),'=',0);
        //                 }else if($jenis_transaksi==2){
        //                     // retur
        //                     $query->where(DB::raw("isnull(h.resepaw,0)"),'!=',0);
        //                 }
        //             })
        //             ->orderBy('id_transaksi')
        //             ->orderBy('no')
        //             ->orderBy('id')
        //             ->get();
        
        // // Create collection
        // $collect_details = collect($detils);//new Collection();
        // //End create collection
        // foreach($transaksi as $trans){
        //     $rincian_per_transaksi = $collect_details->where('id_transaksi',$trans->id_transaksi)->values(
        //         ["id_transaksi","kode_barang_phc","kode_barang_centra","no","jenis","obat","qty"]);
        //     $trans->details = $rincian_per_transaksi;
        // }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data transaksi farmasi',
            'data' => $transaksi,]
        );
    }

    public function cariTransaksiperKode(Request $request){
        $depo = $request->depo;
        $kode_transaksi = $request->kode_transaksi;
        $nomor_resep = $request->nomor_resep;
        $tgl_transaksi = $request->tgl_resep;
        $jenis_transaksi = $request->jenis_transaksi; // 1 : resep - 2 : retur
        $data = null;
        $code = 401;
        $message = '';
        $status = 'error';
        $jumlah_data = 0;

        $device_info = $request->device_info;
        $user = Auth::user();

        if($kode_transaksi!=''){
            //cek kode transaksi
            $jumlah_data = DB::table('if_htrans')
                        ->join('if_mlayanan as l','if_htrans.tipeif','=','l.idlayanan')
                        ->where('l.kode_mutasi',$depo)
                        ->where('id_trans',$kode_transaksi)
                        ->where('if_htrans.active',1)
                        ->where(function ($query) use ($jenis_transaksi){
                            if($jenis_transaksi==1){
                                // resep
                                $query->where(DB::raw("isnull(resepaw,0)"),'=',0);
                            }else if($jenis_transaksi==2){
                                // retur
                                $query->where(DB::raw("isnull(resepaw,0)"),'!=',0);
                            }
                        })
                        ->count();
            if($jumlah_data>0){
                //cek existing data
                $jumlah_data = DB::table('if_htrans')
                ->join('if_mlayanan as l','if_htrans.tipeif','=','l.idlayanan')
                ->where('l.kode_mutasi',$depo)
                ->where('id_trans',$kode_transaksi)
                ->where('if_htrans.active',1)
                ->whereRaw("isnull(sedang_proses_kartu_stok,0) = 1")
                ->count();
                if($jumlah_data>0){
                    $message = 'transaksi ini sedang proses kartu stok';
                }else{
                    $jumlah_data = DB::table('if_htrans')
                                ->join('if_mlayanan as l','if_htrans.tipeif','=','l.idlayanan')
                                ->where('l.kode_mutasi',$depo)
                                ->where('id_trans',$kode_transaksi)
                                ->where('if_htrans.active',1)
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
            //cek nomor & tgl resep
            $jumlah_data = DB::table('if_htrans')
                        ->join('if_mlayanan as l','if_htrans.tipeif','=','l.idlayanan')
                        ->where('l.kode_mutasi',$depo)
                        ->where('nomor',$nomor_resep)
                        ->where(DB::raw("convert(date,tgl)"),Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                        ->where('if_htrans.active',1)
                        ->where(function ($query) use ($jenis_transaksi){
                            if($jenis_transaksi==1){
                                // resep
                                $query->where(DB::raw("isnull(resepaw,0)"),'=',0);
                            }else if($jenis_transaksi==2){
                                // retur
                                $query->where(DB::raw("isnull(resepaw,0)"),'!=',0);
                            }
                        })
                        ->count();
            if($jumlah_data>0){
                //cek existing resep
                $jumlah_data = DB::table('if_htrans')
                        ->join('if_mlayanan as l','if_htrans.tipeif','=','l.idlayanan')
                        ->where('l.kode_mutasi',$depo)
                        ->where('nomor',$nomor_resep)
                        ->where(DB::raw("convert(date,tgl)"),Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                        ->where('if_htrans.active',1)
                        ->whereRaw("isnull(sedang_proses_kartu_stok,0) = 1")
                        ->count();
                if($jumlah_data>0){
                    $message = 'transaksi ini sedang proses kartu stok';
                }else{
                    $jumlah_data = DB::table('if_htrans')
                                ->join('if_mlayanan as l','if_htrans.tipeif','=','l.idlayanan')
                                ->where('l.kode_mutasi',$depo)
                                ->where('nomor',$nomor_resep)
                                ->where(DB::raw("convert(date,tgl)"),Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                                ->where('if_htrans.active',1)
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
                    ->join('if_mketot as k','h.kdot','=','k.mkode')
                    ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                    ->where('l.kode_mutasi',$depo)
                    ->where('h.id_trans',$kode_transaksi)
                    ->where('h.active',1)
                    ->where(function ($query) use ($jenis_transaksi){
                        if($jenis_transaksi==1){
                            // resep
                            $query->where(DB::raw("isnull(h.resepaw,0)"),'=',0);
                        }else if($jenis_transaksi==2){
                            // retur
                            $query->where(DB::raw("isnull(h.resepaw,0)"),'!=',0);
                        }
                    })
                    ->select('h.id_trans as id_transaksi','h.nota','h.nomor','h.tgl','h.antrian as no_antrian',
                        DB::raw("isnull(h.norm,'') as rm"),DB::raw("isnull(h.nmpx,'') as nama"),
                        DB::raw("isnull(h.kddeb,'') as kode_debitur"),DB::raw("isnull(h.nmdeb,'') as debitur"),
                        DB::raw("isnull(h.kddin,'') as kode_dinas"),DB::raw("isnull(h.nmdin,'') as dinas"),
                        DB::raw("isnull(h.kdklin,'') as kode_klinik"),DB::raw("isnull(h.nmklin,'') as klinik"),
                        DB::raw("isnull(h.kddok,'') as kode_dokter"),DB::raw("isnull(h.nmdok,'') as dokter"),
                        DB::raw("isnull(h.kdot,'') as kode_jenis_transaksi"),DB::raw("isnull(k.mnama,'') as jenis_transaksi"))->first();
            }else{
                //cari berdasarkan nomor & tgl transaksi
                $data = DB::table('if_htrans as h')
                    ->join('if_mketot as k','h.kdot','=','k.mkode')
                    ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                    ->where('l.kode_mutasi',$depo)
                    ->where('h.nomor',$nomor_resep)
                    ->where(DB::raw("convert(date,h.tgl)"),Carbon::parse($tgl_transaksi)->format('Y-m-d'))
                    ->where('h.active',1)
                    ->where(function ($query) use ($jenis_transaksi){
                        if($jenis_transaksi==1){
                            // resep
                            $query->where(DB::raw("isnull(h.resepaw,0)"),'=',0);
                        }else if($jenis_transaksi==2){
                            // retur
                            $query->where(DB::raw("isnull(h.resepaw,0)"),'!=',0);
                        }
                    })
                    ->select('h.id_trans as id_transaksi','h.nota','h.nomor','h.tgl','h.antrian as no_antrian',
                        DB::raw("isnull(h.norm,'') as rm"),DB::raw("isnull(h.nmpx,'') as nama"),
                        DB::raw("isnull(h.kddeb,'') as kode_debitur"),DB::raw("isnull(h.nmdeb,'') as debitur"),
                        DB::raw("isnull(h.kddin,'') as kode_dinas"),DB::raw("isnull(h.nmdin,'') as dinas"),
                        DB::raw("isnull(h.kdklin,'') as kode_klinik"),DB::raw("isnull(h.nmklin,'') as klinik"),
                        DB::raw("isnull(h.kddok,'') as kode_dokter"),DB::raw("isnull(h.nmdok,'') as dokter"),
                        DB::raw("isnull(h.kdot,'') as kode_jenis_transaksi"),DB::raw("isnull(k.mnama,'') as jenis_transaksi"),
                        DB::raw("case when h.sts<='2' then 'rawat jalan' else 'rawat inap' end as layanan"))->first();
            }

            $data_kode_transaksi = $data->id_transaksi;
            $data_tgl_transaksi = $data->tgl;
            $data_antrian = $data->no_antrian;

            DB::update('update if_htrans set sedang_proses_kartu_stok=1 where id_trans=?', [$data_kode_transaksi]);

            $rincian = DB::table('if_htrans_ol as h')
                        ->join('if_trans_ol as t','h.id_trans','=','t.id_trans')
                        ->join('if_mdosis_ol as d','d.idjenis','=','t.tipe_qty')
                        ->leftJoin('if_mbrg_gd as b','b.kdbrg','=','t.kdbrg')
                        ->where('h.noantrian',$data_antrian)
                        ->where(DB::raw("convert(date,h.tgl)"),Carbon::parse($data_tgl_transaksi)->format('Y-m-d'))
                        ->where('h.active',1)
                        ->where('t.id',0)
                        ->select(DB::raw("'R/' as INFO"),'t.NO','t.KDBRG AS KODE_BRG',
                            DB::raw("isnull(b.kdbrg_centra,'') as KODE_CENTRA"),'T.NAMABRGFULL as NAMA_OBAT','t.signa2 as SIGNA','t.ketqty as KET',
                            DB::raw("case when t.jumlah_seper = '' then convert(varchar,t.jumlah) else t.jumlah_seper end + ' ' + 
                                case when t.tipe_qty = 1 then '' else d.jenis_dosis end JUMLAH"))
                        ->get();
            
            $rincian_racikan = DB::table('if_htrans_ol as h')
                        ->join('if_trans_ol as t','h.id_trans','=','t.id_trans')
                        ->join('if_mdosis_ol as d','d.idjenis','=','t.tipe_qty')
                        ->leftJoin('if_mbrg_gd as b','b.kdbrg','=','t.kdbrg')
                        ->where('h.noantrian',$data_antrian)
                        ->where(DB::raw("convert(date,h.tgl)"),Carbon::parse($data_tgl_transaksi)->format('Y-m-d'))
                        ->where('h.active',1)
                        ->where('t.id','!=',0)
                        ->select('t.NO','t.ID','t.KDBRG AS KODE_BRG',
                            DB::raw("isnull(b.kdbrg_centra,'') as KODE_CENTRA"),'T.NAMABRGFULL as NAMA_OBAT','t.signa2 as SIGNA','t.ketqty as KET',
                            DB::raw("case when t.jumlah_seper = '' then convert(varchar,t.jumlah) else t.jumlah_seper end + ' ' + 
                                case when t.tipe_qty = 1 then '' else d.jenis_dosis end JUMLAH"))
                        ->orderBy('t.NO')
                        ->orderBy('t.ID')
                        ->get();

            $collect_rincian = collect($rincian);

            $tmp_nomor = $collect_rincian->max('NO');
            $inc_data = 0;
            foreach($rincian_racikan as $racikan){
                if($inc_data==0){
                    $tmp_nomor+=1;
                    $collect_rincian->push([
                        'INFO' => 'R/',
                        'NO' => $racikan->NO,
                        'KODE_BRG' => $racikan->KODE_BRG,
                        'KODE_CENTRA' => $racikan->KODE_CENTRA,
                        'NAMA_OBAT' => $racikan->NAMA_OBAT,
                        'SIGNA' => $racikan->SIGNA,
                        'KET' => $racikan->KET,
                        'JUMLAH' => $racikan->JUMLAH,
                    ]);
                    $inc_data+=1;
                }else{
                    $collect_rincian->push([
                        'INFO' => '',
                        'NO' => $racikan->NO,
                        'KODE_BRG' => $racikan->KODE_BRG,
                        'KODE_CENTRA' => $racikan->KODE_CENTRA,
                        'NAMA_OBAT' => $racikan->NAMA_OBAT,
                        'SIGNA' => $racikan->SIGNA,
                        'KET' => $racikan->KET,
                        'JUMLAH' => $racikan->JUMLAH,
                    ]);
                    if($racikan->SIGNA==''){
                        $inc_data+=1;
                    }else{
                        $inc_data=0;
                    }
                }
            }
            
            // $rincian_specified = $collect_rincian->only(['NO','INFO','KODE_BRG','KODE_CENTRA','NAMA_OBAT','SIGNA','KET','JUMLAH']);
            // $rincian_specified = $collect_rincian->sortBy('NO');
            $rincian_specified = $collect_rincian->sortBy('NO');
            $rincian = $rincian_specified->values()->all();

            // $rincian = DB::select('exec if_sp_kartu_stok_resep_tulisan_dokter ?,?',
            //         [$data_antrian,Carbon::parse($data_tgl_transaksi)->format('Y-m-d')]);

            $data->rincian_eresep = $rincian;

            $rincian_resep = DB::table('if_htrans as h')
                            ->join('if_trans as t','h.id_trans','=','t.id_trans')
                            ->join('if_mbrg_gd as b','b.kdbrg','=','t.kdbrg')
                            ->join('if_mlayanan as l','h.tipeif','=','l.idlayanan')
                            ->where('l.kode_mutasi',$depo)
                            ->where('h.active',1)
                            ->where('h.id_trans',$data_kode_transaksi)
                            ->where(function ($query) use ($jenis_transaksi){
                                if($jenis_transaksi==1){
                                    // resep
                                    $query->where(DB::raw("isnull(h.resepaw,0)"),'=',0);
                                }else if($jenis_transaksi==2){
                                    // retur
                                    $query->where(DB::raw("isnull(h.resepaw,0)"),'!=',0);
                                }
                            })
                            ->select('t.kdbrg as kode_brg_phc',DB::raw("isnull(b.kdbrg_centra,'') as kode_centra"),
                                'b.nmbrg as nama_barang',DB::raw("isnull(t.signa2,'') as signa"),
                                DB::raw("case when isnull(h.resepaw,0)=0 then t.jumlah else t.jumlah*-1 end as jumlah"),
                                DB::raw("isnull(t.ketqty,'') as keterangan"),
                                DB::raw("'-' as lemari"))
                            ->get();

            foreach($rincian_resep as $rinci){
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

            $data->rincian_nota_resep = $rincian_resep;
            $message = 'data transaksi resep';
            $code = 200;
            $status = 'success';

        }

        $this->logUser($user->id,'/transaksi','proses resep kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }
}