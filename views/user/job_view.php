<?php

include_once('../../models/Job.php');
include_once('../../models/Language.php');


$jobModel = new Job();


if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}


$userId = $_SESSION['user_id'];
$jobs = $jobModel->getUserJobs($userId);
?>

<div class="job-list">
    <h2><?= htmlspecialchars(__t('user.legacy_jobs.title')) ?></h2>

    <?php if (!empty($jobs)) : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?= htmlspecialchars(__t('admin.common.title')) ?></th>
                    <th><?= htmlspecialchars(__t('job.description')) ?></th>
                    <th><?= htmlspecialchars(__t('job.status')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($job['id']); ?></td>
                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                        <td><?php echo htmlspecialchars($job['description']); ?></td>
                        <td><?php echo htmlspecialchars($job['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?= htmlspecialchars(__t('user.legacy_jobs.no_jobs')) ?> <a href="create_job.php"><?= htmlspecialchars(__t('user.add_new_job')) ?></a></p>
    <?php endif; ?>
</div>
