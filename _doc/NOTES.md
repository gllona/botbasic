# extra directories at .../httpdocs/.. level:

- backup
- backup/mysql
- downloads
- logs
- logs/bizmodel
- logs/webstub



# gcloud

gcloud config configurations activate CONFIGURATION_NAME

gcloud compute instances describe botbasic-alpha --format="yaml(serviceAccounts)"

>metaserver

curl -H 'Metadata-Flavor: Google' "http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/scopes"

gcloud auth list

>delete gsutil cache after changing instance permissions, force reload

rm -rf /root/.gsutil/



# ngrok free for parser

nohup ngrok http -host-header=beta.bots.logicos.org -log=stdout 80 &
curl http://localhost:4040/api/tunnels | jq '.tunnels[0].public_url'



# testvpn

1. https://github.com/hwdsl2/setup-ipsec-vpn
2. https://bbuckman.github.io/strongswan/2018/05/23/strongswan-ubuntu-18-04.html



# remote debugging with ssh tunneling

https://www.revsys.com/writings/quicktips/ssh-tunnel.html
https://confluence.jetbrains.com/display/PhpStorm/Remote+debugging+in+PhpStorm+via+SSH+tunnel
https://stackoverflow.com/questions/49449404/phpstorm-xdebug-through-ssh-tunnel
https://www.sourcetoad.com/resources/debugging-php-save-time-with-xdebugs-remote-autostart/
http://www.dieuwe.com/blog/xdebug-ubuntu-1604-php7
https://gist.github.com/Xeoncross/1100761
https://gist.github.com/IngmarBoddington/5311858

>in dev server

sudo apt-get install php-xdebug
cat /etc/php/7.2/mods-available/xdebug.ini
zend_extension=xdebug.so
xdebug.remote_enable=1
xdebug.remote_host=127.0.0.1
xdebug.remote_port=9000
xdebug.remote_autostart=1

>in dev machine

ssh -R 9000:localhost:9000 gorka@dev.bots.logicos.org -N   # optional -f for daemon mode
