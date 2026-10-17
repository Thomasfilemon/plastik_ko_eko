@extends('layout.Nav')

@section('content')
<div class="card">
	<div class="card-header">Laporan</div>
	<div class="card-body">
		<div class="list-group">
			<a href="{{ route('laporan.laba-per-faktur') }}" class="list-group-item list-group-item-action">
				Laporan Laba per Faktur
			</a>
			<a href="{{ route('laporan.laba-per-barang') }}" class="list-group-item list-group-item-action">
				Laporan Laba per Barang
			</a>
			<a href="{{ route('laporan.ongkos-kuli') }}" class="list-group-item list-group-item-action">
				Laporan Ongkos Kuli
			</a>
			<a href="{{ route('laporan.komisi-sales') }}" class="list-group-item list-group-item-action">
				Laporan Komisi Sales
			</a>
			<a href="{{ route('stock.mutasi') }}" class="list-group-item list-group-item-action">
				Laporan Stok
			</a>
			<a href="{{ route('laporan.piutang') }}" class="list-group-item list-group-item-action">
				Laporan Piutang Pelanggan
			</a>
			<a href="{{ route('laporan.utang-supplier') }}" class="list-group-item list-group-item-action">
				Laporan Utang Supplier
			</a>
		</div>
	</div>
</div>
@endsection


