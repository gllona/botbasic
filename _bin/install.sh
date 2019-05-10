#!/usr/bin/env bash
# run as root
# before set DNS A entry dev.bots.logicos.org --> this instance external, static IP address
# example call:
#   BB_HOST=alpha BB_SUBDOMAIN=bots BB_ENV=dev BB_REPO=gitlab BB_CODE_BRANCH=master ENABLE_PHPMYADMIN=0 BB_MYSQL_ROOT_PASSWORD=huanaco BB_MYSQL_BOTBASIC_PASSWORD=candela install.sh
#   (BB_REPO can be gitlab, github or gcloud)
# INTERNALS

set -ex
export PATH=$PATH:/snap/google-cloud-sdk/current/bin
crontab -l | crontab -

function assertVarSet() {
    _VARNAME=$1
    if [ "${!_VARNAME}" == "" ]; then
        echo "$SCRIPT: $_VARNAME must be set" >/dev/stderr
        exit 1
    fi
}

function assertFileExists() {
    _FILENAME=$1
    if [ ! -e "$_FILENAME" ]; then
        echo "$SCRIPT: $_FILENAME must exist" >/dev/stderr
        exit 1
    fi
}

# SETTINGS

ENABLE_PHPMYADMIN=0

# ENVIRONMENT

SCRIPT=$(basename $0)
assertVarSet BB_SUBDOMAIN
BB_FQD=$BB_SUBDOMAIN.logicos.org
assertVarSet BB_HOST
assertVarSet BB_ENV
BB_HOST_ENV=$BB_ENV.$BB_FQD
BB_HOST_PUBLIC=$BB_HOST.$BB_FQD
BB_HOST_MEDIA=media-$BB_ENV.$BB_FQD
BB_HOST_PARSER=parser-$BB_HOST.$BB_FQD
BB_HOST_PRIVATE=local.$BB_HOST.$BB_FQD
BB_HOME=/home/botbasic/httpdocs
BB_PHP_VERSION=7.2
assertVarSet BB_MYSQL_ROOT_PASSWORD
assertVarSet BB_REPO
assertVarSet BB_CODE_BRANCH

# PACKAGES

apt-get update
apt-get install -y openssh-server curl htop vim zip mplayer lame python lynx jq
apt-get install -y apache2 mysql-server
apt-get install -y php$BB_PHP_VERSION libapache2-mod-php$BB_PHP_VERSION php$BB_PHP_VERSION-mysql php$BB_PHP_VERSION-curl php$BB_PHP_VERSION-json mysql-client
apt-get install -y php-mbstring
if [ "$ENABLE_PHPMYADMIN" != "0" ]; then
    apt-get install -y phpmyadmin
fi

# RESET

userdel botbasic
rm -rf $(dirname $BB_HOME)
cp /etc/hosts.bb.bak /etc/hosts

# ACCOUNT

useradd -c botbasic -m -s /bin/bash -g 0 botbasic

# CLONE REPO

mkdir -p $BB_HOME
cd $BB_HOME/..
rmdir $(basename $BB_HOME)

if [ "$BB_REPO" == "gcloud" ]; then
    BB_CODE_REPO=botbasic-core
    gcloud source repos clone $BB_CODE_REPO --project=botbasic-enter
elif [ "$BB_REPO" == "gitlab" ]; then
    BB_CODE_REPO=botbasic-core
    git clone https://gitlab.com/botbasic/botbasic-core.git
elif [ "$BB_REPO" == "github" ]; then
    BB_CODE_REPO=botbasic
    git clone https://github.com/gllona/botbasic.git
fi

cd $BB_CODE_REPO
git pull origin $BB_CODE_BRANCH
git checkout $BB_CODE_BRANCH
cd ..
ln -s $BB_CODE_REPO $(basename $BB_HOME)

# FILES AND DIRECTORIES

cd $BB_HOME/..
mkdir -p backup/mysql
mkdir -p media/downloads
mkdir -p media/public
mkdir -p media/private
mkdir -p logs/bizmodel
mkdir -p logs/webstub
touch logs/runtime.log
chmod g+w logs logs/bizmodel logs/webstub logs/runtime.log media/downloads media/public media/private
chgrp www-data logs logs/bizmodel logs/webstub logs/runtime.log media/downloads media/public media/private

cp $BB_HOME/_bin/backup-databases $BB_HOME/../backup

#mkdir -p /home/gorka/telegram/panama_bot
#ln -s /home/botbasic/httpdocs /home/gorka/telegram/panama_bot/httpdocs

# CHECK FOR FILES

BB_SQL_SCHEMA=$BB_HOME/_bin/schema.sql
assertFileExists $BB_SQL_SCHEMA

# HOSTS

cp /etc/hosts /etc/hosts.bb.bak

cat >>/etc/hosts <<END

127.0.0.1   $BB_HOST_ENV
127.0.0.1   $BB_HOST_PUBLIC
127.0.0.1   $BB_HOST_MEDIA
127.0.0.1   $BB_HOST_PARSER
127.0.0.1   $BB_HOST_PRIVATE
END

# LETSENCRYPT

apt-get -y install certbot
apt-get -y install python-certbot-apache
certbot --apache --domains $BB_HOST_ENV -n -m gllona@gmail.com --agree-tos certonly

LINE="14 3 * * 1 certbot renew --pre-hook \"service apache2 stop\" --post-hook \"service apache2 start\" >/dev/null 2>/dev/null"
(crontab -l; echo "$LINE") | crontab -

# APACHE2

a2dissite 000-default.conf

cat >/etc/apache2/sites-available/$BB_HOST_PRIVATE.conf <<END
Include /etc/apache2/mods-available/php$BB_PHP_VERSION.conf
<VirtualHost *:8088>
    ServerName   $BB_HOST_PRIVATE
    DocumentRoot $BB_HOME
    <Directory   $BB_HOME>
