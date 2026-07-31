@extends('layout.Nav')

@section('content')
<div class="container-fluid">
	<div class="row">
		<div class="col-12">
			<div class="card">
				<div class="card-header">
					<h3 class="card-title">Edit Sales Order</h3>
					<div class="card-tools">
						<a href="{{ route('sales-order.show', $salesOrder) }}" class="btn btn-secondary btn-sm">Batal</a>
					</div>
				</div>
				<div class="card-body">
					<form action="{{ route('sales-order.update', $salesOrder) }}" method="POST" id="editSalesOrderForm">
						@csrf
						@method('PUT')

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label>No. Sales Order</label>
									<input type="text" class="form-control" value="{{ $salesOrder->no_so }}" readonly>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="tanggal">Tanggal</label>
									<input type="date" class="form-control" id="tanggal" name="tanggal" value="{{ optional($salesOrder->tanggal)->format('Y-m-d') }}" required>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="customer_id">Customer</label>
									<select class="form-control" id="customer_id" name="customer_id" required>
										@foreach($customers as $customer)
											<option value="{{ $customer->id }}" {{ $salesOrder->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->nama }}</option>
										@endforeach
									</select>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="salesman_id">Salesman</label>
									<select class="form-control" id="salesman_id" name="salesman_id" required>
										@foreach($salesmen as $salesman)
											<option value="{{ $salesman->id }}" {{ $salesOrder->salesman_id == $salesman->id ? 'selected' : '' }}>{{ $salesman->keterangan }}</option>
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
								<div class="form-group" id="hari_tempo_group">
									<label for="hari_tempo">Hari Tempo</label>
									<input type="number" class="form-control" id="hari_tempo" name="hari_tempo" min="0" value="{{ $salesOrder->hari_tempo }}">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label for="tanggal_estimasi">Tanggal Estimasi Pengiriman</label>
									<input type="date" class="form-control" id="tanggal_estimasi" name="tanggal_estimasi" value="{{ optional($salesOrder->tanggal_estimasi)->format('Y-m-d') }}">
								</div>
							</div>
						</div>

						<div class="form-group">
							<label for="keterangan">Keterangan</label>
							<textarea class="form-control" id="keterangan" name="keterangan" rows="2">{{ $salesOrder->keterangan }}</textarea>
						</div>

						<hr>

						<h5>Detail Barang</h5>
						<div id="items-container">
							@foreach($salesOrder->items as $index => $item)
							<div class="item-row" data-index="{{ $index }}">
								<div class="row">
									<div class="col-md-3">
										<div class="form-group">
											<label>Barang</label>
											<select class="form-control item-barang" name="items[{{ $index }}][kode_barang_id]" required>
												@foreach($kodeBarangs as $barang)
													<option value="{{ $barang->id }}" data-harga="{{ $barang->harga_jual }}" data-unit-dasar="{{ $barang->unit_dasar }}" data-kode="{{ $barang->kode_barang }}" {{ $barang->id == $item->kode_barang_id ? 'selected' : '' }}>{{ $barang->kode_barang }} - {{ $barang->name }}</option>
												@endforeach
											</select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="form-group">
											<label>Qty</label>
											<input type="number" class="form-control item-qty" name="items[{{ $index }}][qty]" step="0.01" min="0.01" value="{{ $item->qty }}" required>
										</div>
									</div>
									<div class="col-md-2">
										<div class="form-group">
											<label>Satuan Kecil</label>
											<select class="form-control item-satuan-kecil" name="items[{{ $index }}][satuan_kecil]"></select>
											<input type="hidden" class="item-satuan" name="items[{{ $index }}][satuan]" value="{{ $item->satuan }}">
										</div>
									</div>
									<div class="col-md-2">
										<div class="form-group">
											<label>Satuan Besar</label>
											<select class="form-control item-satuan-besar" name="items[{{ $index }}][satuan_besar]"></select>
										</div>
									</div>
									<div class="col-md-2">
										<div class="form-group">
											<label>Harga</label>
											<input type="number" class="form-control item-harga" name="items[{{ $index }}][harga]" step="0.01" min="0" value="{{ $item->harga }}" required>
										</div>
									</div>
									<div class="col-md-1">
										<div class="form-group">
											<label>Total</label>
											<input type="number" class="form-control item-total" value="{{ $item->total }}" readonly>
										</div>
									</div>
								</div>
							<div class="row">
								<div class="col-md-11">
									<div class="form-group">
										<label>Keterangan</label>
										<input type="text" class="form-control" name="items[{{ $index }}][keterangan]" value="{{ $item->keterangan }}">
									</div>
								</div>
								<div class="col-md-1 d-flex align-items-end justify-content-end">
									<button type="button" class="btn btn-danger btn-sm remove-item" style="display: none;">
										<i class="fas fa-trash"></i>
									</button>
								</div>
							</div>
						</div>
						@endforeach
					</div>

						<div class="row mt-3">
							<div class="col-md-12">
								<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
								<a href="{{ route('sales-order.show', $salesOrder) }}" class="btn btn-secondary">Batal</a>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
	function populateUnitsForRow(row) {
		const barangSelect = row.find('.item-barang');
		const selectedOption = barangSelect.find('option:selected');
		const kodeBarangStr = selectedOption.data('kode');
		const kodeBarangId = barangSelect.val();
		const currentSatuan = row.find('.item-satuan').val() || '{{ $salesOrder->items->first()->satuan ?? '' }}';

		if (!kodeBarangId) {
			row.find('.item-satuan-kecil').html('<option value="LBR">LBR</option>');
			row.find('.item-satuan-besar').html('');
			return;
		}

		// Ambil satuan kecil dari stocks, lalu populate satuan besar dari unit conversion
		$.ajax({
			url: '{{ route('stock.get') }}',
			method: 'GET',
			data: { kode_barang: kodeBarangStr },
			success: function(resp) {
				const kecilSelect = row.find('.item-satuan-kecil');
				const besarSelect = row.find('.item-satuan-besar');
				kecilSelect.empty();
				besarSelect.empty();
				const unitDasar = resp && resp.success && resp.data && resp.data.satuan ? resp.data.satuan : 'LBR';
				kecilSelect.append('<option value="'+unitDasar+'">'+unitDasar+'</option>');
				$.ajax({
					url: `{{ route('sales-order.available-units', '') }}/${kodeBarangId}`,
					method: 'GET',
					success: function(units) {
						const unitList = Array.isArray(units) ? units : (units.units || []);
						if (unitList.length > 0) {
							unitList.forEach(function(unit) {
								if (unit !== unitDasar) {
									besarSelect.append('<option value="'+unit+'">'+unit+'</option>');
								}
							});
						}
						row.find('.item-satuan').val(currentSatuan || unitDasar);
					},
					error: function() {
						row.find('.item-satuan').val(currentSatuan || unitDasar);
					}
				});
			},
			error: function() {
				row.find('.item-satuan-kecil').html('<option value="LBR">LBR</option>');
				row.find('.item-satuan-besar').html('');
				row.find('.item-satuan').val(currentSatuan || 'LBR');
			}
		});
	}

	function calculateItemTotal(row) {
		const qty = parseFloat(row.find('.item-qty').val()) || 0;
		const harga = parseFloat(row.find('.item-harga').val()) || 0;
		const total = qty * harga;
		row.find('.item-total').val(total.toFixed(2));
	}

	// Initialize existing rows
	$('#items-container .item-row').each(function() {
		populateUnitsForRow($(this));
		calculateItemTotal($(this));
	});

	// When barang changes, repopulate units and set harga default
	$(document).on('change', '.item-barang', function() {
		const row = $(this).closest('.item-row');
		const selectedOption = $(this).find('option:selected');
		const harga = selectedOption.data('harga') || 0;
		row.find('.item-harga').val(harga);
		populateUnitsForRow(row);
		calculateItemTotal(row);
	});

	function updateSatuanAndHarga(row, unit) {
		const customerId = $('#customer_id').val();
		const kodeBarangId = row.find('.item-barang').val();
		row.find('.item-satuan').val(unit);
		if (customerId && kodeBarangId && unit) {
			$.ajax({
				url: '{{ route('sales-order.customer-price') }}',
				method: 'GET',
				data: { customer_id: customerId, kode_barang_id: kodeBarangId, unit: unit },
				success: function(priceInfo) {
					row.find('.item-harga').val(priceInfo.harga_jual);
					calculateItemTotal(row);
				}
			});
		}
	}

	$(document).on('change', '.item-satuan-kecil', function() {
		const row = $(this).closest('.item-row');
		const unit = $(this).val();
		row.find('.item-satuan-besar').prop('selectedIndex', -1);
		updateSatuanAndHarga(row, unit);
	});

	$(document).on('change', '.item-satuan-besar', function() {
		const row = $(this).closest('.item-row');
		const unit = $(this).val();
		updateSatuanAndHarga(row, unit);
	});

	// Recalculate total on input changes
	$(document).on('input', '.item-qty, .item-harga', function() {
		calculateItemTotal($(this).closest('.item-row'));
	});
});
</script>
@endpush


