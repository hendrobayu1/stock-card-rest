<?php

namespace App\Http\Controllers\PharmacyMaster;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
// use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Collection;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Validator;

class MasterBarangController extends Controller
{
    public function getListBarangPerDepo(Request $request){
        $depo = $request->depo;
        $debitur = $request->debitur;
        $keyword = $request->keyword;

        $idunit = DB::table('if_mlayanan')
                    ->where('kode_mutasi',$depo)
                    ->value('unit_id');

        $idlayanan = DB::table('if_mlayanan')
                    ->where('kode_mutasi',$depo)
                    ->value('idlayanan');

        $data_mfla = DB::table('if_msigna')
                    ->where('petunjuk',1)
                    ->where('signa','like','%'.$keyword.'%')
                    ->select('kdsigna as kode',DB::raw("'' as kode_centra"),'signa as nama',
                        DB::raw("'' as jenis"),DB::raw("'' as sediaan"),DB::raw("0 as dosis"),DB::raw("'' as satuan_dosis"),
                        DB::raw("999 as stok"),DB::raw("0 as is_obat"));
        $data_all = DB::table('if_mbrg_gd as a')
                        ->join('if_mbrg as b',function ($join) use ($idlayanan){
                            $join->on('a.kdbrg','=','b.kdbrg')
                                ->where('b.tipeif',$idlayanan);
                        })
                    ->join('if_mlayanan as c','c.idlayanan','=','b.tipeif')
                    ->join('if_mjenis as d','d.kdjenis','=','a.jenis')
                    ->join('if_std_detobat as e','e.kdbrg','=','a.kdbrg')
                    ->join('if_std_obat as f','e.kode','=','f.kode')
                    ->join('rirj_mdebitur as g','f.kode','=','g.std_obat')
                    ->join('if_mgrjenis as h','h.kode','=','d.grup')
                    ->where('a.active',1)
                    ->where('b.active',1)
                    ->where('c.active',1)
                    ->where('c.kode_mutasi',$depo)
                    ->where('c.unit_id',$idunit)
                    ->where('g.kddebt','=',$debitur)
                    ->where('a.nmbrg','like','%'.$keyword.'%')
                    ->select('a.kdbrg as kode',DB::raw("isnull(a.kdbrg_centra,'') as kode_centra"),'a.nmbrg as nama',
                            'h.nama2 as jenis',DB::raw("isnull(a.bentuk,'') as sediaan"),
                            DB::raw("isnull(a.dosis,0) as dosis"),DB::raw("isnull(a.satdosis,'') as satuan_dosis"),
                            'b.brgak as stok',DB::raw("1 as is_obat"))
                    ->unionAll($data_mfla);

        $data = DB::table(DB::raw("({$data_all->toSql()}) as list_barang"))
                    ->mergeBindings($data_all)
                    ->select('kode','kode_centra','nama','jenis','sediaan','dosis','satuan_dosis',DB::raw("sum(stok) as stok"),'is_obat')
                    ->groupBy('kode','kode_centra','nama','jenis','sediaan','dosis','satuan_dosis','is_obat')
                    ->orderBy('kode')
                    ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data pencarian obat per depo per debitur',
            'data' => $data,
        ]);
    }
}