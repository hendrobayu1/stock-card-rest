<?php

namespace App\Http\Controllers\Email;
use App\Http\Controllers\Controller;

use App\Mail\SentEmail;
// use Illuminate\Http\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use Spatie\Dropbox\Client;
use Illuminate\Support\Facades\Storage;
use App\EmailKirim;
use App\EmailAttachment;
use App\EmailHistory;
use Illuminate\Support\Facades\URL;

class MailController extends Controller
{
    // public function __construct(){
    //     $this->dropbox = Storage::disk('dropbox')->getDriver()->getAdapter()->getClient();
    // }

    public function getStorage(){
        return DB::table('rirj_email_jenis_storage')
                ->where('active',1)
                ->value('id_jenis_storage');
    }

    public function getTemplate($id_jenis){
        return DB::table('rirj_email_jenis')
                ->where('active',1)
                ->where('id_jenis_email',$id_jenis)
                ->value('view_template');
    }

    public function sentMail(Request $request){
        $alamat = $request->kirim_ke;

        Mail::to($alamat)->send(new SentEmail());

        return "Email telah terkirim ke alamat ".$alamat;
    }

    public function directSentMail(Request $request){
        $code = 401;
        $message = '';
        $status = 'error';
        $id_storage = $this->getStorage();


        $user = $request->user_proses;
        $id_transaksi = $request->id_transaksi;
        // 1 = karcis ; 2 = register ; 3 = id transaksi farmasi ; 4 = id eforvid ; 5 = id erapid
        $jenis_transaksi = $request->jenis_transaksi; 
        // $id_jenis_email = $request->jenis_email;
        $nama = $request->nama;
        $to = $request->to;
        $cc = $request->cc;
        $bcc = $request->bcc;
        $subjek = $request->subjek;

        $from = 'noreply@rsphc.co.id';
        $from_name = 'RS PHC Surabaya';
        $attach_file = '0';

        $view = $this->getTemplate($jenis_transaksi);
        $info_link = '';
        $validate = Validator::make($request->all(),[
            'user_proses' => 'required',
            'id_transaksi' => 'required',
            'jenis_transaksi' => 'required',
            'nama' => 'required',
            'to' => 'required',
            'subjek' => 'required',
            'files.*' => 'file|max:2000'
        ]);
        
        if($validate->fails()){
            $message = $validate->errors();
        }else{

            $valid_jenis = DB::table('rirj_email_jenis')
                        ->where('id_jenis_email',$jenis_transaksi)
                        ->where('active',1)
                        ->count();
            if($valid_jenis==0){
                $message = 'jenis transaksi tidak ada dalam sistem';
            }else{
                try{
                    // $this->validate($request,[
                    //     'files.*' => 'file|max:2000'
                    // ]);
                    if($id_storage==2){
                        $this->dropbox = Storage::disk('dropbox')->getDriver()->getAdapter()->getClient();
                    }else if($id_storage==1){
                        $attach_file = DB::table('if_mglobal')
                                        ->where('tipeglobal','AktifAttachFileEmail')
                                        ->value('valstr');
                    }
                    
                    $filename = [];
                    $path = array();
                    if ($request->has('files')){
                        foreach($request->file('files') as $file){
                            if($file->isValid()){
                                $ext = $file->getClientOriginalExtension();
                                // $title = $file->getClientOriginalName();
                                // $titleWithoutExt = pathinfo($title,PATHINFO_FILENAME);
                                $filename[] = date('YmdHis').uniqid().'.'.$ext;
                                if($id_storage==1){
                                    $file->storeAs('public/upload',$filename[count($filename)-1],'local');
                                    $path[] = env('PUBLIC_URL_FILE',URL::to('/')).'/storage/upload/'.$filename[count($filename)-1];
                                    //$path = $file->storeAs('upload',$filename[count($filename)-1],'custom');
                                }else if($id_storage==2){
                                    $file->storeAs('data',$filename[count($filename)-1], 'dropbox');
                                    array_push($path,json_encode($this->dropbox->createSharedLinkWithSettings('data/'.$filename[count($filename)-1])));
                                    // $path[] = $this->dropbox->createSharedLinkWithSettings('data/'.$filename[count($filename)-1]);
                                }
                            }
                        }
                    }
                    
                    if(count($filename)>0){
                        $info_link = "Bukti pendaftaran : ";
                        if($attach_file=='1'){
                            $info_link = '';
                        }else{
                            foreach($path as $path_row){
                                if($id_storage==1){
                                    $info_link = $info_link.$path_row. " \n";
                                }else if($id_storage==2){
                                    $info_link = $info_link.json_decode($path_row)->url. " \n";
                                }
                            }
                            $info_link = $info_link." \n";
                        }
                    }
                    
                    Mail::send($view, ['nama' => $nama,'link' => $info_link], function ($message) use ($from,$from_name,
                    $to,$cc,$bcc,$subjek,$attach_file,$filename){
                        $message->from($from, $from_name);
                        $message->sender($from, $from_name);
                        $message->to($to, $to);
                        if($cc!=''){
                            $message->cc($cc, $cc); 
                        }
                        if($bcc!=''){
                            $message->bcc($bcc, $bcc); 
                        }
                        $message->subject($subjek);
                        if($attach_file=='1'){
                            foreach($filename as $file){
                                $message->attach(public_path('\storage\upload').'\\'.$file);
                                // $message->attach('\\\\127.0.0.1\\backup\\upload\\'.$file);
                            }
                        }
                    });
                    $code = 200;
                    $message = 'Kirim email berhasil';
                    $status = 'success';
                }catch(\Exception $ex){
                    $message = $ex->getMessage();
                }
            }
            if($attach_file=='1' && $valid_jenis>0){
                foreach($filename as $file){
                    Storage::disk('local')->delete("public/upload/$file");
                }
            }
            if ($valid_jenis>0){
                try{
                    DB::beginTransaction();
                    $header = new EmailKirim();
                    $header->user_sent = $user;
                    if($jenis_transaksi==1){
                        $header->karcis = $id_transaksi;
                    }else if($jenis_transaksi==2){
                        $header->register = $id_transaksi;
                    }else if($jenis_transaksi==3){
                        $header->id_farmasi = $id_transaksi;
                    }else if($jenis_transaksi==4){
                        $header->id_eforvid = $id_transaksi;
                    }else if($jenis_transaksi==5){
                        $header->id_erapid = $id_transaksi;
                    }
                    $header->tgl_sent = now();
                    $header->from_email = $from;
                    $header->to_email = $to;
                    $header->cc_email = $cc;
                    $header->bcc_email = $bcc;
                    $header->jenis_email = $jenis_transaksi;
                    $header->subject = $subjek;
                    $header->template_view = $view;
                    $header->message = 'nama penerima : '.$nama.' '.$info_link;
                    $header->response_api = $message;
                    $header->status_sent = $code==200?1:0;
                    if($header->save()){
                       $id = $header->id_sent;
                       foreach($path as $path_row){
                            $detil_attachment = new EmailAttachment();
                            $detil_attachment->id_sent = $id;
                            if($id_storage==1){
                                $detil_attachment->url_attachment = $path_row;
                            }else if($id_storage==2){
                                $detil_attachment->url_attachment = json_decode($path_row)->url;
                            }
                            $detil_attachment->jenis_storage = $id_storage;
                            $detil_attachment->save();
                       }
                    }
                    DB::commit();
                }catch(\Exception $ex){
                    $message = $ex->getMessage();
                    DB::rollBack();
                }
            }
        }

        try{
            $log = new EmailHistory();
            $log->user_sent = $user;
            if($jenis_transaksi==1){
                $log->karcis = $id_transaksi;
            }else if($jenis_transaksi==2){
                $log->register = $id_transaksi;
            }else if($jenis_transaksi==3){
                $log->id_farmasi = $id_transaksi;
            }else if($jenis_transaksi==4){
                $log->id_eforvid = $id_transaksi;
            }else if($jenis_transaksi==5){
                $log->id_erapid = $id_transaksi;
            }
            $log->tgl_sent = now();
            $log->from_email = $from;
            $log->to_email = $to;
            $log->cc_email = $cc;
            $log->bcc_email = $bcc;
            $log->jenis_email = $jenis_transaksi;
            $log->subject = $subjek;
            $log->response_code = $code;
            $log->response_message = $message;
            $log->save();
        }catch(\Exception $ex){}

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }
}