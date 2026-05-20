# Jobler

Jobler to aplikacja marketplace oparta o PHP i MySQL, sluzaca do publikowania zlecen, przegladania dostepnych ogloszen oraz laczenia klientow z wykonawcami.

Projekt zawiera publiczna strone z lista zlecen, panele uzytkownika i wykonawcy, panel administracyjny, prosty CMS dla statycznych podstron, obsluge newslettera, raporty, transakcje oraz system lokalizacji oparty o pliki jezykowe.

### Funkcje

- Publiczna strona glowna z wyszukiwarka, filtrami, kategoriami i wyborem jezyka
- Rejestracja, logowanie, edycja profilu i zarzadzanie zleceniami przez uzytkownikow
- Panel wykonawcy do przegladania ofert, odpowiadania na zlecenia i sprawdzania wyslanych odpowiedzi
- System wiadomosci i zarzadzania konwersacjami
- Panel administracyjny do obslugi uzytkownikow, ogloszen, wiadomosci, newslettera, raportow, transakcji, rol i ustawien
- CMS dla statycznych podstron z tlumaczeniami oraz kontrola widocznosci linkow w menu i stopce
- Lokalizacja oparta o slowniki PHP w katalogu `lang`
- Ustawienia strony dla brandingu, SEO, jezykow domyslnych, SMTP, limitow, oplat, kopii zapasowych i mapy strony
- Newsletter z zapisem, weryfikacja, wypisem, eksportem i wysylka zbiorcza
- Zarzadzanie rolami i uprawnieniami administracyjnymi
- Raportowanie aktywnosci, transakcji i zgloszen

### Wymagania

- Apache lub inny serwer zgodny z PHP
- PHP 7.4 lub nowszy
- MySQL albo MariaDB
- Wlaczone rozszerzenie PDO MySQL
- Opcjonalnie: rozszerzenie PHP Zip do tworzenia kopii systemu

### Instalacja

1. Sklonuj albo pobierz repozytorium.

```bash
git clone https://github.com/roxio/jobler.git
```

2. Skopiuj pliki projektu do katalogu publicznego serwera.

3. Utworz baze danych i zaimportuj przykladowy schemat oraz dane.

```sql
CREATE DATABASE jobler;
USE jobler;
SOURCE /sciezka/do/sample DB.sql;
```

4. Skonfiguruj polaczenie z baza danych w plikach:

- `config/config.php`
- `models/Database.php`

Domyslna konfiguracja lokalna:

```text
host: localhost
database: jobler
user: root
password: empty
```

5. Skonfiguruj `APP_URL`, SMTP i pozostale ustawienia w `config/config.php` oraz w panelu administracyjnym.

6. Otworz aplikacje w przegladarce.

```text
http://localhost/
```

### Lokalizacja

Tlumaczenia sa ladowane z plikow PHP znajdujacych sie w katalogu `lang`.

Domyslny polski slownik:

```text
lang/PL_pl.php
```

Kolejne jezyki mozna dodac przez utworzenie plikow, np.:

```text
lang/EN_gb.php
lang/DE_de.php
lang/RU_ru.php
```

System automatycznie wykrywa dostepne pliki jezykowe i pokazuje je w wyborze jezyka oraz w zakladkach tlumaczen CMS.

### Przykladowe dane logowania

#### Administrator

- Email: `admin@admin.admin`
- Haslo: `test`

#### Wykonawca

- Email: `executor@executor.executor`
- Haslo: `test`

#### Uzytkownik

- Email: `user@user.user`
- Haslo: `test`

### Licencja

Szczegoly znajduja sie w pliku `LICENSE`.

---

## English

Jobler is a PHP/MySQL marketplace application for posting jobs, browsing available assignments, and connecting clients with executors.

The project includes a public job board, user and executor dashboards, an administration panel, a simple CMS for static pages, newsletter tools, reports, transactions, and a file-based localization system.

### Features

- Public homepage with search, filters, categories, and language selection
- User registration, login, profile editing, and job management
- Executor dashboard for browsing offers, responding to jobs, and checking submitted responses
- Messaging and conversation management
- Admin panel for users, jobs, messages, newsletter, reports, transactions, roles, and settings
- Static page CMS with translations and menu/footer link visibility controls
- File-based localization using PHP dictionaries from the `lang` directory
- Site settings for branding, SEO, default languages, SMTP, limits, fees, backups, and sitemap generation
- Newsletter subscription, verification, unsubscribe, export, and bulk sending tools
- Administrative role and permission management
- Activity, transaction, and report tracking

### Requirements

- Apache or another PHP-compatible web server
- PHP 7.4 or newer
- MySQL or MariaDB
- PDO MySQL extension enabled
- Optional: PHP Zip extension for system backups

### Installation

1. Clone or download the repository.

```bash
git clone https://github.com/roxio/jobler.git
```

2. Copy the project files to your web server document root.

3. Create the database and import the sample schema/data.

```sql
CREATE DATABASE jobler;
USE jobler;
SOURCE /path/to/sample DB.sql;
```

4. Configure the database connection in:

- `config/config.php`
- `models/Database.php`

Default local configuration:

```text
host: localhost
database: jobler
user: root
password: empty
```

5. Configure `APP_URL`, SMTP, and other site settings in `config/config.php` and in the admin panel.

6. Open the application in your browser.

```text
http://localhost/
```

### Localization

Translations are loaded from PHP dictionary files in the `lang` directory.

The default Polish dictionary is:

```text
lang/PL_pl.php
```

Additional languages can be added by creating files such as:

```text
lang/EN_gb.php
lang/DE_de.php
lang/RU_ru.php
```

The system detects available language files automatically and displays them in the language selector and CMS translation tabs.

### Sample Login Data

#### Administrator

- Email: `admin@admin.admin`
- Password: `test`

#### Executor

- Email: `executor@executor.executor`
- Password: `test`

#### User

- Email: `user@user.user`
- Password: `test`

### License

See the `LICENSE` file for details.
