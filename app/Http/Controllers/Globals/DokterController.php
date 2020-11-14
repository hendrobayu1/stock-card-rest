<?php

namespace App\Http\Controllers\Globals;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DokterController extends Controller
{
    public function getListDokterAll(Request $request){
        $keyword = $request->keyword;
        $data = DB::table('dr_mdokter')
                ->where('stsaktif',1)
                ->where('nmdok','like','%'.$keyword.'%')
                ->select('kddok as kode','nmdok as dokter','tipe_dr','tipe_dr_klinik',
                    DB::raw("isnull(klinik,'') as klinik"),'struktural','organik')
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data pencarian dokter',
            'data' => $data,
        ]);
    }

    public function getListDokterSpesialis(Request $request){
        $keyword = $request->keyword;
        $data = DB::table('dr_mdokter')
                ->where('stsaktif',1)
                ->where('tipe_dr','!=',0)
                ->where('nmdok','like','%'.$keyword.'%')
                ->select('kddok as kode','nmdok as dokter','tipe_dr','tipe_dr_klinik',
                    DB::raw("isnull(klinik,'') as klinik"),'struktural','organik')
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data pencarian dokter spesialis',
            'data' => $data,
        ]);
    }
}
