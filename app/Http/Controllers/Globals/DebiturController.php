<?php

namespace App\Http\Controllers\Globals;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Debitur;
use App\Dinas;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DebiturController extends Controller
{
    public function listMasterCentra($url,$success_message,$params=''){
        $status = "error";
        $message = "";
        $data = "";

        $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
        $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
        $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
        if($authLogin['error']==null){
            $auth = $authLogin['response']->user_token;
            $message = $success_message;
            $status = 'success';
            $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService($url,'post',$auth,$params);
            return $data;
        }else{
            $message = $authLogin['error'];
        }
        return response()->json([
            'code' => 200,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function listTradingPartner(){
        return $this->listMasterCentra('base/setting/trading-partner/view','Trading partner data');
    }

    public function listPartnerType(){
        return $this->listMasterCentra('base/setting/partner-type/view','Partner type data');
    }

    public function listPartner(){
        return $this->listMasterCentra('base/md/partner/view','Partner data');
    }

    public function listBank(){
        return $this->listMasterCentra('base/md/bank/view','Bank data');
    }

    public function listBankAccount(Request $request){
        $bank_id = $request->get("bank_id");
        $params = ["bank_id" => $bank_id];
        return $this->listMasterCentra('acc/md/bank-account/view','Bank Account data',$params);
    }
    
    public function getCustomer(Request $request){
        $code = $request->get("code");
        $params = ["code" => $code];
        return $this->listMasterCentra('base/md/partner/view','Partner data by code',$params);
    }

    public function saveDebitur(Request $request){
        $status = "error";
        $message = "";
        $data = "";
        
        $partner_type = $request->get('partner_type');
        $trading_partner = $request->get('trading_partner');
        $chief_name = $request->get('chief_name');
        $chief_position = $request->get('chief_position');
        $phone_2 = $request->get('phone_2');
        $email = $request->get('email');
        $description = $request->get('description');
        $customer_code_centra_existing = $request->get('centra_customer_code');

        $bank_id = $request->get('bank_id');
        $account_no = $request->get('account_no');
        $account_name = $request->get('account_name');
        
        $validasi = Validator::make($request->all(),[
            'nmdebt'                => 'required|unique:rirj_mdebitur',
            'address'               => 'required',
            'city'                  => 'required',
            'partner_type'          => 'required',
            'identity_card'         => 'required',
            'npwp'                  => 'required',
            'phone_1'               => 'required',
            'email'                 => 'sometimes|email',
            'insurance_corporate'   => 'required',
            'formularium'           => 'required',
            'outpatient_rates'      => 'required',
            'medicine_rates'        => 'required',
            'bank_id'               => 'required',
            'account_no'            => 'required',
            'account_name'          => 'required',
        ]);

        if ($validasi->fails()){
            $message = $validasi->errors();
        }else{
            //Validasi lanjutan
            $advanced_validation = DB::select('exec rirj_validasi_lanjutan_create_partner :partner_type,:trading_partner', 
                                    ['partner_type'=>$partner_type,'trading_partner'=>$trading_partner]);
            if(!empty($advanced_validation)){
                if($advanced_validation[0]->status==1){
                    DB::beginTransaction();
                    try{
                        $arr_customer_code = DB::select("select isnull(max(convert(bigint,kddebt)),0)+1 as 'kode_debitur' from rirj_mdebitur where len(kddebt)<5");
                        $customer_code = $arr_customer_code[0]->kode_debitur;
                        $debitur = new Debitur();
                        $debitur->kddebt = $customer_code;
                        $debitur->nmdebt = $request->nmdebt;
                        $debitur->alamat = $request->address;
                        $debitur->kota = $trading_partner != "" ? "2" : "3";
                        $debitur->koderek = "104.02.00.00000";
                        $debitur->koderek2 = "05";
                        $debitur->tglmks = date("Y-m-d");
                        $debitur->tglaw = date("Y-m-d");
                        $tglak = new DateTime();
                        $tglak->setDate(2999,12,31);
                        $debitur->tglak = $tglak->format('Y-m-d');
                        $debitur->tipeadmin = 2;
                        $debitur->admin = 0;
                        $debitur->stt_excess = 2;
                        $debitur->tipe_debitur = $request->insurace_corporate;
                        $debitur->kontak = $chief_name;
                        $debitur->telp = $request->phone_1;
                        $debitur->std_obat = $request->formularium;
                        $debitur->if_excess_valid = 1;
                        $debitur->resep_jalan_tagih_bapel = 1;
                        $debitur->auto_dinas = 1;
                        $debitur->auto_verif_apm = 0;
                        $debitur->status_aktif = 1;
                        $debitur->stt_online = 1;
                        $debitur->acc_id = $trading_partner != "" ? "1103020300" : "1103020200";
                        $debitur->partner_id = $trading_partner != "" ? "1103020300" : "1103020200";
                        $debitur->trading_partner_id = $trading_partner != "" ? $trading_partner : null;
                        $debitur->customer_id = $customer_code_centra_existing;
                        $debitur->v_claim = 1;
                        $debitur->cek_dinas = 0;
                        if ($debitur->save()){
                            $dinas = new Dinas();
                            $dinas->kddebt = $customer_code;
                            $dinas->kddin = "00000";
                            $dinas->nmdin = "Rincian Terlampir";
                            $dinas->divisi = "Rincian Terlampir";
                            $dinas->dept = "Rincian Terlampir";
                            $dinas->save();
                            DB::insert('insert into if_margindeb (idmargin,kddeb) values (?, ?)', 
                                    [$request->medicine_rates, $customer_code]);
                            DB::insert('insert into rj_detail_grup_tarif (kd_grup_tarif,kddebt,crtdt) values (?, ?, ?)', 
                                    [$request->outpatient_rates, $customer_code,date('Y-m-d H:m:s')]);

                            DB::statement('exec inap_sp_tarif ?',[$customer_code]);

                            DB::commit();
                            if($customer_code_centra_existing==''){
                                //Insert to centra

                                $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                                $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                                $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                                if($authLogin['error']==null){
                                    $auth = $authLogin['response']->user_token;
                                    $paramsBank = array(['line_no' => 1,
                                        'bank_id' => $bank_id,
                                        'account_no' => $account_no,
                                        'account_name' => $account_name]);
                                    $params = [
                                        'partner_type_id' => $partner_type,
                                        'trading_partner_id' => $trading_partner=="" ? null : $trading_partner,
                                        'name' => $request->nmdebt,
                                        'city' => $request->city,
                                        'identity_card' => $request->identity_card,
                                        'npwp' => $request->npwp,
                                        'is_customer' => 1,
                                        'address' => $request->address,
                                        'phone' => $request->phone_1,
                                        'phone2' => $request->phone_2,
                                        'email' => $request->email,
                                        'description' => $request->description,
                                        'valid_from' => date("Y-m-d"),
                                        'valid_to' => $tglak,
                                        'chief_name' => $request->chief_name,
                                        'chief_position' => $request->chief_position,
                                        'code' => '',
                                        'partnerBank' => $paramsBank,
                                        'code' =>'2'.substr('000000000'.$customer_code,-9),
                                    ];
                                    // echo json_encode($params);
                                    $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('base/md/partner','post',$auth,$params);
                                    // return $data;
                                    $customer_code_centra_existing = $data['response']->data->code;

                                    $data = $data['response']->data;
                                }
                                //Update kode customer centra pada tabel rirj_mdebitur
                                DB::update('update rirj_mdebitur set customer_id = :cust_id where kddebt = :kode_debitur', 
                                ['cust_id'=>$customer_code_centra_existing,'kode_debitur'=>$customer_code]);
                            }else{
                                $data = $request->all();
                            }
                            $message = "Customer created";
                            $status = "success";
                        }else{
                            DB::rollback();
                            $message = 'Create customer failed';
                        }
                    }catch(\Exception $ex){
                        DB::rollback();
                        $message = $ex->getMessage();
                    }
                }else{
                    $message = $advanced_validation[0]->keterangan;
                }
            }
        }
        return response()->json([
            'code' => 200,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function getListDebitur(Request $request){
        $keyword = $request->keyword;
        $data = DB::table('rirj_mdebitur')
                ->where('status_aktif',1)
                ->where('nmdebt','like','%'.$keyword.'%')
                ->select('kddebt as kode','nmdebt as debitur','kota as jenis_debitur','std_obat',
                    DB::raw("isnull(acc_id,'') as acc_id"),DB::raw("isnull(partner_id,'') as partner_id"),
                    DB::raw("isnull(customer_id,'') as customer_id"),DB::raw("isnull(trading_partner_id,'') as trading_partner_id"))
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data pencarian debitur',
            'data' => $data,
        ]);
    }

    public function getListDinasPerDebitur(Request $request){
        $keyword = $request->keyword;
        $debitur = $request->debitur;
        $data = DB::table('rirj_mdebitur as a')
                ->join('rirj_mdebt_dinas as b','a.kddebt','=','b.kddebt')
                ->where('a.status_aktif',1)
                ->where('a.kddebt',$debitur)
                ->where('b.nmdin','like','%'.$keyword.'%')
                ->select('a.kddebt as kode_debitur','b.kddin as kode_dinas','b.nmdin as dinas')
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'data pencarian dinas',
            'data' => $data,
        ]);
    }
}
