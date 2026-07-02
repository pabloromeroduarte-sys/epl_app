#!/bin/bash
# Script de auto-instalación para el servidor de DigitalOcean

set -e

echo "=== Iniciando configuración del servidor ==="

# 1. Actualizar e instalar dependencias
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y nginx mariadb-server git unzip curl wget software-properties-common

# Instalar PHP y modulos
apt-get install -y php-fpm php-mysql php-mbstring php-xml php-curl php-zip

# 2. Configurar la base de datos
echo "=== Configurando MariaDB ==="
mysql -e "CREATE DATABASE IF NOT EXISTS epldb DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'root'@'localhost' IDENTIFIED BY '';"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;"
mysql -e "FLUSH PRIVILEGES;"

# 3. Preparar directorio web
echo "=== Descargando código y respaldos ==="
rm -rf /var/www/html
git clone https://github.com/pabloromeroduarte-sys/epl_app.git /var/www/html
cd /var/www/html

# Descargar respaldo
curl -k -o vultr_backup.zip "https://padel.207.246.68.77.nip.io/epl_backup.php?token=epl_backup_2026_secreto"
unzip -o vultr_backup.zip

# 4. Importar base de datos
echo "=== Restaurando base de datos ==="
if [ -f "backup_db_temp.sql" ]; then
    mysql epldb < backup_db_temp.sql
    rm backup_db_temp.sql
fi

# 5. Ajustar permisos
echo "=== Ajustando permisos ==="
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html/uploads
chmod -R 755 /var/www/html

# 6. Configurar Nginx
echo "=== Configurando Nginx ==="
cat > /etc/nginx/sites-available/default << 'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    root /var/www/html;
    index index.php index.html index.htm;

    server_name _;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

# Detect php-fpm socket version
PHP_SOCK=$(ls /var/run/php/php*-fpm.sock | head -n 1)
sed -i "s|fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;|fastcgi_pass unix:$PHP_SOCK;|g" /etc/nginx/sites-available/default

systemctl restart nginx
systemctl enable nginx
systemctl enable mariadb

# Restart detected PHP-FPM
PHP_SERVICE=$(basename $PHP_SOCK .sock)
systemctl restart $PHP_SERVICE
systemctl enable $PHP_SERVICE

echo "=== CONFIGURACION FINALIZADA EXITOSAMENTE ==="
echo "Puedes probar entrando a la IP del servidor en tu navegador."
