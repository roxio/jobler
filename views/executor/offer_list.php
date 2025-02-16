?php include '../partials/header.php'; ?>

<div class="container">
    <h1>Moje oferty</h1>

    <?php if (!empty($respondedOffers)) : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID oferty</th>
                    <th>Tytuł</th>
                    <th>Status</th>
                    <th>Data odpowiedzi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($respondedOffers as $offer) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($offer['id']); ?></td>
                        <td><?php echo htmlspecialchars($offer['title']); ?></td>
                        <td><?php echo htmlspecialchars($offer['status']); ?></td>
                        <td><?php echo htmlspecialchars($offer['response_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>Nie odpowiedziałeś jeszcze na żadną ofertę.</p>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>