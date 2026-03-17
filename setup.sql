-- ============================================
-- SETUP DATABASE PERPUSTAKAAN
-- Jalankan file ini di phpMyAdmin atau MySQL CLI
-- ============================================

CREATE DATABASE IF NOT EXISTS `perpustakaan`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

USE `perpustakaan`;

-- ----------------------------
-- Table: mahasiswa
-- ----------------------------
CREATE TABLE IF NOT EXISTS `mahasiswa` (
  `nim` varchar(15) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jurusan` varchar(100) DEFAULT NULL,
  `angkatan` year DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_telp` varchar(15) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`nim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: admin
-- ----------------------------
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_admin` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_admin` (`id_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: anggota
-- ----------------------------
CREATE TABLE IF NOT EXISTS `anggota` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nim` varchar(15) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `tanggal_daftar` date DEFAULT (curdate()),
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `masa_berlaku` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nim` (`nim`),
  CONSTRAINT `anggota_ibfk_1` FOREIGN KEY (`nim`) REFERENCES `mahasiswa` (`nim`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: buku
-- ----------------------------
CREATE TABLE IF NOT EXISTS `buku` (
  `id_buku` varchar(20) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `pengarang` varchar(100) DEFAULT NULL,
  `penerbit` varchar(100) DEFAULT NULL,
  `tahun_terbit` year DEFAULT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `stok` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_buku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: peminjaman
-- ----------------------------
CREATE TABLE IF NOT EXISTS `peminjaman` (
  `id_pinjam` int NOT NULL AUTO_INCREMENT,
  `id_anggota` int NOT NULL,
  `id_buku` varchar(20) NOT NULL,
  `tanggal_pinjam` date DEFAULT (curdate()),
  `tanggal_kembali` date DEFAULT NULL,
  `tanggal_dikembalikan` date DEFAULT NULL,
  `status` enum('dipinjam','dikembalikan','terlambat') DEFAULT 'dipinjam',
  `denda` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id_pinjam`),
  KEY `id_anggota` (`id_anggota`),
  KEY `id_buku` (`id_buku`),
  CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`id_anggota`) REFERENCES `anggota` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`id_buku`) REFERENCES `buku` (`id_buku`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Data: mahasiswa
-- ----------------------------
INSERT IGNORE INTO `mahasiswa` (`nim`, `nama`, `jurusan`, `angkatan`, `email`, `password`) VALUES
('24024059', 'Matthew Mokalu', 'Teknik Informatika', 2024, 'matthew@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('24024068', 'Stevanus Rumbajan', 'Teknik Informatika', 2024, 'stevanus@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('24024078', 'Christian Wowor', 'Teknik Informatika', 2024, 'christian.wowor051@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Password default semua mahasiswa: password

-- ----------------------------
-- Data: admin
-- ----------------------------
INSERT IGNORE INTO `admin` (`id_admin`, `nama`, `password`) VALUES
('admin001', 'Administrator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Password admin: password

-- ----------------------------
-- Data: anggota
-- ----------------------------
INSERT IGNORE INTO `anggota` (`id`, `nim`, `nama`, `tanggal_daftar`, `status`, `masa_berlaku`) VALUES
(1, '24024078', 'Christian Wowor', '2026-03-01', 'aktif', '2027-04-01'),
(2, '24024059', 'Matthew Mokalu', '2026-03-01', 'aktif', '2027-05-01'),
(3, '24024068', 'Stevanus Rumbajan', '2026-03-01', 'aktif', '2027-06-01');

-- ----------------------------
-- Data: buku
-- ----------------------------
INSERT IGNORE INTO `buku` VALUES
('BK-001','Pemrograman Web','Marike Kondoj','Andi Publisher',2020,'Teknologi',3,'2026-03-03 01:00:44'),
('BK-002','Basis Data 2','Franky Manopo','Graha Ilmu',2019,'Teknologi',2,'2026-03-03 01:00:44'),
('BK-003','Serat Optik','Billy Woworuntu','Informatika',2021,'Teknologi',5,'2026-03-03 01:00:44');

-- ----------------------------
-- Data: peminjaman
-- ----------------------------
INSERT IGNORE INTO `peminjaman` VALUES
(1,1,'BK-001','2026-02-24','2026-03-03',NULL,'dikembalikan',0.00),
(2,2,'BK-002','2026-03-03','2026-03-09',NULL,'dipinjam',0.00),
(3,3,'BK-003','2026-03-03','2026-03-09',NULL,'dipinjam',0.00);
