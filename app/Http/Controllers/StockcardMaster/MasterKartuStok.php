<?php

namespace App\Http\Controllers\StockcardMaster;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class MasterKartuStok extends Controller
{
    // public function listMenuNavigation(){
    //     $menu = DB::table('if_kartu_stok_menu_aplikasi')
    //             ->select('id_menu','menu as title','parent_menu','icon','route','is_auth','jenis_menu')
    //             ->where('active','=',1)
    //             ->get();
    //     return response()->json([
    //         'code' => 200,
    //         'status' => 'success',
    //         'message' => 'data navigation',
    //         'data' => $menu,
    //     ]);
    // }

    public function listMenuNavigationAkses(Request $request){
        $akses = $request->akses;
        $menu1 = DB::table('if_kartu_stok_menu_aplikasi')
                ->select('id_menu','menu as title',DB::raw("isnull(parent_menu,0) as parent_menu"),
                'icon','route','is_auth','jenis_menu',DB::raw("'' as sub_menu"))
                ->where('active','=',1)
                ->where(function ($query){
                    $query->where('jenis_menu',1)
                    ->orWhere('route','/');
                });
        $menu = DB::table('if_kartu_stok_menu_aplikasi as s')
                ->join('if_kartu_stok_akses_menu as m','m.id_menu','=','s.id_menu')
                ->where('s.active',1)
                ->where(DB::raw("isnull(s.parent_menu,0)"),0)
                ->where('m.user_akses',$akses)
                ->select('s.id_menu','s.menu as title',DB::raw("isnull(s.parent_menu,0) as parent_menu"),
                    DB::raw("isnull(icon,'') as icon"),'s.route','s.is_auth','s.jenis_menu',DB::raw("'' as sub_menu"))
                ->unionAll($menu1)
                ->orderBy('id_menu')
                ->get();
        $submenu = DB::table('if_kartu_stok_menu_aplikasi as s')
                ->join('if_kartu_stok_akses_menu as m','m.id_menu','=','s.id_menu')
                ->where('s.active',1)
                ->where(DB::raw("isnull(s.parent_menu,0)"),'!=',0)
                ->where('m.user_akses',$akses)
                ->select('s.id_menu','s.menu as title',DB::raw("isnull(s.parent_menu,0) as parent_menu"),
                    DB::raw("isnull(icon,'') as icon"),'s.route','s.is_auth','s.jenis_menu')
                ->orderBy('id_menu')
                ->get();
        
        $collect_submenu = collect($submenu);
        foreach($menu as $menu_app){
            $menu_detil = $collect_submenu->where('parent_menu',$menu_app->id_menu)->values(
                ["id_menu","title","parent_menu","icon","route","is_auth","jenis_menu"]);
            $menu_app->sub_menu = $menu_detil;
        }
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data akses navigation',
            'data' => $menu,
        ]);
    }

    public function listJenisTransaksiCentra(){
        $data = DB::table('if_kartu_stok_jenis_transaksi_centra')
                ->select('kode_jenis_transaksi as kode','jenis_transaksi_centra as jenis_transaksi')
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data master jenis transaksi centra',
            'data' => $data]
        );
    }

    public function listDepo(){
        $data = DB::table('if_mlayanan')
                ->select('idlayanan as id','kode_mutasi as kode_mutasi',DB::raw("(case when idlayanan=1 then 'Depo Pusat' else layanan end) as layanan"))
                ->where('idlayanan','<>','2')
                ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data master depo',
            'data' => $data,
        ]);
    }

    public function listBarangPerDepo(Request $request){
        $depo = $request->get('depo');
        $nama_barang = $request->get('nama_barang');

        $data = DB::table('if_kartu_stok_barang as k')
                ->join('if_mbrg_gd as b','k.kdbrg','=','b.kdbrg')
                ->join('if_mlemari as l','l.idlemari','=','k.id_lemari')
                ->join('if_mlayanan as ly','ly.kode_mutasi','=','l.kdmut')
                ->select("k.KDMUT as kode_unit","ly.LAYANAN as unit","k.kdbrg as kode_barang_phc",DB::raw("isnull(b.kdbrg_centra,'') as kode_barang_centra"),
                        "b.nmbrg as nama_barang","b.kemasan","b.isi","k.id_kartu_stok","k.id_lemari","l.nmlemari as nama_lemari",
                        "k.awal as stok_awal","k.masuk","k.keluar",DB::raw("k.awal+k.masuk-k.keluar as akhir"))
                ->where('k.active','=',1)
                ->where('b.active','=',1)
                ->where('l.aktif','=',1)
                ->where('k.kdmut','=',$depo)
                ->where('b.nmbrg', 'like','%'.$nama_barang.'%')
                ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data master barang',
            'data' => $data,
        ]);
    }

    public function topObat(Request $request){
        // left join if_kartu_stok_dtransaksi as t on t.id_kartu_stok=k.id_kartu_stok and t.active=1 
        // left join if_kartu_stok_htransaksi as h on h.id_transaksi=t.id_transaksi_kartu_stok and h.active=1
        $kdmut = $request->kdmut;

        // $data = DB::table('if_htrans as h')
        //         ->join('if_trans as t','h.id_trans','=','t.id_trans')
        //         ->join('if_mlayanan as x','x.idlayanan','=','h.tipeif')
        //         ->join('if_kartu_stok_barang as k',function ($join){
        //             $join->on('k.kdbrg','=','t.kdbrg')
        //                 ->on('k.kdmut','=','x.kode_mutasi');
        //         })
        //         ->join('if_mbrg_gd as b','b.kdbrg','=','t.kdbrg')
        //         ->join('if_mlemari as l',function ($join){
        //             $join->on('l.idlemari','=','k.id_lemari')
        //                 ->on('l.kdmut','=','k.kdmut');
        //         })
        //         ->where('b.active','=',1)
        //         ->where('l.aktif','=',1)
        //         ->where('h.active','=',1)
        //         ->where('h.kdot','<=','400')
        //         ->where('h.mutasi','=','O')
        //         ->where('h.kddeb','!=','')
        //         ->where('x.kode_mutasi','=',$kdmut)
        //         ->whereBetween(DB::raw("convert(date,h.tgl)"),[Carbon::now()->subMonth()->format('Y-m-d'),
        //             Carbon::now()->format('Y-m-d')])
        //         ->groupBy('k.id_kartu_stok','l.idlemari','l.nmlemari','x.kode_mutasi','x.layanan','b.kdbrg',
        //             DB::raw("isnull(b.kdbrg_centra,'')"),
        //             'b.nmbrg','k.awal','k.masuk','k.keluar',DB::raw('k.awal+k.masuk-k.keluar'))
        //         // ->groupBy('k.id_kartu_stok')
        //         ->select('k.id_kartu_stok','l.idlemari','l.nmlemari','x.kode_mutasi','x.layanan','b.kdbrg',
        //             DB::raw("isnull(b.kdbrg_centra,'') as kode_barang_centra"),'b.nmbrg as nama_barang',
        //             'k.awal','k.masuk','k.keluar',DB::raw("k.awal+k.masuk-k.keluar as akhir"),DB::raw("count(*) as jumlah_transaksi"))
        //         // ->select('k.id_kartu_stok',DB::raw("count(*) as jumlah_transaksi"))
        //         ->orderByDesc('jumlah_transaksi')
        //         ->orderBy('b.kdbrg')
        //         ->limit(24)
        //         ->get();
        
        $data = DB::select('exec if_sp_top_obat :kdmut,:tgl1,:tgl2', 
                ['kdmut' => $kdmut,'tgl1' => Carbon::now()->subMonth()->format('Y-m-d'),
                'tgl2' => Carbon::now()->format('Y-m-d')]);

        // $data = DB::select(DB::raw("select top 24 k.id_kartu_stok,l.idlemari as id_lemari,
        // l.nmlemari as lemari,x.kode_mutasi as kode_unit,x.layanan as unit,b.kdbrg as kode_barang_phc,
        // isnull(b.kdbrg_centra,'') as kode_barang_centra,b.nmbrg nama_barang,
        // k.awal,k.masuk,k.keluar,k.awal+k.masuk-k.keluar as akhir,count(*) as jumlah_transaksi 
        // from if_htrans h inner join if_trans t on h.id_trans=t.id_trans inner join if_mlayanan x on x.idlayanan=h.tipeif 
        // inner join if_kartu_stok_barang as k on k.kdbrg=t.kdbrg and k.kdmut=x.kode_mutasi 
        // inner join if_mbrg_gd as b on b.kdbrg=t.kdbrg 
        // inner join if_mlemari as l on l.idlemari=k.id_lemari and l.kdmut=k.kdmut 
        // where b.active=1 AND k.active=1 AND l.aktif=1 and h.active=1 and h.kdot<='400' and h.mutasi='O' and h.kddeb<>'' 
        // and x.kode_mutasi=:kdmut and convert(date,h.tgl) between :tgl1 and :tgl2 
        // group by k.id_kartu_stok,l.idlemari,l.nmlemari,x.kode_mutasi,x.layanan,b.kdbrg,isnull(b.kdbrg_centra,''),
        // isnull(b.kdbrg_centra,''),b.nmbrg,k.awal,k.masuk,k.keluar,k.awal+k.masuk-k.keluar 
        // order by jumlah_transaksi desc,b.kdbrg"),
        // ['kdmut' => $kdmut,
        // 'tgl1' => Carbon::now()->subMonth()->format('Y-m-d'),
        // 'tgl2' => Carbon::now()->format('Y-m-d')]);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data obat fast moving',
            'data' => $data,
        ]);
    }

    public function ObatAll(Request $request){
        $kdmut = $request->kdmut;
        $data = DB::table('if_kartu_stok_barang as k')
                ->join('if_mbrg_gd as b','k.kdbrg','=','b.kdbrg')
                ->join('if_mlemari as l','k.id_lemari','=','l.idlemari')
                ->where('l.kdmut','=',$kdmut)
                ->where('l.aktif','=',1)
                ->select('k.id_kartu_stok','k.kdmut as kode_unit','l.idlemari as kode_lemari','l.nmlemari as lemari','k.kdbrg as kode_brg_phc',
                DB::raw("isnull(b.kdbrg_centra,'') as kode_brg_centra"),'b.nmbrg as nama_barang','k.tgl_so',
                'k.awal','k.masuk','k.keluar',DB::raw('k.awal+k.masuk-k.keluar as akhir'))
                ->orderBy('b.nmbrg')
                ->paginate(24);
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data semua obat',
            'data' => $data,
        ]);
    }

    public function ObatKosongRak(Request $request){
        $kdmut = $request->kdmut;
        $data = DB::table('if_mbrg as a')
                ->join('if_mbrg_gd as b','a.kdbrg','=','b.kdbrg')
                ->leftJoin('if_kartu_stok_barang as c',function ($join) use($kdmut){
                    $join->on('c.kdbrg','=','b.kdbrg')
                        ->where('c.active',1)
                        ->where('c.kdmut',$kdmut);
                })
                ->join('if_mlayanan as d','d.idlayanan','=','a.tipeif')
                ->join('if_mjenis as e','b.jenis','=','e.kdjenis')
                ->join('if_mgrjenis as f','e.grup','=','f.kode')
                ->where('a.active','=',1)
                ->where('b.active','=',1)
                ->where('d.kode_mutasi','=',$kdmut)
                ->where(DB::raw("isnull(a.brgak,0)"),'>',0)
                ->where(DB::raw("isnull(c.kdbrg,'')"),'=','')
                ->select('b.kdbrg as kode_brg_phc',DB::raw("isnull(b.kdbrg_centra,'') as kode_brg_centra"),
                'b.nmbrg as nama_brg','b.kemasan','f.nama2 as jenis',DB::raw("isnull(a.brgak,0) as stok"))
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data obat tanpa rak',
            'data' => $data,
        ]);
    }

    public function infoObatKartuStok($id){
        $data = DB::table('if_kartu_stok_barang as k')
                    ->join('if_mbrg_gd as b','k.kdbrg','=','b.kdbrg')
                    ->join('if_mlemari as l','l.idlemari','=','k.id_lemari')
                    ->join('if_mjenis as j','j.kdjenis','=','b.jenis')
                    ->join('if_mgrjenis as gj','gj.kode','=','j.grup')
                    ->leftJoin('if_mbrg_pbk as mp','mp.kdbrg','=','b.kdbrg')
                    ->leftJoin('if_mpabrik as p','p.kdpabrik','=','mp.kdpabrik')
                    ->where('k.id_kartu_stok','=',$id)
                    ->where('b.active','=',1)
                    ->where('k.active','=',1)
                    ->where('l.aktif','=',1)
                    ->select('k.id_kartu_stok','k.kdbrg as kode_brg_phc',
                    DB::raw("l.idlemari as id_lemari,l.nmlemari as lemari,isnull(b.kdbrg_centra,'') as kode_brg_centra"),
                    'b.nmbrg as nama_barang','j.kdjenis as kode_jenis','j.nmjenis as jenis','gj.nama2 as grup',
                    DB::raw("isnull(p.kdpabrik,'') as kode_pabrik,isnull(p.pabrik,'') as pabrik"),
                    'k.tgl_so','k.awal','k.masuk','k.keluar',DB::raw('k.awal+k.masuk-k.keluar as akhir'))
                    ->first();
        $data_collection = collect($data);
        $data_collection['supplier'] = DB::table('if_kartu_stok_barang as k')
                    ->join('if_mbrg_gd as b','k.kdbrg','=','b.kdbrg')
                    ->leftJoin('if_mbrg_supp as ms','ms.kdbrg','=','b.kdbrg')
                    ->leftJoin('if_msupp as s','s.kdsupp','=','ms.supp')
                    ->where('k.id_kartu_stok','=',$id)
                    ->where('b.active','=',1)
                    ->where(DB::raw("isnull(s.kdsupp,'')"),'<>',"''")
                    ->select(DB::raw("isnull(s.kdsupp,'') as kode_supplier,isnull(s.nmsupp,'') as supplier"))
                    ->get();
        $data_collection['kandungan'] = DB::table('if_kartu_stok_barang as k')
                    ->join('if_mbrg_gd as b','k.kdbrg','=','b.kdbrg')
                    ->join('if_mbahan_terapi_obat as mto','mto.kdbrg','=','b.kdbrg')
                    ->join('if_mbhntrp_obat as bo','bo.urut','=','mto.urut_bahan_terapi')
                    ->leftJoin('if_mbahan_obat as bhn','bhn.kdbhn','=','bo.kdbhn')
                    ->leftJoin('if_mtrp_obat as trp','trp.kdtrp','=','bo.kdtrp')
                    ->where('k.id_kartu_stok','=',$id)
                    ->where('b.active','=',1)
                    ->select('mto.urut_bahan_terapi as kode_terapi_bahan','bo.kdtrp as kode_terapi',
                    'trp.nmtrp as terapi','bo.kdbhn as kode_bahan','bhn.nmbhn as bahan',
                    DB::raw("concat(convert(float,bo.jml_dosis),' ',bo.sat_dosis,' ',lower(bo.sediaan)) as dosis"))
                    ->get();
            $data_collection['fungsi'] = DB::table('if_kartu_stok_barang as k')
                    ->join('if_mbrg_gd as b','k.kdbrg','=','b.kdbrg')
                    ->join('if_mbahan_terapi_obat as mto','mto.kdbrg','=','b.kdbrg')
                    ->join('if_mbhntrp_obat as bo','bo.urut','=','mto.urut_bahan_terapi')
                    ->join('if_mbahan_fungsi as f','f.kdbhn','=','bo.kdbhn')
                    ->where('k.id_kartu_stok','=',$id)
                    ->where('b.active','=',1)
                    ->select('f.kdbhn as kode','f.fungsi')
                    ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data info obat kartu stok',
            'data' => $data_collection,
        ]);
    }

    public function SearchObat(Request $request){
        $kdmut = $request->kdmut;
        $keyword = $request->keyword;
        $data = DB::table('if_kartu_stok_barang as k')
                ->join('if_mbrg_gd as b','k.kdbrg','=','b.kdbrg')
                ->join('if_mlemari as l','k.id_lemari','=','l.idlemari')
                ->where('l.kdmut','=',$kdmut)
                ->where('l.aktif','=',1)
                ->where(function ($query) use ($keyword){
                    $query->where('b.kdbrg','like','%'.$keyword.'%')
                            ->orWhere('b.nmbrg','like','%'.$keyword.'%');
                })
                ->select('k.id_kartu_stok','k.kdmut as kode_unit','l.idlemari as kode_lemari','l.nmlemari as lemari','k.kdbrg as kode_brg_phc',
                DB::raw("isnull(b.kdbrg_centra,'') as kode_brg_centra"),'b.nmbrg as nama_barang','k.tgl_so',
                'k.awal','k.masuk','k.keluar',DB::raw('k.awal+k.masuk-k.keluar as akhir'))
                ->orderBy('b.nmbrg')
                ->paginate(24);
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data pencarian obat',
            'data' => $data,
        ]);
    }

    public function listBarangDepoAuth(Request $request){
        $depo = $request->depo;
        // $nama_barang = $request->get('nama_barang');
        $user = Auth::user();
        $data = null;
        $code = 401;
        $message = '';
        $status = 'error';
        if($user && $user->id != 353){
            $data = DB::table('if_kartu_stok_barang as k')
                        ->join('if_mbrg_gd as b','k.kdbrg','=','b.kdbrg')
                        ->join('if_mjenis as j','j.kdjenis','=','b.jenis')
                        ->join('if_mgrjenis as gj','j.grup','=','gj.kode')
                        ->join('if_mlemari as l','l.idlemari','=','k.id_lemari')
                        ->join('if_mlayanan as ly','ly.kode_mutasi','=','l.kdmut')
                        ->select("k.KDMUT as kode_unit","ly.LAYANAN as unit","k.id_kartu_stok","k.kdbrg as kode_obat_phc",DB::raw("isnull(b.kdbrg_centra,'') as kode_obat_centra"),
                                "b.nmbrg as nama_obat","b.kemasan","b.isi","j.nmjenis as jenis","gj.nama2 as grup","k.id_kartu_stok","k.id_lemari","l.nmlemari as nama_lemari",
                                "k.awal as stok_awal","k.masuk","k.keluar",DB::raw("k.awal+k.masuk-k.keluar as akhir"))
                        ->where('k.active','=',1)
                        ->where('b.active','=',1)
                        ->where('l.aktif','=',1)
                        ->where('k.kdmut','=',$depo)
                        ->get();
            $message = 'data list obat';
            $code = 200;
            $status = 'success';
        }else{
            $message = 'tidak ada authentikasi user';
        }

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function listMasterBarangPerDepo(Request $request){
        $depo = $request->get('depo');
        $nama_barang = $request->get('nama_barang');

        $data = DB::table('if_kartu_stok_barang as k')
                ->join('if_mbrg_gd as b','k.kdbrg','=','b.kdbrg')
                ->select("k.kdbrg as kode_barang","b.nmbrg as nama_barang")
                ->where('k.active','=',1)
                ->where('b.active','=',1)
                ->where('k.kdmut','=',$depo)
                ->where(function ($query) use ($nama_barang){
                    $query->where('b.nmbrg', 'like','%'.$nama_barang.'%')
                        ->orWhere('b.kdbrg', 'like','%'.$nama_barang.'%');
                })
                ->groupBy('k.kdbrg','b.nmbrg')
                ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data master barang per depo',
            'data' => $data,
        ]);
    }
}