@extends('layout.Nav')

@section('content')
<style>
    .scroll-table {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }
    .scroll-table table { margin-bottom: 0; }
    .scroll-table thead th {
        position: sticky;
        top: 0;
        background-color: #343a40;
        z-index: 10;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="container">
    <h2 class="title-box">Pergerakan Barang - {{ $summary['kode_barang'] }}</h2>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-box mr-2"></i>{{ $summary['nama_barang'] }}
            </div>
            <a href="{{ route('laporan.stok', ['show_pergerakan' => 1]) }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('laporan.stok.pergerakan', $summary['kode_barang']) }}">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Dari</span></div>
                            <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Sampai</span></div>
                            <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <select name="jenis_pergerakan" class="form-control">
                            <option value="">-- Semua --</option>
                            <option value="masuk" {{ $jenisPergerakan == 'masuk' ? 'selected' : '' }}>Masuk (+)</option>
                            <option value="keluar" {{ $jenisPergerakan == 'keluar' ? 'selected' : '' }}>Keluar (-)</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Cari</button>
                            <a href="{{ route('laporan.stok.pergerakan', $summary['kode_barang']) }}" class="btn btn-secondary"><i class="fas fa-sync-alt mr-1"></i> Reset</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-exchange-alt mr-2"></i>Mutasi Stock - {{ $summary['kode_barang'] }} ({{ $summary['nama_barang'] }})
        </div>
        <div class="card-body p-0">
            <div class="scroll-table">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th>No.</th>
                            <th>No Transaksi</th>
                            <th>Tanggal</th>
                            <th>No Nota/No Order</th>
                            <th>Supp./Cust.</th>
                            <th>+</th>
                            <th>-</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pergerakanData as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row['no_transaksi'] }}</td>
                                <td>{{ ($row['tanggal'] ?? '') . ' ' . ($row['waktu'] ?? '') }}</td>
                                <td>{{ $row['no_nota'] ?: '-' }}</td>
                                <td>{{ $row['supplier_customer'] }}</td>
                                <td>{{ number_format($row['qty_masuk'] ?? 0) }}</td>
                                <td>{{ number_format($row['qty_keluar'] ?? 0) }}</td>
                                <td>{{ $row['keterangan'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">Belum ada data mutasi untuk barang ini</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
