// Funkcja do potwierdzenia przed usunięciem użytkownika lub ogłoszenia
function confirmDelete(message) {
    return confirm(message);
}

// Funkcja do otwierania menu na urządzeniach mobilnych
document.addEventListener('DOMContentLoaded', function () {
    const toggler = document.querySelector('.navbar-toggler');
    const navbar = document.querySelector('.navbar-collapse');
    
    toggler.addEventListener('click', function () {
        navbar.classList.toggle('show');
    });
});