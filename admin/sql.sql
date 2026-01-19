-- 1. Table de configuration des taux (Cameroun / OHADA)
CREATE TABLE IF NOT EXISTS `config_paie_cameroun` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `taux_cnps_salarie` decimal(5,2) DEFAULT 4.20,
  `plafond_cnps` decimal(12,2) DEFAULT 750000.00,
  `taux_cfc_salarie` decimal(5,2) DEFAULT 1.00,
  `taux_cac` decimal(5,2) DEFAULT 10.00, -- Centimes Additionnels Communaux
  `abattement_frais_pro` decimal(5,2) DEFAULT 30.00, -- 30% d'abattement sur le brut pour l'IRPP
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initialisation des taux standards Cameroun
INSERT INTO `config_paie_cameroun` (id, taux_cnps_salarie, plafond_cnps, taux_cfc_salarie, taux_cac, abattement_frais_pro) 
VALUES (1, 4.20, 750000.00, 1.00, 10.00, 30.00)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- 2. Vérification de la table entreprise_infos (Au cas où)
CREATE TABLE IF NOT EXISTS `entreprise_infos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `adresse` text,
  `ville` varchar(100),
  `telephone` varchar(50),
  `email` varchar(150),
  `numero_fiscal` varchar(100), -- NIU
  `numero_cnps` varchar(100),
  `registre_commerce` varchar(100), -- RCCM
  `logo` varchar(255),
  `signature_direction` varchar(255),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;