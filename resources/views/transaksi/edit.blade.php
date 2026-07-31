@extends('layout.Nav')

@section('content')
<div id="loadingOverlay" style="display:none;position:fixed;z-index:9999;top:0;left:0;width:100vw;height:100vh;background:rgba(255,255,255,0.7);backdrop-filter:blur(2px);justify-content:center;align-items:center;">
    <div style="font-size:1.5rem;color:#333;">
        <span class="spinner-border text-primary" role="status"></span>
        <span class="ml-2">Memproses...</span>
    </div>
</div>
<div class="container">
    <div class="title-box">
        <h2><i class="fas fa-edit mr-2"></i>Edit Transaksi Penjualan</h2>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Data Transaksi</h5>
        </div>
        <div class="card-body">
            <form id="transactionForm">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="no_transaksi">No. Transaksi</label>
                            <input type="text" class="form-control" id="no_transaksi" name="no_transaksi" value="{{ $transaction->no_transaksi }}" readonly style="background-color: #ffc107; color: #000; font-weight: bold;">
                        </div>

                        <div class="form-group">
                            <label for="tanggal">Tanggal</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="tanggal" name="tanggal" value="{{ $transaction->tanggal->format('Y-m-d') }}">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="customer">Customer</label>
                            <input type="text" id="customer" name="customer_display" class="form-control" value="{{ $customer }}" placeholder="Masukkan kode atau nama customer">
                            <input type="hidden" id="kode_customer" name="kode_customer" value="{{ $transaction->kode_customer }}">
                            <div id="customerDropdown" class="dropdown-menu" style="display: none; position: relative; width: 100%;"></div>
                        </div>

                        <div class="form-group">
                            <label for="customer">Alamat Customer</label>
                            <input type="text" id="alamatCustomer" name="customer-alamat" class="form-control" value="{{ $transaction->customer->alamat ?? '' }}" readonly>
                        </div>

                        <div class="form-group">
                            <label for="customer">No HP / Telp Customer</label>
                            <input type="text" id="hpCustomer" name="customer-hp" class="form-control" value="{{ $transaction->customer->hp ?? '' }} / {{ $transaction->customer->telepon ?? '' }}" readonly>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="sales">Sales</label>
                            <input type="text" id="sales" name="sales_display" class="form-control" value="{{ $transaction->sales }}" placeholder="Masukkan kode atau nama sales">
                            <input type="hidden" id="kode_sales" name="sales" value="{{ $transaction->sales }}">
                            <div id="salesDropdown" class="dropdown-menu" style="display: none; position: relative; width: 100%;"></div>
                        </div>

                        <div class="form-group">
                            <label for="metode_pembayaran">Metode Pembayaran</label>
                            <select class="form-control" id="metode_pembayaran" name="metode_pembayaran">
                                <option selected disabled value=""> Pilih Metode Pembayaran</option>
                                <option value="Tunai" {{ $transaction->pembayaran == 'Tunai' ? 'selected' : '' }}>Tunai</option>
                                <option value="Non Tunai" {{ $transaction->pembayaran == 'Non Tunai' ? 'selected' : '' }}>Non Tunai</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cara_bayar">Cara Bayar</label>
                            <select class="form-control" id="cara_bayar" name="cara_bayar">
                                <option value="{{ $transaction->cara_bayar }}">{{ $transaction->cara_bayar }}</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tanggal_jadi">Tanggal Jadi</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="tanggal_jadi" name="tanggal_jadi" value="{{ $transaction->tanggal_jadi ? $transaction->tanggal_jadi->format('Y-m-d') : date('Y-m-d') }}">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_reason">Alasan Edit <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_reason" name="edit_reason" placeholder="Masukkan alasan perubahan transaksi" required>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Items Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Rincian Barang</h5>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addItemModal">
                <i class="fas fa-plus-circle"></i> Tambah Barang
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Keterangan</th>
                            <th>Harga</th>
                            <th>Qty</th>
                            <th>Satuan Kecil</th>
                            <th>Satuan Besar</th>
                            <th>Total</th>
                            <th>Diskon</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="itemsList">
                        <!-- Dynamic items will be added here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Total</label>
                        <input type="text" class="form-control text-right" id="total" name="total" readonly value="{{ number_format($transaction->subtotal, 0, ',', '.') }}">
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="discount_checkbox" {{ $transaction->discount > 0 ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">Disc(%)</span>
                            </div>
                            <input type="number" class="form-control" id="discount_percent" value="{{ $transaction->discount }}" {{ $transaction->discount > 0 ? '' : 'disabled' }}>
                            <input type="text" class="form-control text-right" id="discount_amount" value="{{ number_format($transaction->discount, 0, ',', '.') }}" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="disc_rp_checkbox" {{ $transaction->disc_rupiah > 0 ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">Disc(Rp.)</span>
                            </div>
                            <input type="number" class="form-control" id="disc_rp" value="{{ $transaction->disc_rupiah }}" {{ $transaction->disc_rupiah > 0 ? '' : 'disabled' }}>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="ppn_checkbox" {{ $transaction->ppn > 0 ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">PPN</span>
                            </div>
                            <input type="text" class="form-control text-right" id="ppn_amount" value="{{ number_format($transaction->ppn, 0, ',', '.') }}" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="dp_checkbox" {{ $transaction->dp > 0 ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">DP</span>
                            </div>
                            <input type="number" class="form-control" id="dp_amount" value="{{ $transaction->dp }}" {{ $transaction->dp > 0 ? '' : 'disabled' }}>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Cara Bayar</label>
                        <select class="form-control" id="cara_bayar_akhir">
                            <option value="{{ $transaction->cara_bayar }}">{{ $transaction->cara_bayar }}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Grand Total</label>
                        <input type="text" class="form-control text-right" id="grand_total" readonly value="{{ number_format($transaction->grand_total, 0, ',', '.') }}" style="font-size: 18px; font-weight: bold;">
                    </div>
                    <div class="form-group">
                        <label for="notes">Catatan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Masukkan catatan tambahan (opsional)">{{ $transaction->notes ?? '' }}</textarea>
                    </div>
                    <div class="form-group text-right mt-4">
                        <button type="button" class="btn btn-success" id="updateTransaction">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <a href="{{ route('transaksi.listnota') }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Tambah Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                @include('transaksi.add_item')
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="saveItemBtn">Tambahkan</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" role="dialog" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editItemModalLabel">Edit Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_item_index">
                <div class="form-group">
                    <label>Nama Barang</label>
                    <input type="text" class="form-control" id="edit_item_nama">
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Qty</label>
                        <input type="number" class="form-control" id="edit_item_qty" step="0.01" min="0.01">
                        <small class="form-text text-muted" id="edit_item_qty_hint" style="display:none;"></small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Harga <small class="text-muted">(per satuan kecil)</small></label>
                        <input type="number" class="form-control" id="edit_item_harga" step="0.01" min="0">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Satuan Kecil</label>
                        <input type="text" class="form-control" id="edit_item_satuan_kecil" readonly>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Satuan Besar</label>
                        <select class="form-control" id="edit_item_satuan_besar"></select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Diskon (%)</label>
                        <input type="number" class="form-control" id="edit_item_diskon" min="0" max="100">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Ongkos Kuli</label>
                        <input type="number" class="form-control" id="edit_item_ongkos_kuli" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Keterangan</label>
                    <input type="text" class="form-control" id="edit_item_keterangan">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="saveEditItemBtn">
                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
@php
    $editItemsPayload = $transaction->items->map(function ($item) {
        $kode = $item->kodeBarang;
        $hasBesar = !empty($item->satuan_besar) && !empty($item->qty_besar) && (float) $item->qty_besar > 0;
        $factor = $hasBesar ? ((float) $item->qty / (float) $item->qty_besar) : 1.0;
        $qtyDisplay = $hasBesar ? (float) $item->qty_besar : (float) $item->qty;
        $satuanKecil = $item->satuan ?: (optional($kode)->unit_dasar ?? 'LBR');

        return [
            'kodeBarang' => $item->kode_barang,
            'kodeBarangId' => optional($kode)->id,
            'namaBarang' => $item->nama_barang,
            'keterangan' => $item->keterangan,
            // Harga selalu per satuan kecil
            'harga' => (float) $item->harga,
            'panjang' => (float) ($item->panjang ?? 0),
            'lebar' => (float) ($item->lebar ?? 0),
            'qty' => $qtyDisplay,
            'qtyKecil' => (float) $item->qty,
            'unitFactor' => $factor,
            'satuan' => $hasBesar ? $item->satuan_besar : $satuanKecil,
            'satuanKecil' => $satuanKecil,
            'satuanBesar' => $item->satuan_besar ?: '',
            'diskon' => (float) $item->diskon,
            'ongkosKuli' => (float) ($item->ongkos_kuli ?? 0),
            'total' => (float) $item->total,
        ];
    })->values();
@endphp
<script>
    function showLoading() {
        $('#loadingOverlay').fadeIn(100);
    }
    function hideLoading() {
        $('#loadingOverlay').fadeOut(100);
    }
    
    $(document).ready(function() {
        // Initialize variables with existing items from the database
        let items = @json($editItemsPayload);
        let grandTotal = {{ $transaction->grand_total }};
        
        // Render items immediately to display existing items
        renderItems();
        calculateTotals();

        // Metode Pembayaran
        $('#metode_pembayaran').on('change', function () {
            const metode = $(this).val();
            $('#cara_bayar').html('<option value="">Loading...</option>');
            
            $.ajax({
                url: '{{ url("api/cara-bayar/by-metode") }}',
                method: 'GET',
                data: { metode: metode },
                success: function (data) {
                    let options = '<option value="">-- Pilih Cara Bayar --</option>';
                    data.forEach(cb => {
                        options += `<option value="${cb.nama}">${cb.nama}</option>`;
                    });
                    $('#cara_bayar').html(options);
                },
                error: function () {
                    $('#cara_bayar').html('<option value="">Gagal load data</option>');
                }
            });
        });

        $('#cara_bayar').on('change', function () {
            const selected = $(this).val();
            $('#cara_bayar_akhir')
                .html(`<option value="${selected}">${selected}</option>`)
                .val(selected);
        });

        // Search customers
        $('#customer').on('input', function () {
            const keyword = $(this).val();
            if (keyword.length > 0) {
                $.ajax({
                    url: "{{ route('api.customers.search') }}",
                    method: "GET",
                    data: { keyword },
                    success: function (data) {
                        let dropdown = '';
                        if (data.length > 0) {
                            data.forEach(customer => {
                                dropdown += `<a class="dropdown-item customer-item" 
                                    data-kode="${customer.kode_customer}" 
                                    data-name="${customer.nama}"
                                    data-alamat="${customer.alamat}"
                                    data-hp="${customer.hp}"
                                    data-telp="${customer.telepon}">
                                ${customer.kode_customer} - ${customer.nama} - ${customer.alamat} - ${customer.hp}</a>`;
                            });
                        } else {
                            dropdown = '<a class="dropdown-item disabled">Tidak ada customer ditemukan</a>';
                        }
                        $('#customerDropdown').html(dropdown).show();
                    },
                    error: function () {
                        alert('Terjadi kesalahan saat mencari customer.');
                    }
                });
            } else {
                $('#customerDropdown').hide();
            }
        });

        // Select Customer
        $(document).on('click', '.customer-item', function () {
            const kodeCustomer = $(this).data('kode');
            const namaCustomer = $(this).data('name');
            const alamatCustomer = $(this).data('alamat');
            const hpCustomer = $(this).data('hp');
            const telpCustomer = $(this).data('telp');
            $('#kode_customer').val(kodeCustomer);
            $('#customer').val(`${kodeCustomer} - ${namaCustomer}`);
            $('#alamatCustomer').val(alamatCustomer);
            $('#hpCustomer').val(`${hpCustomer} / ${telpCustomer}`);
            $('#customerDropdown').hide();
        });

        // Search Sales
        $('#sales').on('input', function () {
            const keyword = $(this).val();
            if (keyword.length > 0) {
                $.ajax({
                    url: "{{ route('api.sales.search') }}",
                    method: "GET",
                    data: { keyword },
                    success: function (data) {
                        let dropdown = '';
                        if (data.length > 0) {
                            data.forEach(sales => {
                                dropdown += `<a class="dropdown-item sales-item" data-kode="${sales.kode_stok_owner}" data-name="${sales.keterangan}">${sales.kode_stok_owner} - ${sales.keterangan}</a>`;
                            });
                        } else {
                            dropdown = '<a class="dropdown-item disabled">Tidak ada sales ditemukan</a>';
                        }
                        $('#salesDropdown').html(dropdown).show();
                    },
                    error: function () {
                        alert('Terjadi kesalahan saat mencari sales.');
                    }
                });
            } else {
                $('#salesDropdown').hide();
            }
        });

        // Select Sales
        $(document).on('click', '.sales-item', function () {
            const kodeSales = $(this).data('kode');
            const namaSales = $(this).data('name');
            $('#kode_sales').val(kodeSales);
            $('#sales').val(`${kodeSales}`);
            $('#salesDropdown').hide();
        });

        // Hide dropdown when clicking outside
        $(document).click(function (e) {
            if (!$(e.target).closest('#customer, #customerDropdown').length) {
                $('#customerDropdown').hide();
            }
            if (!$(e.target).closest('#sales, #salesDropdown').length) {
                $('#salesDropdown').hide();
            }
        });

        // Toggle discount and DP inputs
        $('#discount_checkbox').change(function() {
            $('#discount_percent').prop('disabled', !this.checked);
            calculateTotals();
        });

        $('#disc_rp_checkbox').change(function() {
            $('#disc_rp').prop('disabled', !this.checked);
            calculateTotals();
        });

        $('#ppn_checkbox').change(function() {
            calculateTotals();
        });

        $('#dp_checkbox').change(function() {
            $('#dp_amount').prop('disabled', !this.checked);
            calculateTotals();
        });

        // Calculate input changes
        $('#discount_percent, #disc_rp, #dp_amount').on('input', function() {
            calculateTotals();
        });

       $('#saveItemBtn').click(function() {
        const kodeBarang = $('#kode_barang').val();
        const namaBarang = $('#nama_barang').val();
        const keterangan = $('#keterangan').val();
        const harga = parseFloat($('#harga').val()) || 0; // per satuan kecil
        const panjang = parseFloat($('#panjang').val()) || 0;
        const lebar = parseFloat($('#lebar').val()) || 0;
        const qty = parseFloat($('#quantity').val()) || 0;
        const diskon = parseFloat($('#diskon').val()) || 0;
        const satuanKecil = $('#satuanKecil').val() || 'LBR';
        const satuanBesar = $('#satuanBesar').val() || '';
        const factors = $('#addItemForm').data('unit-factors') || {};
        const factor = satuanBesar ? (parseFloat(factors[satuanBesar]) || 1) : 1;
        const qtyKecil = qty * factor;

        if (!kodeBarang || !namaBarang || harga === undefined || harga === null || !qty) {
            alert('Mohon lengkapi data barang!');
            return;
        }

        // Total = qty(besar) × factor × harga kecil
        const subTotal = harga * qtyKecil;
        const diskonAmount = (diskon / 100) * subTotal;
        const total = subTotal - diskonAmount;

        const newItem = {
            kodeBarang,
            kodeBarangId: $('#kode_barang_id').val() || null,
            namaBarang,
            keterangan,
            harga,
            panjang,
            lebar,
            qty,
            qtyKecil,
            unitFactor: factor,
            satuanKecil,
            satuanBesar,
            satuan: satuanBesar ? satuanBesar : satuanKecil,
            diskon,
            ongkosKuli: parseFloat($('#ongkos_kuli').val()) || 0,
            total
        };

        items.push(newItem);
        renderItems();
        calculateTotals();

        // Reset form and close modal
        $('#addItemForm')[0].reset();
        $('#qtyConversionHint').hide().text('');
        $('#addItemModal').modal('hide');
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    });


        // Function to render items table
        function renderItems() {
            const tbody = $('#itemsList');
            tbody.empty();

            items.forEach((item, index) => {
                const satuanKecil = item.satuanKecil || item.satuan || 'LBR';
                const satuanBesar = item.satuanBesar || '-';
                const qtyLabel = item.satuanBesar && item.qtyKecil
                    ? `${item.qty} ${item.satuanBesar} <small class="text-muted">(= ${item.qtyKecil} ${satuanKecil})</small>`
                    : `${item.qty}`;
                tbody.append(`
                    <tr>
                        <td>${item.kodeBarang}</td>
                        <td>${item.namaBarang}</td>
                        <td>${item.keterangan || '-'}</td>
                        <td class="text-right">${formatCurrency(item.harga)}</td>
                        <td>${qtyLabel}</td>
                        <td>${satuanKecil}</td>
                        <td>${satuanBesar}</td>
                        <td class="text-right">${formatCurrency(item.total)}</td>
                        <td class="text-right">${item.diskon || 0}%</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary edit-item" data-index="${index}" title="Edit barang">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger remove-item" data-index="${index}" title="Hapus barang">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });

            // Remove item handling
            $('.remove-item').click(function() {
                const index = $(this).data('index');
                items.splice(index, 1);
                renderItems();
                calculateTotals();
            });

            $('.edit-item').click(function() {
                openEditItemModal($(this).data('index'));
            });
        }

        function openEditItemModal(index) {
            const item = items[index];
            if (!item) return;

            $('#edit_item_index').val(index);
            $('#edit_item_nama').val(item.namaBarang || '');
            $('#edit_item_qty').val(item.qty);
            $('#edit_item_harga').val(item.harga);
            $('#edit_item_satuan_kecil').val(item.satuanKecil || item.satuan || 'LBR');
            $('#edit_item_diskon').val(item.diskon || 0);
            $('#edit_item_ongkos_kuli').val(item.ongkosKuli || 0);
            $('#edit_item_keterangan').val(item.keterangan || '');

            const besarSelect = $('#edit_item_satuan_besar');
            besarSelect.empty();
            besarSelect.append('<option value="">- (pakai satuan kecil)</option>');

            const unitDasar = item.satuanKecil || item.satuan || 'LBR';
            const seedFactors = { [unitDasar]: 1 };
            if (item.satuanBesar && item.unitFactor) {
                seedFactors[item.satuanBesar] = item.unitFactor;
            }
            $('#editItemModal').data('unit-factors', seedFactors);

            if (item.kodeBarangId) {
                $.ajax({
                    url: `{{ url('sales-order/available-units') }}/${item.kodeBarangId}`,
                    method: 'GET',
                    success: function(units) {
                        const unitList = Array.isArray(units) ? units : (units.units || []);
                        const factors = Array.isArray(units) ? {} : (units.factors || {});
                        $('#editItemModal').data('unit-factors', Object.assign({ [unitDasar]: 1 }, factors));
                        unitList.forEach(function(u) {
                            if (u !== unitDasar) {
                                const factor = factors[u] || 1;
                                besarSelect.append(`<option value="${u}">${u} (1 = ${factor} ${unitDasar})</option>`);
                            }
                        });
                        besarSelect.val(item.satuanBesar || '');
                        updateEditItemQtyHint();
                    },
                    error: function() {
                        if (item.satuanBesar) {
                            besarSelect.append(`<option value="${item.satuanBesar}">${item.satuanBesar}</option>`);
                        }
                        besarSelect.val(item.satuanBesar || '');
                        updateEditItemQtyHint();
                    }
                });
            } else if (item.satuanBesar) {
                besarSelect.append(`<option value="${item.satuanBesar}">${item.satuanBesar}</option>`);
                besarSelect.val(item.satuanBesar);
            }

            $('#editItemModal').modal('show');
            updateEditItemQtyHint();
        }

        function updateEditItemQtyHint() {
            const qty = parseFloat($('#edit_item_qty').val()) || 0;
            const satuanKecil = $('#edit_item_satuan_kecil').val() || 'LBR';
            const satuanBesar = $('#edit_item_satuan_besar').val() || '';
            const factors = $('#editItemModal').data('unit-factors') || {};
            const factor = satuanBesar ? (parseFloat(factors[satuanBesar]) || 1) : 1;
            const hint = $('#edit_item_qty_hint');
            if (satuanBesar && qty > 0 && factor > 1) {
                hint.text(`${qty} ${satuanBesar} = ${qty * factor} ${satuanKecil} (harga × ${satuanKecil})`).show();
            } else {
                hint.hide().text('');
            }
        }

        $(document).on('input change', '#edit_item_qty, #edit_item_satuan_besar', updateEditItemQtyHint);

        $('#saveEditItemBtn').click(function() {
            const index = parseInt($('#edit_item_index').val(), 10);
            const item = items[index];
            if (!item) return;

            const qty = parseFloat($('#edit_item_qty').val()) || 0;
            const harga = parseFloat($('#edit_item_harga').val()) || 0;
            const diskon = parseFloat($('#edit_item_diskon').val()) || 0;
            const ongkosKuli = parseFloat($('#edit_item_ongkos_kuli').val()) || 0;
            const keterangan = $('#edit_item_keterangan').val();
            const namaBarang = $('#edit_item_nama').val() || item.namaBarang;
            const satuanKecil = item.satuanKecil || item.satuan || 'LBR';
            const satuanBesar = $('#edit_item_satuan_besar').val();
            const factors = $('#editItemModal').data('unit-factors') || {};
            const factor = satuanBesar ? (parseFloat(factors[satuanBesar]) || item.unitFactor || 1) : 1;

            if (!qty) {
                alert('Mohon lengkapi qty dan harga!');
                return;
            }

            const qtyKecil = qty * factor;
            const subtotal = harga * qtyKecil;
            const total = subtotal - (subtotal * diskon) / 100;

            items[index] = Object.assign({}, item, {
                namaBarang,
                qty,
                qtyKecil,
                unitFactor: factor,
                harga,
                diskon,
                ongkosKuli,
                keterangan,
                satuanKecil,
                satuanBesar,
                satuan: satuanBesar ? satuanBesar : satuanKecil,
                total
            });

            $('#editItemModal').modal('hide');
            renderItems();
            calculateTotals();
        });

        // Calculate all totals
        function calculateTotals() {
            // Calculate subtotal
            const subtotal = items.reduce((sum, item) => sum + item.total, 0);
            $('#total').val(formatCurrency(subtotal));

            // Calculate discount
            let discountAmount = 0;
            if ($('#discount_checkbox').is(':checked')) {
                const discountPercent = parseFloat($('#discount_percent').val()) || 0;
                discountAmount = (subtotal * discountPercent) / 100;
            }
            $('#discount_amount').val(formatCurrency(discountAmount));

            // Calculate additional discount
            let discRp = 0;
            if ($('#disc_rp_checkbox').is(':checked')) {
                discRp = parseFloat($('#disc_rp').val()) || 0;
            }

            // Calculate PPN
            let ppnAmount = 0;
            if ($('#ppn_checkbox').is(':checked')) {
                ppnAmount = ((subtotal - discountAmount - discRp) * 0.11); // Using 11% for PPN
            }
            $('#ppn_amount').val(formatCurrency(ppnAmount));

            // Calculate DP
            let dpAmount = 0;
            if ($('#dp_checkbox').is(':checked')) {
                dpAmount = parseFloat($('#dp_amount').val()) || 0;
            }

            // Calculate grand total
            grandTotal = subtotal - discountAmount - discRp + ppnAmount - dpAmount;
            $('#grand_total').val(formatCurrency(grandTotal));
        }

        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID').format(amount);
        }

        // Update transaction
        $('#updateTransaction').click(function() {
            if (confirm('Apakah Anda yakin ingin menyimpan perubahan?')) {
                if (!$('#kode_customer').val()) {
                    alert('Pilih customer dari daftar yang tersedia!');
                    return;
                }

                if (items.length === 0) {
                    alert('Tidak ada barang yang ditambahkan!');
                    return;
                }
                
                if (!$('#edit_reason').val().trim()) {
                    alert('Alasan edit harus diisi!');
                    return;
                }

                const transactionData = {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    tanggal: $('#tanggal').val(),
                    kode_customer: $('#kode_customer').val(),
                    sales: $('#sales').val(),
                    pembayaran: $('#metode_pembayaran').val(),
                    cara_bayar: $('#cara_bayar').val(),
                    tanggal_jadi: $('#tanggal_jadi').val(),
                    items: items,
                    subtotal: $('#total').val().replace(/\./g, ''),
                    discount: $('#discount_amount').val().replace(/\./g, ''),
                    disc_rupiah: $('#disc_rp').val(),
                    ppn: $('#ppn_amount').val().replace(/\./g, ''),
                    dp: $('#dp_amount').val(),
                    grand_total: grandTotal,
                    edit_reason: $('#edit_reason').val(),
                    notes: $('#notes').val()
                };

                showLoading();

                // Send data to backend
                $.ajax({
                    url: "{{ route('transaksi.update', $transaction->id) }}",
                    method: "POST",
                    data: transactionData,
                    success: function(response) {
                        hideLoading();
                        alert(response.message || 'Transaksi berhasil diperbarui!');
                        
                        // Use the redirect URL from the response
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            window.location.href = "{{ route('transaksi.shownota', $transaction->id) }}";
                        }
                    },
                    error: function(xhr) {
                        hideLoading();
                        alert('Terjadi kesalahan: ' + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText));
                    }
                });
            }
        });
    });
</script>
@endsection