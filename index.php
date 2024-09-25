<?php

// Tingkatkan batas memori dan hapus batas waktu eksekusi script
ini_set('memory_limit', '128M');
set_time_limit(0); // Script bisa berjalan tanpa batas waktu

// Path ke file TXT yang menyimpan domain terblokir
$blocked_domains_file = __DIR__ . '/blokir.txt';

// Path ke file TXT yang menyimpan daftar error
$error_domains_file = __DIR__ . '/url/error.txt';

// Inisialisasi Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Key Redis untuk melacak jumlah submit dalam 1 menit
$submit_key = 'global_submit_count';

// Cek apakah key Redis sudah ada
if (!$redis->exists($submit_key)) {
    // Jika key tidak ada, set dengan nilai 1 dan TTL (Time to Live) selama 60 detik
    $redis->set($submit_key, 1, 60); // Expire dalam 60 detik
} else {
    // Jika key ada, ambil jumlah submit saat ini
    $current_count = $redis->get($submit_key);

    if ($current_count >= 100) { // Batas maksimal 100 submit per menit
        die('Terlalu banyak permintaan. Silakan coba lagi nanti.');
    } else {
        // Tambah jumlah submit
        $redis->incr($submit_key);
    }
}

// Baca file TXT jika ada, jika tidak ada, set daftar domain terblokir dan error menjadi kosong
$blocked_domains = file_exists($blocked_domains_file) ? file($blocked_domains_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$error_domains = file_exists($error_domains_file) ? file($error_domains_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// Fungsi untuk mengecek status domain menggunakan cURL
function checkDomainWithCurl($domain) {
    // Inisialisasi cURL
    $curl = curl_init();

    // URL API yang akan diakses
    $url = 'https://check.skiddle.id/?domain=' . urlencode($domain) . '&json=true';

    // Set opsi cURL
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Agar hasil cURL dikembalikan sebagai string
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Jangan verifikasi SSL untuk keperluan testing

    // Eksekusi cURL dan ambil responsenya
    $response = curl_exec($curl);

    // Jika terjadi error saat request
    if (curl_errno($curl)) {
        $error_msg = curl_error($curl);
        curl_close($curl);
        return json_encode(['status' => 'error', 'message' => 'cURL Error: ' . $error_msg]);
    }

    // Tutup cURL
    curl_close($curl);

    // Parsing response JSON
    $data = json_decode($response, true);

    // Jika hasil tidak dapat diparsing atau ada kesalahan format
    if (json_last_error() !== JSON_ERROR_NONE) {
        return json_encode(['status' => 'error', 'message' => 'Error parsing response from API.']);
    }

    // Mengecek apakah hasil berisi domain yang diperiksa dan properti 'blocked'
    if (isset($data[$domain]['blocked'])) {
        if ($data[$domain]['blocked'] === true) {
            // Jika blocked true, domain terkena nawala
            return json_encode(['status' => 'blocked', 'domain' => $domain, 'status_message' => 'Terkena Internet Positif!']);
        } else {
            // Jika blocked false, domain aman
            return json_encode(['status' => 'not_blocked', 'domain' => $domain, 'status_message' => 'Aman Dari Internet Positif!']);
        }
    }

    // Jika respons tidak valid atau domain tidak ada di respons
    return json_encode(['status' => 'error', 'message' => 'Invalid response from API.']);
}


// Jika permintaan AJAX (pengecekan domain)
if (isset($_POST['domain'])) {
    $domain = trim($_POST['domain']);
    $domain = strtolower($domain); // Konversi domain ke huruf kecil
    
    // Cek apakah hasil sudah ada di Redis (gunakan nama domain sebagai key)
    $cache_key = 'domain_check:' . $domain;
    $cached_result = $redis->get($cache_key);
    
    if ($cached_result) {
        // Jika hasil sudah ada di Redis, ambil dari cache
        echo $cached_result; // Menampilkan hasil yang di-cache
    } else {
        // Gunakan cURL untuk cek status domain dari API eksternal
        $result = checkDomainWithCurl($domain);
        
        // Simpan hasil di Redis tanpa TTL (lifetime permanen)
        $redis->set($cache_key, $result);
        
        // Tampilkan hasil pengecekan
        echo $result;
    }
    
    exit; // Hentikan eksekusi PHP di sini setelah hasil dikirim
}

// Fungsi untuk memilih warna acak dari daftar warna yang sudah ditentukan
function getRandomThemeColor() {
    // Daftar warna yang akan diacak (dalam format hex)
    $colors = [
        '#FF5733', // Merah Oranye
        '#33FF57', // Hijau Muda
        '#3357FF', // Biru
        '#FF33A1', // Pink
        '#8D33FF', // Ungu
        '#FF8D33', // Oranye
        '#FFD433', // Kuning
        '#33FF88', // Hijau Laut
        '#FF33D4'  // Pink Muda
    ];

    // Pilih warna secara acak
    return $colors[array_rand($colors)];
}

// Gunakan warna acak untuk theme-color
$themeColor = getRandomThemeColor();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="<?php echo $themeColor; ?>">
    <title>TUANKRAB: Alat Cek Internet Positif (100% Realtime)</title>
    <meta name="description" content="Kami menyediakan alat untuk pengecekan apakah domain Anda sudah masuk dalam daftar Internet Positif atau Nawala." />
    <meta property="og:type" content="website" />
    <meta property="twitter:type" content="website" />
    <meta property="og:url" content="https://bot.ini.guru/" />
    <meta property="twitter:url" content="https://bot.ini.guru/" />
    <meta property="og:title" content="TUANKRAB: Alat Cek Internet Positif (100% Realtime)" />
    <meta property="twitter:title" content="TUANKRAB: Alat Cek Internet Positif (100% Realtime)" />
    <meta property="og:description" content="Kami menyediakan alat untuk pengecekan apakah domain Anda sudah masuk dalam daftar Internet Positif atau Nawala." />
    <meta property="twitter:description" content="Kami menyediakan alat untuk pengecekan apakah domain Anda sudah masuk dalam daftar Internet Positif atau Nawala." />
    <meta property="og:image" content="https://cdn.ini.guru/img/setting-dns.webp" />
    <meta property="twitter:image" content="https://cdn.ini.guru/img/setting-dns.webp" />
    <link rel="preload" as="image" href="https://cdn.ini.guru/img/setting-dns.webp">
    <link rel="shortcut icon" type=image/x-icon href="https://toko.ini.guru/storage/icon.png">
    <style>
        /* Reset CSS untuk memastikan kompatibilitas */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            color: #333;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #fff;
            font-size: 2rem;
            text-align: center;
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
        	background-size: 200% 200%;
        	animation: gradient 10s ease infinite;
        	height: auto;
            padding-top: 20px;
            padding-bottom: 20px;
            border-radius: 30px 30px 0 0;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h2 {
            font-size: 1.6rem;
            text-align: center;
            color: #333;
            margin-bottom: 10px;
        }

        .title-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 10px;
        }

        .title-container h2 {
            flex: 1;
            text-align: center;
            font-size: 1.2rem;
            color: #333;
        }

        .icon-arrow {
            font-size: 1.5rem;
            cursor: pointer;
            color: <?php echo $themeColor; ?>;
            transition: color 0.3s ease;
        }

        .icon-arrow:hover {
            color: <?php echo $themeColor; ?>;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th, td {
            padding: 14px;
            text-align: center;
            color: #000;
            font-weight: 500;
        }

        th {
            border-radius: 30px 30px 0 0 ;
            color: #fff;
            border: 1px solid #fff;
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
        	background-size: 500% 500%;
        	animation: gradient 10s ease infinite;
        	height: auto;
        	text-align: center;
        }
        
        td {
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
        	background-size: 500% 500%;
        	animation: gradient 10s ease infinite;
        	color: #fff;
        	border: 1px solid #fff;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 0.9rem;
            color: #000;
        }

        .table-container {
            display: none;
        }

        .table-container.active {
            display: block;
        }

        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 1rem;
            color: #000;
        }
        
        form {
            display: flex;
            flex-direction: column
            justify-content: center;
            margin-bottom: 20px;
        }

        input[type="text"] {
            text-align: center;
            padding: 10px;
            font-size: 16px;
            width: 85%;
            color: #000;
            background-color: #f5f5f5;
            border-radius: 7px;
            margin-right: 10px;
            overflow: hidden; /* Ensures the content is not revealed until the animation */
            white-space: nowrap; /* Keeps the content on a single line */
            margin: 0 auto; /* Gives that scrolling effect as the typing happens */
            letter-spacing: .01em; /* Adjust as needed */
            animation: 
            typing 1.0s steps(90, end),
            blink-caret .1s step-end infinite;
        }

        button {
            padding: 10px 20px;
            font-size: 16px;
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
        	background-size: 200% 200%;
        	animation: gradient 15s ease infinite;
        	height: auto;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 20px;
        }

        button:hover {
            background-color: #0056b3;
        }

        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            text-align: left;
        }
        
        #result {
            text-align: center;
            font-weight: 600;
        }

        /* Warna untuk hasil */
        .blocked {
            color: red;
        }

        .not-blocked {
            color: green;
        }

        .domain {
            color: black; /* Tetap warna hitam untuk domain */
        }
        
        img {
            height: auto;
            width: 100%;
            max-width: 1200px;
            border-radius: 0 0 20px 20px;
            margin-top: 12px;
            margin: 0 auto;
        }

        @keyframes gradient {
        	0% {
        		background-position: 0% 50%;
        	}
        	50% {
        		background-position: 100% 50%;
        	}
        	100% {
        		background-position: 0% 50%;
        	}
        }
        
        /* The typing effect */
        @keyframes typing {
          from { width: 0 }
          to { width: 85% }
        }
        
        /* The typewriter cursor effect */
        @keyframes blink-caret {
          from, to { border-color: transparent }
          50% { border-color: transparent }
        }

        /* Responsif untuk mobile */
        @media (max-width: 768px) {
            body {
                background-color: #f4f4f4;
                color: #333;
                padding: 10px;
            }

            img {
                height: auto;
                width: 100%;
                border-radius: 0 0 20px 20px;
                margin-top: 0 auto;
            }

            h1 {
                font-size: 1.4rem;
            }

            h2 {
                font-size: 1.2rem;
                text-align: center;
            }

            table, th, td {
                font-size: 0.975rem;
            }

            form {
                flex-direction: column;
            }

            input[type="text"] {
                width: 100%;
                margin-bottom: 10px;
                color: #000;
            }
            
        }
    </style>
    <script>
        function showTable(tableId, title) {
            var tables = document.getElementsByClassName('table-container');
            for (var i = 0; i < tables.length; i++) {
                tables[i].classList.remove('active');
            }
            document.getElementById(tableId).classList.add('active');
            
            // Ganti judul sesuai dengan tabel yang ditampilkan
            document.getElementById('tableTitle').innerText = title;
        }

        document.addEventListener('DOMContentLoaded', function() {
            showTable('blockedTable', 'Domain Terblokir');
        });

        // Fungsi untuk mengirim form secara AJAX
        function checkDomain(event) {
            event.preventDefault(); // Cegah pengiriman form biasa

            // Ambil nilai input domain
            const domainInput = document.getElementById('domain').value;

            // Buat request AJAX ke server
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const result = JSON.parse(xhr.responseText);
                    const resultDiv = document.getElementById('result');
                    if (result.status === 'blocked') {
                        resultDiv.innerHTML = '<span class="domain">' + result.domain + '</span> - <span class="blocked">Terkena Internet Positif!</span>';
                    } else if (result.status === 'not_blocked') {
                        resultDiv.innerHTML = '<span class="domain">' + result.domain + '</span> - <span class="not-blocked">Aman Dari Internet Positif!</span>';
                    } else {
                        resultDiv.innerHTML = '<span class="error">' + result.message + '</span>';
                    }
                }
            };
            xhr.send('domain=' + encodeURIComponent(domainInput)); // Kirim domain sebagai POST data
        }

        // Tambahkan event listener ke form
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('checkDomainForm').addEventListener('submit', checkDomain);
        });
    </script>
