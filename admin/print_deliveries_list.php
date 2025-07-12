<?php
// print_deliveries_list.php
session_start();
require_once '../config/database.php';

// Keamanan: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "Akses ditolak. Silakan login sebagai admin.";
    exit;
}

// --- LOGIKA FILTER TANGGAL ---
$tanggal_filter = $_GET['tanggal'] ?? null;
$where_clause = '';
$params = [];
$types = '';
$title_date = 'Semua Periode';

if ($tanggal_filter && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $tanggal_filter)) {
    // Jika tanggal valid, buat klausa WHERE
    $where_clause = "WHERE DATE(p.tanggal_pesan) = ?";
    $params[] = $tanggal_filter;
    $types .= 's';
    
    // Format tanggal untuk judul
    $date_obj = date_create($tanggal_filter);
    $title_date = date_format($date_obj, 'd F Y');
}

// --- AKHIR LOGIKA FILTER ---

// Query dasar dengan LEFT JOIN untuk mencakup semua data
$sql = "
    SELECT
        p.id AS pesanan_id,
        p.tanggal_pesan,
        p.status_pesanan,
        u_pelanggan.nama_lengkap AS nama_pelanggan,
        u_pelanggan.alamat AS alamat_pelanggan,
        peng.id AS pengiriman_id,
        peng.status_pengiriman,
        peng.no_kendaraan,
        u_sopir.nama_lengkap AS nama_sopir
    FROM pesanan p
    JOIN users u_pelanggan ON p.id_pelanggan = u_pelanggan.id
    LEFT JOIN pengiriman peng ON p.id = peng.id_pesanan
    LEFT JOIN users u_sopir ON peng.id_sopir = u_sopir.id
    $where_clause
    ORDER BY p.tanggal_pesan ASC
";

$stmt = $conn->prepare($sql);

// Bind parameter jika ada filter tanggal
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$deliveries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
close_db_connection($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Pengiriman - <?php echo htmlspecialchars($title_date); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .container { width: 95%; margin: 0 auto; }
        h1, h2 { text-align: center; margin-bottom: 5px; }
        h1 { font-size: 18px; }
        h2 { font-size: 14px; font-weight: normal; margin-bottom: 20px;}
        table { width: 99%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        @media print {
            body { margin: 0; }
            .container { width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container">
        <h1>DAFTAR PENGIRIMAN</h1>
        <h2>Tanggal Pesanan: <?php echo htmlspecialchars($title_date); ?></h2>
        
        <table>
            <thead>
                <tr>
                    <th>ID Pesanan</th>
                    <th>Tgl Pesan</th>
                    <th>Pelanggan</th>
                    <th>Sopir Ditugaskan</th>
                    <th>No. Kendaraan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deliveries)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">Tidak ada data pengiriman untuk tanggal yang dipilih.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($deliveries as $delivery): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($delivery['pesanan_id']); ?></td>
                            <td><?php echo date('d-m-Y', strtotime($delivery['tanggal_pesan'])); ?></td>
                            <td><?php echo htmlspecialchars($delivery['nama_pelanggan']); ?></td>
                            <td><?php echo htmlspecialchars($delivery['nama_sopir'] ?? 'Belum Ditugaskan'); ?></td>
                            <td><?php echo htmlspecialchars($delivery['no_kendaraan'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($delivery['status_pengiriman'] ?? $delivery['status_pesanan']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: 40px; text-align: right;">
            <p>Dicetak pada: <?php echo date('d F Y, H:i'); ?></p>
        </div>
    </div>
</body>
</html>