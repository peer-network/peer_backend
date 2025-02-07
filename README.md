# Peer Backend  
This is the repository of Peer Networks Backend Code.
Instructions for contributing to the Project can be found here:  
[![Contributing](https://img.shields.io/badge/Contributing-Guidelines-blue.svg)](https://github.com/peer-network/.github/blob/main/CONTRIBUTING.md)  

# Backend Setup Guide

## 🛠️ Local Quick Setup

Follow these steps to set up the backend on your local machine:

---

### **1. Install PHP 8.3 & Extensions**
```bash
sudo apt install php8.3-cli
sudo apt-get install php-pgsql php-bcmath
```

### **2. Install Composer**
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }"
php composer-setup.php
php -r "unlink('composer-setup.php');"

# Add to PATH (recommended)
sudo mv composer.phar /usr/local/bin/composer
```

### **3. Install & Configure PostgreSQL**
```bash
# Install PostgreSQL
apt install postgresql

# Start service
sudo systemctl enable postgresql
sudo service postgresql start
```

#### Configure Database
```sql
sudo -u postgres psql
-- In PostgreSQL terminal:
ALTER USER postgres WITH PASSWORD 'test';
CREATE DATABASE peer;
GRANT ALL PRIVILEGES ON DATABASE peer TO postgres;
\q
```

#### Edit Authentication Method
```bash
sudo nano /etc/postgresql/<version>/main/pg_hba.conf
```
```conf
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             postgres                                md5
```
```bash
sudo service postgresql restart
```

### **4. Initialize Database**
```bash
psql -U postgres -d peer -f sql/structure.psql
psql -U postgres -d peer -f sql/optional_data.psql

# Access database:
psql -U postgres -d peer
```

### **5. Configure Environment**

**Install Dependencies:**
In project root execute:
```bash
composer install
```

Create `.env` from `.env.schema`

Set these values:
```env
DB_HOST=localhost
DB_PORT=5432
DB_USER=postgres
DB_PASSWORD=test
DB_NAME=peer
```

### **6. Generate Security Keys**
```bash
# From keys directory:
openssl genpkey -algorithm RSA -out private.pem -pkeyopt rsa_keygen_bits:2048
openssl rsa -pubout -in private.pem -out public.pem
openssl genpkey -algorithm RSA -out refresh_private.pem -pkeyopt rsa_keygen_bits:2048
openssl rsa -pubout -in refresh_private.pem -out refresh_public.pem

# Rename files
mv private.pem private.key
mv public.pem public.key
mv refresh_private.pem refresh_private.key
mv refresh_public.pem refresh_public.key
```

### **7. Run Application**
```bash at project root
php -S localhost:8888 -t public/
```

---

## 🌐 Access the API

Endpoint:
```
http://localhost:8888/graphql
```

Recommended Testing Tool:
Postman API Platform  
(Use GraphQL format for requests)

Note: All credentials and security keys in this example are for development purposes only. Always use secure credentials in production environments.  