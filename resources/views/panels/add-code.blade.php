@extends('layout.Nav')

@section('content')
<div class="container">
    <div class="title-box">
        <h2><i class="fas fa-plus-circle mr-2"></i>Add Panels to Inventory</h2>
    </div>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">New Panel Stock Entry</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('code.store-code') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label for="name"><i class="fas fa-ruler mr-1"></i> Nama Barang</label>
                            <input type="text" step="0.01" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Masukkan Nama Barang.</small>
                        </div>
                        <div class="form-group">
                            <label for="grup_barang_id"><i class="fas fa-tags mr-1"></i> Pilih Grup Barang:</label>
                            <select class="form-control @error('grup_barang_id') is-invalid @enderror" id="grup_barang_id" name="grup_barang_id" required>
                                <option value="">Pilih Grup Barang</option>
                                @foreach($group_names as $group_name)
                                    <option value="{{ $group_name }}" {{ old('grup_barang_id') == $group_name ? 'selected' : '' }}>
                                        {{ $group_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('grup_barang_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Pilih grup barang yang sesuai.</small>
                        </div>

                        <div class="form-group" style="display: none;">
                            <label for="attribute"><i class="fas fa-tag mr-1"></i> Attribute (Otomatis):</label>
                            <input type="hidden" class="form-control @error('attribute') is-invalid @enderror" id="attribute" name="attribute" value="{{ old('attribute') }}">
                            @error('attribute')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Attribute akan diisi otomatis berdasarkan grup barang yang dipilih.</small>
                        </div>
                        <div class="form-group">
                            <label for="kode_barang"><i class="fas fa-ruler mr-1"></i>Kode Barang (Otomatis)</label>
                            <input type="text" step="0.01" class="form-control @error('kode_barang') is-invalid @enderror" id="kode_barang" name="kode_barang" value="{{ old('kode_barang') }}" readonly required>
                            @error('kode_barang')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Kode barang akan di-generate otomatis berdasarkan grup barang yang dipilih.</small>
                        </div>
                        <!-- <div class="form-group">
                            <label for="length"><i class="fas fa-ruler mr-1"></i> Panjang Barang (meters)</label>
                            <input type="number" step="0.01" class="form-control @error('panjang') is-invalid @enderror" id="length" name="length" value="{{ old('length') }}" required>
                            @error('length')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Masukkan panjang barang dalam satuan meter.</small>
                        </div> -->
                        
                        <div class="form-group">
                            <label for="unit_dasar"><i class="fas fa-ruler mr-1"></i> Satuan Kecil</label>
                            <input type="text" class="form-control @error('unit_dasar') is-invalid @enderror" id="unit_dasar" name="unit_dasar" value="{{ old('unit_dasar') }}" list="satuan_kecil_suggestions" placeholder="Ketik satuan kecil (mis. LBR, KG, ROLL)" maxlength="20" required>
                            <datalist id="satuan_kecil_suggestions">
                                <option value="LBR"></option>
                                <option value="KG"></option>
                                <option value="M"></option>
                                <option value="PCS"></option>
                                <option value="PAK"></option>
                                <option value="BOX"></option>
                                <option value="ROLL"></option>
                            </datalist>
                            @error('unit_dasar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Ketik satuan sendiri atau pilih dari saran. Satuan terkecil untuk perhitungan harga.</small>
                        </div>
                        <div class="form-group">
                            <label for="harga_jual"><i class="fas fa-tag mr-1"></i> Harga Jual per Satuan Dasar</label>
                            <input type="number" step="0.01" class="form-control @error('harga_jual') is-invalid @enderror" id="harga_jual" name="harga_jual" value="{{ old('harga_jual') }}">
                            @error('harga_jual')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Harga jual per satuan dasar (misal: per LBR, per KG).</small>
                        </div>

                        <div class="form-group">
                            <label for="ongkos_kuli_default"><i class="fas fa-hand-holding-usd mr-1"></i> Ongkos Kuli Default</label>
                            <input type="number" step="0.01" class="form-control @error('ongkos_kuli_default') is-invalid @enderror" id="ongkos_kuli_default" name="ongkos_kuli_default" value="{{ old('ongkos_kuli_default') }}">
                            @error('ongkos_kuli_default')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Ongkos kuli default (opsional).</small>
                        </div>

                        <!-- <div class="form-group">
                            <label for="price"><i class="fas fa-ruler mr-1"></i> Harga Jual (Legacy)</label>
                            <input type="number" step="0.01" class="form-control @error('price') is-invalid @enderror" id="price" name="price" value="{{ old('price') }}" required>
                            @error('price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Harga jual legacy (akan dihitung otomatis berdasarkan satuan dasar).</small>
                        </div> -->
                        <div class="form-row">
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-save mr-1"></i> Tambahkan Barang
                                </button>
                            </div>
                            <div class="col-md-6">
                                <a href="{{ route('master.barang') }}" class="btn btn-secondary btn-block">
                                    <i class="fas fa-arrow-left mr-1"></i> Back to Inventory
                                </a>
                            </div>
                        </div>
                        <hr/>
                        <div class="form-group">
                            <label><i class="fas fa-cogs mr-1"></i> Konversi Satuan</label>
                            <div id="uc_list"></div>
                            <div class="d-flex mt-2">
                                <input type="text" id="uc_unit_add" class="form-control mr-2" placeholder="Satuan Turunan (mis. BOX)">
                                <input type="number" id="uc_value_add" class="form-control mr-2" placeholder="Nilai Konversi (ke kecil)">
                                <button type="button" id="uc_add_btn" class="btn btn-success">Tambah</button>
                            </div>
                            <input type="hidden" id="uc_payload" name="unit_conversions" value="[]">
                        </div>
                    </form>
                </div>
            </div>

            <!-- <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle mr-1"></i> Common Panel Lengths</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <button class="btn btn-outline-primary btn-block length-btn" data-length="4">
                                4 meters
                            </button>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button class="btn btn-outline-primary btn-block length-btn" data-length="6">
                                6 meters
                            </button>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button class="btn btn-outline-primary btn-block length-btn" data-length="8">
                                8 meters
                            </button>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button class="btn btn-outline-primary btn-block length-btn" data-length="10">
                                10 meters
                            </button>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button class="btn btn-outline-primary btn-block length-btn" data-length="12">
                                12 meters
                            </button>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button class="btn btn-outline-primary btn-block length-btn" data-length="3.6">
                                3.6 meters
                            </button>
                        </div>
                    </div>
                </div>
            </div> -->
        </div>
    </div>
</div>

<script>
// Input search handlers
// Open search modals

document.addEventListener('DOMContentLoaded', function() {
    // Quick length selection buttons - DISABLED (field length sudah dihapus)
    // const lengthButtons = document.querySelectorAll('.length-btn');
    // const lengthInput = document.getElementById('length');
    // lengthButtons.forEach(button => {
    //     button.addEventListener('click', function(e) {
    //         e.preventDefault();
    //         const length = this.getAttribute('data-length');
    //         lengthInput.value = length;
    //     });
    // });

    // Auto-fill attribute field when grup barang is selected
    const grupBarangSelect = document.getElementById('grup_barang_id');
    const attributeInput = document.getElementById('attribute');
    const kodeBarangInput = document.getElementById('kode_barang');

    grupBarangSelect.addEventListener('change', function() {
        const selectedGrupBarang = this.value;
        if (selectedGrupBarang) {
            attributeInput.value = selectedGrupBarang;
            
            // Auto-generate kode barang
            generateNextKodeBarang(selectedGrupBarang);
        } else {
            attributeInput.value = '';
            kodeBarangInput.value = '';
        }
    });

    // Function to generate next kode barang
    function generateNextKodeBarang(grupBarangName) {
        // Show loading state
        kodeBarangInput.value = 'Generating...';
        kodeBarangInput.disabled = true;

        // Call API to get next kode barang
        fetch(`/kode_barang/get-next-code?grup_barang_name=${encodeURIComponent(grupBarangName)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    kodeBarangInput.value = data.next_code;
                    kodeBarangInput.disabled = false;
                    
                    // Show success message
                    showMessage('Kode barang berhasil di-generate: ' + data.next_code, 'success');
                } else {
                    kodeBarangInput.value = '';
                    kodeBarangInput.disabled = false;
                    showMessage('Error: ' + (data.error || 'Gagal generate kode barang'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                kodeBarangInput.value = '';
                kodeBarangInput.disabled = false;
                showMessage('Error: Gagal menghubungi server', 'error');
            });
    }

    // Function to show messages
    function showMessage(message, type) {
        // Remove existing messages
        const existingMessage = document.querySelector('.alert');
        if (existingMessage) {
            existingMessage.remove();
        }

        // Create new message
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Insert message before the form
        const form = document.querySelector('form');
        form.parentNode.insertBefore(alertDiv, form);

        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Inline Unit Conversion on Add Form
    const ucList = document.getElementById('uc_list');
    const ucUnitAdd = document.getElementById('uc_unit_add');
    const ucValueAdd = document.getElementById('uc_value_add');
    const ucAddBtn = document.getElementById('uc_add_btn');
    const ucPayload = document.getElementById('uc_payload');
    let ucItems = [];

    function renderUc(){
        if(ucItems.length === 0){ ucList.innerHTML = '<div class="text-muted">Belum ada konversi.</div>'; return; }
        let html = '<table class="table table-sm"><thead><tr><th>Satuan</th><th>Nilai</th><th></th></tr></thead><tbody>';
        ucItems.forEach((it, idx) => {
            html += `<tr>
                        <td>${it.unit_turunan}</td>
                        <td>${it.nilai_konversi}</td>
                        <td><button type="button" class="btn btn-danger btn-sm" data-index="${idx}">Hapus</button></td>
                    </tr>`
        });
        html += '</tbody></table>';
        ucList.innerHTML = html;
        ucPayload.value = JSON.stringify(ucItems);
    }
    renderUc();

    ucAddBtn.addEventListener('click', function(){
        const u = (ucUnitAdd.value||'').trim();
        const v = parseInt(ucValueAdd.value||'0',10);
        if(!u || v < 1){ alert('Isi satuan dan nilai konversi >= 1'); return; }
        if(ucItems.find(x => x.unit_turunan.toUpperCase() === u.toUpperCase())){ alert('Satuan sudah ada'); return; }
        ucItems.push({ unit_turunan: u.toUpperCase(), nilai_konversi: v });
        ucUnitAdd.value=''; ucValueAdd.value='';
        renderUc();
    });

    ucList.addEventListener('click', function(e){
        const btn = e.target.closest('button[data-index]');
        if(!btn) return;
        const idx = parseInt(btn.getAttribute('data-index'), 10);
        ucItems.splice(idx,1);
        renderUc();
    });

    const searchPenambahInput = document.getElementById('searchPenambah');

    document.getElementById('searchPenambahBtn').addEventListener('click', function () {
        // Handle modal opening if needed
    });

    searchPenambahInput.addEventListener('input', function () {
        const keyword = this.value.trim(); // Trim whitespace
        
        if (keyword.length >= 1) {
            searchCodesDropdown(keyword, 'penambah');
        } else {
            // Hide dropdown when input is less than 1 characters or empty
            document.getElementById('penambahDropdown').style.display = 'none';
        }
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('penambahDropdown');
        const input = document.getElementById('searchPenambah');
        
        if (dropdown && input && !dropdown.contains(e.target) && !input.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    function searchCodesDropdown(keyword, type) {
        const panels = @json($group_names ?? []);
        const dropdown = document.getElementById(`${type}Dropdown`);
        
        // Filter panels that match the keyword
        const matchingPanels = panels.filter(panel =>
            panel.toLowerCase().includes(keyword.toLowerCase())
        );

        if (matchingPanels.length > 0) {
            let html = '';
            matchingPanels.forEach(panel => {
                html += `<a class="dropdown-item ${type}-dropdown-item" data-kode="${panel}">
                            ${panel}
                         </a>`;
            });
            dropdown.innerHTML = html;
            dropdown.style.display = 'block';

            // Add click event listeners to the dropdown items
            document.querySelectorAll(`.${type}-dropdown-item`).forEach(item => {
                item.addEventListener('click', function () {
                    const kode = this.getAttribute('data-kode');
                    searchPenambahInput.value = kode;
                    dropdown.style.display = 'none';
                });
            });
        } else {
            // Only show "No matching codes found" if there was actually a search attempt
            if (keyword.length >= 2) {
                dropdown.innerHTML = '<div class="dropdown-item text-muted">No matching codes found</div>';
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
        }
    }
});
</script>
@endsection