<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <div class="container">
    <!-- Logo -->
    <a class="navbar-brand" href="/">
      <img src="/img/logo.png" alt="Logo" style="height: 40px;">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">

           <!-- Dynamiczne kategorie -->
 <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="categoryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Kategorie
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="categoryDropdown">
                        <li><a class="dropdown-item" href="index.php">Wszystkie</a></li>
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <a class="dropdown-item" href="index.php?category=<?= $category['id'] ?>">
                                    <?= htmlspecialchars($category['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

        <!-- Logowanie/rejestracja lub wylogowanie -->
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="views/user/dashboard.php">Panel użytkownika</a>
          </li>

          <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
              <a class="nav-link" href="views/admin/dashboard.php">Panel Administracyjny</a>
            </li>
          <?php endif; ?>

          <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'executor'): ?>
            <li class="nav-item">
              <a class="nav-link" href="views/executor/dashboard.php">Dashboard Wykonawcy</a>
            </li>
          <?php endif; ?>

          <li class="nav-item">
            <a class="nav-link" href="/logout.php">Wyloguj</a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="/login.php">Zaloguj się</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="/register.php">Zarejestruj się</a>
          </li>
        <?php endif; ?>
		<?php if (isset($_SESSION['user_account_balance'])): ?>
		<li class="nav-item"><a class="nav-link" href="../../views/executor/payment.php">Stan konta: <?= $_SESSION['user_account_balance'] ?> punktów</a></li>
<?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Bootstrap JS Bundle (dla obsługi dropdown) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
