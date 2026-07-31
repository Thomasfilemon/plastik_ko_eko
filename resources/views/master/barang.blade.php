@extends('layout.Nav')

@section('content')
<section id="barang">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Master Barang</h2>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                @can('manage kode barang')
                <a href="{{ route('code.create-code') }}" class="btn btn-primary btn-sm me-2">
                    <i class="fas fa-plus mr-1"></i> Tambah Barang
                </a>
                @endcan
            </div>

            <div>
                <a href="{{ route('code.view-code') }}" class="btn btn-sm" style="background-color: #3f8efc; color: white;">
                    <i class="fas fa-file-alt"></i> List Kode Barang
                </a>
            </div>
        </div>

        <div class="card-body">
            <!-- Enhanced search panel similar to History Surat Jalan -->
            <form method="GET" action="{{ route('master.barang') }}" class="row mb-3">
                <div class="col-md-3 mb-2">
                    <select name="search_by" id="search_by" class="form-control">
                        <option selected disabled value=""> Cari Berdasarkan </option>
                        <option value="group_id" {{ request('search_by') == 'group_id' ? 'selected' : '' }}>Kode Barang</option>
                        <option value="name" {{ request('search_by') == 'name' ? 'selected' : '' }}>Nama Barang</option>
                        <option value="group" {{ request('search_by') == 'group' ? 'selected' : '' }}>Group</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" id="search_input" class="form-control" placeholder="Cari..." value="{{ request('search') }}" disabled>
                </div>
                <div class="col-md-2 mb-2">
                    <select name="status_filter" id="status_filter" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="Active" {{ request('status_filter') == 'Active' ? 'selected' : '' }}>Active</option>
                        <option value="Inactive" {{ request('status_filter') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('master.barang') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>

            <div class="card-body">
                @if(isset($inventory) && count($inventory['inventory_by_length']) > 0)
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                                                            <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Kode Barang</th>
                                        <th>Nama</th>
                                        <th>Group</th>
                                        <th>Harga Beli</th>
                                        <th>Harga Jual per Satuan Dasar</th>
                                        <th>Available Quantity</th>
                                        <th>Satuan Dasar</th>
                                        <th>Satuan Besar</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                            <tbody>
                                @foreach($inventory['inventory_by_length'] as $item)
                                    <tr>
                                        <td>{{ $item['id'] }}</td>
                                        <td>{{ $item['group_id'] }}</td>
                                        <td>{{ $item['name'] }}</td>
                                        <td>{{ $item['group'] }}</td>
                                        <td>Rp. {{ number_format($item['cost']) }}</td>
                                        <td>
                                            <strong>Rp. {{ number_format($item['harga_per_satuan_dasar'] ?? $item['price']) }}</strong>
                                            <br>
                                            <small class="text-muted">per {{ $item['unit_dasar'] ?? 'PCS' }}</small>
                                        </td>
                                        <td>{{ $item['quantity'] }}</td>
                                        <td>
                                            <span class="badge bg-primary">{{ $item['unit_dasar'] ?? 'PCS' }}</span>
                                        </td>
                                        <td>
                                            @if(isset($item['satuan_besar']) && count($item['satuan_besar']) > 0)
                                                @foreach($item['satuan_besar'] as $satuan)
                                                    <span class="badge bg-success me-1">
                                                        {{ $satuan['unit'] }} ({{ $satuan['konversi'] }})
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $item['status'] === 'Active' ? 'bg-success' : 'bg-secondary' }}">{{ $item['status'] }}</span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-success btn-edit-barang"
                                                    data-id="{{ $item['id'] ?? '' }}"
                                                    data-group-id="{{ $item['group_id'] }}"
                                                    data-name="{{ $item['name'] }}"
                                                    data-status="{{ $item['status'] }}"
                                                    data-quantity="{{ $item['quantity'] }}">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form action="{{ route('panels.delete-inventory', ['id' => $item['group_id']]) }}" method="POST" onsubmit="return confirm('Hapus barang ini secara permanen? Tindakan ini tidak dapat dibatalkan.');">
                                                    @csrf
                                                    <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Hapus</button>
                                                </form>
                                                <form action="{{ route('code.toggle-status', ['id' => $item['id'] ?? null]) }}" method="POST" onsubmit="return confirm('Ubah status barang ini?');">
                                                    @csrf
                                                    <button class="btn btn-sm {{ ($item['status'] === 'Active') ? 'btn-warning' : 'btn-primary' }}">
                                                        {{ ($item['status'] === 'Active') ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                            <tr class="table-primary">
                                <th colspan="4" class="text-end">Total</th>
                                @can('view-total-harga')
                                <th>
                                    @php
                                        $totalCost = 0;
                                        foreach($inventory['inventory_by_length'] as $item) {
                                            $totalCost += $item['cost'] * $item['quantity'];
                                        }
                                        echo 'Rp. ' . number_format($totalCost);
                                    @endphp
                                </th>
                                <th>
                                    @php
                                        $totalPrice = 0;
                                        foreach($inventory['inventory_by_length'] as $item) {
                                            $totalPrice += ($item['harga_per_satuan_dasar'] ?? $item['price']) * $item['quantity'];
                                        }
                                        echo 'Rp. ' . number_format($totalPrice);
                                    @endphp
                                </th>
                                @else
                                <th class="text-muted">-</th>
                                <th class="text-muted">-</th>
                                @endcan
                                <th>
                                    @php
                                        $totalQuantity = 0;
                                        foreach($inventory['inventory_by_length'] as $item) {
                                            $totalQuantity += $item['quantity'];
                                        }
                                        echo $totalQuantity;
                                    @endphp
                                </th>
                                <th></th>
                                <th></th>
                                <th colspan="2"></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-4">
                        {{ $inventory['paginator']->appends(request()->except('page'))->links() }}
                    </div>
                @else
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-1"></i> No panels currently in inventory.
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<!-- Edit Barang Modal (inline, no page navigation) -->
<div class="modal fade" id="editBarangModal" tabindex="-1" role="dialog" aria-labelledby="editBarangModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="editBarangForm" action="{{ route('panels.update-inventory') }}" method="POST">
                @csrf
                <input type="hidden" name="group_id" id="eb_group_id">
                <input type="hidden" name="quantity" id="eb_quantity">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBarangModalLabel">Edit Barang</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="eb_name">Nama Barang</label>
                        <input type="text" class="form-control" name="name" id="eb_name" required>
                    </div>
                    <div class="form-group">
                        <label for="eb_kode">Kode Barang</label>
                        <input type="text" class="form-control" id="eb_kode" readonly>
                    </div>
                    <div class="form-group">
                        <label for="eb_status">Status</label>
                        <select class="form-control" name="status" id="eb_status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <hr/>
                    <label><i class="fas fa-cogs mr-1"></i> Konversi Satuan Besar</label>
                    <div id="eb_uc_list"></div>
                    <div class="d-flex mt-2">
                        <input type="text" id="eb_uc_unit_add" class="form-control mr-2" placeholder="Satuan Besar (mis. DUS)">
                        <input type="number" id="eb_uc_value_add" class="form-control mr-2" placeholder="Isi per satuan kecil">
                        <button type="button" id="eb_uc_add_btn" class="btn btn-success">Tambah</button>
                    </div>
                    <small class="form-text text-muted">Konversi satuan tersimpan otomatis saat ditambah/diubah.</small>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .table-bordered th,
    .table-bordered td {
        border: 1px solid #000 !important;
    }

    .table-bordered {
        border: 2px solid #000;
    }
</style>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ambil elemen select dan input
    const searchBySelect = document.getElementById('search_by');
    const searchInput = document.getElementById('search_input');

    // Fungsi untuk mengecek status dropdown dan mengatur disabled state pada input
    function updateSearchInputState() {
        // Cek apakah ada opsi yang dipilih dan bukan opsi default (disabled)
        if (searchBySelect.value !== "" && searchBySelect.selectedIndex !== 0) {
            searchInput.disabled = false;
        } else {
            searchInput.disabled = true;
            searchInput.value = ''; // Kosongkan input jika disabled
        }
    }

    // Panggil fungsi saat halaman dimuat untuk mengatur status awal
    updateSearchInputState();

    // Tambahkan event listener untuk dropdown
    searchBySelect.addEventListener('change', updateSearchInputState);
});
</script>

