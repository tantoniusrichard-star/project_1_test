<?php
// login.php - Corporate Login Page with Quick Demo Switcher
require_once __DIR__ . '/config/auth.php';

startAppSession();

// If already logged in, redirect to dashboard
if (getCurrentUser()) {
    header("Location: dashboard.php");
    exit;
}

$error = null;
$msg = null;

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $msg = "Anda berhasil keluar dari sistem.";
}

// Handle Quick Demo Login via GET or POST
if (isset($_GET['demo_id'])) {
    $demoId = (int)$_GET['demo_id'];
    if (quickDemoLogin($demoId)) {
        header("Location: dashboard.php");
        exit;
    }
}

// Handle standard form login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = "Mohon isi Email/NIK dan Password Anda.";
    } else {
        if (loginUser($identifier, $password)) {
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Email/NIK atau password yang Anda masukkan salah.";
        }
    }
}

// Fetch pre-seeded demo accounts for quick switcher
$db = getDb();
$demoAccounts = $db->query("SELECT id, name, email, nik, role, job_title, division, leave_quota FROM users ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — SmartApprove AI Enterprise</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: {
                            800: '#1e293b',
                            900: '#0F172A',
                            950: '#020617',
                        },
                        brand: {
                            500: '#2563EB',
                            600: '#1D4ED8',
                            700: '#1E40AF',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0F172A;
            background-image: 
                radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(15, 23, 42, 0.8) 0px, transparent 50%);
        }
    </style>
</head>
<body class="min-h-screen text-slate-100 flex flex-col justify-between p-4 sm:p-6 lg:p-8">

    <!-- Top Bar Header -->
    <header class="max-w-6xl w-full mx-auto flex items-center justify-between py-2">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-blue-500/30">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-white flex items-center gap-2">
                    SmartApprove <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-400 border border-blue-500/30">AI Engine</span>
                </h1>
                <p class="text-xs text-slate-400">Mobile-First Operational Approvals & AI Intelligence</p>
            </div>
        </div>

        <div class="hidden sm:flex items-center gap-2 text-xs text-slate-400">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Sistem Operasional Aktif</span>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-6xl w-full mx-auto my-auto grid grid-cols-1 lg:grid-cols-12 gap-8 items-center py-6">

        <!-- Left Column: Context & Quick Demo Switcher -->
        <div class="lg:col-span-7 space-y-6">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300 text-xs font-medium">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Persetujuan Cepat Tanpa Ribet Buka Lampiran Manual
                </div>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white leading-tight">
                    Solusi Otomatisasi Persetujuan Kerja dengan <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-indigo-300 to-cyan-400">AI Assistant</span>
                </h2>
                <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                    Atasan sering di luar kantor? Karyawan butuh izin mendadak? SmartApprove AI secara otomatis memindai dokumen lampiran, validasi sisa cuti, cek potensi bentrok jadwal rekan kerja, dan berikan opsi <strong>1-Click ACC</strong> untuk mobile.
                </p>
            </div>

            <!-- Quick Demo Login Switcher Section -->
            <div class="bg-slate-800/60 backdrop-blur-md border border-slate-700/60 rounded-2xl p-5 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-700/50 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">⚡</span>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Quick Demo Login Switcher</h3>
                    </div>
                    <span class="text-xs text-blue-400 font-medium">Klik untuk Masuk Langsung</span>
                </div>

                <p class="text-xs text-slate-400">
                    Pilih salah satu peran di bawah ini untuk menguji alur sistem tanpa repot mengetik kredensial:
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($demoAccounts as $acc): 
                        $badgeColor = 'bg-slate-700 text-slate-300 border-slate-600';
                        $roleName = 'Karyawan';
                        if ($acc['role'] === 'atasan') {
                            $badgeColor = 'bg-amber-500/20 text-amber-300 border-amber-500/30';
                            $roleName = 'Manager / Atasan (1-Click ACC)';
                        } elseif ($acc['role'] === 'hrd') {
                            $badgeColor = 'bg-purple-500/20 text-purple-300 border-purple-500/30';
                            $roleName = 'HR / Admin';
                        }
                    ?>
                    <a href="login.php?demo_id=<?= $acc['id'] ?>" 
                       class="group relative flex items-center gap-3.5 p-3 rounded-xl bg-slate-900/60 hover:bg-slate-900 border border-slate-700/70 hover:border-blue-500 transition-all duration-200 hover:shadow-lg hover:shadow-blue-500/10">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold shrink-0 <?= $acc['role'] === 'atasan' ? 'bg-amber-600/30 text-amber-300 border border-amber-500/40' : ($acc['role'] === 'hrd' ? 'bg-purple-600/30 text-purple-300 border border-purple-500/40' : 'bg-blue-600/30 text-blue-300 border border-blue-500/40') ?>">
                            <?= strtoupper(substr($acc['name'], 0, 2)) ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-white truncate group-hover:text-blue-400 transition-colors">
                                    <?= htmlspecialchars($acc['name']) ?>
                                </h4>
                                <span class="text-[10px] px-1.5 py-0.5 rounded border <?= $badgeColor ?>">
                                    <?= ucfirst($acc['role']) ?>
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($acc['job_title']) ?> • <?= htmlspecialchars($acc['division']) ?></p>
                            <?php if ($acc['role'] === 'karyawan'): ?>
                                <p class="text-[10px] text-emerald-400 mt-0.5 font-medium">Sisa Cuti: <?= $acc['leave_quota'] ?> Hari</p>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Corporate Login Form -->
        <div class="lg:col-span-5">
            <div class="bg-slate-800/80 backdrop-blur-xl border border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="mb-6">
                    <h3 class="text-xl font-bold text-white">Masuk Akun Perusahaan</h3>
                    <p class="text-xs text-slate-400 mt-1">Masukkan Email/NIK dan Password Anda</p>
                </div>

                <?php if ($error): ?>
                    <div class="mb-4 p-3.5 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-xs flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($msg): ?>
                    <div class="mb-4 p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span><?= htmlspecialchars($msg) ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Email Kantor atau NIK</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </span>
                            <input type="text" name="identifier" required placeholder="Contoh: budi@perusahaan.id atau MGR-001" 
                                   class="w-full pl-10 pr-3.5 py-2.5 rounded-xl bg-slate-900/80 border border-slate-700 text-white placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Kata Sandi (Password)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </span>
                            <input type="password" name="password" required placeholder="Default demo: 123456" 
                                   class="w-full pl-10 pr-3.5 py-2.5 rounded-xl bg-slate-900/80 border border-slate-700 text-white placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-400 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" checked class="rounded border-slate-700 bg-slate-900 text-blue-600 focus:ring-0">
                            <span>Ingat sesi saya</span>
                        </label>
                        <span class="text-blue-400 font-medium">Password demo: <code>123456</code></span>
                    </div>

                    <button type="submit" 
                            class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold text-sm shadow-lg shadow-blue-600/30 transition-all duration-200 transform active:scale-[0.99] flex items-center justify-center gap-2">
                        <span>Masuk ke Dashboard</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </form>

                <div class="mt-5 pt-4 border-t border-slate-700/60 text-center">
                    <p class="text-[11px] text-slate-400">
                        Keamanan terlindungi oleh enkripsi standar perusahaan dan AI Audit Log.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="max-w-6xl w-full mx-auto text-center py-3 text-xs text-slate-500">
        &copy; <?= date('Y') ?> SmartApprove AI Enterprise Portal. Hak Cipta Dilindungi.
    </footer>

</body>
</html>
