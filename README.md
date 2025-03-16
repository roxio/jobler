Jobler to prosty skrypt do przypisywania zadań, który umożliwia zarządzanie zadaniami w zespole. Użytkownicy mogą tworzyć, przypisywać i śledzić zadania, co ułatwia współpracę i organizację pracy.
Funkcje

    Tworzenie zadań: Użytkownicy mogą tworzyć nowe zadania, określając ich tytuł, opis i termin wykonania.
    Przypisywanie zadań: Zadania mogą być przypisywane do konkretnych członków zespołu, co pozwala na jasne określenie odpowiedzialności.
    Śledzenie postępu: Użytkownicy mogą aktualizować status zadań, co umożliwia monitorowanie postępu prac.

Wymagania systemowe

    Serwer WWW: Apache lub inny kompatybilny serwer.
    PHP: Wersja 7.0 lub nowsza.
    Baza danych: MySQL lub inna kompatybilna baza danych.

Instalacja

    Pobierz repozytorium:

git clone https://github.com/roxio/jobler.git

Skopiuj pliki na serwer i upewnij się, że mają odpowiednie uprawnienia.

Utwórz bazę danych i zaimportuj plik sample DB.sql:

CREATE DATABASE jobler_db;
USE jobler_db;
SOURCE /ścieżka/do/sample DB.sql;

Skonfiguruj połączenie z bazą danych w pliku config.php:

    <?php
    $db_host = 'localhost';
    $db_name = 'jobler_db';
    $db_user = 'nazwa_użytkownika';
    $db_pass = 'hasło';
    ?>

    Uruchom aplikację w przeglądarce, przechodząc do http://twojadomena/jobler.

Przykładowe dane logowania

    Administrator:
        E-mail: admin@admin.admin
        Hasło: test

    Wykonawca:
        E-mail: executor@executor.executor
        Hasło: test

    Użytkownik:
        E-mail: user@user.user
        Hasło: test

Licencja

Ten projekt jest objęty licencją GPL-3.0. Szczegóły znajdują się w pliku LICENSE.


Jobler

Jobler is a simple task assignment script that enables team task management. Users can create, assign, and track tasks, facilitating collaboration and work organization.
Features

    Task Creation: Users can create new tasks by specifying a title, description, and due date.
    Task Assignment: Tasks can be assigned to specific team members, ensuring clear responsibility.
    Progress Tracking: Users can update task statuses to monitor workflow progress.

System Requirements

    Web Server: Apache or another compatible server.
    PHP: Version 7.0 or later.
    Database: MySQL or another compatible database.

Installation

    Clone the repository:

git clone https://github.com/roxio/jobler.git

Copy the files to your server and ensure they have the correct permissions.

Create a database and import the sample DB.sql file:

CREATE DATABASE jobler_db;
USE jobler_db;
SOURCE /path/to/sample DB.sql;

Configure database connection in config.php:

    <?php
    $db_host = 'localhost';
    $db_name = 'jobler_db';
    $db_user = 'your_username';
    $db_pass = 'your_password';
    ?>

    Run the application in a browser by navigating to http://yourdomain/jobler.

Example Login Credentials

    Administrator:
        Email: admin@admin.admin
        Password: test

    Executor:
        Email: executor@executor.executor
        Password: test

    User:
        Email: user@user.user
        Password: test

License

This project is licensed under the GPL-3.0 license. Details can be found in the LICENSE file.
