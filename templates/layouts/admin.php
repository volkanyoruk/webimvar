<?php /* Basit Tailwind layout */ ?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= isset($title) ? htmlspecialchars($title) . ' — ' : '' ?>Webimvar Ops</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
  <header class="bg-white border-b">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
      <div class="font-semibold">Webimvar Ops</div>
      <nav class="flex gap-2">
        <?php if (\Session::has('user_id')): ?>
          <a class="text-sm px-2 py-1 rounded bg-slate-900 text-white" href="/ops/logout">Çıkış</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main class="max-w-6xl mx-auto p-4">
    <?= $content ?>
  </main>
</body>
</html>