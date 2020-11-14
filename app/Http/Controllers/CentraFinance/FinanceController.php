<?php

namespace App\Http\Controllers\CentraFinance;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
// use Illuminate\Support\Facades\Date;
// use PhpParser\Node\Stmt\Return_;
// use function GuzzleHttp\json_encode;
use App\LogVoidCentra as logVoid;
use App\ApprovalRequest;

class FinanceController extends Controller
{
    public function logCentra($koneksi,$service_name,$parameter,$response_code,$response_message,$user_proses){
        try{
            $url = DB::table('rirj_mglobal')->where('tipe',"URL_CENTRA_PRODUCTION")->value('valstr');
            return DB::connection($koneksi)->statement('exec rirj_setor_keuangan_error_log :url,:service_name,:parameter,:response_code,:response_message,:user_proses',[
                                'url' => $url,'service_name' => $service_name,'parameter' => $parameter,
                                'response_code' => $response_code,'response_message' => $response_message,
                                'user_proses' => $user_proses,
            ]);
        }catch(\Exception $ex){
            return false;
        }
    }

    public function createJurnalKeuangan(Request $request){
        $status = 'error';
        $code = 401;
        $message = '';
        $data = null;
        $doc_no = '';
        $doc_no_billing = '';

        $tgl = $request->tgl;
        $shift_rs = $request->shift;
        $user_transaksi = $request->user_transaksi;
        $debitur = $request->debitur;
        $dinas = $request->dinas;
        $jenis_layanan = $request->jenis_layanan;
        $idunit = $request->idunit;
        $acc_id_bank_tunai = $request->acc_id_bank_tunai; // account id bank untuk transfer tunai klinik satelit
        $kode_bank_tunai = $request->kode_bank_tunai; // kode bank untuk transfer tunai klinik satelit
        $user_proses = $request->user_proses;
        $jenis_tagihan = $request->jenis_tagihan;

        $valid = Validator::make($request->all(),[
            'tgl' => 'required',
            'debitur' => 'required',
            'dinas' => 'required',
            'jenis_layanan' => 'required',
            'idunit' => 'required',
            'user_proses' => 'required',
        ]);

        $validasi_data_pelayanan = [];
        $header_data = [];
        $detil_data = [];
        $data_pasien = [];

        $koneksi = '';
        if($idunit=='001'){
            $koneksi = 'sqlsrv';
        }else if($idunit=='002'){
            $koneksi = 'sqlsrv_perak';
        }
        $layanan = $jenis_layanan<2?'RAWAT JALAN & OBAT':'MEDICAL CEKUP';

        if($valid->fails()){
            $message = $valid->errors();
        }else{
            try{
                // DB::beginTransaction();
                if($idunit=='001'){
                    if($debitur=='999'){
                        if($user_transaksi==''){
                            throw new \Exception('user transksi tidak boleh kosong !');
                        }
                        if($shift_rs==''){
                            throw new \Exception('shift transksi tidak boleh kosong !');
                        }
                    }
                }

                $validasi_data_pelayanan = DB::connection($koneksi)->select('exec rirj_setor_keuangan_validasi_data_pelayanan :tgl,:debitur,:dinas,:jenis_pelayanan,:idunit,:jenis_tagihan',
                                            ['tgl' => Carbon::parse($tgl)->format('Y-m-d'),'debitur' => $debitur,'dinas' => $dinas,'jenis_pelayanan' => $jenis_layanan,'idunit' => $idunit,
                                            'jenis_tagihan' => $jenis_tagihan]);
                $status_bpjs_tk = false;

                if(!empty($validasi_data_pelayanan)){
                    if($debitur=='949'){
                        // BPJS TK
                        $data_pasien = DB::connection($koneksi)->select('exec rirj_setor_keuangan_ambil_rm_pasien :tgl,:debitur,:dinas,:jenis_pelayanan,:idunit,:jenis_tagihan',[
                                        'tgl' => Carbon::parse($tgl)->format('Y-m-d'),'debitur' => $debitur,'dinas' => $dinas,'jenis_pelayanan' => $jenis_layanan,
                                        'idunit' => $idunit,'jenis_tagihan' => $jenis_tagihan,
                            ]);
                        $status_bpjs_tk = true;
                    }

                    $reference = '';
                    $keterangan = '';
                    $info = '';

                    if($jenis_layanan<2){
                        $info = 'RJ&OBAT';
                    }else if($jenis_layanan==2){
                        $info = 'MCU';
                    }

                    if(!$status_bpjs_tk){
                        // NON BPJS TK
                        if($debitur=='999' && $idunit=='001'){
                            $reference = $info.Carbon::parse($tgl)->format('dmY').
                                        str_replace(' ','',$validasi_data_pelayanan[0]->NAMA_DEBITUR).
                                        str_replace(' ','',$validasi_data_pelayanan[0]->NAMA_DINAS).
                                        str_replace(' ','',$validasi_data_pelayanan[0]->NAMA_UNIT).
                                        str_replace(' ','',$user_transaksi);
                            $keterangan = 'PEND.'.$layanan.' '.
                                        Carbon::parse($tgl)->format('d-m-Y').' '.
                                        $validasi_data_pelayanan[0]->NAMA_DEBITUR.' '.
                                        $validasi_data_pelayanan[0]->NAMA_DINAS.' '.
                                        $validasi_data_pelayanan[0]->NAMA_UNIT.' '.
                                        $user_transaksi;
                        }else{
                            $reference = $info.Carbon::parse($tgl)->format('dmY').
                                        str_replace(' ','',$validasi_data_pelayanan[0]->NAMA_DEBITUR).
                                        str_replace(' ','',$validasi_data_pelayanan[0]->NAMA_DINAS).
                                        str_replace(' ','',$validasi_data_pelayanan[0]->NAMA_UNIT);
                            $keterangan = 'PEND.'.$layanan.' '.
                                        Carbon::parse($tgl)->format('d-m-Y').' '.
                                        $validasi_data_pelayanan[0]->NAMA_DEBITUR.' '.
                                        $validasi_data_pelayanan[0]->NAMA_DINAS.' '.
                                        $validasi_data_pelayanan[0]->NAMA_UNIT;
                        }

                        $header_data = DB::connection($koneksi)->select('exec rirj_setor_keuangan_jurnal_template_header :tgl,:debitur,:dinas,:jenis_pelayanan,:idunit,:rm,:jenis_tagihan',[
                                        'tgl' => Carbon::parse($tgl)->format('Y-m-d'),'debitur' => $debitur,'dinas' => $dinas,'jenis_pelayanan' => $jenis_layanan,'idunit' => $idunit,
                                        'rm' => '','jenis_tagihan' => $jenis_tagihan
                        ]);
                        
                        $detil_data = DB::connection($koneksi)->select('exec rirj_setor_keuangan_jurnal_template_rincian :tgl,:debitur,:dinas,
                                        :jenis_pelayanan,:idunit,:rm,:kode_customer,:trading_partner,:acc_id_yma,:acc_id_bank_tunai,:kode_bank_tunai,:jenis_tagihan',[
                                        'tgl' => Carbon::parse($tgl)->format('Y-m-d'),'debitur' => $debitur,'dinas' => $dinas,'jenis_pelayanan' => $jenis_layanan,
                                        'idunit' => $idunit,'rm' => '','kode_customer' => $validasi_data_pelayanan[0]->CUSTOMER_ID,
                                        'trading_partner' => $validasi_data_pelayanan[0]->TRADING_PARTNER_ID,'acc_id_yma' => $validasi_data_pelayanan[0]->ACC_ID,
                                        'acc_id_bank_tunai' => $acc_id_bank_tunai,'kode_bank_tunai' => $kode_bank_tunai,'jenis_tagihan' => $jenis_tagihan
                        ]);

                        // PROSES CENTRA
                        $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                        $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                        $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);

                        if($authLogin['error']==null){
                            
                            $this->logCentra($koneksi,'login-mobile','',200,json_encode($authLogin['response']),$user_proses);

                            $auth = $authLogin['response']->user_token;
                            
                            // CREATE GL
                            $detil_data_collect = collect($detil_data);
                            $debet = $detil_data_collect->sum('debit_src');
                            $kredit = $detil_data_collect->sum('credit_src');
                            $params = [
                                'date_doc' => Carbon::parse($tgl)->format('Y-m-d'),
                                'date_accounted' => date('Y-m-d'),
                                'reference' => $reference,
                                'currency_id' => 'IDR',
                                'currency_rate' => 1,
                                'gl_type' => $debitur=='999'?'JKM':'JR',
                                'debit' => $debet,
                                'credit' => $kredit,
                                'description' => $keterangan,
                                'doc_status' => 'Completed',
                                'generalLedgerLine' => $detil_data,
                            ];

                            // return $params;
                            // die();

                            $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('acc/general-ledger','post',$auth,$params);
                            
                            if($data['error']==null){
                                
                                $this->logCentra($koneksi,'acc/general-ledger',json_encode($params),200,json_encode($data['response']->data),$user_proses);

                                $result = json_encode($data['response']->data);
                                $data = $data['response']->data;
                                $doc_no = json_decode($result)->doc_no;
                                if($debitur=='999'){
                                    // CREATE AR
                                    $detil_billing_data = DB::connection($koneksi)->select('exec rirj_setor_keuangan_jurnal_template_billing_rincian :tgl,:debitur,:dinas,
                                        :jenis_pelayanan,:idunit,:jenis_tagihan',[
                                        'tgl' => Carbon::parse($tgl)->format('Y-m-d'),'debitur' => $debitur,'dinas' => $dinas,'jenis_pelayanan' => $jenis_layanan,
                                        'idunit' => $idunit,'jenis_tagihan'=>$jenis_tagihan,
                                    ]);

                                    $uper_idx = $detil_data_collect->max('line_no');
                                    $params = [
                                        'partner_id' => '1000009999',
                                        'currency_id' => 'IDR',
                                        'currency_rate' => 1,
                                        'reference' => $reference.'-'.$doc_no,
                                        'date_doc' => Carbon::parse($tgl)->format('Y-m-d'),
                                        'date_due' => date('Y-m-d'),
                                        'date_accounted' => date('Y-m-d'),
                                        'description' => $keterangan,
                                        'payment_term' => '',
                                        'grand_total' => 0,
                                        'down_payment' => $debet,
                                        'amount' => $debet,
                                        'content_letter' => '',
                                        'ttd' => '',
                                        'tax' => '',
                                        'doc_status' => 'Completed',
                                        'paymentRequestGLAccount' => $detil_billing_data,
                                        'invoiceLineDp' => array([
                                            'line_no' => 1,
                                            'res_id_line' => $uper_idx,
                                            'doc_ref' => $doc_no,
                                            'amount' => $debet,
                                            'assignment' => $doc_no,
                                            'total_amount' => $debet,
                                            'paymentRequestWithholding' => null,
                                        ]),
                                    ];

                                    // return $params;

                                    $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('acc/billing','post',$auth,$params);
                                    if($data['error']==null){

                                        $this->logCentra($koneksi,'acc/billing',json_encode($params),200,json_encode($data['response']->data),$user_proses);

                                        $result = json_encode($data['response']->data);
                                        $doc_no_billing = json_decode($result)->doc_no;

                                        // $doc_no_billing = $data['response']->data->doc_no;
                                        $code = 200;
                                        $status = 'success';
                                        $data = $data['response']->data;
                                        $message = 'GL : '.$doc_no.' | AR : '.$doc_no_billing; 

                                        try{
                                            DB::beginTransaction();
                                            DB::connection($koneksi)->statement('exec rirj_setor_keuangan_jurnal_update_transaksi :tgl,:debitur,:dinas,
                                                :jenis_pelayanan,:idunit,:user_proses,:rm,:doc_gl,:doc_ar,:jenis_tagihan',[
                                                'tgl' => Carbon::parse($tgl)->format('Y-m-d'),'debitur' => $debitur,'dinas' => $dinas,'jenis_pelayanan' => $jenis_layanan,
                                                'idunit' => $idunit,'user_proses' => $user_proses,'rm' => '','doc_gl' => $doc_no,'doc_ar' => $doc_no_billing,
                                                'jenis_tagihan' => $jenis_tagihan
                                            ]);
                                            DB::commit();
                                        }catch(\Exception $ex){
                                            DB::rollBack();
                                        }
                                    }else{
                                        // ERROR CREATE BILLIING -> GL SUDAH TERCREATE -> VOID GL

                                        $this->logCentra($koneksi,'acc/billing',json_encode($params),$code,json_encode($data['error']),$user_proses);

                                        $message = $data['error'];
                                        $params = [
                                            'id' => $doc_no,
                                            'note' => 'AR tidak tercreate',
                                        ];
                                        $data_void = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('acc/general-ledger/void','post',$auth,$params);
                                        if($data_void['error']==null){
                                            // VOID GL BERHASIL -> SIMPAN LOG
                                            $this->logCentra($koneksi,'acc/general-ledger/void',json_encode($params),200,json_encode($data['response']),$user_proses);
                                        }else{
                                            // GAGAL VOID GL, SIMPAN LOG
                                            $this->logCentra($koneksi,'acc/general-ledger/void',json_encode($params),$code,json_encode($data['error']),$user_proses);
                                        }
                                    }
                                }else{
                                    $code = 200;
                                    $status = 'success';
                                    $message = 'nomor dokumen : '.$doc_no;

                                    try{
                                        DB::beginTransaction();
                                        DB::connection($koneksi)->statement('exec rirj_setor_keuangan_jurnal_update_transaksi :tgl,:debitur,:dinas,
                                            :jenis_pelayanan,:idunit,:user_proses,:rm,:doc_gl,:doc_ar,:jenis_tagihan',[
                                            'tgl' => Carbon::parse($tgl)->format('Y-m-d'),'debitur' => $debitur,'dinas' => $dinas,'jenis_pelayanan' => $jenis_layanan,
                                            'idunit' => $idunit,'user_proses' => $user_proses,'rm' => '','doc_gl' => $doc_no,'doc_ar' => '',
                                            'jenis_tagihan' => $jenis_tagihan
                                        ]);
                                        DB::commit();
                                    }catch(\Exception $ex){
                                        DB::rollBack();
                                    }
                                }
                            }else{
                                // ERROR GL KE CENTRA
                                $message = $data['error'];
                                $this->logCentra($koneksi,'acc/general-ledger',json_encode($params),$code,json_encode($data['error']),$user_proses);
                            }
                        }else{
                            $this->logCentra($koneksi,'login-mobile','',$code,json_encode($authLogin['error']),$user_proses);
                            $message = $authLogin['error'];
                        }

                    }else{
                        // BPJS TK
                        // PROSES CENTRA
                        $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                        $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                        $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                        
                        if($authLogin['error']==null){

                            $this->logCentra($koneksi,'login-mobile','',200,json_encode($authLogin['response']),$user_proses);

                            foreach($data_pasien as $id_pasien){
                                $header_data = DB::connection($koneksi)->select('exec rirj_setor_keuangan_jurnal_template_header :tgl,:debitur,:dinas,:jenis_pelayanan,:idunit,:rm,:jenis_tagihan',[
                                            'tgl' => Carbon::parse($tgl)->format('Y-m-d'),'debitur' => $debitur,'dinas' => $dinas,'jenis_pelayanan' => $jenis_layanan,'idunit' => $idunit,
                                            'rm' => $id_pasien->rm,'jenis_tagihan' => $jenis_tagihan
                                ]);
    
                                $detil_data = DB::connection($koneksi)->select('exec rirj_setor_keuangan_jurnal_template_rincian :tgl,:debitur,:dinas,
                                            :jenis_pelayanan,:idunit,:rm,:kode_customer,:trading_partner,:acc_id_yma,:acc_id_bank_tunai,:kode_bank_tunai,:jenis_tagihan',[
                                            'tgl' => Carbon::parse($tgl)->format('Y-m-d'),'debitur' => $debitur,'dinas' => $dinas,'jenis_pelayanan' => $jenis_layanan,
                                            'idunit' => $idunit,'rm' => $id_pasien->rm,'kode_customer' => $validasi_data_pelayanan[0]->CUSTOMER_ID,
                                            'trading_partner' => $validasi_data_pelayanan[0]->TRADING_PARTNER_ID,'acc_id_yma' => $validasi_data_pelayanan[0]->ACC_ID,
                                            'acc_id_bank_tunai' => $acc_id_bank_tunai,'kode_bank_tunai' => $kode_bank_tunai,'jenis_tagihan' => $jenis_tagihan
                                ]);
    
                                $reference = $info.Carbon::parse($tgl)->format('dmY').
                                            str_replace(' ','',$validasi_data_pelayanan[0]->NAMA_DEBITUR).
                                            str_replace(' ','',$validasi_data_pelayanan[0]->NAMA_DINAS).
                                            str_replace(' ','',$validasi_data_pelayanan[0]->NAMA_UNIT).
                                            str_replace(' ','',$id_pasien->nama);
                                $keterangan = 'PEND.'.$layanan.' '.
                                            Carbon::parse($tgl)->format('d-m-Y').' '.
                                            $validasi_data_pelayanan[0]->NAMA_DEBITUR.' '.
                                            $validasi_data_pelayanan[0]->NAMA_DINAS.' '.
                                            $validasi_data_pelayanan[0]->NAMA_UNIT.' '.
                                            $id_pasien->nama;
                                            $auth = $authLogin['response']->user_token;
                                
                                // CREATE GL
                                $detil_data_collect = collect($detil_data);
                                $debet = $detil_data_collect->sum('debit_src');
                                $kredit = $detil_data_collect->sum('credit_src');
                                $params = [
                                    'date_doc' => Carbon::parse($tgl)->format('Y-m-d'),
                                    'date_accounted' => date('Y-m-d'),
                                    'reference' => $reference,
                                    'currency_id' => 'IDR',
                                    'currency_rate' => 1,
                                    'gl_type' => $debitur=='999'?'JKM':'JR',
                                    'debit' => $debet,
                                    'credit' => $kredit,
                                    'description' => $keterangan,
                                    'doc_status' => 'Completed',
                                    'generalLedgerLine' => $detil_data,
                                ];
                                $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('acc/general-ledger','post',$auth,$params);
                                if($data['error']==null){
                                    $this->logCentra($koneksi,'acc/general-ledger',json_encode($params),200,json_encode($data['response']->data),$user_proses);
                                    $code = 200;
                                    $status = 'success';
                                    $data = $data['response']->data;
                                    
                                    $result = json_encode($data['response']->data);

                                    $message = $message.'GL : '.json_decode($result)->doc_no.' | ';
                                }else{
                                    // ERROR CREATE GL KE CENTRA
                                    $this->logCentra($koneksi,'acc/general-ledger',json_encode($params),$code,json_encode($data['error']),$user_proses);
                                    $message = $message.$data['error'];
                                }
                            }
                        }else{
                            $this->logCentra($koneksi,'login-mobile','',$code,json_encode($authLogin['error']),$user_proses);
                            $message = $authLogin['error'];
                        }

                    }
                }else{
                    throw new \Exception('ada kesalahan pemrosesan data');
                }
            }catch(\Exception $ex){
                // DB::rollBack();
                $message = $ex->getMessage();
            }
        }
        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function voidLedgerCentra(Request $request){
        $status = 'error';
        $code = 401;
        $message = '';
        $data = null;

        $cek_data = 0;
        $gl = $request->gl;
        $debitur = $request->debitur;
        $note = $request->note;
        $user_proses = $request->user_proses;
        // 1 = rawat jalan & obat (non tunai) ; 2 = rawat jalan tunai
        // 3 = obat (non tunai / tunai)
        // 4 = rawat inap ; 5 = excess rawat inap
        $jenis_transaksi = $request->jenis_transaksi; 

        $valid = Validator::make($request->all(),[
            'gl' => 'required',
            'jenis_transaksi' => 'required',
        ]);
        if($valid->fails()){
            $message = $valid->errors();
        }else{
            if($debitur=='999'){
                $message = 'maaf, debitur tunai tidak bisa void jurnal';
            }else{
                if($jenis_transaksi==1){
                    $cek_data = DB::table('rj_karcis_bayar')
                                ->whereRaw("isnull(doc_no_centra,'')='$gl'")
                                ->where('kddebt',$debitur)
                                ->whereRaw("isnull(stbtl,0)=0")
                                ->count();
                    if($cek_data==0){
                        $cek_data = DB::table('if_htrans')
                                    ->whereRaw("isnull(doc_no_centra,'')='$gl'")
                                    ->where('active',1)
                                    ->where('kddeb',$debitur)
                                    ->count();
                    }
                }else if($jenis_transaksi==3){
                    $cek_data = DB::table('if_htrans')
                                ->whereRaw("isnull(doc_no_centra,'')='$gl'")
                                ->where('active',1)
                                ->where('kddeb',$debitur)
                                ->count();
                }else if($jenis_transaksi==4){
                    $cek_data = DB::table('ri_masterpx')
                                ->where(function ($query) use ($gl){
                                    $query->where(DB::raw("isnull(doc_no_centra,'')"),'=',$gl)
                                        ->orWhere(DB::raw("isnull(doc_no_bpjs_cob_centra,'')"),'=',$gl)
                                        ->orWhere(DB::raw("isnull(doc_no_centra2,'')"),'=',$gl);
                                })
                                ->where(DB::raw("isnull(sts_batal,0)"),0)
                                ->where('kdebi',$debitur)
                                ->count();
                }else if($jenis_transaksi==2 || $jenis_transaksi==5){
                    $message = 'jenis ini belum ada layanan untuk void';
                }
                
                if($cek_data>0){
                    // proses centra
                    $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                    $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                    $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                    if($authLogin['error']==null){
                        // login sukses
                        
                        $this->logCentra('sqlsrv','login-mobile','',200,json_encode($authLogin['response']),$user_proses);
                        $auth = $authLogin['response']->user_token;
                        // void ledger
                        $params = [
                            'id' => $gl,
                            'note' => $note,
                        ];
                        $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('acc/general-ledger/void','post',$auth,$params);

                        if($data['error']==null){
                            // access service void success
                            $this->logCentra('sqlsrv','acc/general-ledger/void',json_encode($params),$data['response']->responseCode=='OK'?200:401,json_encode($data['response']->message),$user_proses);
                            $code = $data['response']->responseCode=='OK'?200:401;
                            $status = $data['response']->responseCode=='OK'?'success':'error';
                            
                            $message = $data['response']->message;
                            if($data['response']->responseCode=='OK'){
                                $list_transaksi = null;
                                $list_transaksi2 = null;

                                if($jenis_transaksi==1){
                                    $list_transaksi = DB::table('rj_karcis_bayar')
                                                    ->where(DB::raw("isnull(stbtl,0)"),'=',0)
                                                    ->where('kddebt',$debitur)
                                                    ->where('doc_no_centra',$gl)
                                                    ->select('nota')
                                                    ->get();
                                    $list_transaksi2 = DB::table('if_htrans')
                                                    ->where('active','=',1)
                                                    ->where('kddeb',$debitur)
                                                    ->where('doc_no_centra',$gl)
                                                    ->select('id_trans')
                                                    ->get();

                                    DB::update("update rj_karcis_bayar 
                                    set verifbapel=0,verifkeu=0,jurnal=null,usrverifbapel=null,
                                    dtverifbapel=null,doc_no_centra=null,gl_line=null 
                                    where isnull(stbtl,0)=0 and kddebt=:debitur doc_no_centra=:gl",
                                    ['debitur' => $debitur,'gl' => $gl]);

                                    DB::update("update if_htrans 
                                    set jurnal = null,doc_no_centra=null,keu=0,gl_line=null 
                                    where active=1 and kddeb=:debitur and doc_no_centra=:gl", 
                                    ['debitur' => $debitur,'gl' => $gl]);

                                    if($debitur=='926'){
                                        DB::update('update tdbpjs_keuangan set active = 0 
                                        where active=1 and jurnal=?', [$gl]);
                                    }
                                }else if($jenis_transaksi==3){
                                    $list_transaksi = DB::table('if_htrans')
                                                        ->where('active','=',1)
                                                        ->where('kddeb',$debitur)
                                                        ->where('doc_no_centra',$gl)
                                                        ->select('id_trans')
                                                        ->get();

                                    DB::update("update if_htrans 
                                    set jurnal = null,doc_no_centra=null,keu=0,gl_line=null 
                                    where active=1 and kddeb=:debitur and doc_no_centra=:gl", 
                                    ['debitur' => $debitur,'gl' => $gl]);
                                }else if($jenis_transaksi==4){
                                    $arr_gl = DB::table('ri_masterpx')
                                                ->where(function ($query) use ($gl){
                                                    $query->where(DB::raw("isnull(doc_no_centra,'')"),'=',$gl)
                                                            ->orWhere(DB::raw("isnull(doc_no_bpjs_cob_centra,'')"),'=',$gl)
                                                            ->orWhere(DB::raw("isnull(doc_no_centra2,'')"),'=',$gl);
                                                })
                                                ->whereRaw("isnull(sts_batal,0)=0")
                                                ->where('kdebi',$debitur)
                                                ->select(DB::raw("isnull(doc_no_centra,'') as doc_no_centra"),
                                                    DB::raw("isnull(doc_no_bpjs_cob_centra,'') as doc_no_bpjs_cob_centra"),
                                                    DB::raw("isnull(doc_no_centra2,'') as doc_no_centra2"))
                                                ->first();
                                    $list_transaksi = DB::table('ri_masterpx')
                                                        ->where(function ($query) use ($gl){
                                                            $query->where(DB::raw("isnull(doc_no_centra,'')"),'=',$gl)
                                                                    ->orWhere(DB::raw("isnull(doc_no_bpjs_cob_centra,'')"),'=',$gl)
                                                                    ->orWhere(DB::raw("isnull(doc_no_centra2,'')"),'=',$gl);
                                                        })
                                                        ->whereRaw("isnull(sts_batal,0)=0")
                                                        ->where('kdebi',$debitur)
                                                        ->select('noreg')
                                                        ->get();
                                    
                                    if($arr_gl->doc_no_centra!='' && $arr_gl->doc_no_centra==$gl){
                                        DB::update("update ri_masterpx 
                                        set gl_line = null,doc_no_centra=null,verifbapel=0,verifkeuangan=0,tglverifbapel=null,tglverifkeuangan=null,userverifbapel=null,userverifkeuangan=null 
                                        where isnull(sts_batal,0)=0 and kdebi=:debitur and doc_no_centra=:gl", 
                                        ['debitur' => $debitur,'gl' => $gl]);
                                    }
                                    if($arr_gl->doc_no_bpjs_cob_centra!='' && $arr_gl->doc_no_bpjs_cob_centra==$gl){
                                        DB::update("update ri_masterpx 
                                        set gl_line_bpjs_cob_centra = null,doc_no_bpjs_cob_centra=null 
                                        where isnull(sts_batal,0)=0 and kdebi=:debitur and doc_no_bpjs_cob_centra=:gl", 
                                        ['debitur' => $debitur,'gl' => $gl]);
                                    }
                                    if($arr_gl->doc_no_centra2!='' && $arr_gl->doc_no_centra2==$gl){
                                        DB::update("update ri_masterpx 
                                        set gl_line2 = null,doc_no_centra2=null 
                                        where isnull(sts_batal,0)=0 and kdebi=:debitur and doc_no_centra2=:gl", 
                                        ['debitur' => $debitur,'gl' => $gl]);
                                    }
                                }
                                foreach($list_transaksi as $list_tr){
                                    $log = new logVoid();
                                    if($jenis_transaksi==1){
                                        $log->nota_jalan = $list_tr->nota;
                                    }else if($jenis_transaksi==3){
                                        $log->id_trans_farmasi = $list_tr->id_trans;
                                    }else if($jenis_transaksi==4){
                                        $log->noreg = $list_tr->noreg;
                                    }
                                    $log->doc_no = $gl;
                                    $log->tgl_void = Carbon::now();
                                    $log->alasan = $note;
                                    $log->user_proses = $user_proses;
                                    $log->save();
                                }
                                if($list_transaksi2!=null){
                                    foreach($list_transaksi2 as $list_tr){
                                        $log = new logVoid();
                                        $log->id_trans_farmasi = $list_tr->id_trans;
                                        $log->doc_no = $gl;
                                        $log->tgl_void = Carbon::now();
                                        $log->alasan = $note;
                                        $log->user_proses = $user_proses;
                                        $log->save();
                                    }
                                }

                                $nipp = DB::table('rirj_muser')
                                        ->where('userid',$user_proses)
                                        ->where('aktif',1)
                                        ->value(DB::raw("isnull(nipp,'')"));

                                $approval = new ApprovalRequest();
                                $approval->ID_MITEM = 19;
                                $approval->ID_KEY = $gl;
                                $approval->NIPP_REQUEST = $nipp;
                                $approval->WAKTU_PERMOHONAN = Carbon::now();
                                $approval->CATATAN_PERMOHONAN = 'void transaksi karena '.$note;
                                $approval->NIPP_APPROVE = $nipp;
                                $approval->WAKTU_APPROVE = Carbon::now()->addHour();
                                $approval->STATUS_APPROVE = 1;
                                $approval->STATUS_AKTIF = 1;
                                $approval->CRTUSR = $nipp;
                                $approval->MODIUSR = $nipp;
                                $approval->save();

                            }
                        }else{
                            // access service void fail
                            $this->logCentra('sqlsrv','acc/general-ledger/void',json_encode($params),$code,json_encode($data['error']),$user_proses);
                            $message = $data['error'];
                        }
                    }else{
                        // error login
                        $this->logCentra('sqlsrv','login-mobile','',$code,json_encode($authLogin['error']),$user_proses);
                        $message = $authLogin['error'];
                    }
                }else{
                    if($jenis_transaksi!=2 && $jenis_transaksi!=5){
                        $message = 'tidak ada rincian transaksi yang divoid';
                    }
                }
            }
        }

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
        
    }
}