#       Order allow,deny
#       Allow from all
        Require all granted
    </Directory>
    ErrorLog  $BB_HOME/../logs/error.log
    CustomLog $BB_HOME/../logs/access.log combined
</VirtualHost>
END

cat >/etc/apache2/sites-available/$BB_HOST_PARSER.conf <<END
Include /etc/apache2/mods-available/php$BB_PHP_VERSION.conf
<VirtualHost *:8080>
    ServerName   $BB_HOST_PARSER
    DocumentRoot $BB_HOME/scripts/parser
    <Directory   $BB_HOME/scripts/parser>
#       Order allow,deny
#       Allow from all
        Require all granted
    </Directory>
    ErrorLog  $BB_HOME/../logs/error.log
    CustomLog $BB_HOME/../logs/access.log combined
</VirtualHost>
END

cat >/etc/apache2/sites-available/$BB_HOST_MEDIA.conf <<END
Include /etc/apache2/mods-available/php$BB_PHP_VERSION.conf
<VirtualHost *:8080>
    ServerName   $BB_HOST_MEDIA
    DocumentRoot $BB_HOME/media/public
    <Directory   $BB_HOME/media/public>
#       Order allow,deny
#       Allow from all
        Require all granted
    </Directory>
    ErrorLog  $BB_HOME/../logs/error.log
    CustomLog $BB_HOME/../logs/access.log combined
</VirtualHost>
END

cat >/etc/apache2/sites-available/$BB_HOST_ENV.conf <<END
Include /etc/apache2/mods-available/php$BB_PHP_VERSION.conf
#LoadModule ssl_module modules/mod_ssl.so
#Listen 443
<VirtualHost *:443>
    DocumentRoot $BB_HOME/webhooks
    <Directory   $BB_HOME/webhooks>
#       Order allow,deny
#       Allow from all
#       AllowOverride None
        Require all granted
    </Directory>
    ErrorLog  $BB_HOME/../logs/error.log
    CustomLog $BB_HOME/../logs/access.log combined
    SSLEngine on
    SSLProtocol all -SSLv2 -SSLv3
    SSLCipherSuite ALL:!DH:!EXPORT:!RC4:+HIGH:+MEDIUM:!LOW:!aNULL:!eNULL
    SSLCertificateFile    /etc/letsencrypt/live/$BB_HOST_ENV/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$BB_HOST_ENV/privkey.pem
</VirtualHost>
END

cat >>/etc/apache2/ports.conf <<END

Listen 8080
Listen 8088
END

a2enmod ssl

sed -i -e 's/^\([ \t]*StartServers\).*$/\1 20/' /etc/apache2/mods-available/mpm_prefork.conf
sed -i -e 's/^\([ \t]*MinSpareServers\).*$/\1 10/' /etc/apache2/mods-available/mpm_prefork.conf
sed -i -e 's/^\([ \t]*MaxSpareServers\).*$/\1 20/' /etc/apache2/mods-available/mpm_prefork.conf
sed -i -e 's/^\([ \t]*MaxRequestWorkers\).*$/\1 72/' /etc/apache2/mods-available/mpm_prefork.conf
sed -i -e 's/^\([ \t]*MaxConnectionsPerChild\).*$/\1 1000/' /etc/apache2/mods-available/mpm_prefork.conf

sed -i -e 's/^\(KeepAlive\) On$/\1 Off/' /etc/apache2/apache2.conf

sed -i -e 's/^\(memory_limit =\) .*$/\1 32M/' /etc/php/$BB_PHP_VERSION/apache2/php.ini
sed -i -e 's/^\(max_input_time =\) .*$/\1 30/' /etc/php/$BB_PHP_VERSION/apache2/php.ini

# MYSQL

if [ "$ENABLE_PHPMYADMIN" != "0" ]; then
    ln -s /usr/share/phpmyadmin/ $BB_HOME/scripts/phpmyadmin
fi

mysqladmin -u root password $BB_MYSQL_ROOT_PASSWORD
mysql -u root --password=$BB_MYSQL_ROOT_PASSWORD <$BB_SQL_SCHEMA

mysql -u root --password=$BB_MYSQL_ROOT_PASSWORD <<END
CREATE USER 'botbasic'@'localhost' IDENTIFIED BY '$BB_MYSQL_BOTBASIC_PASSWORD';
GRANT ALL PRIVILEGES ON botbasic.* TO 'botbasic'@'localhost';
FLUSH PRIVILEGES;
END

# CRONTAB

LINE="#0 0 * * * $BB_HOME/../backup/backup-databases >/dev/null 2>&1"
(crontab -l; echo "$LINE") | crontab -

LINE="#*/1 * * * * $BB_HOME/scripts/downloader/launcher.sh $BB_HOST_PRIVATE:8088 5 0 >/dev/null 2>/dev/null"
(crontab -l; echo "$LINE") | crontab -

LINE="#*/1 * * * * $BB_HOME/scripts/telegramsender/launcher.sh $BB_HOST_PRIVATE:8088 375 750 >/dev/null 2>/dev/null"
(crontab -l; echo "$LINE") | crontab -

# SUDOERS

cat >>/etc/sudoers <<END

www-data        ALL=(ALL) NOPASSWD: /snap/bin/gsutil, /bin/chown
END

# ENABLE SERVICES

a2ensite $BB_HOST_PRIVATE.conf
a2ensite $BB_HOST_PARSER.conf
a2ensite $BB_HOST_MEDIA.conf
a2ensite $BB_HOST_ENV.conf
service apache2 restart

# DONE

exit 0
