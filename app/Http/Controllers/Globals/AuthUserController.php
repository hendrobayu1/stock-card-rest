<?php

namespace App\Http\Controllers\Globals;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
// use Auth;
use App\LogKartuStok;

class AuthUserController extends Controller
{
    public function logUser($userid,$route,$process_name,$params,$response_message,$device_info){
        // $message = '';
        try{
            LogKartuStok::create([
                'userid' => $userid,
                'route' => $route,
                'process_name' => $process_name,
                'params' => $params,
                'response_message' => $response_message,
                'device_info' => $device_info,
                'created_date' => date('Y-m-d H:i:s'),
            ]);
            // $message = '';
            return true;
        }catch(\Exception $ex){
            // $message = $ex;
            return false;
        }
        // return $message;
    }

    public function listUserLogin(Request $request){
        $data = DB::table('if_users_api as u')
                ->join('if_muser as a','u.userid','=','a.userid')
                ->where('u.active',1)
                ->where(function ($query) use($request){
                    $query->where('u.userid','like','%'.$request->keyword.'%')
                            ->orWhere('u.name','like','%'.$request->keyword.'%')
                            ->orWhere('u.email','like','%'.$request->keyword.'%');
                })
                ->select('u.id','u.userid','u.name','u.email')
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'list user login',
            'data' => $data,
        ]);
    }

    public function listDepoPerUser(Request $request){
        $id = $request->id;
        $data = DB::table('if_users_lokasi as u')
                ->join('if_mlayanan as l','u.kdmut','=','l.kode_mutasi')
                ->where('id_user',$id)
                ->where('l.active',1)
                ->select('l.idlayanan as id','l.kode_mutasi','l.layanan')
                ->get();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'list depo user',
            'data' => $data,
        ]);
    }

    public function login(Request $request){
        $kdmut = $request->kdmut;
        $username = $request->userid;
        $device_info = $request->device_info;
        $status = 'error';
        $message = '';
        $data = null;
        $code = 401;
        $validation = Validator::make($request->all(),[
            'userid' => 'required',
            'password' => 'required',
            'kdmut' => 'required',
        ]);
        if ($validation->fails()){
            $message = $validation->errors();
        }else{
            $jumlah_depo = DB::table('if_mlayanan')->where('kode_mutasi','=',$kdmut)->count();
            if($jumlah_depo>0){
                $user = User::where('active','=',1)
                        ->where(function ($query) use ($username){
                            $query->where('userid','=',$username)
                            ->orWhere('email','=',$username);
                        })->first();
                if ($user){
                    // $user_akses = DB::select('exec if_sp_kartu_stok_cek_akses :userid',['userid'=>$request->userid]);
                    $userid = $user->userid;
                    $user_akses = DB::table('if_muser as u')
                                    ->join('if_muser_akses as a','u.akses','=','a.id_akses')
                                    ->where('u.userid',$userid)
                                    ->select('a.id_akses','a.nama_akses as akses')
                                    ->first();//User::where('userid',$request->userid)->value('akses');
                    // if (!empty($user_akses)){
                    if (!empty($user_akses)){
                        // if($user_akses[0]->akses==1){
                        if($user_akses->id_akses<=2){
                            if(Hash::check($request->password, $user->password)){
                                $user->generateToken();
                                $data_user = $user->toArray();

                                $unit = DB::table('if_mlayanan')
                                        ->where('kode_mutasi','=',$kdmut)
                                        ->value('layanan');

                                $arr_unit = [
                                    "kode_unit" => $kdmut,
                                    "unit" => $unit,
                                    "id_akses" => $user_akses->id_akses,
                                    "akses" => $user_akses->akses,
                                ];
                                $data = array_merge($data_user,$arr_unit);
                                $status = 'success';
                                $message = 'login berhasil';
                                $code = 200;
                            }else{
                                $message = 'login gagal, password salah';
                            }
                        }else{
                            $message = "akses anda dibatasi";
                        }
                    }else{
                        $message = "akses user tidak ada dalam sistem";
                    }
                }else{
                    $message = 'login gagal, username salah';
                }
            }else{
                $message = 'kode unit tidak ada dalam sistem';
            }
        }

        $this->logUser($username,'/login','login kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ],$code);
    }

    public function getUserInfo($userid,$kdmut){
        $user = User::where('active','=',1)
                ->where('userid','=',$userid)
                ->first();
        $user_akses = DB::table('if_muser as u')
                ->join('if_muser_akses as a','u.akses','=','a.id_akses')
                ->where('u.userid',$userid)
                ->select('a.id_akses','a.nama_akses as akses')
                ->first();
        $unit = DB::table('if_mlayanan')
                ->where('kode_mutasi','=',$kdmut)
                ->value('layanan');
        $data_user = $user->toArray();
        $arr_unit = [
            "kode_unit" => $kdmut,
            "unit" => $unit,
            "id_akses" => $user_akses->id_akses,
            "akses" => $user_akses->akses,
        ];
        $data = array_merge($data_user,$arr_unit);
        return $data;
    }

    public function getProfilUser($userid){
        $data = DB::table('if_users_api as api')
                ->join('if_muser as u','u.userid','=','api.userid')
                ->join('if_muser_akses as a','a.id_akses','=','u.akses')
                ->where('u.userid','=',$userid)
                ->where('a.active','=',1)
                ->where('api.active','=',1)
                ->select('api.userid',DB::raw("isnull(api.email,'') as email"),'u.nama','a.nama_akses as akses')
                ->first();
        return $data;
    }

    public function getUser(Request $request){
        $user = Auth::user();
        $data = null;
        $code = 401;
        $message = '';
        $status = 'error';
        if($user && $user->id != 353){
            $data = $this->getUserInfo($user->userid,$request->kdmut);
            $message = 'data user';
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
    
    public function profilUser(){
        $user = Auth::user();
        $data = null;
        $code = 401;
        $message = '';
        $status = 'error';
        if($user && $user->id != 353){
            $data = $this->getProfilUser($user->userid);
            $message = 'data profil user';
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

    public function updateDepo(Request $request){
        $user = Auth::user();
        $obj = null;
        $code = 401;
        $status = 'error';
        $message = '';
        $device_info = $request->device_info;
        // return array($user);
        // non user guest
        if($user && $user->id != 353){
            $userid = $user->userid;
            $user_akses = DB::table('if_muser as u')
                            ->join('if_muser_akses as a','u.akses','=','a.id_akses')
                            ->where('u.userid',$userid)
                            ->select('a.id_akses','a.nama_akses as akses')
                            ->first();
            $unit = DB::table('if_mlayanan')
                ->where('kode_mutasi','=',$request->kdmut)
                ->value('layanan');
                        
            $obj = json_decode($user);
            $obj->kode_unit = $request->kdmut;
            $obj->unit = $unit;
            $obj->id_akses = $user_akses->id_akses;
            $obj->akses = $user_akses->akses;
            $code = 200;
            $status = 'success';
            $message = 'update depo berhasil';
        }else{
            $message = 'tidak ada authentikasi user';
        }

        $this->logUser($user->id,'/update-depo','update depo kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => 200,
            'status' => $status,
            'message' => $message,
            'data' => $obj,
        ]);
    }

    public function updateProfil(Request $request){
        $status = 'error';
        $message = '';
        $code = 401;
        $user = Auth::user();
        $device_info = $request->device_info;
        if($user && $user->id != 353){
            $user->name = $request->nama;
            $user->email = $request->email;
            $user->save();
            DB::update('update if_muser set nama = :nama where userid = :userid', 
            ['nama' => $request->nama,'userid' => $user->userid]);
            $status = 'success';
            $message = 'update profil berhasil';
            $data = $this->getProfilUser($user->userid);
            $code = 200;
        }else{
            $message = 'tidak ada authentikasi user';
        }
        
        $this->logUser($user->id,'/update-profil','update profil user kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function updatePassword(Request $request){
        $status = 'error';
        $message = '';
        $code = 401;
        $validate = Validator::make($request->all(),[
            'password' => 'required',
            'newPassword' => 'required|size:6',
            'confirmPassword' => 'required|same:newPassword',
        ]);
        $device_info = $request->device_info;
        $user = Auth::user();
        if($validate->fails()){
            $message = $validate->errors();
        }else{
            if($user && $user->id != 353){
               if(Hash::check($request->password, $user->password)){
                    $user->password = Hash::make($request->newPassword);
                    $user->save();
                    DB::update('update if_muser set pass = :password_baru where userid = :userid', 
                    ['password_baru' => $request->newPassword,'userid' => $user->userid]);
                    $status = 'success';
                    $message = 'update password berhasil';
                    $code = 200;
               }else{
                    $message = 'password lama tidak sesuai';
               }
            }else{
                $message = 'authentikasi user tidak ditemukan';
            }
        }

        $this->logUser($user->id,'/update-password','update password kartu stok',json_encode($request->all()),$message,$device_info);
        
        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }

    public function listUser(){
        $status = 'error';
        $message = '';
        $code = 401;
        $data = [];
        $user = Auth::user();
        if($user && $user->id != 353){
            $akses_id = DB::table('if_muser')
                        ->where('userid','=',$user->userid)
                        ->value('akses');
            if($akses_id==0){
                $data = DB::table('if_users_api as a')
                        ->join('if_muser as b','a.userid','=','b.userid')
                        ->join('if_muser_akses as c','c.id_akses','=','b.akses')
                        ->where('a.active','=',1)
                        ->whereIn('b.akses',[1,2])
                        ->select('a.userid','b.nama',DB::raw("isnull(a.email,'') as email"),'c.nama_akses as akses')
                        ->orderBy('b.nama')
                        ->get();
                $message = 'data list user';
                $code = 200;
                $status = 'success';
            }else{
                $message = 'user ini tidak memiliki akses';
            }
        }else{
            $message = 'authentikasi user tidak ditemukan';
        }

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function updateUser(Request $request){
        $status = 'error';
        $message = '';
        $code = 401;
        $validate = Validator::make($request->all(), 
                    ['nama' => 'required|string|max:100',
                    'email' => 'required|email',]
                );
        $device_info = $request->device_info;
        $user = Auth::user();
        if($validate->fails()){
            $message = $validate->errors();
        }else{
            if($user && $user->id != 353){
                $userid = $request->userid;
                $nama = $request->nama;
                $email = $request->email;
                if($user){
                    $akses_id = DB::table('if_muser')
                            ->where('userid','=',$user->userid)
                            ->value('akses');
                    if($akses_id==0){
                        DB::update('update if_users_api set name = ?,email = ? where userid = ?', 
                        [$nama,$email,$userid]);
                        DB::update('update if_muser set nama = ? where userid = ?', 
                        [$nama,$userid]);
                        $message = 'update user berhasil';
                        $code = 200;
                        $status = 'success';
                    }else{
                        $message = 'user ini tidak memiliki akses';
                    }
                }else{
                    $message = 'authentikasi user tidak ditemukan';
                }
            }else{
                $message = 'user ini tidak memiliki akses';
            }
        }

        $this->logUser($user->id,'/registrasi','update user kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }

    public function resetPassword(Request $request){
        $status = 'error';
        $message = '';
        $code = 401;
        $data = '';
        $user = Auth::user();
        $userid = $request->userid;
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            $akses_id = DB::table('if_muser')
                        ->where('userid','=',$user->userid)
                        ->value('akses');
            if($akses_id==0){
                $newPass = \Illuminate\Support\Str::random(6);
                DB::update('update if_muser set pass = ? where userid = ?', [$newPass,$userid]);
                DB::update('update if_users_api set password = ? where userid = ?', [Hash::make($newPass),$userid]);
                $message = "reset password berhasil.<br>Password baru ".$request->userid." : ".$newPass;
                $code = 200;
                $status = 'success';
                $data = ['password_baru' => $newPass];
            }else{
                $message = 'user ini tidak memiliki akses';
            }
        }else{
            $message = 'authentikasi user tidak ditemukan';
        }

        $this->logUser($user->id,'/registrasi','reset password kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function hapusUser(Request $request){
        $status = 'error';
        $message = '';
        $code = 401;
        $user = Auth::user();
        $userid = $request->userid;
        $device_info = $request->device_info;

        if($user && $user->id != 353){
            $akses_id = DB::table('if_muser')
                        ->where('userid','=',$user->userid)
                        ->value('akses');
            if($akses_id==0){
                DB::update('update if_users_api set active = 0 where userid = ?', [$userid]);
                $message = 'hapus user berhasil';
                $code = 200;
                $status = 'success';
            }else{
                $message = 'user ini tidak memiliki akses';
            }
        }else{
            $message = 'authentikasi user tidak ditemukan';
        }

        $this->logUser($user->id,'/registrasi','hapus user kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ]);
    }

    public function logout(Request $request){
        $user = Auth::user();
        $status = 'error';
        $message = '';
        $device_info = $request->device_info;
        if  ($user && $user->id != 353){
            $user->api_token=null;
            $user->save();
            $status = 'success';
            $message = 'logout berhasil';
        }else{
            $message = 'authentikasi user tidak valid';
        }

        $this->logUser($user->id,'/keluar','logout kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => 200,
            'status' => $status,
            'message' => $message,
            'data' => null,
        ],200);
    }
    
    public function register(Request $request){
        $validate = Validator::make($request->all(), 
                    ['userid' => 'required|string|max:35|unique:if_users_api',
                    'nama' => 'required|string|max:100',
                    'email' => 'required|email',]
                );
        $status = 'error';
        $message = '';
        $data = null;
        $code = 400;
        $user = Auth::user();
        $device_info = $request->device_info;

        if ($validate->fails()){
            $message = $validate->errors();
        }else{
            if($user && $user->id != 353){
                $akses_id = DB::table('if_muser')
                ->where('userid','=',$user->userid)
                ->value('akses');
                if($akses_id==0){
                    $password = \Illuminate\Support\Str::random(6);
                    $user_data = DB::table('if_muser')->where('userid',$request->userid)->first();
                    if ($user_data==null){
                        DB::insert('insert into if_muser (userid, nama,pass,akses) 
                        values (?, ?, ?, ?)', 
                        [$request->userid, $request->nama,$password,2]);
                    }

                    $user_baru = User::create([
                        'userid' => $request->userid,
                        'name' => $request->nama,
                        'email' => $request->email,
                        'password' => Hash::make($password),
                        'created_by' => $user->id,
                    ]);

                    if ($user_baru){
                        $status = 'success';
                        $message = 'registrasi user berhasil. Password anda : '.$password;
                        $data = $user_baru->toArray();
                        $code = 200;
                    }else{
                        $message = 'registrasi gagal';
                    }
                }else{
                    $message = 'user ini tidak memiliki akses';
                }
            }else{
                $message = 'user ini tidak memiliki akses';
            }
        }

        $this->logUser($user->id,'/registrasi','registrasi user kartu stok',json_encode($request->all()),$message,$device_info);

        return response()->json([
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ],$code);
    }

    public function redirectToGoogleProvider(){
        $parameters = [
            'access_type' => 'offline',
            'approval_prompt' => 'force'
        ];
        return Socialite::driver('google')->scopes(["https://www.googleapis.com/auth/drive"])->with($parameters)->redirect();
    }
 
    public function handleProviderGoogleCallback(){
        $auth_user = Socialite::driver('Google')->user();
        $data = [
            'api_token' => $auth_user->token,
            'expires_in' => $auth_user->expiresIn,
            'name' => $auth_user->name
        ];
        if($auth_user->refreshToken){
            $data['refresh_token'] = $auth_user->refreshToken;
        }
        $user = User::updateOrCreate(
            [
                'email' => $auth_user->email
            ],
            $data
        );
        Auth::login($user, true);
        return redirect()->to('/'); // Redirect to a secure page
    }
}