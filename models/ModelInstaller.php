<?php
require_once __DIR__ . '/AccessControl.php';
require_once __DIR__ . '/Executor.php';
require_once __DIR__ . '/Job.php';
require_once __DIR__ . '/Message.php';
require_once __DIR__ . '/Newsletter.php';
require_once __DIR__ . '/Page.php';
require_once __DIR__ . '/Rating.php';
require_once __DIR__ . '/SiteSettings.php';
require_once __DIR__ . '/User.php';

class ModelInstaller {
    public static function installOrUpdateAll($pdo = null) {
        $models = [
            new AccessControl(),
            new Executor(),
            new Job(),
            new Message($pdo),
            new Newsletter(),
            new Page(),
            new Rating(),
            new SiteSettings($pdo),
            new User(),
        ];

        foreach ($models as $model) {
            if (method_exists($model, 'installOrUpdateSchema')) {
                $model->installOrUpdateSchema();
            }
        }
    }
}
?>