</head>
<body>
    <h1>Layanan Pengecekan Nawala</h1>
    <center><img src="https://cdn.ini.guru/img/setting-dns.webp" alt="Seting DNS Anti Inpos" width="640" height="360" loading="eager"> </center>
    <div class="container">
        <h2>Cek Domain Status</h2>
        <!-- Mulai form pengecekan domain -->
        <form id="checkDomainForm" action="submit.php" method="POST">
            <input type="text" id="domain" name="domain" placeholder="Masukan Nama Domain" required>
            <button type="submit">Cek Sekarang!</button>
        </form>
        <div id="result"></div> <!-- Div untuk menampilkan hasil pengecekan -->
    </div>
    <div class="container">
        <div class="title-container">
            <span class="icon-arrow" onclick="showTable('blockedTable', 'Domain Terblokir')">&#8592;</span>
            <h2 id="tableTitle">Domain Terblokir</h2>
            <span class="icon-arrow" onclick="showTable('errorTable', 'Link Error')">&#8594;</span>
        </div>

        <!-- Tabel Domain Terblokir -->
        <div id="blockedTable" class="table-container active">
            <table>
                <thead>
					                    <tr>
                        <th>No.</th>
                        <th>Domain</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($blocked_domains) > 0): ?>
                        <?php foreach ($blocked_domains as $index => $domain): ?>
                            <tr>
                                <td><?php echo $index + 1; ?>.</td>
                                <td><?php echo htmlspecialchars($domain); ?></td>
                                <td>Terblokir</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3"><center>Tidak ada domain yang terkena nawala untuk saat ini.</center></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tabel Domain Error -->
        <div id="errorTable" class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Link</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($error_domains) > 0): ?>
                        <?php foreach ($error_domains as $index => $domain): ?>
                            <tr>
                                <td><?php echo $index + 1; ?>.</td>
                                <td><?php echo htmlspecialchars(getPathFromUrl($domain)); ?></td> <!-- Menampilkan hanya path -->
                                <td>Error</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3"><center>Tidak ada link error untuk saat ini.</center></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        &copy; 2024 Tools Checker Domain Tuankrab. Dibuat dengan 💖.
    </div>

</body>
</html>
