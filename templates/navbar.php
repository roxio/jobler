<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <div class="container">
    <a class="navbar-brand" href="/">Home</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <!-- Link do strony głównej -->
        <li class="nav-item">
          <a class="nav-link" href="/">Strona główna</a>
        </li>
        
        <!-- Link do logowania/rejestracji lub do wylogowania w zależności od sesji -->
        <?php if (isset($_SESSION['user_id'])): ?>
          <!-- Zalogowani użytkownicy -->
          <li class="nav-item">
            <a class="nav-link" href="views/user/dashboard.php">Dashboard</a>
          </li>
          
          <!-- Jeżeli użytkownik jest administratorem -->
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
              <a class="nav-link" href="views/admin/dashboard.php">Panel Administracyjny</a>
            </li>
          <?php endif; ?>
          
          <!-- Jeżeli użytkownik jest wykonawcą -->
          <?php if ($_SESSION['role'] === 'executor'): ?>
            <li class="nav-item">
              <a class="nav-link" href="views/executor/dashboard.php">Dashboard Wykonawcy</a>
            </li>
          <?php endif; ?>
          
          <!-- Wylogowanie -->
          <li class="nav-item">
            <a class="nav-link" href="/logout.php">Wyloguj</a>
          </li>
        <?php else: ?>
          <!-- Niezalogowani użytkownicy -->
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