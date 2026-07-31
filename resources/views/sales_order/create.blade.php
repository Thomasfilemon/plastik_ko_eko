@extends('layout.Nav')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Buat Sales Order Baru</h3>
                    <div class="card-tools">
                        <a href="{{ route('sales-order.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('sales-order.store') }}" method="POST" id="salesOrderForm">
                        @csrf
                        
                        <!-- Header Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="no_so">No. Sales Order</label>
                                    <input type="text" class="form-control" id="no_so" name="no_so" value="{{ $noSo }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tanggal">Tanggal</label>
                                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer_id">Customer</label>
                                    <select class="form-control" id="customer_id" name="customer_id" required>
                                        <option value="">Pilih Customer</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" 
                                                data-limit-kredit="{{ $customer->limit_kredit }}"
                                                data-hari-tempo="{{ $customer->limit_hari_tempo }}">
                                                {{ $customer->nama }} - {{ $customer->getStatusKredit() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="salesman_id">Salesman</label>
                                    <select class="form-control" id="salesman_id" name="salesman_id" required>
                                        <option value="">Pilih Salesman</option>
                                        @foreach($salesmen as $salesman)
                                            <option value="{{ $salesman->id }}">{{ $salesman->keterangan }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="cara_bayar">Cara Bayar</label>
                                    <select class="form-control" id="cara_bayar" name="cara_bayar" required>
                                        <option value="Kredit" selected>Kredit</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group" id="hari_tempo_group" style="display: none;">
                                    <label for="hari_tempo">Hari Tempo</label>
                                    <input type="number" class="form-control" id="hari_tempo" name="hari_tempo" min="0" value="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group" id="jatuh_tempo_group" style="display: none;">
                                    <label for="tanggal_jatuh_tempo">Tanggal Jatuh Tempo</label>
                                    <input type="date" class="form-control" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="tanggal_estimasi">Tanggal Estimasi Pengiriman</label>
                                    <input type="date" class="form-control" id="tanggal_estimasi" name="tanggal_estimasi">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="2"></textarea>
                        </div>

                        <hr>

                        <!-- Items Section -->
                        <h5>Detail Barang</h5>
                        <div id="items-container">
                            <div class="item-row" data-index="0">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Barang</label>
                                            <select class="form-control item-barang" name="items[0][kode_barang_id]" required>
                                                <option value="">Pilih Barang</option>
                                                @foreach($kodeBarangs as $barang)
                                                    <option value="{{ $barang->id }}" 
                                                        data-harga="{{ $barang->harga_jual }}"
                                                        data-unit-dasar="{{ $barang->unit_dasar }}"
                                                        data-kode="{{ $barang->kode_barang }}">
                                                        {{ $barang->kode_barang }} - {{ $barang->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Qty</label>
                                            <input type="number" class="form-control item-qty" name="items[0][qty]" step="0.01" min="0.01" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Satuan Kecil</label>
                                            <select class="form-control item-satuan-kecil" name="items[0][satuan_kecil]">
                                                <option value="LBR">LBR</option>
                                            </select>
                                            <input type="hidden" class="item-satuan" name="items[0][satuan]" value="LBR">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Satuan Besar</label>
                                            <select class="form-control item-satuan-besar" name="items[0][satuan_besar]"></select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Harga</label>
                                            <input type="number" class="form-control item-harga" name="items[0][harga]" step="0.01" min="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="form-group">
                                            <label>Total</label>
                                            <input type="number" class="form-control item-total" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-11">
                                        <div class="form-group">
                                            <label>Keterangan</label>
                                            <input type="text" class="form-control" name="items[0][keterangan]">
                                        </div>
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end justify-content-end">
                                        <button type="button" class="btn btn-danger btn-sm remove-item" style="display: none;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-success btn-sm" id="add-item">
                                    <i class="fas fa-plus"></i> Tambah Item
                                </button>
                            </div>
                        </div>

                        <hr>

                        <!-- Summary -->
                        <div class="row">
                            <div class="col-md-6 offset-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Subtotal:</strong></td>
                                        <td class="text-right">Rp <span id="subtotal">0</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Grand Total:</strong></td>
                                        <td class="text-right">Rp <span id="grand-total">0</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Sales Order
                                </button>
                                <a href="{{ route('sales-order.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Error Messages -->
@if($errors->any())
<div class="alert alert-danger">
    <ul>
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let itemIndex = 0;
    
    console.log('Sales Order form initialized');
    
    // Debug: Check if jQuery is loaded
    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded!');
        return;
    }
    
    // Debug: Check if add-item button exists
    if ($('#add-item').length === 0) {
        console.error('Add item button not found!');
        return;
    }
    
    console.log('Add item button found:', $('#add-item').length);
    
    // Initialize the first item row
    $('.item-row').first().find('.remove-item').hide();
    
    // Handle cara bayar change
    $('#cara_bayar').change(function() {
        if ($(this).val() === 'Kredit') {
            $('#hari_tempo_group').show();
            $('#hari_tempo').prop('required', true);
            $('#jatuh_tempo_group').show();
        } else {
            $('#hari_tempo_group').hide();
            $('#hari_tempo').prop('required', false);
            $('#jatuh_tempo_group').hide();
            $('#tanggal_jatuh_tempo').val('');
        }
    });

    // Initialize visibility on load (default Kredit)
    if ($('#cara_bayar').val() === 'Kredit') {
        $('#hari_tempo_group').show();
        $('#hari_tempo').prop('required', true);
        $('#jatuh_tempo_group').show();
    } else {
        $('#hari_tempo_group').hide();
        $('#hari_tempo').prop('required', false);
        $('#jatuh_tempo_group').hide();
    }

    function recalcSoJatuhTempo(){
        const base = $('#tanggal').val();
        const hari = parseInt($('#hari_tempo').val()||'0',10);
        if(!base || isNaN(hari)) return;
        const d = new Date(base);
        d.setDate(d.getDate()+hari);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth()+1).padStart(2,'0');
        const dd = String(d.getDate()).padStart(2,'0');
        $('#tanggal_jatuh_tempo').val(`${yyyy}-${mm}-${dd}`);
    }
    $('#tanggal').on('change', recalcSoJatuhTempo);
    $('#hari_tempo').on('input', recalcSoJatuhTempo);

    // Handle customer change
    $('#customer_id').change(function() {
        const selectedOption = $(this).find('option:selected');
        const limitKredit = selectedOption.data('limit-kredit');
        const hariTempo = selectedOption.data('hari-tempo');
        
        if ($('#cara_bayar').val() === 'Kredit') {
            $('#hari_tempo').val(hariTempo);
        }
    });

    // Handle barang change
    $(document).on('change', '.item-barang', function() {
        const row = $(this).closest('.item-row');
        const selectedOption = $(this).find('option:selected');
        const harga = selectedOption.data('harga') || 0;
        const kodeBarangStr = selectedOption.data('kode');
        const unitDasarFromOption = selectedOption.data('unit-dasar') || 'LBR';
        
        row.find('.item-harga').val(harga);
        
        // Immediately set small unit from selected item's unit_dasar (like Pembelian)
        const kecilSelect = row.find('.item-satuan-kecil');
        const besarSelect = row.find('.item-satuan-besar');
        kecilSelect.empty();
        besarSelect.empty();
        kecilSelect.append('<option value="'+unitDasarFromOption+'">'+unitDasarFromOption+'</option>');
        row.find('.item-satuan').val(unitDasarFromOption);
        
        // Get available units for this product (populate big units)
        const kodeBarangId = $(this).val();
        if (kodeBarangId) {
            $.ajax({
                url: `{{ route('sales-order.available-units', '') }}/${kodeBarangId}`,
                method: 'GET',
                success: function(units) {
                    const unitList = Array.isArray(units) ? units : (units.units || []);
                    if (unitList.length > 0) {
                        unitList.forEach(function(unit) {
                            if (unit !== unitDasarFromOption) {
                                besarSelect.append('<option value="'+unit+'">'+unit+'</option>');
                            }
                        });
                    }
                    calculateItemTotal(row);
                },
                error: function() {
                    // leave besar empty
                    calculateItemTotal(row);
                }
            });
        } else {
            // If no product selected, reset to default
            row.find('.item-satuan-kecil').html('<option value="LBR">LBR</option>');
            row.find('.item-satuan-besar').html('');
            row.find('.item-satuan').val('LBR');
        }
        
        calculateItemTotal(row);
    });

    // Handle qty change
    $(document).on('input', '.item-qty', function() {
        calculateItemTotal($(this).closest('.item-row'));
    });

    // Handle harga change
    $(document).on('input', '.item-harga', function() {
        calculateItemTotal($(this).closest('.item-row'));
    });

    function updateSatuanAndHarga(row, unit) {
        const customerId = $('#customer_id').val();
        const kodeBarangId = row.find('.item-barang').val();
        row.find('.item-satuan').val(unit);
        if (customerId && kodeBarangId && unit) {
            $.ajax({
                url: '{{ route("sales-order.customer-price") }}',
                method: 'GET',
                data: { customer_id: customerId, kode_barang_id: kodeBarangId, unit: unit },
                success: function(priceInfo) {
                    row.find('.item-harga').val(priceInfo.harga_jual);
                    calculateItemTotal(row);
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching customer price:', error);
                }
            });
        }
    }

    // Handle satuan kecil change
    $(document).on('change', '.item-satuan-kecil', function() {
        const row = $(this).closest('.item-row');
        const unit = $(this).val();
        // Clear selection on besar (visual only)
        row.find('.item-satuan-besar').prop('selectedIndex', -1);
        updateSatuanAndHarga(row, unit);
    });

    // Handle satuan besar change
    $(document).on('change', '.item-satuan-besar', function() {
        const row = $(this).closest('.item-row');
        const unit = $(this).val();
        updateSatuanAndHarga(row, unit);
    });

    // Add new item
    $('#add-item').on('click', function() {
        console.log('Add item button clicked');
        
        itemIndex++;
        console.log('Creating new item row with index:', itemIndex);
        
        const newRow = $('.item-row').first().clone();
        console.log('Cloned row:', newRow.length);
        
        // Update names and IDs
        newRow.attr('data-index', itemIndex);
        newRow.find('select, input').each(function() {
            const name = $(this).attr('name');
            if (name) {
                const newName = name.replace('[0]', `[${itemIndex}]`);
                $(this).attr('name', newName);
                console.log('Updated name:', name, '->', newName);
            }
        });
        
        // Clear values
        newRow.find('input').val('');
        newRow.find('select').val('');
        newRow.find('.item-satuan-kecil').html('<option value="LBR">LBR</option>');
        newRow.find('.item-satuan-besar').html('');
        newRow.find('.item-satuan').val('LBR');
        newRow.find('.item-total').val('0');
        newRow.find('.remove-item').show();
        
        // Re-enable all form elements
        newRow.find('input, select').prop('disabled', false);
        
        $('#items-container').append(newRow);
        
        // Scroll to the new row
        $('html, body').animate({
            scrollTop: newRow.offset().top - 100
        }, 500);
        
        console.log('Added new item row with index:', itemIndex);
    });

    // Remove item
    $(document).on('click', '.remove-item', function() {
        const row = $(this).closest('.item-row');
        const index = row.attr('data-index');
        console.log('Removing item row with index:', index);
        
        row.remove();
        calculateTotals();
        
        // If no items left, show the first remove button
        if ($('.item-row').length === 1) {
            $('.remove-item').hide();
        }
    });

    // Calculate item total
    function calculateItemTotal(row) {
        const qty = parseFloat(row.find('.item-qty').val()) || 0;
        const harga = parseFloat(row.find('.item-harga').val()) || 0;
        const total = qty * harga;
        
        row.find('.item-total').val(total.toFixed(2));
        calculateTotals();
    }

    // Calculate totals
    function calculateTotals() {
        let subtotal = 0;
        $('.item-total').each(function() {
            subtotal += parseFloat($(this).val()) || 0;
        });
        
        $('#subtotal').text(subtotal.toLocaleString('id-ID'));
        $('#grand-total').text(subtotal.toLocaleString('id-ID'));
    }

    // Form validation
    $('#salesOrderForm').submit(function(e) {
        const customerId = $('#customer_id').val();
        const caraBayar = $('#cara_bayar').val();
        
        if (caraBayar === 'Kredit' && !customerId) {
            alert('Customer harus dipilih untuk transaksi kredit');
            e.preventDefault();
            return false;
        }
        
        // Check if at least one item has data
        let hasItems = false;
        $('.item-barang').each(function() {
            if ($(this).val()) {
                hasItems = true;
                return false;
            }
        });
        
        if (!hasItems) {
            alert('Minimal satu item harus diisi');
            e.preventDefault();
            return false;
        }
    });
});
</script>
@endpush
