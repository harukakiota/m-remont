-- количество вставок = количеству таблиц (3)

INSERT INTO client (surname, name, father_name, phone, email) VALUES
    ('First', 'Client', 'Name', '78007777777', 'example10@example.com');

INSERT INTO user (surname, name, father_name, phone, email, password, type, active) VALUES
    ('SuperAdmin', NULL, NULL, '78008888888', 'example2@example.com','password',3, DEFAULT);