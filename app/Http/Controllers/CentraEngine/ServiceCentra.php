<?php

namespace App\Http\Controllers\CentraEngine;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceCentra extends Controller
{
    public function getService($service,$method,$authorization,$data){
        $url = DB::table('rirj_mglobal')->where('tipe',"URL_CENTRA_PRODUCTION")->value('valstr');
        $url = $url.$service;
        
        $curl = curl_init();
        if($method != "get"){
            // if($service=="login-mobile"){
            //     $url = $url."?".$data;
            // }else{
                // $post_data = http_build_query($data);
                curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
            // }
        }else{
            $url = $url.$data;
        }
        // echo $url;
        // echo json_encode($data);
        // die();
        curl_setopt($curl,CURLOPT_URL,$url);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_TIMEOUT,30);
        curl_setopt($curl,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,$method);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        if($method!="login-mobile"){
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "Accept: application/json",
                "Authorization: Bearer ".$authorization,
            ));
        }else{
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "Accept: application/json",
            ));
        }
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        
        return [
            'error' => json_decode($error),
            'response' => json_decode($response),
        ];
    }

    public function loginCentra($username,$password){
        $params = [
            'username' => $username,
            'password' => $password,
            'remember' => true,
        ];//"username=".$username."&password=".$password."&remember=true";
        $data = $this->getService('login-mobile','post','',$params);
        return $data;
    }
}
