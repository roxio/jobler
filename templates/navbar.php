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
        <!-- Dropdown menu "Usługi" -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="servicesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Usługi
          </a>
          <ul class="dropdown-menu" aria-labelledby="servicesDropdown">
            <li><a class="dropdown-item" href="/services/budowa-domu.php">Budowa domu</a></li>
            <li><a class="dropdown-item" href="/services/elektryk.php">Elektryk</a></li>
            <li><a class="dropdown-item" href="/services/hydraulik.php">Hydraulik</a></li>
            <li><a class="dropdown-item" href="/services/malarz.php">Malarz</a></li>
            <li><a class="dropdown-item" href="/services/meble-i-zabudowa.php">Meble i zabudowa</a></li>
            <li><a class="dropdown-item" href="/services/motoryzacja.php">Motoryzacja</a></li>
            <li><a class="dropdown-item" href="/services/ogrod.php">Ogród</a></li>
            <li><a class="dropdown-item" href="/services/organizacja-imprez.php">Organizacja imprez</a></li>
            <li><a class="dropdown-item" href="/services/projektowanie.php">Projektowanie</a></li>
            <li><a class="dropdown-item" href="/services/remont.php">Remont</a></li>
            <li><a class="dropdown-item" href="/services/sprzatanie.php">Sprzątanie</a></li>
            <li><a class="dropdown-item" href="/services/szkolenia-jezyki.php">Szkolenia i języki obce</a></li>
            <li><a class="dropdown-item" href="/services/transport.php">Transport</a></li>
            <li><a class="dropdown-item" href="/services/uslugi-dla-biznesu.php">Usługi dla biznesu</a></li>
            <li><a class="dropdown-item" href="/services/montaz-naprawa.php">Montaż i naprawa</a></li>
            <li><a class="dropdown-item" href="/services/uslugi-finansowe.php">Usługi finansowe</a></li>
            <li><a class="dropdown-item" href="/services/uslugi-prawne-administracyjne.php">Usługi prawne i administracyjne</a></li>
            <li><a class="dropdown-item" href="/services/uslugi-zdalne.php">Usługi zdalne</a></li>
            <li><a class="dropdown-item" href="/services/zdrowie-uroda.php">Zdrowie i uroda</a></li>
            <li><a class="dropdown-item" href="/services/zlota-raczka.php">Złota rączka</a></li>
          </ul>
        </li>

        <!-- Logowanie/rejestracja lub wylogowanie -->
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="views/user/dashboard.php">Dashboard</a>
          </li>

          <?php if (isset($_SESSION['ole']) && $_SESSION['role'] === 'admin'): ?>
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
      </ul>
    </div>
  </div>
</nav>

<!-- Bootstrap JS Bundle (dla obsługi dropdown) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
