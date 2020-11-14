<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function(){
    // Route::post('logincentra','CentraFinance\BillingController@loginCentra');
    // create billing bpjs
    Route::post('billing-centra','CentraFinance\BillingController@createBilling');
    //void centra
    Route::post('gl-void','CentraFinance\FinanceController@voidLedgerCentra');

    // create customer
    Route::post('list-trading-partner','Globals\DebiturController@listTradingPartner');
    Route::post('list-partner-type','Globals\DebiturController@listPartnerType');
    Route::post('list-partner','Globals\DebiturController@listPartner');
    Route::post('list-bank','Globals\DebiturController@listBank');
    Route::post('list-bank-account','Globals\DebiturController@listBankAccount');
    Route::post('save-customer','Globals\DebiturController@saveDebitur');

    //send email
    // Route::post('kirim-email','Email\MailController@sentMail');
    Route::post('kirim-email','Email\MailController@directSentMail');

    // api google
    Route::get('login/google', 'AuthController@redirectToGoogleProvider');
    Route::get('login/google/callback', 'AuthController@handleProviderGoogleCallback');
    Route::get('drive', 'CloudStorage\DriveController@getDrive'); // retreive folders 
    Route::get('drive/upload', 'CloudStorage\DriveController@uploadFile'); // File upload form
    Route::post('drive/upload', 'CloudStorage\DriveController@uploadFile'); // Upload file to Drive from Form
    Route::get('drive/create', 'CloudStorage\DriveController@create'); // Upload file to Drive from Storage
    Route::get('drive/delete/{id}', 'CloudStorage\DriveController@deleteFile'); // Delete file or folder

    // api dropboox
    Route::get('dropbox', 'CloudStorage\DropboxController@index');
    Route::get('dropbox/lihat/{file}', 'CloudStorage\DropboxController@view');
    Route::get('dropbox/unduh/{file}', 'CloudStorage\DropboxController@download');
    Route::get('dropbox/hapus/{file}', 'CloudStorage\DropboxController@delete');
    Route::post('dropbox/upload', 'CloudStorage\DropboxController@store');

    Route::post('kartu-stok/generate-password',function(Request $request){
        return Hash::make($request->password);
    });

    // kartu stok
    Route::post('kartu-stok/akses-menu','StockcardMaster\MasterKartuStok@listMenuNavigationAkses');
    Route::middleware('auth:api')->group(function(){
        //create jurnal centra
        Route::post('ledger-centra','CentraFinance\FinanceController@createJurnalKeuangan');

        // using token guest
        Route::post('kartu-stok/list-user-login','Globals\AuthUserController@listUserLogin');
        Route::post('kartu-stok/list-depo-user','Globals\AuthUserController@listDepoPerUser');

        Route::post('kartu-stok/login','Globals\AuthUserController@login');
        Route::post('kartu-stok/list-depo','StockcardMaster\MasterKartuStok@listDepo');
        Route::post('kartu-stok/random-lemari','StockcardMaster\LemariController@randomData');
        Route::post('kartu-stok/top-obat','StockcardMaster\MasterKartuStok@topObat');
        Route::post('kartu-stok/lemari','StockcardMaster\LemariController@lemariAll');
        Route::post('kartu-stok/lemari-all','StockcardMaster\LemariController@lemariAllPage');
        Route::post('kartu-stok/obat','StockcardMaster\MasterKartuStok@ObatAll');
        Route::post('kartu-stok/info-lemari/{id}','StockcardMaster\LemariController@infoLemari');
        Route::post('kartu-stok/info-obat/{id}','StockcardMaster\MasterKartuStok@infoObatKartuStok');
        Route::post('kartu-stok/cari-obat','StockcardMaster\MasterKartuStok@SearchObat');

        // using token login
        Route::post('kartu-stok/update-depo','Globals\AuthUserController@updateDepo');

        Route::post('kartu-stok/profil-user','Globals\AuthUserController@profilUser');
        Route::post('kartu-stok/update-profil','Globals\AuthUserController@updateProfil');

        Route::post('kartu-stok/list-user','Globals\AuthUserController@listUser');
        Route::post('kartu-stok/info-user','Globals\AuthUserController@getUser');
        Route::post('kartu-stok/registrasi-user','Globals\AuthUserController@register');
        Route::post('kartu-stok/update-user','Globals\AuthUserController@updateUser');
        Route::post('kartu-stok/reset-password','Globals\AuthUserController@resetPassword');
        Route::post('kartu-stok/hapus-user','Globals\AuthUserController@hapusUser');
        Route::post('kartu-stok/update-password','Globals\AuthUserController@updatePassword');

        Route::post('kartu-stok/list-lemari-perbarang','StockcardMaster\LemariController@listLemariPerBarangPerDepo');
        Route::post('kartu-stok/list-lemari-array-barang','StockcardMaster\LemariController@listLemariArrayBarangPerDepo');
        Route::post('kartu-stok/simpan-lemari','StockcardMaster\LemariController@saveLemari');
        Route::post('kartu-stok/update-lemari','StockcardMaster\LemariController@updateLemari');
        Route::post('kartu-stok/hapus-lemari','StockcardMaster\LemariController@deleteLemari');

        Route::post('kartu-stok/list-barang-tanpa-rak','StockcardMaster\MasterKartuStok@ObatKosongRak');
        Route::post('kartu-stok/registrasi-barang','StockcardTransaction\MutasiBarang@saveRegistrasiBarang');

        Route::post('kartu-stok/list-obat','StockcardMaster\MasterKartuStok@listBarangDepoAuth');
        Route::post('kartu-stok/list-barang-depo','StockcardMaster\MasterKartuStok@listBarangPerDepo');
        
        Route::post('kartu-stok/jenis-transaksi','StockcardTransaction\MutasiBarang@listJenisTransaksi');
        Route::post('kartu-stok/list-transaksi','StockcardTransaction\ResepFarmasi@listTransaksiFarmasi');
        Route::post('kartu-stok/cari-transaksi-farmasi','StockcardTransaction\ResepFarmasi@cariTransaksiperKode');
        Route::post('kartu-stok/jenis-transaksi-centra','StockcardTransaction\MutasiBarang@listJenisTransaksiCentra');
        Route::post('kartu-stok/list-transaksi-kartu-stok','StockcardTransaction\MutasiBarang@listTransaksiKartuStok');
        Route::post('kartu-stok/cancel-proses-kartu-stok-resep','StockcardTransaction\MutasiBarang@cancelProsesKartuStok');
        Route::post('kartu-stok/simpan-transfer-stok-resep','StockcardTransaction\MutasiBarang@saveMutasiFarmasi');

        Route::post('kartu-stok/list-master-obat-depo','StockcardMaster\MasterKartuStok@listMasterBarangPerDepo');
        Route::post('kartu-stok/list-lemari-per-depo-khusus','StockcardMaster\LemariController@listLemariPerPerDepoExcept');
        Route::post('kartu-stok/list-transaksi-transfer-stok','StockcardTransaction\MutasiBarang@listTransaksiTransferStok');
        Route::post('kartu-stok/simpan-transfer-stok','StockcardTransaction\MutasiBarang@saveTransferStok');
        // Route::post('kartu-stok/detail-transaksi','StockcardTransaction\MutasiBarang@rincianTransaksiFarmasi');
        
        Route::post('kartu-stok/list-transaksi-dko','StockcardTransaction\DKOGudang@listDKOGudang');
        Route::post('kartu-stok/cari-transaksi-dko','StockcardTransaction\DKOGudang@cariDKOperKode');
        Route::post('kartu-stok/simpan-transaksi-dko','StockcardTransaction\MutasiBarang@saveDKOGudang');

        Route::post('kartu-stok/list-transaksi-pembelian-langsung','StockcardTransaction\PembelianLangsung@listPembelianLangsung');
        Route::post('kartu-stok/cari-transaksi-pembelian-langsung','StockcardTransaction\PembelianLangsung@cariPembelianLangsungperKode');
        Route::post('kartu-stok/simpan-transaksi-pembelian-langsung','StockcardTransaction\MutasiBarang@savePembelianLangsung');

        Route::post('kartu-stok/list-transaksi-mutasi-antar-unit','StockcardTransaction\MutasiAntarUnitController@listTransaksiMutasiFarmasi');
        Route::post('kartu-stok/cari-transaksi-mutasi-antar-unit','StockcardTransaction\MutasiAntarUnitController@cariTransaksiperKode');
        Route::post('kartu-stok/simpan-transaksi-mutasi-antar-unit','StockcardTransaction\MutasiBarang@saveMutasiAntarUnit');

        Route::post('kartu-stok/validasi-hapus-transaksi/{id_transaksi}','StockcardTransaction\MutasiBarang@cekExistingTransaksiKartuStok');
        Route::post('kartu-stok/hapus-transaksi-kartu-stok','StockcardTransaction\MutasiBarang@hapusTransaksiKartuStok');

        Route::post('kartu-stok/report-transaksiku','StockcardTransaction\MutasiBarang@laporanRincianMyTransaksi');
        Route::post('kartu-stok/report-transaksi','StockcardTransaction\MutasiBarang@laporanRincianTransaksi');
        Route::post('kartu-stok/report-movement','StockcardTransaction\MutasiBarang@laporanMovementBarang');

        Route::post('barang/list-per-depo-per-debitur','PharmacyMaster\MasterBarangController@getListBarangPerDepo');
        Route::post('signa/list-signa','PharmacyMaster\MasterSignaController@getListSigna');
        Route::post('debitur/list-debitur','Globals\DebiturController@getListDebitur');
        Route::post('debitur/list-dinas','Globals\DebiturController@getListDinasPerDebitur');
        Route::post('dokter/list-dokter','Globals\DokterController@getListDokterAll');
        Route::post('dokter/list-dokter-spesialis','Globals\DokterController@getListDokterSpesialis');

        Route::post('udd/list-ruang-pasien-inap','InpatientData\InfoPasienRIController@getListRuanganRawatInapPasien');
        Route::post('udd/list-pasien-inap','InpatientData\InfoPasienRIController@getListPasienRawatInapAktif');
        Route::post('udd/info-pasien-inap','InpatientData\InfoPasienRIController@cariRegisterRawatInap');

        Route::post('kartu-stok/logout','Globals\AuthUserController@logout');
    });
});
// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
