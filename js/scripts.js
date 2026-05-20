function confirmDelete(message) {
    return confirm(message);
}

document.addEventListener('DOMContentLoaded', function () {
    const toggler = document.querySelector('.navbar-toggler');
    const navbar = document.querySelector('.navbar-collapse');

    toggler.addEventListener('click', function () {
        navbar.classList.toggle('show');
    });
});