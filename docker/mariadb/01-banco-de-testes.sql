-- Banco separado para os testes automatizados (o de desenvolvimento é o `ranco`).
CREATE DATABASE IF NOT EXISTS ranco_testes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON ranco_testes.* TO 'ranco'@'%';
