<?php

namespace App\Http\Controllers\CentraFinance;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    // public function loginCentra($username,$password){
    //     $params = "username=".$username."&password=".$password."&remember=true";
    //     $data = app('App\Http\Controllers\CentraEngine\CentraEngine\ServiceCentra')->getService('login-mobile','post','',$params);
    //     return $data;
    // }

    public function createBilling(Request $request){
        $bulan = $request->bulan;
        $tahun = $request->tahun;
        $debitur = $request->debitur;
        $userid = $request->userid;

        $status = "error";
        $message = "";
        $data = "";

        try{
            $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
            $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
            $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);//$this->loginCentra($username,$password);
            
            // return response()->json($authLogin);
            
            if ($authLogin['error'] == null){
                $billing_validation = DB::select("exec rj_sp_validasi_billing_sep :bulan,:tahun,:debitur", ["bulan"=>$bulan,"tahun" => $tahun,"debitur"=>$debitur]);
                // return response()->json($billing_validation);
                if(!empty($billing_validation)){
                    if($billing_validation[0]->STATUS == 1){
                        $auth = $authLogin['response']->user_token;
                        $grand_total = $billing_validation[0]->GT;
                        $dp = $billing_validation[0]->DP;
                        $amount = $billing_validation[0]->AMOUNT;
    
                        $billing_header = DB::select("exec rj_sp_ambil_info_header_billing_sep :bulan,:tahun,:debitur", ["bulan"=>$bulan,"tahun" => $tahun,"debitur"=>$debitur]);
                        $billing_rincian = DB::select("exec rj_sp_ambil_info_rincian_billing_sep :bulan,:tahun,:debitur", ["bulan"=>$bulan,"tahun" => $tahun,"debitur"=>$debitur]);
                        $billing_reference = DB::select("exec rj_sp_ambil_info_reference_billing_sep :bulan,:tahun,:debitur", ["bulan"=>$bulan,"tahun" => $tahun,"debitur"=>$debitur]);
    
                        $params = [
                            'partner_id'                => $billing_header[0]->TYPE_ID,
                            'currency_id'               => 'IDR',
                            'currency_rate'             => '1',
                            'reference'                 => $billing_header[0]->REFERENCE,
                            'date_doc'                  => $billing_header[0]->DATE_DOC,
                            'date_due'                  => $billing_header[0]->DATE_DUE,
                            'date_accounted'            => $billing_header[0]->DATE_ACCOUNTED,
                            'description'               => $billing_header[0]->DESCRIPTION,
                            'payment_term'              => '',
                            'grand_total'               => $grand_total,
                            'down_payment'              => $dp*-1,
                            'amount'                    => $amount,
                            'content_letter'            => '',
                            'ttd'                       => '',
                            'tax'                       => '',
                            'doc_status'                => 'Draft',
                            'paymentRequestGLAccount'   => $billing_rincian,
                            'invoiceLineDp'             => $billing_reference,
                            'paymentRequestWithholding' => null,
                        ];
                        // echo $auth;
                        // return $params;
                        // $data = $params;
                        $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('acc/billing','post',$auth,$params);
                        if($data['error']==''){
                            if($data['response']->responseCode=="ok"){
                                $data = $data['response']->data;
                                $message = 'Billing created';
                                $status = 'success';
                                DB::statement('exec rj_sp_update_transaksi_billing_sep :bln,:thn,:debitur,:user_id,:doc_ar', ['bln'=>$bulan,'thn'=>$tahun,'debitur'=>$debitur,'user_id'=>$userid,'doc_ar'=>$data->doc_no]);
                            }
                        }
                    }else{
                        $message = $billing_validation[0]->KETERANGAN;
                    }
                }else{
                    $message = "Ada kesalahan pengecekan !";
                }
            }else{
                $message = $authLogin['error'];
            }
        }catch(\Exception $ex){
            $message = $ex->getMessage();
            DB::insert('insert into rirj_log_bridging_centra(service_name,method,respon_code,respon_message,user_proses,date_log) 
                    values (:service_name, :method, :respon_code, :respon_message, :user_proses, :datelog)', 
                    ['service_name' => 'billing centra','method' => 'post','respon_code' => 99999,'respon_message'=>$message,'user_proses'=>$userid,'datelog'=>date('Y-m-d H:m:s')]);
        }
        
        return response()->json([
            'code' => 200,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }
}