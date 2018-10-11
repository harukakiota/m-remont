SET NAMES 'utf8';

-- для каждого поля помнить про NOT NULL, UNIQUE и DEFAULT

-- CREATE DATABASE IF NOT EXISTS `remont_db` CHARACTER SET utf8 COLLATE utf8_general_ci;

-- USE `remont_db`;

CREATE TABLE client (
	client_id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(50) NOT NULL,
	surname VARCHAR(50) NOT NULL,
	father_name VARCHAR(50),
	phone VARCHAR(30),
	email VARCHAR(50) UNIQUE -- для возможности идентификации одних и тех же клиентов в базе
);

CREATE TABLE user (
	user_id INT AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(50) UNIQUE NOT NULL, -- фактически, это и есть первичный ключ, но целочисленные работают быстрее. И еще это login
	password VARCHAR(255) NOT NULL,
	name VARCHAR(50), -- может не иметь личной инфы
	surname VARCHAR(50),
	father_name VARCHAR(50),
	phone VARCHAR(30),
	type ENUM('user','admin','superadmin') NOT NULL DEFAULT 'user',
	active BOOLEAN NOT NULL DEFAULT FALSE,
	token VARCHAR(64) DEFAULT NULL
);

CREATE TABLE deal (
	deal_id INT AUTO_INCREMENT PRIMARY KEY,
	client_id INT NOT NULL,
	user_id INT NOT NULL,
	address VARCHAR(255) NOT NULL,
	square_m DECIMAL(5,2), -- площадь от -999,99 кв.м до 999,99 кв.м
	rooms_number TINYINT,
	commentary VARCHAR(255),
	files VARCHAR(255), -- просто директория на сервере?
	date DATE,
	commission DECIMAL(65,2), -- ну скажем они считают с копейками, всё равно по этому поиск не производится. Олсо чё там с валютой? Только рубли?
	status ENUM('new_deal','contact','design_agreement','contract_signing','design_rework','draft_work','finishing','signing_off','canceled') NOT NULL DEFAULT 'new_deal',
	FOREIGN KEY fk_client_id(client_id) REFERENCES client(client_id) ON UPDATE CASCADE ON DELETE CASCADE,
	FOREIGN KEY fk_user_id(user_id) REFERENCES user(user_id) ON UPDATE CASCADE ON DELETE CASCADE
);
