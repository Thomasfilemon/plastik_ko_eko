@extends('layout.Nav')

@section('content')
<div class="container py-2">
    <h3>Daftar Transaksi</h3>

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
        tr.canceled-row {
            background-color: rgba(220, 53, 69, 0.1) !important;
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

    <!-- Search and Date Filters -->
    <form action="{{ route('transaksi.index') }}" method="GET" class="row mb-3">
        <div class="col-md-3 mb-2">
            <select name="search_by" id="search_by" class="form-control">
                <option selected disabled value="">Cari Berdasarkan</option>
                <option value="no_transaksi" {{ request('search_by') == 'no_transaksi' ? 'selected' : '' }}>No Transaksi</option>
                <option value="customer" {{ request('search_by') == 'customer' ? 'selected' : '' }}>Customer</option>
                <option value="alamat" {{ request('search_by') == 'alamat' ? 'selected' : '' }}>Alamat</option>
                <option value="sales" {{ request('search_by') == 'sales' ? 'selected' : '' }}>Sales</option>
            </select>
        </div>
        <div class="col-md-3 mb-2">
            <input type="text" name="search" id="search_input" class="form-control" placeholder="Cari..." value="{{ request('search') }}" {{ request('search_by') ? '' : 'disabled' }}>
        </div>
        <div class="col-md-2 mb-2">
            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
        </div>
        <div class="col-md-2 mb-2">
            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
        </div>
        <div class="col-md-2 mb-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary mr-2">Terapkan</button>
            <a href="{{ route('transaksi.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <table class="table table-bordered" id="transactionTable">
        <thead>
            <tr>
                <th>No Transaksi</th>
                <th>Tanggal</th>
                <th>Customer</th>
                <th>Alamat</th>
                <th>Sales</th>
                <th>Total</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transactions as $transaction)
                <tr class="{{ $transaction->is_edited ? 'edited-row' : '' }} {{ $transaction->status == 'canceled' ? 'canceled-row' : '' }}">
                    <td>
                        {{ $transaction->no_transaksi }}
                        @if($transaction->is_edited)
                            <i class="fas fa-edit text-warning ml-1" data-toggle="tooltip" title="Diedit oleh: {{ $transaction->edited_by }} pada {{ date('d/m/Y H:i', strtotime($transaction->edited_at)) }}"></i>
                        @endif
                    </td>
                    <td>{{ date('Y-m-d', strtotime($transaction->tanggal)) }}</td>
                    <td>{{ $transaction->customer->nama ?? 'N/A' }}</td>
                    <td>{{ $transaction->customer->alamat ?? 'N/A' }}</td>
                    <td>{{ $transaction->nama_salesman }}</td>
                    <td class="text-right">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
                    <td>
                        @if($transaction->status == 'canceled')
                            <span class="badge badge-pill badge-danger">Dibatalkan</span>
                            <small class="d-block edit-info mt-1">
                                Oleh: {{ $transaction->canceled_by ?? 'Unknown' }}
                            </small>
                            <small class="d-block edit-info">
                                {{ isset($transaction->canceled_at) ? date('d/m/Y H:i', strtotime($transaction->canceled_at)) : '' }}
                            </small>
                        @elseif($transaction->is_edited)
                            <span class="badge badge-pill badge-warning">Diedit</span>
                            <small class="d-block edit-info mt-1">
                                Oleh: {{ $transaction->edited_by ?? 'Unknown' }}
                            </small>
                            <small class="d-block edit-info">
                                {{ isset($transaction->edited_at) ? date('d/m/Y H:i', strtotime($transaction->edited_at)) : '' }}
                            </small>
                        @else
                            <span class="badge badge-pill badge-success">Aktif</span>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="{{ route('transaksi.shownota', $transaction->id) }}" class="btn btn-info btn-sm" title="Lihat Nota">
                                <i class="fas fa-eye"></i>
                            </a>

                            @if($transaction->status != 'canceled')
                                @can('edit penjualan')
                                <a href="{{ route('transaksi.edit', $transaction->id) }}" class="btn btn-warning btn-sm" title="Edit Transaksi">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endcan

                                @can('cancel penjualan')
                                <button type="button" class="btn btn-danger btn-sm cancel-btn"
                                        data-toggle="modal"
                                        data-target="#cancelModal"
                                        data-id="{{ $transaction->id }}"
                                        data-notransaksi="{{ $transaction->no_transaksi }}"
                                        title="Batalkan Transaksi">
                                    <i class="fas fa-ban"></i>
                                </button>
                                @endcan
                            @endif

                            @if($transaction->is_edited)
                                <button type="button" class="btn btn-info btn-sm edit-history-btn"
                                        data-toggle="modal"
                                        data-target="#editHistoryModal"
                                        data-notransaksi="{{ $transaction->no_transaksi }}"
                                        data-editor="{{ $transaction->edited_by }}"
                                        data-date="{{ date('d/m/Y H:i', strtotime($transaction->edited_at)) }}"
                                        data-reason="{{ $transaction->edit_reason }}">
                                    <i class="fas fa-history"></i>
                                </button>
                            @endif

                            <a href="{{ route('transaksi.nota', $transaction->id) }}?auto_print=1"
                                class="btn btn-primary btn-sm print-nota-btn"
                                target="_blank"
                                title="Cetak Nota">
                                <i class="fas fa-print"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="d-flex justify-content-center">
        {{ $transactions->links() }}
    </div>
</div>

<!-- Cancel Modal -->
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
                <p>Apakah Anda yakin ingin membatalkan transaksi <strong id="cancel-notransaksi-display"></strong>?</p>
                {{-- <p class="text-danger">Perhatian: Tindakan ini akan mengembalikan stok barang dan menandai transaksi sebagai batal!</p> --}}

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
                    <strong>No. Transaksi:</strong> <span id="edit-notransaksi-display"></span>
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

        // Search input enable/disable
        function updateSearchInputState() {
            if ($("#search_by").val() !== "" && $("#search_by")[0].selectedIndex !== 0) {
                $("#search_input").prop('disabled', false);
            } else {
                $("#search_input").prop('disabled', true);
                $("#search_input").val('');
            }
        }

        $("#search_by").on('change', updateSearchInputState);

        // Cancel Modal handling
        $('.cancel-btn').click(function() {
            const id = $(this).data('id');
            const notransaksi = $(this).data('notransaksi');

            $('#cancel-notransaksi-display').text(notransaksi);
            $('#cancelForm').attr('action', `{{ url('/transaksi/cancel') }}/${id}`);
        });

        // Edit History Modal handling
        $('.edit-history-btn').click(function() {
            const notransaksi = $(this).data('notransaksi');
            const editor = $(this).data('editor');
            const date = $(this).data('date');
            const reason = $(this).data('reason');

            $('#edit-notransaksi-display').text(notransaksi);
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
