# Guia de Deploy em Producao

Este guia descreve a preparacao de um servidor Linux para o projeto
`meu_horario` e o procedimento para publicar novas versoes a partir do branch
`main`.

## 1. Requisitos

- Ubuntu 22.04 ou 24.04
- PHP 8.2 ou superior
- MariaDB 10.11 ou MySQL compativel
- Nginx
- Composer
- Node.js e npm
- Supervisor
- Git
- Certificado HTTPS

O servidor deve ter pelo menos 2 GB de RAM, disco para a aplicacao, logs e
backups, e acesso SSH.

## 2. Pacotes do sistema

Exemplo para Ubuntu:

```bash
sudo apt update
sudo apt install -y nginx mariadb-server git unzip curl supervisor \
    php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml \
    php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl
```

Instalar Composer conforme a documentacao oficial e instalar Node.js LTS.

Confirmar versoes:

```bash
php -v
composer --version
node --version
npm --version
```

## 3. Utilizador da aplicacao

Nao executar a aplicacao como `root`.

```bash
sudo adduser deploy
sudo usermod -aG www-data deploy
sudo mkdir -p /var/www
sudo chown deploy:www-data /var/www
```

## 4. Acesso GitHub por SSH

Criar uma chave SSH no servidor como utilizador `deploy`:

```bash
sudo -iu deploy
ssh-keygen -t ed25519 -C "deploy@servidor"
cat ~/.ssh/id_ed25519.pub
```

Adicionar a chave publica no GitHub como Deploy Key do repositorio
`jmvgouveia/meu_horario`, preferencialmente sem permissao de escrita.

Testar:

```bash
ssh -T git@github.com
```

## 5. Clone inicial

```bash
sudo -iu deploy
cd /var/www
git clone git@github.com:jmvgouveia/meu_horario.git meu_horario
cd /var/www/meu_horario
```

## 6. Base de dados

Criar uma base e um utilizador dedicado:

```sql
CREATE DATABASE meu_horario CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'meu_horario'@'127.0.0.1' IDENTIFIED BY 'PASSWORD_FORTE_AQUI';
GRANT ALL PRIVILEGES ON meu_horario.* TO 'meu_horario'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Nao guardar a password no Git nem neste ficheiro.

## 7. Configuracao Laravel

Dentro do projeto:

```bash
cd /var/www/meu_horario
cp .env.example .env
php artisan key:generate
```

Editar `.env` diretamente no servidor:

```env
APP_NAME="Meu Horario"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://horarios.exemplo.pt

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=meu_horario
DB_USERNAME=meu_horario
DB_PASSWORD=PASSWORD_FORTE_AQUI

QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.exemplo.pt
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@exemplo.pt"
MAIL_FROM_NAME="Meu Horario"

# Apenas para uma base de dados nova. Nunca guardar este valor no Git.
INITIAL_ADMIN_PASSWORD="PASSWORD_INICIAL_FORTE"
```

Validar a configuracao antes de continuar:

```bash
php artisan config:clear
php artisan about
```

`INITIAL_ADMIN_PASSWORD` so e usado quando a migration inicial cria o primeiro
utilizador. Em bases de dados existentes, nao recria nem altera utilizadores.
Se o primeiro utilizador for criado numa instalação nova, o seeder atribui-lhe
o role `Super Admin`. Numa instalação existente, alterar a password do
administrador diretamente no sistema antes de abrir o ambiente ao público.
Nunca executar `migrate:fresh` em producao.

## 8. Dependencias e primeira preparacao

```bash
cd /var/www/meu_horario
composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan storage:link
php artisan optimize
```

O seeder cria as permissoes e roles necessarias, incluindo
`manage user activation`.

## 9. Permissoes

```bash
sudo chown -R deploy:www-data /var/www/meu_horario
sudo chmod -R ug+rwx /var/www/meu_horario/storage
sudo chmod -R ug+rwx /var/www/meu_horario/bootstrap/cache
```

O `.env` deve ser legivel pelo processo PHP, mas nao deve ser publico nem
estar dentro de `public/`.

## 10. Nginx

Criar `/etc/nginx/sites-available/meu_horario`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name horarios.exemplo.pt;

    root /var/www/meu_horario/public;
    index index.php;

    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header Referrer-Policy strict-origin-when-cross-origin;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Ativar e validar:

```bash
sudo ln -s /etc/nginx/sites-available/meu_horario /etc/nginx/sites-enabled/meu_horario
sudo nginx -t
sudo systemctl reload nginx
```

Configurar HTTPS com Certbot ou outro fornecedor antes de abrir o sistema aos
utilizadores.

## 11. Queue worker

Criar `/etc/supervisor/conf.d/meu_horario-worker.conf`:

```ini
[program:meu_horario-worker]
command=php /var/www/meu_horario/artisan queue:work database --sleep=3 --tries=3 --timeout=90
directory=/var/www/meu_horario
user=deploy
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/meu_horario/storage/logs/worker.log
stopwaitsecs=3600
```

Ativar:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start meu_horario-worker:*
sudo supervisorctl status
```

## 12. Scheduler

Adicionar ao crontab do utilizador `deploy`:

```bash
crontab -e
```

```cron
* * * * * cd /var/www/meu_horario && php artisan schedule:run >> /dev/null 2>&1
```

## 13. Deploy de atualizacoes

Executar como `deploy`:

```bash
cd /var/www/meu_horario
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan optimize
php artisan queue:restart
```

Confirmar depois do deploy:

```bash
git log -1 --oneline
php artisan about
sudo supervisorctl status
sudo systemctl is-active nginx
sudo systemctl is-active php8.2-fpm
```

Nunca usar `git reset --hard` ou apagar o `.env` durante um deploy.

## 14. Backups e rollback

Fazer backup da base de dados antes de cada migration:

```bash
mysqldump --single-transaction -u meu_horario -p meu_horario \
    | gzip > /var/backups/meu_horario-$(date +%F-%H%M).sql.gz
```

Guardar backups fora do servidor e testar regularmente a restauracao.

Se uma versao causar problemas:

```bash
git log --oneline -5
git checkout <commit-estavel>
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize
php artisan queue:restart
```

Nao fazer rollback de migrations automaticamente sem confirmar a estrategia de
dados e ter um backup valido.

## 15. Checklist final

- [ ] DNS aponta para o servidor.
- [ ] HTTPS ativo e a funcionar.
- [ ] `APP_DEBUG=false`.
- [ ] `.env` configurado e fora do Git.
- [ ] `INITIAL_ADMIN_PASSWORD` definido apenas numa instalação nova.
- [ ] Password do administrador inicial alterada/confirmada em instalações existentes.
- [ ] Base de dados criada e com backup.
- [ ] `php artisan migrate --force` executado.
- [ ] `RolesAndPermissionsSeeder` executado.
- [ ] Storage e permissoes validados.
- [ ] Nginx aponta para `public/`.
- [ ] PHP-FPM ativo.
- [ ] Queue worker ativo.
- [ ] Scheduler configurado.
- [ ] Email de ativacao e notificacoes testados.
- [ ] Login, MFA, horarios, inscricoes, exports e pedidos de troca testados.
- [ ] `php artisan test` passou antes do deploy.
