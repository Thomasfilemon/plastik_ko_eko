@extends('layout.Nav')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-money-bill-wave mr-2"></i>Pembayaran Utang ke Supplier
                    </h3>
                    <div class="card-tools">
                        @can('create pembayaran utang supplier')
                        <a href="{{ route('pembayaran-utang-supplier.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus mr-1"></i>Tambah Pembayaran
                        </a>
                        @endcan
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3>Rp {{ number_format($summary['total_pembayaran_hari_ini'], 0, ',', '.') }}</h3>
                                    <p>Pembayaran Hari Ini</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3>Rp {{ number_format($summary['total_pembayaran_bulan_ini'], 0, ',', '.') }}</h3>
                                    <p>Pembayaran Bulan Ini</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3>Rp {{ number_format($summary['total_utang_tertagih'], 0, ',', '.') }}</h3>
                                    <p>Total Utang Tertagih</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3>Rp {{ number_format($summary['total_utang_jatuh_tempo'], 0, ',', '.') }}</h3>
                                    <p>Utang Jatuh Tempo</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Section -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari no pembayaran, supplier...">
                        </div>
                        <div class="col-md-2">
                            <select class="form-control" id="statusFilter">
                                <option value="">Semua Status</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" id="dateFrom" placeholder="Dari Tanggal">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" id="dateTo" placeholder="Sampai Tanggal">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary" onclick="filterTable()">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <button class="btn btn-secondary" onclick="resetFilter()">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="pembayaranTable">
                            <thead>
                                <tr>
                                    <th>No Pembayaran</th>
                                    <th>Tanggal</th>
                                    <th>Supplier</th>
                                    <th>Total Bayar</th>
                                    <th>Nota Debit</th>
                                    <th>Metode</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pembayarans as $pembayaran)
                                <tr>
                                    <td>{{ $pembayaran->no_pembayaran }}</td>
                                    <td>{{ $pembayaran->tanggal_bayar->format('d/m/Y') }}</td>
                                    <td>{{ $pembayaran->supplier->nama ?? 'N/A' }}</td>
                                    <td class="text-right">Rp {{ number_format($pembayaran->total_bayar, 0, ',', '.') }}</td>
                                    <td class="text-right">Rp {{ number_format($pembayaran->total_nota_debit, 0, ',', '.') }}</td>
                                    <td>{{ $pembayaran->metode_pembayaran }}</td>
                                    <td>
                                        @if($pembayaran->status === 'pending')
                                            <span class="badge badge-warning">Pending</span>
                                        @elseif($pembayaran->status === 'confirmed')
                                            <span class="badge badge-success">Confirmed</span>
                                        @else
                                            <span class="badge badge-danger">Cancelled</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('pembayaran-utang-supplier.show', $pembayaran) }}" 
                                               class="btn btn-info btn-sm" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @if($pembayaran->status === 'pending')
                                                @can('edit pembayaran utang supplier')
                                                <a href="{{ route('pembayaran-utang-supplier.edit', $pembayaran) }}" 
                                                   class="btn btn-warning btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @endcan
                                                @can('manage pembayaran utang supplier')
                                                <form action="{{ route('pembayaran-utang-supplier.confirm', $pembayaran) }}" 
                                                      method="POST" style="display: inline;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                            title="Confirm" onclick="return confirm('Konfirmasi pembayaran ini?')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form action="{{ route('pembayaran-utang-supplier.cancel', $pembayaran) }}" 
                                                      method="POST" style="display: inline;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            title="Cancel" onclick="return confirm('Batalkan pembayaran ini?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $pembayarans->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function filterTable() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    const rows = document.querySelectorAll('#pembayaranTable tbody tr');
    
    rows.forEach(row => {
        const noPembayaran = row.cells[0].textContent.toLowerCase();
        const supplier = row.cells[2].textContent.toLowerCase();
        const status = row.cells[6].textContent.toLowerCase();
        const date = row.cells[1].textContent;
        
        let showRow = true;
        
        // Search filter
        if (searchInput && !noPembayaran.includes(searchInput) && !supplier.includes(searchInput)) {
            showRow = false;
        }
        
        // Status filter
        if (statusFilter && !status.includes(statusFilter)) {
            showRow = false;
        }
        
        // Date filter
        if (dateFrom || dateTo) {
            const rowDate = new Date(date.split('/').reverse().join('-'));
            if (dateFrom && rowDate < new Date(dateFrom)) {
                showRow = false;
            }
            if (dateTo && rowDate > new Date(dateTo)) {
                showRow = false;
            }
        }
        
        row.style.display = showRow ? '' : 'none';
    });
}

function resetFilter() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    
    const rows = document.querySelectorAll('#pembayaranTable tbody tr');
    rows.forEach(row => {
        row.style.display = '';
    });
}
</script>
@endsection
