<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <title><?= isset($title) ? htmlspecialchars($title) . ' — ' : '' ?>Webimvar SaaS Panel</title>
  <style>
    .feature-check { color: #10b981; }
    .feature-x { color: #ef4444; }
  </style>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
  <!-- Header -->
  <header class="bg-white shadow-sm border-b">
    <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
      <div class="flex items-center space-x-4">
        <h1 class="text-xl font-bold text-blue-600">Webimvar SaaS</h1>
        <nav class="hidden md:flex space-x-4">
          <a href="/ops/dashboard.php" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded">Dashboard</a>
          <a href="/ops/packages.php" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded bg-blue-50 text-blue-700">Paketler</a>
          <a href="#" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded">Müşteriler</a>
          <a href="#" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded">Ödemeler</a>
        </nav>
      </div>
      <div class="flex items-center space-x-4">
        <span class="text-sm text-gray-600">
          <?= htmlspecialchars(Session::get('user_name', 'Admin')) ?>
        </span>
        <a href="/ops/logout.php" class="bg-gray-900 text-white px-3 py-2 rounded text-sm hover:bg-gray-800">
          Çıkış
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="max-w-7xl mx-auto px-4 py-6">
    <?= $content ?? '' ?>
  </main>

  <!-- Footer -->
  <footer class="bg-white border-t mt-12">
    <div class="max-w-7xl mx-auto px-4 py-4">
      <p class="text-center text-sm text-gray-500">
        © 2024 Webimvar SaaS Platform - Enterprise Panel v1.0
      </p>
    </div>
  </footer>
</body>
</html>