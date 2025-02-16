-- Tworzenie bazy danych
CREATE DATABASE jobler;

-- Używanie stworzonej bazy danych
USE jobler;

-- Tworzenie tabeli użytkowników
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'executor', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tworzenie tabeli ogłoszeń
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tworzenie tabeli odpowiedzi (od wykonawców na ogłoszenia)
CREATE TABLE responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    executor_id INT NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id),
    FOREIGN KEY (executor_id) REFERENCES users(id)
);

-- Tworzenie tabeli wiadomości
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);


-- Dodanie przykładowych użytkowników
INSERT INTO users (email, password, role) VALUES
('user1@example.com', 'hashed_password_1', 'user'),
('executor1@example.com', 'hashed_password_2', 'executor'),
('admin@example.com', 'hashed_password_3', 'admin');

-- Dodanie przykładowych ogłoszeń
INSERT INTO jobs (user_id, title, description, status) VALUES
(1, 'Potrzebuję pomocy przy remoncie mieszkania', 'Szukam wykonawcy do remontu w moim mieszkaniu.', 'open'),
(1, 'Szukam specjalisty od SEO', 'Chciałbym poprawić widoczność mojej strony w wyszukiwarkach.', 'open');

-- Dodanie odpowiedzi wykonawców na ogłoszenia
INSERT INTO responses (job_id, executor_id, message) VALUES
(1, 2, 'Jestem zainteresowany tym zleceniem. Proszę o kontakt.'),
(2, 2, 'Chętnie podejmę się SEO dla Twojej strony.');

-- Dodanie przykładowych wiadomości między użytkownikami
INSERT INTO messages (sender_id, receiver_id, message) VALUES
(1, 2, 'Witam, chciałbym umówić się na spotkanie w sprawie zlecenia.'),
(2, 1, 'Dziękuję za wiadomość. Proszę podać termin spotkania.');