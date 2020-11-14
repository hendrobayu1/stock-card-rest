<?php

namespace App\Http\Controllers\StockcardMaster;
use App\Http\Controllers\Controller;

use App\Lemari;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\LogKartuStok;

class LemariController extends Controller
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

    public function lemariAll(Request $request){
        $data = Lemari::where('kdmut',$request->kdmut)
                ->where('aktif',1)
                ->orderBy('nmlemari')->paginate(24);
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data lemari',
            'data' => $data,
        ]);
    }

    public function lemariAllPage(Request $request){
        $data = Lemari::where('kdmut',$request->kdmut)
                ->where('aktif',1)
                ->orderBy('idlemari')
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data semua lemari',
            'data' => $data,
        ]);
    }

    public function randomData(Request $request){
        $kdmut = $request->kdmut;
        $data = Lemari::select('*')
                ->inRandomOrder()
                ->where('kdmut','=',$kdmut)
                ->where('aktif','=',1)
                ->limit(24)
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data lemari random',
            'data' => $data,
        ]);
    }

    public function infoLemari($id){
        $data = Lemari::where('idlemari','=',$id)
                ->select('idlemari as id_lemari','nmlemari as lemari','keterangan')
                ->first();
        $data['obat'] = DB::table('if_kartu_stok_barang as k')
                    ->join('if_mbrg_gd as b','k.kdbrg','=','b.kdbrg')
                    ->join('if_mlemari as l','l.idlemari','=','k.id_lemari')
                    ->where('k.id_lemari','=',$id)
                    ->where('b.active','=',1)
                    ->where('k.active','=',1)
                    ->where('l.aktif','=',1)
                    ->select('k.id_kartu_stok','k.kdbrg as kode_brg_phc',
                    DB::raw("l.idlemari as id_lemari,l.nmlemari as lemari,isnull(b.kdbrg_centra,'') as kode_brg_centra"),'b.nmbrg as nama_barang','k.tgl_so',
                    'k.awal','k.masuk','k.keluar',DB::raw('k.awal+k.masuk-k.keluar as akhir'))
                    ->orderBy('b.nmbrg')
                    ->paginate(24);
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data info lemari',
            'data' => $data,
        ]);
    }

    public function listLemariArrayBarangPerDepo(Request $request){
        $kode_brg = $request->kode_brg;
        $kode_depo = $request->kode_depo;

        $data = DB::table('if_kartu_stok_barang as k')
                ->join('if_mlemari as l','k.id_lemari','=','l.idlemari')
                ->whereIn('k.kdbrg',$kode_brg)
                ->where('k.kdmut',$kode_depo)
                ->where('l.aktif',1)
                ->where('k.active',1)
                ->select('k.id_lemari as id','l.nmlemari as lemari',DB::raw("sum(k.awal+k.masuk-k.keluar) as akhir"))
                ->groupBy('k.id_lemari','l.nmlemari')
                ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data list lemari per barang per depo',
            'data' => $data,
        ]);
    }

    public function listLemariPerBarangPerDepo(Request $request){
        $kode_brg = $request->kode_brg;
        $kode_depo = $request->kode_depo;

        $data = DB::table('if_kartu_stok_barang as k')
                ->join('if_mlemari as l','k.id_lemari','=','l.idlemari')
                ->where('k.kdbrg',$kode_brg)
                ->where('k.kdmut',$kode_depo)
                ->where('l.aktif',1)
                ->where('k.active',1)
                ->select('k.id_lemari as id','l.nmlemari as lemari',DB::raw("sum(k.awal+k.masuk-k.keluar) as akhir"))
                ->groupBy('k.id_lemari','l.nmlemari')
                ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data list lemari per barang per depo',
            'data' => $data,
        ]);
    }

    public function listLemariPerPerDepoExcept(Request $request){
        $kode_lemari = $request->kode_lemari;
        $kode_barang = $request->kode_barang;
        $kode_depo = $request->kode_depo;
        
        $data1 = DB::table('if_kartu_stok_barang as k')
                ->join('if_mlemari as l','k.id_lemari','=','l.idlemari')
                ->where('k.id_lemari','!=',$kode_lemari)
                ->where('k.kdmut',$kode_depo)
                ->where('l.aktif',1)
                ->where('k.active',1)
                ->select('k.id_lemari as id','l.nmlemari as lemari',DB::raw('0 as akhir'))
                ->groupBy('k.id_lemari','l.nmlemari');
                //->get();

        $data2 = DB::table('if_kartu_stok_barang as k')
                ->join('if_mlemari as l','k.id_lemari','=','l.idlemari')
                ->where('k.id_lemari','!=',$kode_lemari)
                ->where('k.kdbrg',$kode_barang)
                ->where('k.kdmut',$kode_depo)
                ->where('l.aktif',1)
                ->where('k.active',1)
                ->select('k.id_lemari as id','l.nmlemari as lemari',DB::raw("sum(k.awal+k.masuk-k.keluar) as akhir"))
                ->groupBy('k.id_lemari','l.nmlemari')
                ->unionAll($data1);
                // ->orderBy('id')
                // ->get();
        $data = DB::table(DB::raw("({$data2->toSql()}) as sub"))
                ->mergeBindings($data2)
                ->select('id','lemari',DB::raw("sum(akhir) as akhir"))
                ->groupBy('id','lemari')
                ->get();
                
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data list lemari per barang per depo',
            'data' => $data,
        ]);
    }

    public function saveLemari(Request $request){
        $code = 401;
        $message = '';
        $status = 'error';
        $data = null;
        $lemari = $request->nama_lemari;
        $depo = $request->depo;
        $nama_depo = $request->nama_depo;
        $user = Auth::user();
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            $akses = DB::table('if_muser')
                    ->where('userid','=',$user->userid)
                    ->value('akses');
            if($akses<=1){
                $validasi = Validator::make($request->all(),[
                    'nama_lemari' => 'required',
                ]);
                if ($validasi->fails()){
                    $message = $validasi->errors();
                }else{
                    $jumlah = DB::table('if_mlemari')
                            ->where('nmlemari',$lemari)
                            // ->where('kdmut',$depo)
                            // ->where('aktif',1)
                            ->count();
                    if($jumlah>0){
                        $message = 'nama lemari sudah ada dalam sistem';
                    }else{
                        $idlemari = Lemari::max('idlemari');
                        $lemari_baru = Lemari::create([
                            'idlemari' => $idlemari+1,
                            'kdmut' => $depo,
                            'nmlemari' => $lemari,
                            'keterangan' => $nama_depo,
                            'crtusr' => $user->id,
                            'aktif' => 1,
                        ]);
                        if($lemari_baru){
                            $code = 200;
                            $status = 'success';
                            $data = $lemari_baru;
                            $message = 'simpan data lemari obat berhasil';
                        }else{
                            $message = 'error penyimpanan data lemari obat';
                        }
                    }
                }
            }else{
                $message = 'anda tidak memiliki akses untuk menambahkan data lemari obat';    
            }
        }else{
            $message = 'tidak ada authentikasi user';
        }

        $this->logUser($user->id,'/list-lemari','simpan rak baru kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function updateLemari(Request $request){
        $code = 401;
        $message = '';
        $status = 'error';
        $data = null;
        $id_lemari = $request->id_lemari;
        $lemari = $request->lemari;
        $depo = $request->depo;
        $user = Auth::user();
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            $akses = DB::table('if_muser')
                    ->where('userid','=',$user->userid)
                    ->value('akses');
            if($akses<=1){
                $validasi = Validator::make($request->all(),[
                    'lemari' => 'required',
                ]);
                if ($validasi->fails()){
                    $message = $validasi->errors();
                }else{
                    $jumlah = DB::table('if_mlemari')
                            ->where('nmlemari',$lemari)
                            ->where('idlemari','!=',$id_lemari)
                            ->where('kdmut',$depo)
                            ->where('aktif',1)
                            ->count();
                    if($jumlah>0){
                        $message = 'nama lemari sudah ada dalam sistem';
                    }else{
                        
                        $lemari_update = DB::update('update if_mlemari set nmlemari = :nmlemari,updateusr = :userid where idlemari = :idlemari and kdmut= :kdmut', 
                                    ['nmlemari' => $lemari,'userid'=>$user->id,'idlemari' => $id_lemari,'kdmut' => $depo]);
                        if($lemari_update){
                            $code = 200;
                            $status = 'success';
                            $data = $lemari_update;
                            $message = 'udpate data lemari obat berhasil';
                        }else{
                            $message = 'error update data lemari obat';
                        }
                    }
                }
            }else{
                $message = 'anda tidak memiliki akses untuk menambahkan data lemari obat';    
            }
        }else{
            $message = 'tidak ada authentikasi user';
        }

        $this->logUser($user->id,'/list-lemari','update rak kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function deleteLemari(Request $request){
        $code = 401;
        $message = '';
        $status = 'error';
        $idlemari = $request->idlemari;
        $depo = $request->depo;
        $user = Auth::user();
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            $akses = DB::table('if_muser')
                    ->where('userid','=',$user->userid)
                    ->value('akses');
            if($akses<=1){
                $jumlah = DB::table('if_mlemari')
                        ->where('kdmut','=',$depo)
                        ->where('aktif',1)
                        ->where('idlemari',$idlemari)
                        ->count();
                if($jumlah>0){
                    $jumlah = DB::table('if_kartu_stok_barang as b')
                    ->join('if_mlemari as l','l.idlemari','=','b.id_lemari')
                    ->where('b.kdmut','=',$depo)
                    ->where('l.idlemari','=',$idlemari)
                    ->where('l.aktif',1)
                    ->where('b.active',1)
                    ->where(DB::raw("b.awal+b.masuk-b.keluar"),'>',0)
                    ->count();
                    if($jumlah<=0){
                        DB::update('update if_mlemari set aktif=0,deldt=getdate(),delusr=:user where idlemari=:idlemari and kdmut=:depo', 
                        ['user'=>$user->id,'idlemari'=>$idlemari,'depo'=>$depo]);
                        $code = 200;
                        $status = 'success';
                        $message = 'hapus data lemari berhasil';
                    }else{
                        $message = 'proses hapus data lemari gagal. lemari masih ada stok barang';                    
                    }
                }else{
                    $message = 'data lemari obat tidak ditemukan';
                }
            }else{
                $message = 'anda tidak memiliki akses untuk menghapus data lemari obat';    
            }
        }else{
            $message = 'tidak ada authentikasi user';
        }

        $this->logUser($user->id,'/list-lemari','hapus rak kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }
}
