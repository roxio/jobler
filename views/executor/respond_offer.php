<?php include '../partials/header.php'; ?>

<div class="container">
    <h1>Odpowiedz na ofertę: <?php echo htmlspecialchars($offer['title']); ?></h1>

    <form method="POST" action="/executor/respond_offer.php">
        <div class="form-group">
            <label for="response">Twoja odpowiedź:</label>
            <textarea class="form-control" id="response" name="response" required></textarea>
        </div>
        <button type="submit" class="btn btn-success">Złóż ofertę</button>
    </form>
</div>

<?php include '../partials/footer.php'; ?>