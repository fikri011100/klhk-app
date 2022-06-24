<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Pengajuan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PengajuanController extends Controller
{
    /**
     * * View for New Pengajuan
     *
     * @return void
     */
    public function create()
    {
        return view('user.create')
            ->with('user', Auth::user());
    }
    /**
     * * Create New Pengajuan
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {
        if (!$request->has('tipe')) {
            return back()
                ->with('error', 'Tipe usaha kosong!');
        }

        $validator = Validator::make($request->all(), [
            'nama_pengajuan' => 'required',
            'perihal' => 'required'
        ]);

        if ($validator->fails()) {
            return back()
                ->with('error', 'Ada field yang kosong!');
        }

        $total_today_pengajuan_count = Pengajuan::all()
            ->where('created_at', '=', Carbon::now())->count() + 1;

        $pengajuan = new Pengajuan;
        $pengajuan->nama_pengajuan = $request->input('nama_pengajuan');
        $pengajuan->no_surat = '552/' . $total_today_pengajuan_count . '/123.4/' . Carbon::now()->format('Y');
        $pengajuan->perihal = $request->input('perihal');
        $pengajuan->skala_usaha = $request->input('tipe');
        $pengajuan->save();

        // ? Redirect to input detail pengajuan (kecil/menengah)
        if ($request->input('tipe') === 'kecil') {
            return redirect('/user/upload-file/low/' . $pengajuan->id)
                ->with('user', Auth::user());
        } else if ($request->input('tipe') === 'menengah') {
            return redirect('/user/upload-file/middle/' . $pengajuan->id)
                ->with('user', Auth::user());
        }
    }
    /**
     * * View Lower Pengajuan by Id
     *
     * @param int $id
     * @return void
     */
    public function create_low($id)
    {
        $pengajuan = DB::table('pengajuan')
            ->where('id', '=', $id)
            ->where('user_id', '=', Auth::id())
            ->get();

        // ! Prevent url param brute force
        $available = $pengajuan->count();

        if ($available < 1) {
            return abort(404);
        }
        // ! ===============================

        // ! Return 404 if not Proposal for Low

        if ($pengajuan[0]->skala_usaha !== 'kecil') {
            return abort(404);
        }

        // ! ===============================

        $file = DB::table('file_detail_pengajuan')
            ->where('id_pengajuan', '=', $id)
            ->get();

        return view('uploads.low')
            ->with('user', Auth::user())
            ->with('detail_pengajuan', $file)
            ->with('page_id', $id);
    }
    /**
     * * Store new Pengajuan by Lower Bussiness
     *
     * @param Request $request
     * @param int $id
     * @return void
     */
    public function store_low(Request $request, $id)
    {
        $pengajuan = DB::table('pengajuan')
            ->where('id', '=', $id)
            ->where('user_id', '=', Auth::id())
            ->get();

        // ! Prevent url param brute force
        $available = $pengajuan->count();

        if ($available < 1) {
            return abort(404);
        }
        // ! ===============================

        // ! Return 404 if not Proposal for Low

        if ($pengajuan[0]->skala_usaha !== 'kecil') {
            return abort(404);
        }

        // ! ===============================

        $base_filename = Auth::id() . '_' . Carbon::now()->format('Y-m-d-H-i-s');

        // ? Upload Surat Permohonan
        if ($request->hasFile('surat_permohonan')) {
            $file = $request->file('surat_permohonan');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/surat_permohonan', $filename);

            $surat_permohonan_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'surat_permohonan')
                ->get()
                ->count();

            if ($surat_permohonan_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'surat_permohonan',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload NIB
        if ($request->hasFile('nib')) {
            $file = $request->file('nib');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/nib', $filename);

            $nib_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'nib')
                ->get()
                ->count();

            if ($nib_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'nib',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload SPPL
        if ($request->hasFile('sppl')) {
            $file = $request->file('sppl');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/sppl', $filename);

            $sppl_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'sppl')
                ->get()
                ->count();

            if ($sppl_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'sppl',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload Surat Pernyataan Pengolahan
        if ($request->hasFile('surat_pernyataan_pengolahan')) {
            $file = $request->file('surat_pernyataan_pengolahan');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/surat_pernyataan_pengolahan', $filename);

            $surat_pernyataan_pengolahan_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'surat_pernyataan_pengolahan')
                ->get()
                ->count();

            if ($surat_pernyataan_pengolahan_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'surat_pernyataan_pengolahan',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload Pernyataan OSS
        if ($request->hasFile('pernyataan_oss')) {
            $file = $request->file('pernyataan_oss');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/pernyataan_oss', $filename);

            $pernyataan_oss_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'pernyataan_oss')
                ->get()
                ->count();

            if ($pernyataan_oss_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'pernyataan_oss',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        $file = DB::table('file_detail_pengajuan')
            ->where('id_pengajuan', '=', $id)
            ->get();

        return back()
            ->with('user', Auth::user())
            ->with('detail_pengajuan', $file)
            ->with('page_id', $id);
    }
    /**
     * * View for Pengajuan for Middle Bussiness
     *
     * @param int $id
     * @return void
     */
    public function create_middle($id)
    {
        $pengajuan = DB::table('pengajuan')
            ->where('id', '=', $id)
            ->where('user_id', '=', Auth::id())
            ->get();

        // ! Prevent url param brute force
        $available = $pengajuan->count();

        if ($available < 1) {
            return abort(404);
        }
        // ! ===============================

        // ! Return 404 if not Proposal for Middle

        if ($pengajuan[0]->skala_usaha !== 'menengah') {
            return abort(404);
        }

        // ! ===============================

        $file = DB::table('file_detail_pengajuan')
            ->where('id_pengajuan', '=', $id)
            ->get();

        return view('uploads.middle')
            ->with('user', Auth::user())
            ->with('detail_pengajuan', $file)
            ->with('page_id', $id);
    }
    /**
     * * Create New Pengajuan for Middle Bussiness
     *
     * @param Request $request
     * @param int $id
     * @return void
     */
    public function store_middle(Request $request, $id)
    {
        $pengajuan = DB::table('pengajuan')
            ->where('id', '=', $id)
            ->where('user_id', '=', Auth::id())
            ->get();

        // ! Prevent url param brute force
        $available = $pengajuan->count();

        if ($available < 1) {
            return abort(404);
        }
        // ! ===============================

        // ! Return 404 if not Proposal for Middle

        if ($pengajuan[0]->skala_usaha !== 'menengah') {
            return abort(404);
        }

        // ! ===============================

        $base_filename = Auth::id() . '_' . Carbon::now()->format('Y-m-d-H-i-s');

        // ? Upload NIB
        if ($request->hasFile('nib')) {
            $file = $request->file('nib');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/nib', $filename);

            $nib_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'nib')
                ->get()
                ->count();

            if ($nib_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'nib',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload Akta
        if ($request->hasFile('akta')) {
            $file = $request->file('akta');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/akta', $filename);

            $akta_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'akta')
                ->get()
                ->count();

            if ($akta_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'akta',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload SPPL
        if ($request->hasFile('sppl')) {
            $file = $request->file('sppl');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/sppl', $filename);

            $sppl_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'sppl')
                ->get()
                ->count();

            if ($sppl_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'sppl',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload Proposal
        if ($request->hasFile('proposal')) {
            $file = $request->file('proposal');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/proposal', $filename);

            $proposal_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'proposal')
                ->get()
                ->count();

            if ($proposal_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'proposal',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload Jaminan Pasokan
        if ($request->hasFile('jaminan_pasokan')) {
            $file = $request->file('jaminan_pasokan');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/jaminan_pasokan', $filename);

            $jaminan_pasokan_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'jaminan_pasokan')
                ->get()
                ->count();

            if ($jaminan_pasokan_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'jaminan_pasokan',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload Bukti Mesin
        if ($request->hasFile('bukti_mesin')) {
            $file = $request->file('bukti_mesin');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/bukti_mesin', $filename);

            $bukti_mesin_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'bukti_mesin')
                ->get()
                ->count();

            if ($bukti_mesin_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'bukti_mesin',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload Bukti Prasarana
        if ($request->hasFile('bukti_prasarana')) {
            $file = $request->file('bukti_prasarana');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/bukti_prasarana', $filename);

            $bukti_prasarana_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'bukti_prasarana')
                ->get()
                ->count();

            if ($bukti_prasarana_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'bukti_prasarana',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        // ? Upload Dokumen TKP
        if ($request->hasFile('dokumen_tkp')) {
            $file = $request->file('dokumen_tkp');
            $filename = $base_filename . "." . $file->extension();

            $file->storeAs('pengajuan/' . Auth::id() . '/dokumen_tkp', $filename);

            $dokumen_tkp_availability = DB::table('file_detail_pengajuan')
                ->where('id_pengajuan', '=', $pengajuan[0]->id)
                ->where('jenis_file', '=', 'dokumen_tkp')
                ->get()
                ->count();

            if ($dokumen_tkp_availability === 0) {
                DB::table('file_detail_pengajuan')
                    ->insert([
                        'id_pengajuan' => $pengajuan[0]->id,
                        'jenis_file' => 'dokumen_tkp',
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('file_detail_pengajuan')
                    ->where('id_pengajuan', '=', $id)
                    ->update([
                        'name' => $filename,
                        'type' => $file->extension(),
                        'size' => $file->getSize(),
                        'updated_at' => Carbon::now()
                    ]);
            }
        }

        $file = DB::table('file_detail_pengajuan')
            ->where('id_pengajuan', '=', $id)
            ->get();

        return back()
            ->with('user', Auth::user())
            ->with('detail_pengajuan', $file)
            ->with('page_id', $id);
    }
    /**
     * * Access file from URL
     *
     * @param int $id
     * @param string $filename
     * @return void
     */
    public function create_file($id, $filename)
    {
        $file = DB::table('pengajuan')
            ->join('file_detail_pengajuan', 'file_detail_pengajuan.id_pengajuan', 'pengajuan.id')
            ->where('pengajuan.user_id', '=', Auth::id())
            ->where('file_detail_pengajuan.name', '=', $filename)
            ->get();

        $komentar = DB::table('komentar_file_pengajuan')
            ->join('users', 'users.id', 'komentar_file_pengajuan.user_id')
            ->where('id_file_pengajuan', '=', $file[0]->id)
            ->get();

        Debugbar::log($komentar);

        if (count($file) === 0) {
            abort(404);
        }

        $path = 'pengajuan/' . Auth::id() . '/' . $file[0]->jenis_file . '/' . $file[0]->name;
        $file_bin = Storage::get($path);

        return view('user.detail')
            ->with('user', Auth::user())
            ->with('id', $id)
            ->with('filename', $filename)
            ->with('komentar', $komentar)
            ->with('attachment', base64_encode($file_bin));
    }
    /**
     * * Add comment to document
     *
     * @param int $id
     * @param string $filename
     * @return void
     */
    public function store_file($id, $filename, Request $request) {
        $request->validate([
            'komentar' => 'required'
        ]);

        $file = DB::table('pengajuan')
            ->join('file_detail_pengajuan', 'file_detail_pengajuan.id_pengajuan', 'pengajuan.id')
            ->where('pengajuan.user_id', '=', Auth::id())
            ->where('file_detail_pengajuan.name', '=', $filename)
            ->get();

        DB::table('komentar_file_pengajuan')
            ->insert([
                'id_file_pengajuan' => $file[0]->id,
                'user_id' => Auth::id(),
                'komentar' => $request->input('komentar')
            ]);

        return back();
    }
}
