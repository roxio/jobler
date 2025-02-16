<?php
session_start();

// Zniszczenie sesji i wylogowanie
session_unset();
session_destroy();

header('Location: /');
exit;
