<?php

namespace App\Http\Controllers\CentraMaterial;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MaterialCentraCotroller extends Controller
{
    public function searchMaterial(Request $request){
        $code = 401;
        $message = '';
        $status = 'error';
        $data = [];

        $mode = $request->mode;
        $storage_code = $request->storage_code;
        $limit = 100;
        $from = 0;
        $material_name = $request->material_name;
        if(strtolower($mode)=='invstorageproductview'){
            // call centra api
            $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
            $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
            $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
            if($authLogin['error']==null){
                $auth = $authLogin['response']->user_token;

                $params = "?mode=invStorageProductView&storagecode=$storage_code&limit=$limit&from=$from&materialname=$material_name";
                $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/view','get',$auth,$params);
                // belum tahu response dari centra
                if($data['error']==null){
                    $code = 200;
                    $status = 'success';
                    $message = 'search material success';
                    $data = $data['response'];
                }else{
                    $message = $data['error'];
                }
            }else{
                $message = $authLogin['error'];
            }
        }else{
            $message = 'mode view belum ditentukan';
        }

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function listDepo(Request $request){
        $code = 401;
        $message = '';
        $status = 'error';
        $data = [];

        $wh_code = $request->wh_code;
        
        // call centra api
        $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
        $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
        $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
        if($authLogin['error']==null){
            $auth = $authLogin['response']->user_token;

            $params = "?filter[0][column]=inv_wh.code&filter[0][operator]==&filter[0][query]=$wh_code";
            $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('inv/md/storage-location','get',$auth,$params);
            // belum tahu response dari centra
            if($data['error']==null){
                $code = 200;
                $status = 'success';
                $message = 'list depo success';
                $data = $data['response'];
            }else{
                $message = $data['error'];
            }
        }else{
            $message = $authLogin['error'];
        }
        
        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function prosesMR(Request $request){
        $mode = $request->mode;

        //new mr
        $reqVxMaterialMovementTypeCode = '';
        $reqDescription = ''; //include edit mr
        // $reqDeliveryPoint = '';
        // $reqLocationAddress = '';
        $reqReference = ''; //include edit mr
        $reqLine = []; //include edit mr,close mr

        //edit mr
        $reqMrDoc = ''; //include close mr,approve mr,void mr,delete mr,view mr
        
        $code = 401;
        $message = '';
        $status = 'error';
        $data = [];
        if($mode!=''){
            if(strtolower($mode)=='mr' || strtolower($mode)=='mr_draft'){
                $reqVxMaterialMovementTypeCode = $request->reqVxMaterialMovementTypeCode;
                $reqDescription = $request->reqDescription; //optional
                // $reqDeliveryPoint = $request->reqDeliveryPoint; //optional
                // $reqLocationAddress = $request->reqLocationAddress; //optional
                $reqReference = $request->reqReference;
                $reqLine = json_decode($request->reqLine,true);
                if($reqVxMaterialMovementTypeCode==''){
                    $message = 'kode movement type material belum ditentukan';
                }else{
                    if($reqReference==''){
                        $message = 'referensi tidak boleh kosong';
                    }else{
                        foreach($reqLine as $req){
                            if($req->reqProductCode==''){
                                $message = 'kode material tidak boleh kosong';
                                break;
                            }
                            if($req->reqStorageCode==''){
                                $message = 'kode storage tidak boleh kosong';
                                break;
                            }
                            if($req->reqQty=='' || $req->reqQty==0){
                                $message = 'qty material tidak boleh kosong';
                                break;
                            }
                            if($req->reqCostCenterCode==''){
                                $message = 'cost center tidak boleh kosong';
                                break;
                            }
                        }
                        if (count($reqLine)>0){
                            if($message==''){
                                // call centra api
                                $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                                $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                                $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                                if($authLogin['error']==null){
                                    $auth = $authLogin['response']->user_token;
                                    $params = [
                                        'mode' => strtolower($mode),
                                        'reqVxMaterialMovementTypeCode' => $reqVxMaterialMovementTypeCode,
                                        'reqDescription' => $reqDescription,
                                        'reqDeliveryPoint' => '',//$reqDeliveryPoint,
                                        'reqLocationAddress' => '',//$reqLocationAddress,
                                        'reqReference' => $reqReference,
                                        'reqLine' => $reqLine,
                                    ];
                                    $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/add','post',$auth,$params);
                                    // belum tahu response dari centra
                                    if($data['error']==null){
                                        $code = 200;
                                        $status = 'success';
                                        $message = 'mr created';
                                        $data = $data['response'];
                                    }else{
                                        $message = $data['error'];
                                    }
                                }else{
                                    $message = $authLogin['error'];
                                }
                            }
                        }else{
                            $message = 'line kosong';
                        }
                    }
                }
            }else if(strtolower($mode)=='mr_edit'){
                $reqMrDoc = $request->reqMrDoc;
                $reqDescription = $request->reqDescription; //optional
                $reqReference = $request->reqReference;
                $reqLine = json_decode($request->reqLine,true);
                if($reqMrDoc==''){
                    $message = 'nomor mr tidak boleh kosong';
                }
                if($message=='' && $reqReference==''){
                    $message = 'referensi tidak boleh kosong';
                }else{
                    foreach($reqLine as $req){
                        if($req->reqMrLine==''){
                            $message = 'line mr belum ditentukan';
                            break;
                        }
                        if($req->reqQty=='' || $req->reqQty==0){
                            $message = 'qty material tidak boleh kosong';
                            break;
                        }
                        if($req->reqStatusAdd!='1' && $req->reqStatusAdd!='0'){
                            $message = 'status line belum ditentukan';
                            break;
                        }
                        if($req->reqProductCode==''){
                            $message = 'kode material tidak boleh kosong';
                            break;
                        }
                        if($req->reqStorageCode==''){
                            $message = 'kode storage tidak boleh kosong';
                            break;
                        }
                        if($req->reqCostCenterCode==''){
                            $message = 'cost center tidak boleh kosong';
                            break;
                        }
                    }
                    if (count($reqLine)>0){
                        if($message==''){
                            // call centra api
                            $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                            $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                            $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                            if($authLogin['error']==null){
                                $auth = $authLogin['response']->user_token;
                                $params = [
                                    'mode' => strtolower($mode),
                                    'reqMrDoc' => $reqMrDoc,
                                    'reqDescription' => $reqDescription,
                                    'reqReference' => $reqReference,
                                    'reqLine' => $reqLine,
                                ];
                                $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/add','post',$auth,$params);
                                // belum tahu response dari centra
                                if($data['error']==null){
                                    $code = 200;
                                    $status = 'success';
                                    $message = 'edit mr successfull';
                                    $data = $data['response'];
                                }else{
                                    $message = $data['error'];
                                }
                            }else{
                                $message = $authLogin['error'];
                            }
                        }
                    }else{
                        $message = 'line kosong';
                    }
                }
            }else if(strtolower($mode)=='mr_close'){
                $reqMrDoc = $request->reqMrDoc;
                $reqLine = json_decode($request->reqLine,true);
                if($reqMrDoc==''){
                    $message = 'nomor mr tidak boleh kosong';
                }else{
                    foreach($reqLine as $req){
                        if($req->reqMrLine==''){
                            $message = 'line mr belum ditentukan';
                            break;
                        }
                    }
                    if(count($reqLine)>0){
                        if($message==''){
                            // call centra api
                            $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                            $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                            $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                            if($authLogin['error']==null){
                                $auth = $authLogin['response']->user_token;
                                $params = [
                                    'mode' => strtolower($mode),
                                    'docno' => $reqMrDoc,
                                    'reqLine' => $reqLine,
                                ];
                                $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/add','post',$auth,$params);
                                // belum tahu response dari centra
                                if($data['error']==null){
                                    $code = 200;
                                    $status = 'success';
                                    $message = 'close mr successfull';
                                    $data = $data['response'];
                                }else{
                                    $message = $data['error'];
                                }
                            }else{
                                $message = $authLogin['error'];
                            }
                        }
                    }else{
                        $message = 'line kosong';
                    }
                }
            }else if(strtolower($mode)=='mr_approve' || strtolower($mode)=='mr_void' || strtolower($mode)=='mr_delete' || strtolower($mode)=='mr_view'){
                $reqMrDoc = $request->reqMrDoc;
                if($reqMrDoc==''){
                    $message = 'nomor mr tidak boleh kosong';
                }else{
                    // call centra api
                    $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                    $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                    $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                    if($authLogin['error']==null){
                        $auth = $authLogin['response']->user_token;
                        if (strtolower($mode)!='mr_view'){
                            $params = [
                                'mode' => strtolower($mode),
                                'docno' => $reqMrDoc,
                            ];
                            $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/add','post',$auth,$params);
                        }else{
                            $params = "?mode=".strtolower($mode)."&docno=$reqMrDoc";
                            $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/view','get',$auth,$params);
                        }
                        // belum tahu response dari centra
                        if($data['error']==null){
                            $code = 200;
                            $status = 'success';
                            if(strtolower($mode)=='mr_approve'){
                                $message = 'mr approved';
                            }else if(strtolower($mode)=='mr_void'){
                                $message = 'mr void';
                            }else if(strtolower($mode)=='mr_delete'){
                                $message = 'mr deleted';
                            }else if(strtolower($mode)=='mr_view'){
                                $message = 'load mr';
                            }
                            $data = $data['response'];
                        }else{
                            $message = $data['error'];
                        }
                    }else{
                        $message = $authLogin['error'];
                    }
                }
            }
        }else{
            $message = 'mode mr belum ditentukan';
        }

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function prosesGI(Request $request){
        $mode = $request->mode;

        //new gi
        $reqVxMaterialMovementTypeCode = '';
        $reqLine = []; //include gi rev
        
        //void gi
        $reqGIDoc = '';
        
        $code = 401;
        $message = '';
        $status = 'error';
        $data = [];
        if($mode!=''){
            if(strtolower($mode)=='gi'){
                $reqVxMaterialMovementTypeCode = $request->reqVxMaterialMovementTypeCode;
                $reqLine = json_decode($request->reqLine,true);
                if($reqVxMaterialMovementTypeCode==''){
                    $message = 'kode movement type material belum ditentukan';
                }else{
                    foreach($reqLine as $req){
                        if($req->reqMrDoc==''){
                            $message = 'doc mr tidak boleh kosong';
                            break;
                        }
                        if($req->reqMrLine=='' || $req->reqMrLine==0){
                            $message = 'line mr tidak boleh kosong';
                            break;
                        }
                        if($req->reqQty=='' || $req->reqQty==0){
                            $message = 'qty mr tidak boleh kosong';
                            break;
                        }
                    }
                    if (count($reqLine)>0){
                        if($message==''){
                            // call centra api
                            $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                            $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                            $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                            if($authLogin['error']==null){
                                $auth = $authLogin['response']->user_token;
                                $params = [
                                    'mode' => strtolower($mode),
                                    'reqVxMaterialMovementTypeCode' => $reqVxMaterialMovementTypeCode,
                                    'reqLine' => $reqLine,
                                ];
                                $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/add','post',$auth,$params);
                                // belum tahu response dari centra
                                if($data['error']==null){
                                    $code = 200;
                                    $status = 'success';
                                    $message = 'gi created';
                                    $data = $data['response'];
                                }else{
                                    $message = $data['error'];
                                }
                            }else{
                                $message = $authLogin['error'];
                            }
                        }
                    }else{
                        $message = 'line kosong';
                    }
                }
            }else if(strtolower($mode)=='gi_void' || strtolower($mode)=='gi_view'){
                $reqGIDoc = $request->reqGIDoc;
                if($reqGIDoc==''){
                    $message = 'nomor gi tidak boleh kosong';
                }else{
                    // call centra api
                    $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                    $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                    $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                    if($authLogin['error']==null){
                        $auth = $authLogin['response']->user_token;
                        if(strtolower($mode)!='gi_view'){
                            $params = [
                                'mode' => strtolower($mode),
                                'docno' => $reqGIDoc,
                            ];
                            $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/add','post',$auth,$params);
                        }else{
                            $params = "?mode=".strtolower($mode)."&docno=$reqGIDoc";
                            $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/view','get',$auth,$params);
                        }
                        // belum tahu response dari centra
                        if($data['error']==null){
                            $code = 200;
                            $status = 'success';
                            if(strtolower($mode)=='gi_void'){
                                $message = 'gi void';
                            }else if(strtolower($mode)=='gi_view'){
                                $message = 'load gi';
                            }
                            $data = $data['response'];
                        }else{
                            $message = $data['error'];
                        }
                    }else{
                        $message = $authLogin['error'];
                    }
                }
            }
        }else{
            $message = 'mode gi belum ditentukan';
        }

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function prosesGR(Request $request){
        $mode = $request->mode;

        //new gr
        $reqVxMaterialMovementCategoryCode = '';
        $reqDescription = '';
        $reqLine = [];

        $reqGRDoc = '';
        
        $code = 401;
        $message = '';
        $status = 'error';
        $data = [];
        if($mode!=''){
            if(strtolower($mode)=='gr'){
                $reqVxMaterialMovementCategoryCode = $request->reqVxMaterialMovementCategoryCode;
                $reqDescription = $request->reqDescription; //optional
                $reqLine = json_decode($request->reqLine,true);
                if($reqVxMaterialMovementCategoryCode==''){
                    $message = 'kode movement type material belum ditentukan';
                }else{
                    foreach($reqLine as $req){
                        if($req->reqLineNo=='' || $req->reqLineNo==0){
                            $message = 'no line tidak boleh kosong';
                            break;
                        }
                        if($req->reqProductCode==''){
                            $message = 'kode material tidak boleh kosong';
                            break;
                        }
                        if($req->reqQty=='' || $req->reqQty==0){
                            $message = 'qty material tidak boleh kosong';
                            break;
                        }
                        if($req->reqQtyBad=='' || $req->reqQtyBad==0){
                            $message = 'qty bad material tidak boleh kosong';
                            break;
                        }
                        if($req->reqWhCode==''){
                            $message = 'kode warehouse tidak boleh kosong';
                            break;
                        }
                        if($req->reqStorageCode==''){
                            $message = 'kode storage tidak boleh kosong';
                            break;
                        }
                        if($req->reqGrItemType==''){
                            $message = 'tipe item belum ditentukan';
                            break;
                        }
                        if($req->reqCostCenterCode==''){
                            $message = 'cost center tidak boleh kosong';
                            break;
                        }
                    }
                    if (count($reqLine)>0){
                        if($message==''){
                            // call centra api
                            $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                            $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                            $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                            if($authLogin['error']==null){
                                $auth = $authLogin['response']->user_token;
                                $params = [
                                    'mode' => strtolower($mode),
                                    'reqVxMaterialMovementCategoryCode' => $reqVxMaterialMovementCategoryCode,
                                    'reqDescription' => $reqDescription,
                                    'reqDeliveryPoint' => '',
                                    'reqLocationAddress' => '',
                                    'reqLine' => $reqLine,
                                ];
                                $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/add','post',$auth,$params);
                                // belum tahu response dari centra
                                if($data['error']==null){
                                    $code = 200;
                                    $status = 'success';
                                    $message = 'gr created';
                                    $data = $data['response'];
                                }else{
                                    $message = $data['error'];
                                }
                            }else{
                                $message = $authLogin['error'];
                            }
                        }
                    }else{
                        $message = 'line kosong';
                    }
                }
            }else if(strtolower($mode)=='gr_price' || strtolower($mode)=='gr_void_stock' || strtolower($mode)=='gr_void'){
                $reqGRDoc = $request->reqGRDoc;
                if($reqGRDoc==''){
                    $message = 'nomor gr tidak boleh kosong';
                }else{
                    // call centra api
                    $username = DB::table('rirj_mglobal')->where('tipe','USER_CENTRA_PRODUCTION')->value('valstr');
                    $password = DB::table('rirj_mglobal')->where('tipe','PASSWORD_CENTRA_PRODUCTION')->value('valstr');
                    $authLogin = app('App\Http\Controllers\CentraEngine\ServiceCentra')->loginCentra($username,$password);
                    if($authLogin['error']==null){
                        $auth = $authLogin['response']->user_token;
                        $params = [
                            'mode' => strtolower($mode),
                            'docno' => $reqGRDoc,
                        ];
                        $data = app('App\Http\Controllers\CentraEngine\ServiceCentra')->getService('valsix/jboosapi/add','post',$auth,$params);
                        // belum tahu response dari centra
                        if($data['error']==null){
                            $code = 200;
                            $status = 'success';
                            if(strtolower($mode)=='gr_price'){
                                $message = 'gr price approved';
                            }else if(strtolower($mode)=='gr_void_stock'){
                                $message = 'gr stock void';
                            }else if(strtolower($mode)=='gr_void'){
                                $message = 'gr void';
                            }
                            $data = $data['response'];
                        }else{
                            $message = $data['error'];
                        }
                    }else{
                        $message = $authLogin['error'];
                    }
                }
            }
        }else{
            $message = 'mode gr belum ditentukan';
        }

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }
}