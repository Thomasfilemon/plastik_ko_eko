@extends('layout.Nav')

@section('content')
<div class="container">
    <div class="title-box">
        <h2><i class="fas fa-file-invoice mr-2"></i>Daftar Nota Pembelian</h2>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif

    <!-- Custom CSS for highlighting edited rows -->
    <style>
        tr.edited-row {
            background-color: rgba(255, 193, 7, 0.1) !important;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
            font-weight: bold;
        }
        .edit-info {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('pembelian.nota.list') }}">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <select name="search_by" id="search_by" class="form-control">
                            <option selected disabled value="">Cari Berdasarkan</option>
                            <option value="nota" {{ request('search_by') == 'nota' ? 'selected' : '' }}>No. Nota</option>
                            <option value="kode_supplier" {{ request('search_by') == 'kode_supplier' ? 'selected' : '' }}>Kode Supplier</option>
                            <option value="nama_supplier" {{ request('search_by') == 'nama_supplier' ? 'selected' : '' }}>Nama Supplier</option>
                            <option value="cara_bayar" {{ request('search_by') == 'cara_bayar' ? 'selected' : '' }}>Cara Bayar</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="text" id="search" name="search" class="form-control" placeholder="Cari..." value="{{ $search ?? '' }}" disabled>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label for="startDate">Dari Tanggal</label>
                        <input type="date" id="startDate" name="startDate" class="form-control" value="{{ $startDate ?? '' }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label for="endDate">Sampai Tanggal</label>
                        <input type="date" id="endDate" name="endDate" class="form-control" value="{{ $endDate ?? '' }}">
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">Terapkan</button>
                        <a href="{{ route('pembelian.nota.list') }}" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Riwayat Transaksi Pembelian</h5>
            <a href="{{ route('pembelian.index') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle"></i> Transaksi Baru
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="purchaseTable">
                    <thead>
                        <tr>
                            <th>No. Nota</th>
                            <th>Tanggal</th>
                            <th>Supplier</th>
                            <th>Cara Bayar</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchases as $purchase)
                        <tr class="{{ (isset($purchase->is_edited) && $purchase->is_edited) ? 'edited-row' : '' }}">
                            <td>
                                {{ $purchase->nota }}
                                @if(isset($purchase->is_edited) && $purchase->is_edited)
                                    <i class="fas fa-edit text-warning ml-1" data-toggle="tooltip" title="Diedit oleh: {{ $purchase->edited_by }} pada {{ date('d/m/Y H:i', strtotime($purchase->edited_at)) }}"></i>
                                @endif
                            </td>
                            <td>{{ date('Y-m-d', strtotime($purchase->tanggal)) }}</td>
                            <td>{{ $purchase->kode_supplier }} - {{ $purchase->supplierRelation->nama ?? '' }}</td>
                            <td>{{ $purchase->cara_bayar }}</td>
                            <td class="text-right">Rp {{ number_format($purchase->grand_total, 0, ',', '.') }}</td>
                            <td>
                                @if(isset($purchase->status) && $purchase->status == 'canceled')
                                    <span class="badge badge-danger">Dibatalkan</span>
                                    <small class="d-block edit-info mt-1">
                                        Oleh: {{ $purchase->canceled_by ?? 'Unknown' }}
                                    </small>
                                    <small class="d-block edit-info">
                                        {{ isset($purchase->canceled_at) ? date('d/m/Y H:i', strtotime($purchase->canceled_at)) : '' }}
                                    </small>
                                @elseif(isset($purchase->is_edited) && $purchase->is_edited)
                                    <span class="badge badge-warning">Diedit</span>
                                    <small class="d-block edit-info mt-1">
                                        Oleh: {{ $purchase->edited_by ?? 'Unknown' }}
                                    </small>
                                    <small class="d-block edit-info">
                                        {{ isset($purchase->edited_at) ? date('d/m/Y H:i', strtotime($purchase->edited_at)) : '' }}
                                    </small>
                                @else
                                    <span class="badge badge-success">Aktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('pembelian.nota.show', $purchase->id) }}" class="btn btn-sm btn-info" title="Lihat Nota">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    @if(!isset($purchase->status) || $purchase->status != 'canceled')
                                        @can('edit pembelian')
                                        <a href="{{ route('pembelian.edit', $purchase->id) }}" class="btn btn-sm btn-warning" title="Edit Nota">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endcan
                                        
                                        @can('cancel pembelian')
                                        <button type="button" class="btn btn-sm btn-danger cancel-btn" 
                                                data-toggle="modal" 
                                                data-target="#cancelModal" 
                                                data-id="{{ $purchase->id }}"
                                                data-nota="{{ $purchase->nota }}"
                                                title="Batalkan Nota">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        @endcan
                                    @endif
                                    
                                    @if(isset($purchase->is_edited) && $purchase->is_edited)
                                        <button type="button" class="btn btn-sm btn-info edit-history-btn" 
                                                data-toggle="modal" 
                                                data-target="#editHistoryModal"
                                                data-nota="{{ $purchase->nota }}"
                                                data-editor="{{ $purchase->edited_by }}"
                                                data-date="{{ date('d/m/Y H:i', strtotime($purchase->edited_at)) }}"
                                                data-reason="{{ $purchase->edit_reason }}">
                                            <i class="fas fa-history"></i>
                                        </button>
                                    @endif
                                    
                                    <a href="{{ route('pembelian.nota.show', $purchase->id) }}?auto-print=1" 
                                    class="btn btn-sm btn-primary print-nota-btn" 
                                    target="_blank" 
                                    title="Cetak Nota">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">Tidak ada data transaksi pembelian</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="d-flex justify-content-center mt-4">
                    {{ $purchases->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelModalLabel">Konfirmasi Pembatalan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin membatalkan nota pembelian <strong id="cancel-nota-display"></strong>?</p>
                <p class="text-danger">Perhatian: Tindakan ini akan mengembalikan stok barang dan menandai transaksi sebagai batal!</p>
                
                <div class="form-group">
                    <label for="cancel_reason">Alasan Pembatalan:</label>
                    <textarea id="cancel_reason" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <form id="cancelForm" action="" method="POST">
                    @csrf
                    <input type="hidden" name="cancel_reason" id="cancel_reason_input">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Batalkan Transaksi</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit History Modal -->
<div class="modal fade" id="editHistoryModal" tabindex="-1" role="dialog" aria-labelledby="editHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editHistoryModalLabel">Riwayat Edit</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>No. Nota:</strong> <span id="edit-nota-display"></span>
                </div>
                <div class="mb-3">
                    <strong>Diedit oleh:</strong> <span id="edit-user-display"></span>
                </div>
                <div class="mb-3">
                    <strong>Tanggal Edit:</strong> <span id="edit-date-display"></span>
                </div>
                <div class="mb-3">
                    <strong>Alasan Edit:</strong>
                    <p id="edit-reason-display" class="p-2 bg-light rounded"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();
        
        // Manage search input state based on dropdown selection
        const searchBySelect = document.getElementById('search_by');
        const searchInput = document.getElementById('search');

        function updateSearchInputState() {
            if (searchBySelect.value !== "" && searchBySelect.selectedIndex !== 0) {
                searchInput.disabled = false;
            } else {
                searchInput.disabled = true;
                searchInput.value = '';
            }
        }

        updateSearchInputState();
        searchBySelect.addEventListener('change', updateSearchInputState);
        
        // Cancel Modal handling
        $('.cancel-btn').click(function() {
            const id = $(this).data('id');
            const nota = $(this).data('nota');
            
            $('#cancel-nota-display').text(nota);
            $('#cancelForm').attr('action', `{{ url('/pembelian/cancel') }}/${id}`);
        });
        
        // Edit History Modal handling
        $('.edit-history-btn').click(function() {
            const nota = $(this).data('nota');
            const editor = $(this).data('editor');
            const date = $(this).data('date');
            const reason = $(this).data('reason');
            
            $('#edit-nota-display').text(nota);
            $('#edit-user-display').text(editor);
            $('#edit-date-display').text(date);
            $('#edit-reason-display').text(reason);
        });
        
        // Validate and submit the cancel form
        $('#cancelForm').on('submit', function() {
            const reason = $('#cancel_reason').val().trim();
            if (!reason) {
                alert('Alasan pembatalan harus diisi!');
                return false;
            }
            
            $('#cancel_reason_input').val(reason);
            return true;
        });
    });
</script>
@endsection