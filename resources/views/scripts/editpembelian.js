// Purchase Transaction JavaScript for Edit Form
$(document).ready(function () {
    // Initialize variables with existing items from the database
    let items = initialItems || [];
    let grandTotal = 0;
    
    // Render items immediately to display existing items
    renderItems();
    calculateTotals();

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
        $("#kode_supplier").val(kodeSupplier); // Fill hidden input with supplier code
        $("#supplier").val(`${kodeSupplier} - ${namaSupplier}`); // Display supplier code and name in main input
        $("#supplierDropdown").hide();
    });

    // Hide dropdowns when clicking outside
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

    // Search Kode Barang
    $("#kode_barang").on("input", function () {
        const keyword = $(this).val();
        if (keyword.length > 0) {
            $.ajax({
                url: window.kodeBarangSearchUrl,
                method: "GET",
                data: { keyword },
                success: function (data) {
                    let dropdown = "";
                    if (data.length > 0) {
                        data.forEach((item) => {
                            dropdown += `<a class="dropdown-item kode-barang-item" 
                                            data-id="${item.id}"
                                            data-kode="${item.kode_barang}" 
                                            data-name="${item.name || item.attribute}"
                                            data-unit="${item.unit_dasar || 'LBR'}"
                                            data-length="${item.length || 0}"
                                            data-cost="${item.cost || 0}">
                                        ${item.kode_barang} - ${item.name || item.attribute}
                                        </a>`;
                        });
                    } else {
                        dropdown =
                            '<a class="dropdown-item disabled">Kode barang tidak ditemukan! Tambahkan di Master Data.</a>';
                    }
                    $("#kodeBarangDropdown").html(dropdown).show();
                },
                error: function (xhr, status, error) {
                    console.error("Error searching kode barang:", error);
                    alert("Terjadi kesalahan saat mencari kode barang.");
                },
            });
        } else {
            $("#kodeBarangDropdown").hide();
        }
    });

    // Select Kode Barang
   $(document).on("click", ".kode-barang-item", function () {
        const kodeBarangId = $(this).data("id");
        const kodeBarang = $(this).data("kode");
        const namaBarang = $(this).data("name");
        const unitDasar = $(this).data("unit") || "LBR";
        const length = $(this).data("length");
        const cost = $(this).data("cost");

        $("#kode_barang").val(kodeBarang);
        $("#nama_barang").val(namaBarang);
        $("#panjang").val(length);
        $("#harga").val(cost);
        $("#kode_barang").data("kode-barang-id", kodeBarangId);
        $("#satuanKecil").html(`<option value="${unitDasar}">${unitDasar}</option>`).val(unitDasar);

        if (kodeBarangId && window.availableUnitsUrl) {
            $.ajax({
                url: `${window.availableUnitsUrl}/${kodeBarangId}`,
                method: "GET",
                success: function (units) {
                    const besar = $("#satuanBesar");
                    besar.empty().append('<option value="">- (pakai satuan kecil)</option>');
                    const unitList = Array.isArray(units) ? units : (units.units || []);
                    const factors = Array.isArray(units) ? {} : (units.factors || {});
                    $("#kode_barang").data("unit-factors", Object.assign({ [unitDasar]: 1 }, factors));
                    unitList.forEach((u) => {
                        if (u !== unitDasar) {
                            const factor = factors[u] || 1;
                            besar.append(`<option value="${u}">${u} (1 = ${factor} ${unitDasar})</option>`);
                        }
                    });
                }
            });
        }
        
        // Get panel name with AJAX request
        $.ajax({
            url: window.getPanelInfoUrl,
            method: "GET",
            data: { kode_barang: kodeBarang },
            success: function(response) {
                if (response.success && response.panel_name) {
                    // Set both Nama Barang and Keterangan to the same Panel name
                    $("#nama_barang").val(response.panel_name); 
                    $("#keterangan").val(response.panel_name);
                } else {
                    $("#keterangan").val(namaBarang);
                }
                updateItemPreview();
            },
            error: function() {
                $("#keterangan").val(namaBarang);
                updateItemPreview();
            }
        });
        
        $("#kodeBarangDropdown").hide();
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

    // Preview item in modal
    $("#harga, #quantity, #panjang, #diskon").on("input", function () {
        updateItemPreview();
    });
    $("#satuanKecil, #satuanBesar").on("change", function () {
        updateItemPreview();
    });

    function getEditPembelianFactor(satuanBesar) {
        if (!satuanBesar) return 1;
        const factors = $("#kode_barang").data("unit-factors") || {};
        const factor = parseFloat(factors[satuanBesar]);
        return factor > 0 ? factor : 1;
    }

    function updateItemPreview() {
        const kodeBarang = $("#kode_barang").val() || "-";
        const keterangan = $("#keterangan").val() || "-";
        const harga = parseFloat($("#harga").val()) || 0;
        const quantity = parseFloat($("#quantity").val()) || 0;
        const panjang = parseFloat($("#panjang").val()) || 0;
        const satuanKecil = $("#satuanKecil").val();
        const satuanBesar = $("#satuanBesar").val();
        const diskon = parseFloat($("#diskon").val()) || 0;
        const factor = getEditPembelianFactor(satuanBesar);
        const qtyKecil = quantity * factor;

        const total = harga * qtyKecil;
        const diskonAmount = (total * diskon) / 100;
        const subTotal = total - diskonAmount;

        const hint = $("#qtyConversionHint");
        if (satuanBesar && quantity > 0 && factor > 1) {
            hint.text(`${quantity} ${satuanBesar} = ${qtyKecil} ${satuanKecil || "satuan kecil"} (harga × ${satuanKecil || "kecil"})`).show();
        } else {
            hint.hide().text("");
        }

        const tbody = $("#itemPreview");
        tbody.empty();
        tbody.append(`
            <tr>
                <td>${kodeBarang}</td>
                <td>${keterangan}</td>
                <td class="text-right">${formatCurrency(harga)}</td>
                <td>${quantity}${satuanBesar ? ` ${satuanBesar} (=${qtyKecil} ${satuanKecil||""})` : ""}</td>
                <td>${panjang > 0 ? panjang + " m" : "-"}</td>
                <td class="text-right">${formatCurrency(total)}</td>
                <td>${satuanKecil || "-"}</td>
                <td>${satuanBesar || "-"}</td>
                <td>${diskon}%</td>
                <td class="text-right">${formatCurrency(subTotal)}</td>
            </tr>
        `);
    }

    // Add item to the table
    $("#saveItemBtn").click(function () {
        const kodeBarang = $("#kode_barang").val();
        const namaBarang = $("#nama_barang").val();
        const keterangan = $("#keterangan").val();
        const harga = parseFloat($("#harga").val()) || 0;
        const qty = parseFloat($("#quantity").val()) || 0;
        const satuanKecil = $("#satuanKecil").val();
        const satuanBesar = $("#satuanBesar").val();
        const panjang = parseFloat($("#panjang").val()) || 0;
        const diskon = parseFloat($("#diskon").val()) || 0;
        const factor = getEditPembelianFactor(satuanBesar);
        const qtyKecil = qty * factor;

        if (!kodeBarang || !namaBarang || !harga || !qty) {
            alert("Mohon lengkapi data barang!");
            return;
        }

        // Check if kode_barang is valid by searching for it
        $.ajax({
            url: window.kodeBarangSearchUrl,
            method: "GET",
            data: { keyword: kodeBarang },
            async: false,
            success: function (data) {
                if (
                    data.length === 0 ||
                    !data.some((item) => item.kode_barang === kodeBarang)
                ) {
                    alert(
                        "Kode barang tidak terdaftar! Silakan tambahkan di Master Data terlebih dahulu."
                    );
                    return false;
                }

                const subtotal = harga * qtyKecil;
                const total = subtotal - (subtotal * diskon) / 100;
                const satuan = satuanBesar ? satuanBesar : (satuanKecil || "LBR");

                const newItem = {
                    kodeBarang,
                    kodeBarangId: $("#kode_barang").data("kode-barang-id") || null,
                    namaBarang,
                    keterangan,
                    harga,
                    qty,
                    qtyKecil,
                    unitFactor: factor,
                    satuanKecil: satuanKecil || "LBR",
                    satuanBesar: satuanBesar || "",
                    satuan,
                    panjang,
                    diskon,
                    total,
                };
                items.push(newItem);
                renderItems();
                calculateTotals();

                $("#addItemForm")[0].reset();
                $("#kode_barang").removeData("kode-barang-id");
                $("#qtyConversionHint").hide().text("");
                $("#itemPreview").empty();
                $("#addItemModal").modal("hide");
            },
            error: function () {
                alert("Terjadi kesalahan saat memvalidasi kode barang.");
            },
        });
    });

    // Item search functionality
    $("#findItem").click(function () {
        $("#kodeBarangSearchModal").modal("show");
    });

    // Function to render items table
    function renderItems() {
        const tbody = $("#itemsList");
        tbody.empty();

        items.forEach((item, index) => {
            const satuanKecil = item.satuanKecil || item.satuan || "LBR";
            const satuanBesar = item.satuanBesar || "-";
            const qtyLabel = item.satuanBesar && item.qtyKecil
                ? `${item.qty} ${item.satuanBesar} <small class="text-muted">(= ${item.qtyKecil} ${satuanKecil})</small>`
                : `${item.qty}`;
            tbody.append(`
                <tr>
                    <td>${item.kodeBarang}</td>
                    <td>${item.namaBarang}</td>
                    <td>${item.keterangan || "-"}</td>
                    <td class="text-right">${formatCurrency(item.harga)}</td>
                    <td class="text-center">${qtyLabel}</td>
                    <td class="text-center">${satuanKecil}</td>
                    <td class="text-center">${satuanBesar}</td>
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

    function openEditItemModal(index) {
        const item = items[index];
        if (!item) return;

        $("#edit_item_index").val(index);
        $("#edit_item_nama").val(item.namaBarang || "");
        $("#edit_item_qty").val(item.qty);
        $("#edit_item_harga").val(item.harga);
        $("#edit_item_satuan_kecil").val(item.satuanKecil || item.satuan || "LBR");
        $("#edit_item_diskon").val(item.diskon || 0);
        $("#edit_item_panjang").val(item.panjang || 0);
        $("#edit_item_keterangan").val(item.keterangan || "");

        const besarSelect = $("#edit_item_satuan_besar");
        besarSelect.empty();
        besarSelect.append('<option value="">- (pakai satuan kecil)</option>');

        const unitDasar = item.satuanKecil || item.satuan || "LBR";
        const seedFactors = { [unitDasar]: 1 };
        if (item.satuanBesar && item.unitFactor) {
            seedFactors[item.satuanBesar] = item.unitFactor;
        }
        $("#editItemModal").data("unit-factors", seedFactors);

        if (item.kodeBarangId && window.availableUnitsUrl) {
            $.ajax({
                url: `${window.availableUnitsUrl}/${item.kodeBarangId}`,
                method: "GET",
                success: function (units) {
                    if (Array.isArray(units) || (units && units.units)) {
                        const unitList = Array.isArray(units) ? units : (units.units || []);
                        const factors = Array.isArray(units) ? {} : (units.factors || {});
                        $("#editItemModal").data("unit-factors", Object.assign({ [unitDasar]: 1 }, factors));
                        unitList.forEach((u) => {
                            if (u !== unitDasar) {
                                const factor = factors[u] || 1;
                                besarSelect.append(`<option value="${u}">${u} (1 = ${factor} ${unitDasar})</option>`);
                            }
                        });
                    }
                    besarSelect.val(item.satuanBesar || "");
                    updateEditItemQtyHint();
                },
                error: function () {
                    if (item.satuanBesar) {
                        besarSelect.append(
                            `<option value="${item.satuanBesar}">${item.satuanBesar}</option>`
                        );
                    }
                    besarSelect.val(item.satuanBesar || "");
                    updateEditItemQtyHint();
                },
            });
        } else if (item.satuanBesar) {
            besarSelect.append(
                `<option value="${item.satuanBesar}">${item.satuanBesar}</option>`
            );
            besarSelect.val(item.satuanBesar);
        }

        $("#editItemModal").modal("show");
        updateEditItemQtyHint();
    }

    $("#saveEditItemBtn").click(function () {
        const index = parseInt($("#edit_item_index").val(), 10);
        const item = items[index];
        if (!item) return;

        const qty = parseFloat($("#edit_item_qty").val()) || 0;
        const harga = parseFloat($("#edit_item_harga").val()) || 0;
        const diskon = parseFloat($("#edit_item_diskon").val()) || 0;
        const panjang = parseFloat($("#edit_item_panjang").val()) || 0;
        const keterangan = $("#edit_item_keterangan").val();
        const namaBarang = $("#edit_item_nama").val() || item.namaBarang;
        const satuanKecil = item.satuanKecil || item.satuan || "LBR";
        const satuanBesar = $("#edit_item_satuan_besar").val();
        const factors = $("#editItemModal").data("unit-factors") || {};
        const factor = satuanBesar ? (parseFloat(factors[satuanBesar]) || item.unitFactor || 1) : 1;
        const qtyKecil = qty * factor;

        if (!qty) {
            alert("Mohon lengkapi qty dan harga!");
            return;
        }

        const subtotal = harga * qtyKecil;
        const total = subtotal - (subtotal * diskon) / 100;

        items[index] = Object.assign({}, item, {
            namaBarang,
            qty,
            qtyKecil,
            unitFactor: factor,
            harga,
            diskon,
            panjang,
            keterangan,
            satuanKecil,
            satuanBesar,
            satuan: satuanBesar ? satuanBesar : satuanKecil,
            total,
        });

        $("#editItemModal").modal("hide");
        renderItems();
        calculateTotals();
    });

    function updateEditItemQtyHint() {
        const qty = parseFloat($("#edit_item_qty").val()) || 0;
        const satuanKecil = $("#edit_item_satuan_kecil").val() || "LBR";
        const satuanBesar = $("#edit_item_satuan_besar").val() || "";
        const factors = $("#editItemModal").data("unit-factors") || {};
        const factor = satuanBesar ? (parseFloat(factors[satuanBesar]) || 1) : 1;
        const hint = $("#edit_item_qty_hint");
        if (satuanBesar && qty > 0 && factor > 1) {
            hint.text(`${qty} ${satuanBesar} = ${qty * factor} ${satuanKecil}`).show();
        } else {
            hint.hide().text("");
        }
    }

    $(document).on("input change", "#edit_item_qty, #edit_item_satuan_besar", updateEditItemQtyHint);

    // Calculate all totals
    function calculateTotals() {
        // Calculate subtotal (item.total already net of line discount)
        const subtotal = items.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0);

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

    // Save transaction - FIXED from saveTransaction to updateTransaction
    $("#updateTransaction").click(function () {
        if (confirm("Apakah Anda yakin ingin menyimpan perubahan?")) {
            if (!$("#kode_supplier").val()) {
                alert("Pilih supplier dari daftar yang tersedia!");
                return;
            }

            if (items.length === 0) {
                alert("Tidak ada barang yang ditambahkan!");
                return;
            }

            const transactionData = {
                _token: window.csrfToken || $('meta[name="csrf-token"]').attr("content"),
                nota: $("#no_nota").val(),
                no_surat_jalan: $("#no_surat_jalan").val(),    
                tanggal: $("#tanggal").val(),
                kode_supplier: $("#kode_supplier").val(),
                pembayaran: $("#metode_pembayaran").val(), // Fixed: changed from pembayaran to metode_pembayaran
                cara_bayar: $("#cara_bayar_akhir").val(), // Fixed: use cara_bayar_akhir instead of cara_bayar
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
                edit_reason: $("#edit_reason").val()  // <-- Added this line
            };

            if (!transactionData.edit_reason || !String(transactionData.edit_reason).trim()) {
                alert("Alasan edit harus diisi!");
                return;
            }

            // Send data to backend - FIXED to use updateTransactionUrl
            $.ajax({
                url: window.updateTransactionUrl,
                method: "POST", // Note: Some Laravel apps might require PATCH or PUT for updates
                data: transactionData,
                headers: {
                    "X-CSRF-TOKEN": window.csrfToken || $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    alert("Transaksi berhasil diperbarui!");
                    // Redirect to purchase view page
                    window.location.href = window.notaShowUrl;
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

    // Kode Barang search modal
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
                                <td>${item.attribute}</td>
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
        }
    });

    // Select Kode Barang from search modal
    $(document).on("click", ".select-kode-barang", function () {
        const kodeBarang = $(this).data("kode");
        const namaBarang = $(this).data("name");
        const length = $(this).data("length");
        const cost = $(this).data("cost");

        $("#kode_barang").val(kodeBarang);
        $("#nama_barang").val(namaBarang);
        $("#panjang").val(length);
        $("#harga").val(cost);
        
        // Get panel name with AJAX request
        $.ajax({
            url: window.getPanelInfoUrl,
            method: "GET",
            data: { kode_barang: kodeBarang },
            success: function(response) {
                if (response.success && response.panel_name) {
                    // Set both Nama Barang and Keterangan to the same Panel name
                    $("#nama_barang").val(response.panel_name);
                    $("#keterangan").val(response.panel_name);
                } else {
                    $("#keterangan").val(namaBarang);
                }
                updateItemPreview();
            },
            error: function() {
                $("#keterangan").val(namaBarang);
                updateItemPreview();
            }
        });

        $("#kodeBarangSearchModal").modal("hide");
    });
});