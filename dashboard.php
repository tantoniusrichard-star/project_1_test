<?php
// dashboard.php - Unified Main Application Shell
define('APP_RUN', true);

require_once __DIR__ . '/config/auth.php';

$user = requireAuth();
$db = getDb();

// Fetch pre-seeded demo accounts for quick switcher in navbar
$demoAccounts = $db->query("SELECT id, name, role, job_title, division FROM users ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartApprove AI — <?= htmlspecialchars($user['name']) ?> (<?= ucfirst($user['role']) ?>)</title>
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
                radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(15, 23, 42, 0.8) 0px, transparent 50%);
        }
    </style>
</head>
<body class="min-h-screen text-slate-100 flex flex-col justify-between">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-40 bg-slate-900/85 backdrop-blur-md border-b border-slate-700/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            
            <!-- Logo & Brand -->
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center shadow-md shadow-blue-500/20">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-base font-bold text-white tracking-tight flex items-center gap-1.5">
                            SmartApprove <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-blue-500/20 text-blue-400 border border-blue-500/30">AI</span>
                        </span>
                        <span class="text-[10px] text-slate-400 block -mt-0.5 sm:inline">Approval Portal</span>
                    </div>
                </a>
            </div>

            <!-- Center/Right User Info & Demo Switcher -->
            <div class="flex items-center gap-2 sm:gap-3">
                
                <!-- Quick Demo Switcher Dropdown -->
                <div class="relative">
                    <button onclick="toggleDemoDropdown()" 
                            class="px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700/80 border border-slate-700 text-xs text-slate-300 hover:text-white flex items-center gap-1.5 transition-all">
                        <span class="text-amber-400">⚡</span>
                        <span class="hidden md:inline">Ganti Akun Demo:</span>
                        <strong class="text-white text-[11px] sm:text-xs"><?= htmlspecialchars($user['name']) ?></strong>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="demoDropdown" class="hidden absolute right-0 mt-2 w-72 rounded-2xl bg-slate-900 border border-slate-700 shadow-2xl p-2 z-50 space-y-1">
                        <div class="px-3 py-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                            Pilih Akun Pengujian:
                        </div>
                        <?php foreach ($demoAccounts as $acc): 
                            $isActive = ($acc['id'] == $user['id']);
                        ?>
                        <a href="login.php?demo_id=<?= $acc['id'] ?>" 
                           class="flex items-center justify-between p-2 rounded-xl text-xs <?= $isActive ? 'bg-blue-600/20 text-blue-300 font-bold border border-blue-500/30' : 'hover:bg-slate-800 text-slate-300' ?> transition-colors">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-slate-800 flex items-center justify-center text-[10px] font-bold text-white shrink-0">
                                    <?= strtoupper(substr($acc['name'], 0, 2)) ?>
                                </div>
                                <div class="truncate">
                                    <div class="text-xs truncate"><?= htmlspecialchars($acc['name']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-normal"><?= htmlspecialchars($acc['job_title']) ?></div>
                                </div>
                            </div>
                            <span class="text-[10px] px-1.5 py-0.5 rounded uppercase font-semibold <?= $acc['role'] === 'atasan' ? 'bg-amber-500/20 text-amber-300' : ($acc['role'] === 'hrd' ? 'bg-purple-500/20 text-purple-300' : 'bg-slate-800 text-slate-400') ?>">
                                <?= $acc['role'] ?>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Current Role Pill -->
                <?php 
                    $roleBadge = 'bg-blue-500/20 text-blue-300 border-blue-500/30';
                    $roleText = 'Karyawan';
                    if ($user['role'] === 'atasan') {
                        $roleBadge = 'bg-amber-500/20 text-amber-300 border-amber-500/30';
                        $roleText = 'Manager (1-Click ACC)';
                    } elseif ($user['role'] === 'hrd') {
                        $roleBadge = 'bg-purple-500/20 text-purple-300 border-purple-500/30';
                        $roleText = 'HR Admin';
                    }
                ?>
                <span class="hidden sm:inline-flex items-center text-xs px-2.5 py-1 rounded-full border <?= $roleBadge ?> font-semibold">
                    <?= $roleText ?>
                </span>

                <!-- Logout Button -->
                <a href="actions/logout.php" 
                   title="Keluar Akun"
                   class="p-2 rounded-xl bg-slate-800 hover:bg-red-500/20 text-slate-400 hover:text-red-300 border border-slate-700 hover:border-red-500/30 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </a>
            </div>

        </div>
    </header>

    <!-- Main Dynamic Role Body -->
    <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-1">
        <?php
        if ($user['role'] === 'karyawan') {
            require __DIR__ . '/views/karyawan.php';
        } elseif ($user['role'] === 'atasan') {
            require __DIR__ . '/views/supervisor.php';
        } elseif ($user['role'] === 'hrd') {
            require __DIR__ . '/views/admin.php';
        }
        ?>
    </main>

    <!-- Global App Footer -->
    <footer class="border-t border-slate-800 bg-slate-900/60 py-4 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>
                &copy; <?= date('Y') ?> <strong>SmartApprove AI Enterprise</strong> — Solusi Persetujuan Operasional Mobile & Cepat
            </div>
            <div class="flex items-center gap-3 text-slate-400">
                <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    Database SQLite & AI Engine Siap
                </span>
            </div>
        </div>
    </footer>

    <script>
    function toggleDemoDropdown() {
        const dd = document.getElementById('demoDropdown');
        dd.classList.toggle('hidden');
    }

    // Close dropdown when clicked outside
    window.addEventListener('click', function(e) {
        const dd = document.getElementById('demoDropdown');
        const btn = e.target.closest('button');
        if (!dd.contains(e.target) && (!btn || !btn.onclick || !btn.onclick.toString().includes('toggleDemoDropdown'))) {
            dd.classList.add('hidden');
        }
    });
    </script>
</body>
</html>
