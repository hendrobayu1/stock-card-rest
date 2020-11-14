<?php

namespace App\Http\Controllers\PharmacyMaster;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
// use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Collection;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Validator;

class MasterSignaController extends Controller
{
    public function getListSigna(Request $request){
        $keyword = $request->keyword;
        $data = DB::table('if_msigna')
                    ->where(DB::raw("isnull(petunjuk,0)"),0)
                    ->where(function ($query) use ($keyword){
                        $query->where('signacepat','like','%'.$keyword.'%')
                            ->orWhere('signa','like','%'.$keyword.'%')
                            ->orWhere('uraian','like','%'.$keyword.'%');
                    })
                    ->select('kdsigna as kode','signacepat as signa_cepat','signa','uraian','perhari',
                        'frek1d','jmlpakai as jumlah','default_igd','skip_jam_udd','signa_syringe_pump')
                    ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data pencarian signa',
            'data' => $data,
        ]);
    }
}
