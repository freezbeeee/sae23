-- Batiment table
CREATE TABLE Batiment (
    id_batiment INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL UNIQUE
);

-- Salle table
CREATE TABLE Salle (
    nom_salle VARCHAR(255) PRIMARY KEY,
    id_batiment INT,
    type VARCHAR(100),
    capacite INT,
    FOREIGN KEY (id_batiment) REFERENCES Batiment(id_batiment)
);

-- Capteur table
CREATE TABLE Capteur (
    nom_capteur VARCHAR(255) PRIMARY KEY,
    type VARCHAR(100),
    unite VARCHAR(50),
    nom_salle VARCHAR(255),
    FOREIGN KEY (nom_salle) REFERENCES Salle(nom_salle)
);

-- Mesure table
CREATE TABLE Mesure (
    id_mesure INT AUTO_INCREMENT PRIMARY KEY,
    date DATE,
    horaire TIME,
    valeur FLOAT,
    nom_capteur VARCHAR(255),
    FOREIGN KEY (nom_capteur) REFERENCES Capteur(nom_capteur)
);

-- Connexion table
CREATE TABLE Connexion (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    identifiant VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(100) NOT NULL,
    role ENUM('admin', 'gestionnaire') NOT NULL
);