<script>
$(function () {
    const csrfToken = "{{ csrf_token() }}";
    let currentKode = null;

    // Open the edit modal and populate fields (no page navigation)
    $(document).on('click', '.btn-edit-barang', function () {
        const btn = $(this);
        currentKode = btn.data('group-id');

        $('#eb_group_id').val(currentKode);
        $('#eb_kode').val(currentKode);
        $('#eb_name').val(btn.data('name'));
        $('#eb_quantity').val(btn.data('quantity'));
        $('#eb_status').val(btn.data('status'));

        loadUc();
        $('#editBarangModal').modal('show');
    });

    function ucUrl(suffix) {
        return `/unit-conversion/by-kode/${encodeURIComponent(currentKode)}${suffix || ''}`;
    }

    function renderUc(items) {
        const list = $('#eb_uc_list');
        if (!Array.isArray(items) || items.length === 0) {
            list.html('<div class="text-muted">Belum ada konversi satuan besar.</div>');
            return;
        }
        let html = '<table class="table table-sm mb-0"><thead><tr><th>Satuan Besar</th><th>Isi per Satuan Kecil</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
        items.forEach(it => {
            html += `<tr id="eb-uc-row-${it.id}">
                <td>
                    <span class="uc-display">${it.unit_turunan}</span>
                    <input type="text" class="form-control form-control-sm uc-edit" id="eb-uc-unit-${it.id}" value="${it.unit_turunan}" style="display:none;">
                </td>
                <td>
                    <span class="uc-display">${it.nilai_konversi}</span>
                    <input type="number" class="form-control form-control-sm uc-edit" id="eb-uc-value-${it.id}" value="${it.nilai_konversi}" style="display:none;">
                </td>
                <td>${it.is_active ? 'Aktif' : 'Nonaktif'}</td>
                <td>
                    <span class="uc-display">
                        <button type="button" class="btn btn-sm btn-primary" data-id="${it.id}" data-action="edit"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-sm btn-warning" data-id="${it.id}" data-action="toggle"><i class="fas fa-toggle-${it.is_active ? 'on' : 'off'}"></i></button>
                        <button type="button" class="btn btn-sm btn-danger" data-id="${it.id}" data-action="delete"><i class="fas fa-trash"></i></button>
                    </span>
                    <span class="uc-edit" style="display:none;">
                        <button type="button" class="btn btn-sm btn-success" data-id="${it.id}" data-action="save"><i class="fas fa-check"></i></button>
                        <button type="button" class="btn btn-sm btn-secondary" data-id="${it.id}" data-action="cancel"><i class="fas fa-times"></i></button>
                    </span>
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
        list.html(html);
    }

    function loadUc() {
        $('#eb_uc_list').html('<div class="text-muted">Memuat...</div>');
        fetch(ucUrl(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(data => renderUc(data.items || []))
            .catch(err => $('#eb_uc_list').html('<div class="text-danger">Gagal memuat konversi satuan.</div>'));
    }

    // Add conversion
    $('#eb_uc_add_btn').on('click', function () {
        const unit = ($('#eb_uc_unit_add').val() || '').trim();
        const val = parseInt($('#eb_uc_value_add').val() || '0', 10);
        if (!unit || val < 1) { alert('Isi satuan besar dan nilai konversi minimal 1.'); return; }

        fetch(ucUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ unit_turunan: unit, nilai_konversi: val })
        })
        .then(r => r.ok ? r.json() : Promise.reject(r.status))
        .then(data => {
            if (data.success) { $('#eb_uc_unit_add').val(''); $('#eb_uc_value_add').val(''); loadUc(); }
            else { alert((data.errors ? Object.values(data.errors).flat().join(', ') : 'Gagal menambah konversi.')); }
        })
        .catch(() => alert('Gagal menambah konversi satuan.'));
    });

    // Row actions
    $('#eb_uc_list').on('click', 'button[data-id]', function () {
        const id = $(this).data('id');
        const action = $(this).data('action');
        const row = $(`#eb-uc-row-${id}`);

        if (action === 'edit') {
            row.find('.uc-display').hide();
            row.find('.uc-edit').show();
        } else if (action === 'cancel') {
            row.find('.uc-display').show();
            row.find('.uc-edit').hide();
        } else if (action === 'save') {
            const unit = ($(`#eb-uc-unit-${id}`).val() || '').trim();
            const val = parseInt($(`#eb-uc-value-${id}`).val() || '0', 10);
            if (!unit || val < 1) { alert('Isi satuan besar dan nilai konversi minimal 1.'); return; }
            fetch(ucUrl(`/${id}`), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ unit_turunan: unit, nilai_konversi: val })
            })
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(() => loadUc())
            .catch(() => alert('Gagal mengubah konversi satuan.'));
        } else if (action === 'toggle') {
            fetch(ucUrl(`/${id}/toggle`), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(() => loadUc())
            .catch(() => alert('Gagal mengubah status konversi.'));
        } else if (action === 'delete') {
            if (!confirm('Hapus satuan besar ini?')) return;
            fetch(ucUrl(`/${id}`), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(() => loadUc())
            .catch(() => alert('Gagal menghapus konversi satuan.'));
        }
    });
});
</script>
@endsection