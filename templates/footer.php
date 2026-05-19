<?php
// Pobieranie ustawień strony
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/SiteSettings.php';
require_once __DIR__ . '/../models/Page.php';
require_once __DIR__ . '/../models/Language.php';

$siteSettingsModel = new SiteSettings();
$siteSettings = $siteSettingsModel->getSettings();
$siteTitle = $siteSettings['title'] ?? 'Jobler';
$copyrightText = $siteSettingsModel->formatCopyrightText($siteSettings);
$footerPageModel = new Page();
$footerLocale = Language::current('frontend');
$footerPages = $footerPageModel->getVisibleInFooter($footerLocale);
?>

<footer class="footer mt-auto py-4" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);">
    <div class="container">
        <div class="row">
            <!-- Informacje o stronie -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="footer-brand d-flex align-items-center mb-3">
                    <?php if (!empty($siteSettings['logo']) && file_exists("../img/{$siteSettings['logo']}")): ?>
                        <img src="../img/<?= htmlspecialchars($siteSettings['logo']) ?>" alt="<?= htmlspecialchars($siteTitle) ?>" height="40" class="me-2">
                    <?php endif; ?>
                    <h5 class="text-white mb-0"><?= htmlspecialchars($siteTitle) ?></h5>
                </div>
                <p class="text-light opacity-75"><?= htmlspecialchars(__t('footer.description'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="social-icons mt-3">
                    <?php if (!empty($siteSettings['facebook_url'])): ?>
                    <a href="<?= htmlspecialchars($siteSettings['facebook_url']) ?>" class="text-light me-2" aria-label="Facebook" target="_blank">
                        <i class="bi bi-facebook fs-5"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($siteSettings['twitter_url'])): ?>
                    <a href="<?= htmlspecialchars($siteSettings['twitter_url']) ?>" class="text-light me-2" aria-label="Twitter" target="_blank">
                        <i class="bi bi-twitter fs-5"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($siteSettings['instagram_url'])): ?>
                    <a href="<?= htmlspecialchars($siteSettings['instagram_url']) ?>" class="text-light me-2" aria-label="Instagram" target="_blank">
                        <i class="bi bi-instagram fs-5"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($siteSettings['linkedin_url'])): ?>
                    <a href="<?= htmlspecialchars($siteSettings['linkedin_url']) ?>" class="text-light" aria-label="LinkedIn" target="_blank">
                        <i class="bi bi-linkedin fs-5"></i>
                    </a>
                    <?php endif; ?>
                </div>
				
            </div>

            <!-- Szybkie linki -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="text-white mb-3 fw-bold"><?= htmlspecialchars(__t('footer.quick_links'), ENT_QUOTES, 'UTF-8') ?></h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="/" class="text-light text-decoration-none opacity-75 hover-opacity-100">
                            <i class="bi bi-house-door me-2"></i><?= htmlspecialchars(__t('footer.home'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="/index.php#jobs-section" class="text-light text-decoration-none opacity-75 hover-opacity-100">
                            <i class="bi bi-list-ul me-2"></i><?= htmlspecialchars(__t('footer.jobs'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?= isset($_SESSION['user_id']) ? '/views/user/create_job.php' : '/login.php' ?>" class="text-light text-decoration-none opacity-75 hover-opacity-100">
                            <i class="bi bi-plus-circle me-2"></i><?= htmlspecialchars(__t('footer.add_job'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="/views/user/dashboard.php" class="text-light text-decoration-none opacity-75 hover-opacity-100">
                            <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars(__t('footer.user_panel'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Kategorie -->
            
<!-- Newsletter -->
<div class="col-lg-3 col-md-6 mb-4">
    <h6 class="text-white mb-3 fw-bold"><?= htmlspecialchars(__t('footer.newsletter'), ENT_QUOTES, 'UTF-8') ?></h6>
    <p class="text-light opacity-75 small"><?= htmlspecialchars(__t('footer.newsletter_text'), ENT_QUOTES, 'UTF-8') ?></p>
    <form id="newsletterForm">
        <div class="input-group">
            <input type="email" name="email" class="form-control form-control-sm" placeholder="<?= htmlspecialchars(__t('footer.email_placeholder'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Email" required>
            <button class="btn btn-light btn-sm" type="submit">
                <i class="bi bi-send"></i>
            </button>
        </div>
    </form>
    <div id="newsletterMessage" class="mt-2 small"></div>
</div>

            <!-- Kontakt -->
            <div class="col-lg-4 col-md-6 mb-4">
                <h6 class="text-white mb-3 fw-bold"><?= htmlspecialchars(__t('footer.contact'), ENT_QUOTES, 'UTF-8') ?></h6>
                <div class="contact-info">
                    <?php if (!empty($siteSettings['contact_email'])): ?>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-envelope text-light me-2"></i>
                        <span class="text-light opacity-75"><?= htmlspecialchars($siteSettings['contact_email']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($siteSettings['contact_phone'])): ?>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-telephone text-light me-2"></i>
                        <span class="text-light opacity-75"><?= htmlspecialchars($siteSettings['contact_phone']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($siteSettings['contact_address'])): ?>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-geo-alt text-light me-2"></i>
                        <span class="text-light opacity-75"><?= htmlspecialchars($siteSettings['contact_address']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($siteSettings['business_hours'])): ?>
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-clock text-light me-2"></i>
                        <span class="text-light opacity-75"><?= htmlspecialchars($siteSettings['business_hours']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Newsletter -->
               
            </div>
        </div>

        <hr class="my-4 bg-light opacity-25">

        <!-- Stopka dolna -->
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="text-light opacity-75 mb-0">
                    <?= htmlspecialchars($copyrightText, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="footer-links">
                    <?php foreach ($footerPages as $index => $footerPage): ?>
                        <a href="<?= htmlspecialchars($footerPageModel->publicUrl($footerPage, $footerLocale), ENT_QUOTES, 'UTF-8') ?>" class="text-light text-decoration-none opacity-75 hover-opacity-100 <?= $index < count($footerPages) - 1 ? 'me-3' : '' ?> small">
                            <?= htmlspecialchars($footerPage['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Wczytanie plików JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/scripts.js"></script>
<script>
$(document).ready(function() {
    $('#newsletterForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        var messageDiv = $('#newsletterMessage');
        
        messageDiv.removeClass('alert-danger alert-success').html('');
        
        $.ajax({
            url: '/subscribe-newsletter.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    messageDiv.addClass('alert alert-success').html(response.message);
                    $('#newsletterForm')[0].reset();
                } else {
                    messageDiv.addClass('alert alert-danger').html(response.message);
                }
            },
            error: function() {
                messageDiv.addClass('alert alert-danger').html('Wystąpił błąd podczas przetwarzania żądania.');
            }
        });
    });
});
</script>
</body>
</html>
