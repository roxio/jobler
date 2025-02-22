<?php
include_once('../../models/Message.php');

$messageModel = new Message();

// Pobranie listy unikalnych konwersacji
$conversations = $messageModel->getAllConversations();
?>

<?php include '../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
           <!-- Menu boczne -->
        <?php include 'sidebar.php'; ?>

        <!-- Główna zawartość -->
        <div class="col-md-10 col-lg-10 main-content">
            <h1>Zarządzanie konwersacjami</h1>

            <!-- Lista konwersacji -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <h3>Lista konwersacji</h3>
                    <table class="table table-bordered">
                        <tr>
                            <th>Job ID</th>
                            <th>Conversation ID</th>
                            <th>Podgląd</th>
                        </tr>
                        <?php foreach ($conversations as $conv) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($conv['job_id']); ?></td>
                            <td><?php echo htmlspecialchars($conv['conversation_id']); ?></td>
                            <td><a href="?conversation_id=<?php echo $conv['conversation_id']; ?>" class="btn btn-primary">Podgląd</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- Podgląd wiadomości -->
            <?php
            if (isset($_GET['conversation_id'])) {
                $conversation_id = $_GET['conversation_id'];
                $messages = $messageModel->getConversationById($conversation_id);
            ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <h3>Wiadomości w konwersacji #<?php echo htmlspecialchars($conversation_id); ?></h3>
                    <div class="card">
                        <div class="card-body">
                            <?php foreach ($messages as $msg) : ?>
                            <p><strong>Od: <?php echo $msg['sender_id']; ?> do <?php echo $msg['receiver_id']; ?>:</strong> 
                            <?php echo htmlspecialchars($msg['content']); ?> 
                            <em>(<?php echo $msg['created_at']; ?>)</em></p>
                            <hr>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>
