<?php

namespace App\Http\Controllers\Dokter;

use App\Http\Controllers\Controller;
use App\Models\DaftarPoli;
use App\Models\DetailPeriksa;
use App\Models\Obat;
use App\Models\Periksa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PeriksaPasienController extends Controller
{
    public function index()
    {
        $dokterId = Auth::id();

        $daftarPasien = DaftarPoli::with(['pasien', 'jadwalPeriksa', 'periksas'])
            ->whereHas('jadwalPeriksa', function ($query) use ($dokterId) {
                $query->where('id_dokter', $dokterId);
            })
            ->orderBy('no_antrian')
            ->get();

        return view('dokter.periksa-pasien.index', compact('daftarPasien'));
    }

    public function create(int $id)
    {
        $obats = Obat::all();
        return view('dokter.periksa-pasien.create', compact('obats', 'id'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'obat_json' => 'required',
            'catatan' => 'nullable|string',
            'biaya_periksa' => 'required|integer',
        ]);

        $obatIds = json_decode($request->obat_json, true);

        if (!$obatIds || !is_array($obatIds)) {
            return back()->with('message', 'Pilih minimal satu obat.')->with('type', 'danger');
        }

        $obats = Obat::whereIn('id', $obatIds)->get();

        foreach ($obats as $obat) {
            if ($obat->stok <= 0) {
                return back()
                    ->with('message', "Obat {$obat->nama_obat} stok habis. Silakan pilih obat lain.")
                    ->with('type', 'danger');
            }
        }

        $periksa = Periksa::create([
            'id_daftar_poli' => $request->id_daftar_poli,
            'tgl_periksa' => now(),
            'catatan' => $request->catatan,
            'biaya_periksa' => $request->biaya_periksa + 150000,
        ]);

        foreach ($obats as $obat) {
            DetailPeriksa::create([
                'id_periksa' => $periksa->id,
                'id_obat' => $obat->id,
            ]);

            $obat->decrement('stok', 1);
        }

        return redirect()->route('periksa-pasien.index')->with('message', 'Data periksa berhasil disimpan.')->with('type', 'success');
    }
}
