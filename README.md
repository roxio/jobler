# Jobler

Jobler to prosty skrypt do oferowania i zarządzania usługami.  
Pozwala na wystawienie zlecenia/oferty, wybór usługodawcy oraz przyjęcie zadania.

---

## Funkcje

- **Tworzenie zadań**: Użytkownicy mogą tworzyć nowe zadania, określając ich tytuł, opis i termin wykonania.  
- **Przypisywanie zadań**: Zadania mogą być przypisywane do konkretnych profili freelancerów.  
- **Śledzenie postępu**: Użytkownicy mogą aktualizować status zadań, co umożliwia monitorowanie postępu prac.  

---

## Wymagania systemowe

- **Serwer WWW**: Apache lub inny kompatybilny serwer  
- **PHP**: Wersja 7.0 lub nowsza  
- **Baza danych**: MySQL lub inna kompatybilna baza danych  

---

## Instalacja

1. **Pobierz repozytorium**
   ```bash
   git clone https://github.com/roxio/jobler.git
   ```

2. **Skopiuj pliki** na serwer i upewnij się, że mają odpowiednie uprawnienia.  

3. **Utwórz bazę danych** i zaimportuj przykładowe dane:
   ```sql
   CREATE DATABASE jobler_db;
   USE jobler_db;
   SOURCE /ścieżka/do/sample DB.sql;
   ```

4. **Skonfiguruj połączenie z bazą danych** w pliku `config.php`:
   ```php
   <?php
   $db_host = 'localhost';
   $db_name = 'jobler_db';
   $db_user = 'nazwa_użytkownika';
   $db_pass = 'hasło';
   ?>
   ```

5. **Uruchom aplikację** w przeglądarce:  
   ```
   http://twojadomena/jobler
   ```

---

## Przykładowe dane logowania

### Administrator
- E-mail: `admin@admin.admin`  
- Hasło: `test`  

### Wykonawca
- E-mail: `executor@executor.executor`  
- Hasło: `test`  

### Użytkownik
- E-mail: `user@user.user`  
- Hasło: `test`  



------


# Jobler

Jobler is a simple script for offering and managing services.  
It allows you to post a job/offer, choose a service provider, and accept an assignment.

---

## Features

- **Task creation**: Users can create new tasks by specifying a title, description, and deadline.  
- **Task assignment**: Tasks can be assigned to specific freelancer profiles.  
- **Progress tracking**: Users can update task statuses, enabling monitoring of work progress.  

---

## System Requirements

- **Web Server**: Apache or any compatible server  
- **PHP**: Version 7.0 or newer  
- **Database**: MySQL or any compatible database  

---

## Installation

1. **Download the repository**
   ```bash
   git clone https://github.com/roxio/jobler.git
   ```

2. **Copy the files** to your server and make sure they have the correct permissions.  

3. **Create a database** and import the sample data:
   ```sql
   CREATE DATABASE jobler_db;
   USE jobler_db;
   SOURCE /path/to/sample DB.sql;
   ```

4. **Configure database connection** in `config.php`:
   ```php
   <?php
   $db_host = 'localhost';
   $db_name = 'jobler_db';
   $db_user = 'username';
   $db_pass = 'password';
   ?>
   ```

5. **Run the application** in your browser:  
   ```
   http://yourdomain/jobler
   ```

---

## Sample Login Data

### Administrator
- Email: `admin@admin.admin`  
- Password: `test`  

### Executor
- Email: `executor@executor.executor`  
- Password: `test`  

### User
- Email: `user@user.user`  
- Password: `test`  
