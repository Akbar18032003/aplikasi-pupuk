<?php
session_start();
require_once '../config/database.php';

// --- KEAMANAN: Periksa apakah pengguna sudah login dan memiliki peran 'admin' ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}
// --- AKHIR KEAMANAN ---

$pengiriman_id = $_GET['pengiriman_id'] ?? null;
$surat_jalan_data = null;
$pupuk_items_detail = [];

if (!$pengiriman_id || !is_numeric($pengiriman_id)) {
    // Bisa redirect atau tampilkan pesan error
    die("ID Pengiriman tidak valid.");
}

// --- Query untuk mengambil semua data yang dibutuhkan untuk Surat Jalan ---
$stmt = $conn->prepare("
    SELECT
        peng.id AS pengiriman_id_no,
        peng.no_kendaraan,
        peng.tanggal_kirim,
        peng.catatan_sopir,
        p.id AS pesanan_id,
        p.alamat_pengiriman,
        p.catatan AS catatan_pesanan,
        u_pelanggan.nama_lengkap AS nama_pelanggan,
        u_pelanggan.telepon AS telepon_pelanggan,
        u_sopir.nama_lengkap AS nama_sopir
    FROM pengiriman peng
    JOIN pesanan p ON peng.id_pesanan = p.id
    JOIN users u_pelanggan ON p.id_pelanggan = u_pelanggan.id
    LEFT JOIN users u_sopir ON peng.id_sopir = u_sopir.id
    WHERE peng.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $pengiriman_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $surat_jalan_data = $result->fetch_assoc();

    // Query untuk mengambil detail pupuk dalam pesanan ini
    $stmt_items = $conn->prepare("
        SELECT
            dp.jumlah,
            dp.harga_satuan,
            pu.nama_pupuk,
            pu.jenis_pupuk,
            pu.kemasan, -- Ambil kolom kemasan
            pu.deskripsi -- Untuk referensi jika perlu item_keterangan
        FROM detail_pesanan dp
        JOIN pupuk pu ON dp.id_pupuk = pu.id
        WHERE dp.id_pesanan = ?
    ");
    $stmt_items->bind_param("i", $surat_jalan_data['pesanan_id']);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while ($row_item = $result_items->fetch_assoc()) {
        $pupuk_items_detail[] = $row_item;
    }
    $stmt_items->close();

} else {
    die("Data pengiriman tidak ditemukan untuk Surat Jalan ini.");
}
$stmt->close();
close_db_connection($conn); // Tutup koneksi database setelah semua data diambil

// --- Informasi Statis Perusahaan (Ganti dengan data perusahaan Anda) ---
$company_name = "PT. Usaha Enam Saudara";
$company_address = "Jl. Cimanuk Blok D No. 11, Komplek Pusri, Sukamaju, Kenten, Palembang - kode pos 30164";
$company_phone = "Telp: 0711-XXXXXXX";
// --- AKHIR INFORMASI STATIS ---

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan - No. <?php echo htmlspecialchars($surat_jalan_data['pengiriman_id_no']); ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20mm; /* Margin cetak standar */
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
        }
        .container {
            width: 100%;
            max-width: 190mm; /* Lebar A4 potret */
            margin: 0 auto;
            border: 1px solid #000;
            padding: 10mm;
            box-sizing: border-box;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header .company-info {
            text-align: left;
            font-size: 10pt;
            line-height: 1.3;
        }
        .header .company-info h2 {
            margin: 0 0 5px 0;
            font-size: 14pt;
            text-transform: uppercase;
        }
        .header .recipient-info {
            text-align: right;
            font-size: 10pt;
        }
        .doc-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .doc-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .doc-meta .left-meta, .doc-meta .right-meta {
            font-size: 10pt;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .item-table th, .item-table td {
            border: 1px solid #000;
            padding: 8px 5px;
            text-align: center;
            font-size: 10pt;
        }
        .item-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .item-table .item-name {
            text-align: left;
        }
        .footer-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 50px;
        }
        .footer-section .sign-block {
            text-align: center;
            width: 30%;
            font-size: 10pt;
        }
        .footer-section .sign-line {
            margin-top: 60px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .note-section {
            margin-top: 20px;
            font-size: 10pt;
        }
        .vehicle-info {
            margin-top: 20px;
            font-size: 10pt;
        }

        /* Print specific styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                border: none; /* Hapus border kotak utama saat dicetak */
                padding: 0;
            }
            .header, .doc-title, .doc-meta, .item-table, .footer-section, .note-section, .vehicle-info {
                page-break-inside: avoid; /* Hindari pemotongan di tengah elemen */
            }
        }
    </style>
    <script>
        // Skrip untuk memicu dialog cetak secara otomatis
        window.onload = function() {
            window.print();
        };
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-info">
                <h2><?php echo htmlspecialchars($company_name); ?></h2>
                <p><?php echo htmlspecialchars($company_address); ?><br>
                   <?php echo htmlspecialchars($company_phone); ?></p>
            </div>
            <div class="recipient-info">
                <p>Kepada Yth.:</p>
                <p><strong><?php echo htmlspecialchars($surat_jalan_data['nama_pelanggan']); ?></strong></p>
                <p><?php echo nl2br(htmlspecialchars($surat_jalan_data['alamat_pengiriman'])); ?></p>
                <p>Telp: <?php echo htmlspecialchars($surat_jalan_data['telepon_pelanggan']); ?></p>
            </div>
        </div>

        <div class="doc-title">SURAT JALAN</div>

        <div class="doc-meta">
            <div class="left-meta">
                <p>No.: <strong><?php echo htmlspecialchars($surat_jalan_data['pengiriman_id_no']); ?></strong></p>
            </div>
            <div class="right-meta">
                <p>Palembang, <?php echo date('d-m-Y', strtotime($surat_jalan_data['tanggal_kirim'])); ?></p>
            </div>
        </div>

        <table class="item-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="item-name">Nama Barang</th>
                    <th>Kemasan</th>
                    <th>Banyaknya</th>
                    <th>Quantity</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $total_quantity = 0; // Misal total quantity dalam unit yang sama (karung)
                foreach ($pupuk_items_detail as $item):
                    // Asumsi Banyaknya = jumlah kemasan, Quantity = jumlah per kemasan * banyaknya
                    // Jika jumlah adalah total berat, maka Banyaknya mungkin 1 dan Quantity total berat
                    // Sesuaikan ini dengan bagaimana Anda mendefinisikan 'jumlah'
                    $quantity_per_unit = 50; // Contoh: jika 1 unit pupuk = 50kg
                    $display_quantity = $item['jumlah'] * $quantity_per_unit . ' kg'; // Contoh: 2 karung * 50kg = 100kg
                    // Atau jika jumlah adalah banyaknya kemasan, maka display quantity adalah item['jumlah']
                    // $display_quantity = $item['jumlah'] . ' ' . $item['kemasan'];

                    // Anda bisa menyesuaikan kolom "Quantity" dan "Banyaknya"
                    // Jika jumlah di detail_pesanan adalah "banyaknya" (misal: jumlah karung):
                    $banyaknya_display = $item['jumlah'];
                    $quantity_display = number_format($item['jumlah'] * $item['harga_satuan'], 0, ',', '.'); // Atau total berat jika pupuk memiliki berat per unit

                    // Jika "Quantity" adalah total berat, dan pupuk punya berat per unit
                    // Misalnya, pupuk.berat_per_satuan_jual
                    // $quantity_display = ($item['jumlah'] * $pupuk['berat_per_satuan_jual'] ?? 1) . ' Kg';
                    // Untuk saat ini, asumsikan Quantity adalah total harga sub-item, atau total kemasan.
                    // Akan menggunakan total kemasan karena "Banyaknya" sudah ada.
                    // Quantity: total harga per item, atau total unit (karung)
                    // Keterangan Item: bisa dari deskripsi pupuk atau catatan pesanan
                    $item_keterangan = htmlspecialchars($item['jenis_pupuk']); // Atau bisa dari pu.deskripsi
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td class="item-name"><?php echo htmlspecialchars($item['nama_pupuk']); ?></td>
                    <td><?php echo htmlspecialchars($item['kemasan']); ?></td>
                    <td><?php echo htmlspecialchars($item['jumlah']); ?></td>
                    <td>Rp <?php echo number_format(htmlspecialchars($item['jumlah'] * $item['harga_satuan']), 0, ',', '.'); ?></td> <td><?php echo htmlspecialchars($item_keterangan); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="vehicle-info">
            <p>No. Kendaraan: <strong><?php echo htmlspecialchars($surat_jalan_data['no_kendaraan'] ?? '-'); ?></strong></p>
        </div>

        <?php if (!empty($surat_jalan_data['catatan_pesanan']) || !empty($surat_jalan_data['catatan_sopir'])): ?>
        <div class="note-section">
            <p>Keterangan Umum:</p>
            <p><?php echo nl2br(htmlspecialchars($surat_jalan_data['catatan_pesanan'] ?? '')); ?></p>
            <?php if (!empty($surat_jalan_data['catatan_sopir'])): ?>
            <p>Catatan Sopir: <?php echo nl2br(htmlspecialchars($surat_jalan_data['catatan_sopir'])); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="footer-section">
            <div class="sign-block">
                <p>Tanda Terima,</p>
                <div class="sign-line"></div>
            </div>
            <div class="sign-block">
                <p>Sopir,</p>
                <div class="sign-line"><?php echo htmlspecialchars($surat_jalan_data['nama_sopir'] ?? '-'); ?></div>
            </div>
            <div class="sign-block">
                <p>Hormat Kami,</p>
                <div class="sign-line">PT. Usaha Enam Saudara</div>
            </div>
        </div>
    </div>
</body>
</html>