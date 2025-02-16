<?php
?php include '../partials/header.php'; ?>

<div class="container">
    <h1>Witaj, <?php echo htmlspecialchars($executor['name']); ?>!</h1>
    <p>Twoje konto jest aktywne. Przeglądaj dostępne oferty i reaguj na nie.</p>

    <div class="row">
        <div class="col-md-6">
            <h3>Dostępne oferty</h3>
            <p>Masz <strong><?php echo $availableOffersCount; ?></strong> dostępnych ofert do rozważenia.</p>
            <a href="/executor/offer_list.php" class="btn btn-primary">Zobacz oferty</a>
        </div>
        <div class="col-md-6">
            <h3>Odpowiedz na oferty</h3>
            <p>Sprawdź oferty, na które jeszcze nie odpowiedziałeś.</p>
            <a href="/executor/respond_offer.php" class="btn btn-secondary">Odpowiedz na oferty</a>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>