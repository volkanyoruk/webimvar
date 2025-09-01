<?php $title='Giriş'; ob_start(); ?>
<div class="mx-auto max-w-md bg-white p-6 rounded-xl shadow">
  <h1 class="text-xl font-semibold mb-4">Yönetici Girişi</h1>

  <?php if ($m = Session::getFlash('err')): ?>
    <div class="mb-3 rounded bg-red-50 text-red-700 px-3 py-2 text-sm">
      <?= htmlspecialchars($m) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/ops/login" class="grid gap-3">
    <input name="email" type="email" required placeholder="E-posta" class="border rounded px-3 py-2">
    <input name="password" type="password" required placeholder="Şifre" class="border rounded px-3 py-2">
    <button class="rounded bg-slate-900 text-white px-4 py-2">Giriş</button>
  </form>
</div>
<?php $content = ob_get_clean(); include __DIR__.'/../layouts/admin.php'; ?>