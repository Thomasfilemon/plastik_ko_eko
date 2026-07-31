// Purchase Transaction JavaScript - New Version with Inline Form
$(document).ready(function () {
    // Initialize variables
    let items = [];
    let grandTotal = 0;

    // Search suppliers
    $("#supplier").on("input", function () {
        console.log("Supplier input changed:", $(this).val());
        const keyword = $(this).val();
        if (keyword.length > 0) {
            console.log(
                "About to send AJAX request to:",
                window.supplierSearchUrl
            );
            $.ajax({
                url: window.supplierSearchUrl,
                method: "GET",
                data: { keyword },
                success: function (data) {
                    console.log("Supplier search results:", data);
                    let dropdown = "";
                    if (data.length > 0) {
                        data.forEach((supplier) => {
                            dropdown += `<a class="dropdown-item supplier-item" data-kode="${supplier.kode_supplier}" data-name="${supplier.nama}">${supplier.kode_supplier} - ${supplier.nama}</a>`;
                        });
                    } else {
                        dropdown =
                            '<a class="dropdown-item disabled">Tidak ada supplier ditemukan</a>';
                    }
                    $("#supplierDropdown").html(dropdown).show();
                },
                error: function (xhr, status, error) {
                    console.error(
                        "Error searching suppliers:",
                        xhr.responseText
                    );
                    console.error("Status:", status);
                    console.error("Error:", error);
                    alert("Terjadi kesalahan saat mencari supplier.");
                },
            });
        } else {
            $("#supplierDropdown").hide();
        }
    });

    // Select Supplier
    $(document).on("click", ".supplier-item", function () {
        const kodeSupplier = $(this).data("kode");
        const namaSupplier = $(this).data("name");
        $("#kode_supplier").val(kodeSupplier); // Isi input hidden dengan kode supplier
        $("#supplier").val(`${kodeSupplier} - ${namaSupplier}`); // Tampilkan kode dan nama supplier di input utama
        $("#supplierDropdown").hide();
    });

    // Hide dropdown when clicking outside
    $(document).click(function (e) {
        if (
            !$(e.target).closest(
                "#supplier, #supplierDropdown, #kode_barang, #kodeBarangDropdown"
            ).length
        ) {
            $("#supplierDropdown").hide();
            $("#kodeBarangDropdown").hide();
        }
    });

    // Toggle discount inputs
    $("#discount_checkbox").change(function () {
        $("#discount_percent").prop("disabled", !this.checked);
        calculateTotals();
    });

    $("#ppn_checkbox").change(function () {
        calculateTotals();
    });

    // Calculate input changes
    $("#discount_percent").on("input", function () {
        calculateTotals();
    });

    // Handle item-barang change (like sales order)
    $(document).on('change', '.item-barang', function() {
        const row = $(this).closest('.item-row');
        const selectedOption = $(this).find('option:selected');
        const kodeBarangId = $(this).val();
        
        if (kodeBarangId) {
            // Get data from selected option
            const harga = selectedOption.data('harga') || 0;
            const unitDasar = selectedOption.data('unit-dasar') || 'LBR';
            const kodeBarang = selectedOption.data('kode') || '';
            const namaBarang = selectedOption.data('nama') || '';
            
            // Set harga
            row.find('.item-harga').val(harga);
            
            // Set satuan kecil
            const satuanKecilSelect = row.find('.item-satuan-kecil');
            satuanKecilSelect.empty();
            satuanKecilSelect.append(`<option value="${unitDasar}">${unitDasar}</option>`);
            row.find('.item-satuan').val(unitDasar);
            
            // Get available units for satuan besar
            $.ajax({
                url: `${window.availableUnitsUrl}/${kodeBarangId}`,
                method: 'GET',
                success: function (units) {
                    const satuanBesarSelect = row.find('.item-satuan-besar');
                    satuanBesarSelect.empty();
                    satuanBesarSelect.append('<option value="">- (pakai satuan kecil)</option>');
                    const unitList = Array.isArray(units) ? units : (units.units || []);
                    const factors = Array.isArray(units) ? {} : (units.factors || {});
                    row.data('unit-factors', Object.assign({ [unitDasar]: 1 }, factors));

                    if (unitList.length > 0) {
                        unitList.forEach(unit => {
                            if (unit !== unitDasar) {
                                const factor = factors[unit] || 1;
                                satuanBesarSelect.append(`<option value="${unit}">${unit} (1 = ${factor} ${unitDasar})</option>`);
                            }
                        });
                    }
                },
                error: function () {
                    console.log('Error fetching available units');
                }
            });
            
            // Calculate total
            calculateItemTotal(row);
        }
    });

    // Handle qty, harga, satuan changes
    $(document).on('input', '.item-qty', function() {
        const row = $(this).closest('.item-row');
        calculateItemTotal(row);
    });

    $(document).on('input', '.item-harga', function() {
        const row = $(this).closest('.item-row');
        calculateItemTotal(row);
    });

    $(document).on('change', '.item-satuan-kecil', function() {
        const row = $(this).closest('.item-row');
        const unit = $(this).val();
        row.find('.item-satuan').val(unit);
        calculateItemTotal(row);
    });

    $(document).on('change', '.item-satuan-besar', function() {
        const row = $(this).closest('.item-row');
        // Qty boleh dalam satuan besar; harga tetap per satuan kecil
        calculateItemTotal(row);
    });

    // Calculate item total — qty satuan besar dikonversi ke kecil untuk total
    function calculateItemTotal(row) {
        const qty = parseFloat(row.find('.item-qty').val()) || 0;
        const harga = parseFloat(row.find('.item-harga').val()) || 0;
        const satuanBesar = row.find('.item-satuan-besar').val() || '';
        const factors = row.data('unit-factors') || {};
        const factor = satuanBesar ? (parseFloat(factors[satuanBesar]) || 1) : 1;
        const total = qty * factor * harga;
        row.find('.item-total').val(total);
    }

    // Add Item Button
    $('#addItemBtn').click(function() {
        const row = $(this).closest('.item-row');
        const kodeBarangSelect = row.find('.item-barang');
        const selectedOption = kodeBarangSelect.find('option:selected');
        
        const kodeBarang = selectedOption.data('kode');
        const namaBarang = selectedOption.data('nama');
        const kodeBarangId = kodeBarangSelect.val();
        const keterangan = row.find('#keterangan').val();
        const harga = parseFloat(row.find('.item-harga').val()) || 0; // per satuan kecil
        const qty = parseFloat(row.find('.item-qty').val()) || 0;
        const satuanKecil = row.find('.item-satuan-kecil').val();
        const satuanBesar = row.find('.item-satuan-besar').val();
        const satuan = satuanBesar ? satuanBesar : satuanKecil;
        const factors = row.data('unit-factors') || {};
        const factor = satuanBesar ? (parseFloat(factors[satuanBesar]) || 1) : 1;
        const diskon = parseFloat(row.find('#diskon').val()) || 0;
        const panjang = parseFloat(row.find('#panjang').val()) || 0;

        if (!kodeBarangId || !kodeBarang || !namaBarang || !harga || !qty) {
            alert('Mohon lengkapi data barang!');
            return;
        }

        // Total = qty (besar) × factor × harga kecil
        const qtyKecil = qty * factor;
        const subtotal = harga * qtyKecil;
        const diskonAmount = (subtotal * diskon) / 100;
        const total = subtotal - diskonAmount;

        const newItem = {
            kodeBarang,
            kodeBarangId,
            namaBarang,
            keterangan,
            harga: harga,
            qty,
            qtyKecil,
            unitFactor: factor,
            satuanKecil,
            satuan,
            satuanBesar,
            diskon,
            panjang,
            total
        };

        items.push(newItem);
        renderItems();
        calculateTotals();

        // Reset form
        row.find('select, input').val('');
        row.find('.item-satuan-kecil').html('<option value="LBR">LBR</option>');
        row.find('.item-satuan-besar').empty();
        row.find('.item-satuan').val('LBR');
    });

    // Function to render items table
    function renderItems() {
        const tbody = $('#itemsList');
        tbody.empty();

        items.forEach((item, index) => {
            tbody.append(`
                <tr>
                    <td>${item.kodeBarang}</td>
                    <td>${item.namaBarang}</td>
                    <td>${item.keterangan || '-'}</td>
                    <td class="text-right">${formatCurrency(item.harga)}</td>
                    <td>${item.qty} ${item.satuan || 'LBR'}</td>
                    <td>${item.satuanBesar || '-'}</td>
                    <td class="text-right">${formatCurrency(item.total)}</td>
                    <td class="text-center">${item.panjang || '-'}</td>
                    <td class="text-right">${item.diskon || 0}%</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary edit-item" data-index="${index}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger remove-item" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });

        // Remove item handling
        $(".remove-item").click(function () {
            const index = $(this).data("index");
            items.splice(index, 1);
            renderItems();
            calculateTotals();
        });

        // Edit item handling
        $(".edit-item").click(function () {
            openEditItemModal($(this).data("index"));
        });
    }

    // Populate & open the edit modal for a line item
    function openEditItemModal(index) {
        const item = items[index];
        if (!item) return;

        $('#edit_item_index').val(index);
        $('#edit_item_nama').val(item.namaBarang || '');
        $('#edit_item_qty').val(item.qty);
        $('#edit_item_harga').val(item.harga);
        $('#edit_item_satuan_kecil').val(item.satuanKecil || item.satuan || 'LBR');
        $('#edit_item_diskon').val(item.diskon || 0);
        $('#edit_item_panjang').val(item.panjang || 0);
        $('#edit_item_keterangan').val(item.keterangan || '');

        // Build satuan besar options
        const besarSelect = $('#edit_item_satuan_besar');
        besarSelect.empty();
        besarSelect.append('<option value="">- (pakai satuan kecil)</option>');

        const unitDasar = item.satuanKecil || item.satuan || 'LBR';
        if (item.kodeBarangId && window.availableUnitsUrl) {
            $.ajax({
                url: `${window.availableUnitsUrl}/${item.kodeBarangId}`,
                method: 'GET',
                success: function (units) {
                    if (Array.isArray(units)) {
                        units.forEach(u => {
                            if (u !== unitDasar) {
                                besarSelect.append(`<option value="${u}">${u}</option>`);
                            }
                        });
                    }
                    besarSelect.val(item.satuanBesar || '');
                },
                error: function () {
                    if (item.satuanBesar) {
                        besarSelect.append(`<option value="${item.satuanBesar}">${item.satuanBesar}</option>`);
                    }
                    besarSelect.val(item.satuanBesar || '');
                }
            });
        } else if (item.satuanBesar) {
            besarSelect.append(`<option value="${item.satuanBesar}">${item.satuanBesar}</option>`);
            besarSelect.val(item.satuanBesar);
        }

        $('#editItemModal').modal('show');
    }

    // Save edited item
    $('#saveEditItemBtn').click(function () {
        const index = parseInt($('#edit_item_index').val(), 10);
        const item = items[index];
        if (!item) return;

        const qty = parseFloat($('#edit_item_qty').val()) || 0;
        const harga = parseFloat($('#edit_item_harga').val()) || 0;
        const diskon = parseFloat($('#edit_item_diskon').val()) || 0;
        const panjang = parseFloat($('#edit_item_panjang').val()) || 0;
        const keterangan = $('#edit_item_keterangan').val();
        const satuanKecil = item.satuanKecil || item.satuan || 'LBR';
        const satuanBesar = $('#edit_item_satuan_besar').val();

        if (!qty || harga === undefined) {
            alert('Mohon lengkapi qty dan harga!');
            return;
        }

        const subtotal = harga * qty;
        const total = subtotal - (subtotal * diskon) / 100;

        items[index] = Object.assign({}, item, {
            namaBarang: $('#edit_item_nama').val() || item.namaBarang,
            qty,
            harga,
            diskon,
            panjang,
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
        const subtotal = items.reduce((sum, item) => {
            const itemDiskon = (item.total * item.diskon) / 100;
            return sum + (item.total - itemDiskon);
        }, 0);

        $("#total").val(formatCurrency(subtotal));

        // Calculate discount
        let discountAmount = 0;
        if ($("#discount_checkbox").is(":checked")) {
            const discountPercent =
                parseFloat($("#discount_percent").val()) || 0;
            discountAmount = (subtotal * discountPercent) / 100;
        }
        $("#discount_amount").val(formatCurrency(discountAmount));

        // Calculate PPN
        let ppnAmount = 0;
        if ($("#ppn_checkbox").is(":checked")) {
            ppnAmount = ((subtotal - discountAmount) * 11) / 100; // Using 11% for PPN
        }
        $("#ppn_amount").val(formatCurrency(ppnAmount));

        // Calculate grand total
        grandTotal = subtotal - discountAmount + ppnAmount;
        $("#grand_total").val(formatCurrency(grandTotal));
    }

    // Format currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat("id-ID").format(amount);
    }

    // Save transaction
    $("#saveTransaction").click(function () {
    if (confirm("Apakah Anda yakin ingin menyimpan?")) {
        if (!$("#kode_supplier").val()) {
            alert("Pilih supplier dari daftar yang tersedia!");
            return;
        }

        if (items.length === 0) {
            alert("Tidak ada barang yang ditambahkan!");
            return;
        }

        // IMPORTANT: Changed 'supplier' to 'kode_supplier' to match the database column
        const transactionData = {
            nota: $("#no_nota").val(),
            no_surat_jalan: $("#no_surat_jalan").val(),
            tanggal: $("#tanggal").val(),
            kode_supplier: $("#kode_supplier").val(), // This field name must match your database column
            pembayaran: $("#pembayaran").val(),
            cara_bayar: $("#cara_bayar").val(),
            hari_tempo: parseInt($("#hari_tempo").val() || '0', 10),
            tanggal_jatuh_tempo: $("#tanggal_jatuh_tempo").val() || null,
            items: items,
            subtotal: parseFloat(
                $("#total").val().replace(/\./g, "").replace(/,/g, ".")
            ),
            diskon: parseFloat(
                $("#discount_amount")
                    .val()
                    .replace(/\./g, "")
                    .replace(/,/g, ".")
            ),
            ppn: parseFloat(
                $("#ppn_amount").val().replace(/\./g, "").replace(/,/g, ".")
            ),
            grand_total: grandTotal,
        };

        // Send data to backend
        $.ajax({
            url: window.storeTransactionUrl,
            method: "POST",
            data: transactionData,
            headers: {
                "X-CSRF-TOKEN": window.csrfToken,
            },
            success: function (response) {
                // Tampilkan modal invoice
                $("#invoiceNota").text(response.nota);
                $("#invoiceTanggal").text(response.tanggal);
                $("#invoiceSupplier").text(
                    response.supplier_name || response.kode_supplier
                );
                $("#invoiceGrandTotal").text(
                    "Rp " +
                        new Intl.NumberFormat("id-ID").format(
                            response.grand_total || 0
                        )
                );

                // Simpan ID transaksi untuk tombol Print
                const transactionId = response.id;

                // --- PERUBAHAN DI SINI ---
                // Tombol Print
                $("#printInvoiceBtn")
                    .off("click")
                    .on("click", function () {
                        // Buat URL dengan parameter auto_print=1 dan buka di tab baru
                        const printUrl = `${window.printInvoiceUrl}${transactionId}?auto_print=1`;
                        window.open(printUrl, '_blank');
                    });
                
                // Tombol Kembali
                $("#backToFormBtn")
                    .off("click")
                    .on("click", function () {
                        window.location.href = window.backToPembelian;
                    });

                $("#invoiceModal").modal("show");
            },
            error: function (xhr) {
                alert(
                    "Terjadi kesalahan: " +
                        (xhr.responseJSON
                            ? xhr.responseJSON.message
                            : xhr.statusText)
                );
            },
        });
    }
});

    // Cancel transaction
    $("#cancelTransaction").click(function () {
        if (confirm("Batalkan transaksi? Semua data akan hilang.")) {
            $("#transactionForm")[0].reset();
            items = [];
            renderItems();
            calculateTotals();
        }
    });

    // Enhanced Kode Barang search modal
    $("#searchKodeBarangBtn").click(function () {
        const keyword = $("#searchKodeBarangInput").val();
        if (keyword.length > 0) {
            $.ajax({
                url: window.kodeBarangSearchUrl,
                method: "GET",
                data: { keyword },
                success: function (data) {
                    let html = "";
                    if (data.length > 0) {
                        data.forEach((item) => {
                            html += `<tr>
                                <td>${item.kode_barang}</td>
                                <td>${item.name}</td>
                                <td>${item.length} m</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary select-kode-barang"
                                        data-kode="${item.kode_barang}" 
                                        data-name="${item.attribute}"
                                        data-length="${item.length}"
                                        data-cost="${item.cost}">
                                        <i class="fas fa-check"></i> Pilih
                                    </button>
                                </td>
                            </tr>`;
                        });
                    } else {
                        html =
                            '<tr><td colspan="4" class="text-center">Tidak ada data ditemukan</td></tr>';
                    }
                    $("#kodeBarangSearchResults").html(html);
                },
                error: function () {
                    alert("Terjadi kesalahan saat mencari kode barang.");
                },
            });
        } else {
            alert("Masukkan kata kunci pencarian!");
        }
    });

    // Select Kode Barang from search modal
    $(document).on("click", ".select-kode-barang", function () {
        const kodeBarang = $(this).data("kode");
        const namaBarang = $(this).data("name");
        const length = $(this).data("length");
        const cost = $(this).data("cost");

        // Find the option in the dropdown and select it
        const option = $(`.item-barang option[data-kode="${kodeBarang}"]`);
        if (option.length > 0) {
            option.prop('selected', true);
            $('.item-barang').trigger('change');
        }

        // Set other fields
        $('.item-harga').val(cost);
        $('#panjang').val(length);
        $('#keterangan').val(namaBarang);

        $("#kodeBarangSearchModal").modal("hide");
    });
});
