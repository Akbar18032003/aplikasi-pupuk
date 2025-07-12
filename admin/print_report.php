<?php
session_start();
require_once '../config/database.php';

// Keamanan: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "<h1>Akses Ditolak</h1><p>Anda harus login sebagai admin untuk mengakses halaman ini.</p>";
    exit;
}

// --- Logika Pengambilan Data (Sama seperti sebelumnya, sudah baik) ---
// 1. Total Pendapatan
$stmt_revenue = $conn->prepare("SELECT SUM(total_harga) AS total_revenue FROM pesanan WHERE status_pesanan = 'selesai'");
$stmt_revenue->execute();
$total_revenue = $stmt_revenue->get_result()->fetch_assoc()['total_revenue'] ?? 0;
$stmt_revenue->close();

// 2. Total Pesanan
$stmt_orders = $conn->prepare("SELECT COUNT(*) AS total_orders FROM pesanan");
$stmt_orders->execute();
$total_pesanan = $stmt_orders->get_result()->fetch_assoc()['total_orders'] ?? 0;
$stmt_orders->close();

// 3. Rincian Stok
$rincian_pupuk = $conn->query("SELECT nama_pupuk, jenis_pupuk, stok, harga_per_unit FROM pupuk ORDER BY nama_pupuk ASC")->fetch_all(MYSQLI_ASSOC);

$total_nilai_stok = 0;
foreach ($rincian_pupuk as $pupuk) {
    $total_nilai_stok += $pupuk['stok'] * $pupuk['harga_per_unit'];
}

close_db_connection($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan - <?php echo date('d-m-Y'); ?></title>
    <style>
        /* CSS Dioptimalkan untuk Cetak */
        @page {
            size: A4;
            margin: 25mm 20mm; /* Margin standar untuk dokumen formal */
        }

        body {
            font-family: 'Times New Roman', Times, serif; /* Font klasik untuk laporan */
            font-size: 11pt;
            color: #000;
            background: #fff; /* Pastikan latar belakang putih untuk cetak */
            line-height: 1.5;
        }

        .report-header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .report-header h1 {
            margin: 0;
            font-size: 18pt;
            text-transform: uppercase;
        }

        .report-header p {
            margin: 5px 0 0 0;
            font-size: 10pt;
            color: #333;
        }

        h2 {
            font-size: 14pt;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }

        .summary-table {
            width: 100%;
            margin-bottom: 30px;
            border: 1px solid #999;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 8px 12px;
            border: 1px solid #999;
        }

        .summary-table td:first-child {
            font-weight: bold;
            width: 30%;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }

        .detail-table th, .detail-table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }

        .detail-table thead th {
            background-color: #e0e0e0;
            font-weight: bold;
            -webkit-print-color-adjust: exact; /* Memastikan warna header tercetak */
            print-color-adjust: exact;
        }

        .detail-table tfoot th {
             background-color: #f0f0f0;
             -webkit-print-color-adjust: exact;
             print-color-adjust: exact;
        }

        .text-right { text-align: right !important; }
        .no-data { text-align: center; font-style: italic; padding: 20px; }

        .print-info { display: none; } /* Sembunyikan pesan ini saat dicetak */

        @media screen {
            /* Gaya untuk tampilan layar agar lebih ramah */
            body {
                background-color: #f0f2f5;
                padding: 20px;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .report-container {
                width: 210mm; /* Lebar A4 */
                min-height: 297mm; /* Tinggi A4 */
                padding: 25mm 20mm;
                background-color: #fff;
                box-shadow: 0 0 15px rgba(0,0,0,0.2);
            }
            .print-info {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                background-color: #3498db;
                color: white;
                text-align: center;
                padding: 10px;
                z-index: 100;
            }
        }

    </style>
</head>
<body>
    <div class="print-info">
        Halaman ini dioptimalkan untuk dicetak. Dialog cetak akan muncul otomatis.
    </div>

    <div class="report-container">
        <header class="report-header">
            <!-- Ganti dengan nama perusahaan/sistem Anda -->
            <h1>Laporan Sistem Pupuk</h1> 
            <h1>PT USAHA ENAM SAUDARA PALEMBANG</h1>
            <p>Laporan Keuangan dan Inventaris</p>
            <p>Dicetak pada: <?php echo date('d F Y, H:i:s'); ?></p>
        </header>

        <main>
            <h2>Ringkasan Data Utama</h2>
            <table class="summary-table">
                <tr>
                    <td>Total Pendapatan (Pesanan Selesai)</td>
                    <td class="text-right"><strong>Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></strong></td>
                </tr>
                 <tr>
                    <td>Total Transaksi Pesanan (Semua Status)</td>
                    <td class="text-right"><?php echo number_format($total_pesanan); ?></td>
                </tr>
                <tr>
                    <td>Total Nilai Inventaris Stok</td>
                    <td class="text-right">Rp <?php echo number_format($total_nilai_stok, 0, ',', '.'); ?></td>
                </tr>
            </table>

            <h2>Rincian Stok Inventaris</h2>
            <?php if (empty($rincian_pupuk)): ?>
                <p class="no-data">Tidak ada data stok pupuk yang tersedia.</p>
            <?php else: ?>
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th style="width:5%;">No</th>
                            <th style="width:40%;">Nama Pupuk</th>
                            <th style="width:20%;">Jenis</th>
                            <th class="text-right">Stok (Unit)</th>
                            <th class="text-right">Harga Satuan</th>
                            <th class="text-right">Nilai Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($rincian_pupuk as $pupuk): ?>
                            <tr>
                                <td><?php echo $i++; ?>.</td>
                                <td><?php echo htmlspecialchars($pupuk['nama_pupuk']); ?></td>
                                <td><?php echo htmlspecialchars($pupuk['jenis_pupuk']); ?></td>
                                <td class="text-right"><?php echo number_format($pupuk['stok']); ?></td>
                                <td class="text-right">Rp <?php echo number_format($pupuk['harga_per_unit'], 0, ',', '.'); ?></td>
                                <td class="text-right">Rp <?php echo number_format($pupuk['stok'] * $pupuk['harga_per_unit'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" class="text-right">Total Keseluruhan Nilai Stok</th>
                            <th class="text-right">Rp <?php echo number_format($total_nilai_stok, 0, ',', '.'); ?></th>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Trigger dialog cetak saat halaman selesai dimuat.
        window.addEventListener('load', function() {
            window.print();
        });
    </script>
</body>
</html>