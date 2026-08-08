@extends('layout.Nav')

@section('content')
<style>
    .scroll-table {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }

    .scroll-table table {
        margin-bottom: 0;
    }

    .scroll-table thead th {
        position: sticky;
        top: 0;
        background-color: #343a40; /* Matching your dark theme */
        z-index: 10;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
    }
    
    /* Loading indicator */
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 100;
    }
</style>

<div class="container">
    <h2 class="title-box">Mutasi Stock Barang</h2>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter mr-2"></i>Filter
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('stock.mutasi') }}">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <select class="form-control" name="kolom">
                                    <option value="kode_barang" {{ $kolom == 'kode_barang' ? 'selected' : '' }}>Kode Barang</option>
                                    <option value="nama" {{ $kolom == 'nama' ? 'selected' : '' }}>Nama Barang</option>
                                </select>
                            </div>
                            <input type="text" name="value" class="form-control" placeholder="Filter..." value="{{ $value ?? '' }}">
                            <div class="input-group-append">
                                <button type="button" id="clearSearchValue" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">Dari</span>
                            </div>
                            <input type="date" name="tanggal_awal" class="form-control" value="{{ $tanggal_awal ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">Sampai</span>
                            </div>
                            <input type="date" name="tanggal_akhir" class="form-control" value="{{ $tanggal_akhir ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search mr-1"></i> Cari
                            </button>
                            <button type="button" id="resetFilter" class="btn btn-secondary">
                                <i class="fas fa-sync-alt mr-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-4">
                        <label>Grup Barang</label>
                        <select class="form-control" name="grup_barang">
                            <option value="">-- Semua Grup --</option>
                            @php $grupList = \App\Models\GrupBarang::orderBy('name')->get(); @endphp
                            @foreach($grupList as $g)
                                <option value="{{ $g->id }}" {{ request('grup_barang') == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <div class="btn-group">
                            <a href="{{ route('stock.print.good') }}?kolom={{ $kolom }}&value={{ $value }}" target="_blank" class="btn btn-success">
                                <i class="fas fa-print mr-1"></i> Cetak Good Stock
                            </a>
                            <a href="{{ route('stock.mutasi') }}" class="btn btn-danger">
                                <i class="fas fa-times mr-1"></i> Exit
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Display Barang -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-layer-group mr-2"></i>Display Barang
            </div>
            <div class="input-group w-50">
                <input type="text" id="quickSearch" class="form-control" placeholder="Cari cepat...">
                <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="button" id="clearQuickSearch">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="position-relative">
                <div id="loadingItems" class="loading-overlay" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
                <div class="scroll-table">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="thead-dark">
                            <tr>
                                <th>Kode Barang</th>
                                <th>Nama</th>
                                <th>Good Stock</th>
                                <th>Satuan</th>
                                <th>Bad Stock</th>
                                <th width="150px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="stockTableBody">
                            @forelse($stocks as $stock)
                                <tr>
                                    <td>{{ $stock->kode_barang }}</td>
                                    <td>{{ $stock->nama_barang }}</td>
                                    <td>{{ number_format($stock->good_stock) }}</td>
                                    <td>{{ $stock->satuan }}</td>
                                    <td>{{ number_format($stock->bad_stock) }}</td>
                                    <td>
                                        <a href="{{ route('stock.mutasi', [
                                            'kolom' => $kolom, 
                                            'value' => $value,
                                            'tanggal_awal' => $tanggal_awal,
                                            'tanggal_akhir' => $tanggal_akhir,
                                            'grup_barang' => $grupId ?? request('grup_barang'),
                                            'selected_kode_barang' => $stock->kode_barang
                                        ]) }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-search mr-1"></i> Lihat Mutasi
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">Belum ada data stock</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Mutasi Stock -->
    @if(isset($selectedStock))
        <div class="card">
            <div class="card-header">
                <i class="fas fa-exchange-alt mr-2"></i>Mutasi Stock - {{ $selectedStock->kode_barang }} ({{ $selectedStock->nama_barang }})
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
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($tanggal_awal || (isset($openingBalance) && abs($openingBalance) > 0.0001))
                                <tr class="table-danger font-weight-bold">
                                    <td colspan="7">Saldo Awal</td>
                                    <td class="text-right">{{ number_format($openingBalance, 2) }}</td>
                                </tr>
                            @endif
                            
                            @php $runningTotal = $openingBalance; @endphp
                            
                            @forelse($mutations as $index => $mutation)
                                @php 
                                    $runningTotal = $mutation->total;
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $mutation->no_transaksi }}</td>
                                    <td>{{ \Carbon\Carbon::parse($mutation->tanggal)->format('d M Y H:i') }}</td>
                                    <td>{{ $mutation->no_nota ?: '-' }}</td>
                                    <td>{{ $mutation->supplier_customer }}</td>
                                    <td>{{ number_format($mutation->plus) }}</td>
                                    <td>{{ number_format($mutation->minus) }}</td>
                                    <td>{{ number_format($mutation->total) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">Belum ada data mutasi untuk barang ini</td>
                                </tr>
                            @endforelse
                            @if(isset($selectedStock) && $mutations->isNotEmpty())
                                <tr class="table-success font-weight-bold">
                                    <td colspan="7">Good Stock (Master / Mutasi)</td>
                                    <td class="text-right">{{ number_format($selectedStock->good_stock) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let debounceTimer;
        
        // Debounce function for search
        function debounce(func, delay) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(func, delay);
        }

        // Quick search filter
        $('#quickSearch').on('input', function() {
            const input = $(this).val().toLowerCase();
            debounce(function() {
                filterStocks(input);
            }, 300); // 300ms delay for better performance
        });
        
        function filterStocks(keyword) {
            $('#loadingItems').show();
            
            setTimeout(() => {
                $('#stockTableBody tr').each(function() {
                    const kodeBarang = $(this).find('td:nth-child(1)').text().toLowerCase();
                    const namaBarang = $(this).find('td:nth-child(2)').text().toLowerCase();
                    const visible = kodeBarang.includes(keyword) || namaBarang.includes(keyword);
                    $(this).toggle(visible);
                });
                $('#loadingItems').hide();
            }, 10);
        }
        
        // Clear quick search
        $('#clearQuickSearch').on('click', function() {
            $('#quickSearch').val('');
            filterStocks('');
        });
        
        // Clear search value
        $('#clearSearchValue').on('click', function() {
            $('input[name="value"]').val('');
        });
        
        // Reset filter
        $('#resetFilter').on('click', function() {
            window.location = '{{ route('stock.mutasi') }}';
        });
    });
</script>
@endsection