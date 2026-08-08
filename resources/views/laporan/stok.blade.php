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
        background-color: #343a40;
        z-index: 10;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
    }

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
    <h2 class="title-box">{{ $pageTitle ?? 'Laporan Stok' }}</h2>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter mr-2"></i>Filter
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('laporan.stok') }}">
                @if($showBatches)
                    <input type="hidden" name="show_batches" value="1">
                @endif
                @if($showPergerakan)
                    <input type="hidden" name="show_pergerakan" value="1">
                @endif

                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <select class="form-control" name="kolom">
                                    <option value="kode_barang" {{ ($kolom ?? '') == 'kode_barang' ? 'selected' : '' }}>Kode Barang</option>
                                    <option value="nama" {{ ($kolom ?? '') == 'nama' ? 'selected' : '' }}>Nama Barang</option>
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
                                <option value="{{ $g->id }}" {{ ($grupId ?? request('grup_barang')) == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
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
                            <a href="{{ route('laporan.stok', array_filter(['show_batches' => $showBatches ? 1 : null, 'show_pergerakan' => $showPergerakan ? 1 : null])) }}" class="btn btn-danger">
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
                                    <td>
                                        {{ number_format($stock->good_stock) }}
                                        @if(!empty($stock->good_stock_mixed))
                                            <br><small class="text-muted">{{ $stock->good_stock_mixed }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $stock->satuan }}</td>
                                    <td>
                                        {{ number_format($stock->bad_stock) }}
                                        @if(!empty($stock->bad_stock_mixed) && (float) $stock->bad_stock != 0)
                                            <br><small class="text-muted">{{ $stock->bad_stock_mixed }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('laporan.stok', array_filter([
                                            'kolom' => $kolom,
                                            'value' => $value,
                                            'tanggal_awal' => $tanggal_awal,
                                            'tanggal_akhir' => $tanggal_akhir,
                                            'grup_barang' => $grupId ?? request('grup_barang'),
                                            'selected_kode_barang' => $stock->kode_barang,
                                            'show_batches' => $showBatches ? 1 : null,
                                            'show_pergerakan' => $showPergerakan ? 1 : null,
                                        ])) }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-search mr-1"></i>
                                            @if($showBatches)
                                                Lihat Batch
                                            @else
                                                Lihat Mutasi
                                            @endif
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

    @if(isset($selectedStock))
        @if($showBatches)
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-layer-group mr-2"></i>Detail Batch - {{ $selectedStock->kode_barang }} ({{ $selectedStock->nama_barang }})
                    <span class="float-right">
                        Good Stock (Master/Mutasi): <strong>{{ number_format($selectedStock->good_stock) }}</strong>
                        @if(!empty($selectedStock->good_stock_mixed))
                            <small class="text-muted">({{ $selectedStock->good_stock_mixed }})</small>
                        @endif
                        &nbsp;|&nbsp;
                        Total Qty Sisa Batch: <strong>{{ number_format($batches->sum('qty_sisa')) }}</strong>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="scroll-table">
                        <table class="table table-bordered table-striped mb-0">
                            <thead class="thead-dark">
                                <tr>
                                    <th>No.</th>
                                    <th>Batch</th>
                                    <th>Tgl Masuk</th>
                                    <th>Qty Masuk</th>
                                    <th>Qty Sisa</th>
                                    <th>Harga Beli</th>
                                    <th>Nilai Sisa</th>
                                    <th>Tipe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($batches as $index => $batch)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>#{{ $batch->id }}</td>
                                        <td>{{ optional($batch->tanggal_masuk)->format('d M Y') }}</td>
                                        <td>{{ number_format($batch->qty_masuk) }}</td>
                                        <td>{{ number_format($batch->qty_sisa) }}</td>
                                        <td>{{ number_format($batch->harga_beli) }}</td>
                                        <td>{{ number_format($batch->qty_sisa * $batch->harga_beli) }}</td>
                                        <td>{{ $batch->tipe_batch ?? 'pembelian' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">Belum ada batch tersisa untuk barang ini</td>
                                    </tr>
                                @endforelse
                                @if($batches->isNotEmpty())
                                    <tr class="table-success font-weight-bold">
                                        <td colspan="4">Good Stock dipakai = Master / Mutasi Stok</td>
                                        <td>{{ number_format($selectedStock->good_stock) }}</td>
                                        <td colspan="3">
                                            @if(abs($batches->sum('qty_sisa') - $selectedStock->good_stock) > 0.0001)
                                                <span class="text-danger">Batch FIFO beda dari mutasi — pakai angka Good Stock di atas</span>
                                            @else
                                                Cocok dengan mutasi
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-exchange-alt mr-2"></i>
                    {{ $showPergerakan ? 'Pergerakan Barang' : 'Mutasi Stock' }}
                    - {{ $selectedStock->kode_barang }} ({{ $selectedStock->nama_barang }})
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

                                @forelse($mutations as $index => $mutation)
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
                                @if($mutations->isNotEmpty())
                                    <tr class="table-success font-weight-bold">
                                        <td colspan="7">Good Stock (Master / Mutasi)</td>
                                        <td class="text-right">
                                            {{ number_format($selectedStock->good_stock) }}
                                            @if(!empty($selectedStock->good_stock_mixed))
                                                <br><small class="font-weight-normal text-muted">{{ $selectedStock->good_stock_mixed }}</small>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let debounceTimer;

        function debounce(func, delay) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(func, delay);
        }

        $('#quickSearch').on('input', function() {
            const input = $(this).val().toLowerCase();
            debounce(function() {
                filterStocks(input);
            }, 300);
        });

        function filterStocks(keyword) {
            $('#loadingItems').show();
            setTimeout(() => {
                $('#stockTableBody tr').each(function() {
                    const kodeBarang = $(this).find('td:nth-child(1)').text().toLowerCase();
                    const namaBarang = $(this).find('td:nth-child(2)').text().toLowerCase();
                    $(this).toggle(kodeBarang.includes(keyword) || namaBarang.includes(keyword));
                });
                $('#loadingItems').hide();
            }, 10);
        }

        $('#clearQuickSearch').on('click', function() {
            $('#quickSearch').val('');
            filterStocks('');
        });

        $('#clearSearchValue').on('click', function() {
            $('input[name="value"]').val('');
        });

        $('#resetFilter').on('click', function() {
            const params = new URLSearchParams();
            @if($showBatches)
                params.set('show_batches', '1');
            @endif
            @if($showPergerakan)
                params.set('show_pergerakan', '1');
            @endif
            const qs = params.toString();
            window.location = '{{ route('laporan.stok') }}' + (qs ? ('?' + qs) : '');
        });
    });
</script>
@endsection
