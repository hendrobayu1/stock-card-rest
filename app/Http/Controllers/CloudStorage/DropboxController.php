<?php

namespace App\Http\Controllers\CloudStorage;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Spatie\Dropbox\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;
use App\FileUpload;
// use Illuminate\Contracts\Cache\Store;

class DropboxController extends Controller
{
    // private $dropbox;
    public function __construct(){
        $this->dropbox = Storage::disk('dropbox')->getDriver()->getAdapter()->getClient();
    }

    public function index(){
    	//Mengambil semua data berkas
    	$file = FileUpload::all();
    	//ke view index bersama dengan variable berkas 
    	return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'list file upload',
            'data' => $file,
        ]);
    }

    public function store(){
    	//melakukan validasi data
    	$data = request()->validate([
            'karcis' => 'required',
            'file' => 'required|mimes:jpeg,jpg,pdf,zip',
        ]); 

    	//membuat variabel berkas
        $file = $data['file'];
        //membuat nama file
        $namafile = uniqid().'.'.$file->getClientOriginalExtension();
        //mengupload berkas
        $file->storeAs('data',$namafile, 'dropbox');
        //membuat link untuk file
        $response = $this->dropbox->createSharedLinkWithSettings('data/'.$namafile);
        $result = json_encode($response);
        //memasukan data file ke database
        // FileUpload::create([
        //     'karcis' => $data['karcis'],
        //     'nama_file' => $namafile,
        //     'ekstensi' => $file->getClientOriginalExtension(),
        //     'ukuran' => $file->getSize()
        // ]);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'upload file berhasil',
            'data' => json_decode($result)->url,
        ]);
    }

    public function view($file){
    	try {
    	    //menyiapkan link
            $link = $this->dropbox->listSharedLinks('public/file/'.$file);
            //membuat link untuk melihat file
            $raw = explode("?", $link[0]['url']);
            $file_original = $raw[0].'?raw=1';
            $tempFile = tempnam(sys_get_temp_dir(), $file);
            copy($file_original, $tempFile);
            //menampilkan file
            return response()->file($tempFile);
	        // return response()->json([
            //     'code' => 200,
            //     'status' => 'success',
            //     'message' => 'view file berhasil',
            //     'data' => file($tempFile),
            // ]);
    			
    	} catch (\Exception $e) {
             //abort jika tidak ada file
             return abort(404);
            // return response()->json([
            //     'code' => 404,
            //     'status' => 'error',
            //     'message' => 'terjadi kesalahan pemrosesan data',
            //     'data' => null,
            // ]);
    	}
    }

    public function download($file){
    	try {  		
    	    //unduh file
	        return Storage::disk('dropbox')->download('public/file/'.$file);
    			
    	} catch (\Exception $e) {	
    	    //abort jika tidak ada file
    	    return abort(404);
    	}
    }

    public function delete(FileUpload $file)
    {	
    	//hapus file di dropbox
    	$this->dropbox->delete('public/file/'.$file->nama_file);
    	//hapus data di database
    	$file->delete();

        return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'file berhasil dihapus',
                'data' => null,
            ]);
    }
}