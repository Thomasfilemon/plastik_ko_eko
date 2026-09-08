@extends('layout.Nav')

@section('content')
<div class="card">
	<div class="card-header">Laporan Pembayaran Piutang</div>
	<div class="card-body">
		@if(isset($summary))
		<div class="mb-3">
			<strong>Total Faktur:</strong> {{ number_format($summary['total_faktur'] ?? 0) }} |
			<strong>Total Nilai Faktur:</strong> Rp {{ number_format($summary['total_nilai_faktur'] ?? 0, 0, ',', '.') }} |
			<strong>Total Sudah Dibayar:</strong> Rp {{ number_format($summary['total_sudah_dibayar'] ?? 0, 0, ',', '.') }} |
			<strong>Total Sisa Piutang:</strong> Rp {{ number_format($summary['total_sisa_piutang'] ?? 0, 0, ',', '.') }}
			@if(!empty($allDates))
				| <small class="text-muted"><strong>Filter Tanggal:</strong> Semua Tanggal</small>
			@else
				| <small class="text-muted"><strong>Periode:</strong> {{ \Illuminate\Support\Carbon::parse($startDate)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($endDate)->format('d/m/Y') }}</small>
			@endif
		</div>
		@endif

		<form method="GET" action="{{ route('pembayaran-piutang.laporan') }}" class="mb-3" id="filterPembayaranPiutangForm">
			<div class="row">
				<div class="col-md-3">
					<label>Start Date</label>
					<input type="date" name="start_date" id="start_date" class="form-control"
						value="{{ request('start_date', \Illuminate\Support\Carbon::parse($startDate)->format('Y-m-d')) }}"
						{{ !empty($allDates) ? 'disabled' : '' }}>
				</div>
				<div class="col-md-3">
					<label>End Date</label>
					<input type="date" name="end_date" id="end_date" class="form-control"
						value="{{ request('end_date', \Illuminate\Support\Carbon::parse($endDate)->format('Y-m-d')) }}"
						{{ !empty($allDates) ? 'disabled' : '' }}>
				</div>
				<div class="col-md-3 d-flex align-items-end mb-2">
					<div class="form-check">
						<input type="checkbox" class="form-check-input" name="all_dates" id="all_dates" value="1"
							{{ !empty($allDates) || request('all_dates') ? 'checked' : '' }}>
						<label class="form-check-label" for="all_dates">Semua Tanggal</label>
					</div>
				</div>
				<div class="col-md-3">
					<label>Customer</label>
					<select name="kode_customer" class="form-control">
						<option value="">-- Semua Customer --</option>
						@foreach($customers as $c)
							<option value="{{ $c->kode_customer }}" {{ (request('kode_customer') == $c->kode_customer) ? 'selected' : '' }}>
								{{ $c->nama }}
							</option>
						@endforeach
					</select>
				</div>
			</div>
			<div class="row mt-2">
				<div class="col-md-3">
					<label>Status Piutang</label>
					<select name="status_piutang" class="form-control">
						<option value="">-- Semua --</option>
						<option value="lunas" {{ request('status_piutang') == 'lunas' ? 'selected' : '' }}>Lunas</option>
						<option value="sebagian" {{ request('status_piutang') == 'sebagian' ? 'selected' : '' }}>Sebagian</option>
						<option value="belum_dibayar" {{ request('status_piutang') == 'belum_dibayar' ? 'selected' : '' }}>Belum Dibayar</option>
					</select>
				</div>
				<div class="col-md-3">
					<label>Urutkan Berdasarkan</label>
					<select name="sort_by" class="form-control">
						<option value="tanggal" {{ ($sortBy ?? request('sort_by', 'tanggal')) == 'tanggal' ? 'selected' : '' }}>Tanggal Dibuat (Faktur)</option>
						<option value="tanggal_jatuh_tempo" {{ ($sortBy ?? request('sort_by')) == 'tanggal_jatuh_tempo' ? 'selected' : '' }}>Tanggal Jatuh Tempo</option>
					</select>
				</div>
				<div class="col-md-3">
					<label>Arah Urutan</label>
					<select name="sort_dir" class="form-control">
						<option value="asc" {{ ($sortDir ?? request('sort_dir')) == 'asc' ? 'selected' : '' }}>Naik (1 → …)</option>
						<option value="desc" {{ ($sortDir ?? request('sort_dir', 'desc')) == 'desc' ? 'selected' : '' }}>Turun (… → 1)</option>
					</select>
				</div>
				<div class="col-md-3 d-flex align-items-end">
					<button type="submit" class="btn btn-primary me-2">Filter</button>
					<a href="{{ route('pembayaran-piutang.laporan') }}" class="btn btn-secondary">Reset</a>
				</div>
			</div>
		</form>

		<div class="table-responsive">
			<table class="table table-bordered table-sm">
				<thead>
					<tr>
						<th style="width: 50px;">No</th>
						<th>No Faktur</th>
						<th>Tanggal</th>
						<th>Jatuh Tempo</th>
						<th>Customer</th>
						<th>Total</th>
						<th>Sudah Dibayar</th>
						<th>Sisa</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					@forelse(($transaksi ?? []) as $index => $t)
					<tr>
						<td>{{ $index + 1 }}</td>
						<td>{{ $t->no_transaksi }}</td>
						<td>{{ optional($t->tanggal)->format('d/m/Y') }}</td>
						<td>{{ optional($t->tanggal_jatuh_tempo)->format('d/m/Y') ?? '-' }}</td>
						<td>{{ optional($t->customer)->nama ?? '-' }}</td>
						<td class="text-right">{{ number_format($t->grand_total, 0, ',', '.') }}</td>
						<td class="text-right">{{ number_format($t->total_dibayar, 0, ',', '.') }}</td>
						<td class="text-right">{{ number_format($t->sisa_piutang, 0, ',', '.') }}</td>
						<td>{{ $t->status_piutang }}</td>
					</tr>
					@empty
					<tr><td colspan="9" class="text-center">Tidak ada data</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
	const allDates = document.getElementById('all_dates');
	const startDate = document.getElementById('start_date');
	const endDate = document.getElementById('end_date');
	const form = document.getElementById('filterPembayaranPiutangForm');

	function syncDateInputs() {
		const disabled = allDates.checked;
		startDate.disabled = disabled;
		endDate.disabled = disabled;
	}

	allDates.addEventListener('change', syncDateInputs);
	syncDateInputs();

	form.addEventListener('submit', function () {
		if (!allDates.checked) {
			startDate.disabled = false;
			endDate.disabled = false;
		}
	});
});
</script>
@endsection
