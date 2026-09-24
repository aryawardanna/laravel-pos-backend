<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\PurchaseItem;
use App\Models\Satuan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\Facades\DataTables;

class LaporanStokController extends Controller
{
    public function index()
    {
        $satuans = Satuan::where('status', '!=', -1)->orderBy('name')->get(['id', 'name']);

        return view('pages.laporan.stok.index', compact('satuans'));
    }

    protected function baseQuery(Request $request)
    {
        $query = BahanBaku::query()
            ->select('bahan_bakus.*')
            ->leftJoin('satuans', 'satuans.id', '=', 'bahan_bakus.satuan_id')
            ->with(['satuan'])
            ->where('bahan_bakus.status', '!=', -1)
            ->orderBy('bahan_bakus.name', 'asc');

        if ($request->filled('satuan_id')) {
            $query->where('bahan_bakus.satuan_id', $request->input('satuan_id'));
        }
        if ($request->filled('status')) {
            $query->where('bahan_bakus.status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('bahan_bakus.name', 'like', '%' . $s . '%')
                    ->orWhere('bahan_bakus.code', 'like', '%' . $s . '%');
            });
        }
        if ($request->filled('kondisi')) {
            if ($request->input('kondisi') === 'menipis') {
                $query->whereColumn('bahan_bakus.stock', '<=', 'bahan_bakus.min_stock');
            } elseif ($request->input('kondisi') === 'habis') {
                $query->where('bahan_bakus.stock', '<=', 0);
            } elseif ($request->input('kondisi') === 'aman') {
                $query->whereColumn('bahan_bakus.stock', '>', 'bahan_bakus.min_stock');
            }
        }

        return $query;
    }

    protected function batchSummary($bahanIds)
    {
        $today = Carbon::today()->toDateString();
        $summary = [];
        if (empty($bahanIds)) {
            return $summary;
        }
        $batches = PurchaseItem::whereIn('bahan_baku_id', $bahanIds)
            ->where('remaining_qty', '>', 0)
            ->get(['bahan_baku_id', 'remaining_qty', 'expired_date']);
        foreach ($batches as $b) {
            $id = $b->bahan_baku_id;
            if (! isset($summary[$id])) {
                $summary[$id] = ['aktif' => 0, 'expired' => 0, 'segera' => 0];
            }
            if ($b->expired_date && $b->expired_date->toDateString() < $today) {
                $summary[$id]['expired']++;
            } else {
                $summary[$id]['aktif']++;
                if ($b->expired_date && $b->expired_date->toDateString() <= Carbon::today()->addDays(30)->toDateString()) {
                    $summary[$id]['segera']++;
                }
            }
        }

        return $summary;
    }

    public function data(Request $request)
    {
        $query = $this->baseQuery($request);

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('name', fn (BahanBaku $row) => e($row->name))
            ->addColumn('code', fn (BahanBaku $row) => $row->code ? e($row->code) : '-')
            ->addColumn('satuan_name', fn (BahanBaku $row) => $row->satuan ? e($row->satuan->name) : '-')
            ->addColumn('stock', fn (BahanBaku $row) => e(FormatQty($row->stock)))
            ->addColumn('min_stock', fn (BahanBaku $row) => e(FormatQty($row->min_stock)))
            ->addColumn('price', fn (BahanBaku $row) => e(FormatMoney($row->price)))
            ->addColumn('nilai', fn (BahanBaku $row) => e(FormatMoney((float) $row->stock * (float) $row->price)))
            ->addColumn('batch_info', function (BahanBaku $row) {
                $s = $this->batchSummary([$row->id])[$row->id] ?? ['aktif' => 0, 'expired' => 0, 'segera' => 0];

                return '<span class="badge badge-info">' . $s['aktif'] . ' batch</span>'
                    . ($s['segera'] > 0 ? ' <span class="badge badge-warning">' . $s['segera'] . ' segera ED</span>' : '')
                    . ($s['expired'] > 0 ? ' <span class="badge badge-danger">' . $s['expired'] . ' kedaluwarsa</span>' : '');
            })
            ->addColumn('kondisi', function (BahanBaku $row) {
                if ((float) $row->stock <= 0) {
                    return '<span class="badge badge-danger">Habis</span>';
                }
                if ((float) $row->stock <= (float) $row->min_stock) {
                    return '<span class="badge badge-warning">Menipis</span>';
                }

                return '<span class="badge badge-success">Aman</span>';
            })
            ->addColumn('status', fn (BahanBaku $row) => (int) $row->status === 1
                ? '<span class="badge badge-success">Aktif</span>'
                : '<span class="badge badge-secondary">Nonaktif</span>')
            ->rawColumns(['batch_info', 'kondisi', 'status'])
            ->toJson();
    }

    public function summary(Request $request)
    {
        $rows = $this->baseQuery($request)->get();
        $nilai = $rows->sum(fn ($r) => (float) $r->stock * (float) $r->price);
        $habis = $rows->filter(fn ($r) => (float) $r->stock <= 0)->count();
        $menipis = $rows->filter(fn ($r) => (float) $r->stock > 0 && (float) $r->stock <= (float) $r->min_stock)->count();

        return response()->json([
            'total_bahan' => $rows->count(),
            'stok_habis' => $habis,
            'stok_menipis' => $menipis,
            'nilai_formatted' => 'Rp ' . FormatMoney($nilai),
        ]);
    }

    public function export(Request $request)
    {
        $data = $this->reportData($request);
        $filename = 'laporan-stok-' . Carbon::now()->format('Ymd-His') . '.xls';

        return response()
            ->view('pages.laporan.stok.export', $data, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
    }

    public function print(Request $request)
    {
        return view('pages.laporan.stok.print', $this->reportData($request));
    }

    protected function reportData(Request $request): array
    {
        $rows = $this->baseQuery($request)->get();
        $nilai = $rows->sum(fn ($r) => (float) $r->stock * (float) $r->price);
        $batch = $this->batchSummary($rows->pluck('id')->all());

        return [
            'rows' => $rows,
            'batch' => $batch,
            'totalNilai' => (float) $nilai,
            'totalBahan' => $rows->count(),
            'stokHabis' => $rows->filter(fn ($r) => (float) $r->stock <= 0)->count(),
            'stokMenipis' => $rows->filter(fn ($r) => (float) $r->stock > 0 && (float) $r->stock <= (float) $r->min_stock)->count(),
            'generatedAt' => Carbon::now()->format('d F Y H:i'),
        ];
    }
}
