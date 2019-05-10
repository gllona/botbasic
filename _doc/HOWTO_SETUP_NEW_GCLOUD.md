# HOW_TO setup new gcloud account with botbasic

## Platform

* Install Google Cloud SDK

* Create Gmail account

* Enable gcloud free tier 12 month with 300 USD

* Create new project
  - name: botbasic-dev-2019
  
* Create new Google Compute VM instance
  - g1-small
  - us-west-1 / us-west-1b
  - name: beta
  - image: Ubuntu 18.04 LTS
  - complete access to API
  - HTTP & HTTPS
  - disks / (no) delete disk image at instance deletion
  - network / network interfaces / edit / external IP / create IP address
    - name: bots19-beta-external
  - create & copy external IP
  
* Create new Cloud DNS zone
  - name: bots19-logicos-org
  - public
  - DNS name: bots19.logicos.org
  - copy NS entries (ex. ns-cloud-c1.googledomains.com) for next step
  - add record set
  - entries:
    - A / beta.bots19.logicos.org / external IP of instance / TTL 300s
    - CNAME / dev.bots19.logicos.org / beta.bots19.logicos.org / TTL 300s
    - CNAME / media-dev.bots19.logicos.org / dev.bots19.logicos.org / TTL 300s
    - CNAME / parser-beta.bots19.logicos.org / beta.bots19.logicos.org / TTL 300s
    
* Enable external network access to parser w/o ngrok
  - VPC networks / default / Firewall rules
  - Add firewall rule
    - name: allow-http-8080
    - type: ingress
    - targets: apply to all (or reduce access to VM instance)
    - IP ranges: 0.0.0.0/0
    - protocols/ports: tcp:8080
    - action: allow
    - priority: 1000

* Redirect bots19.logicos.org to gcloud
  - switch UI to main DNS provider (ex. DNS server for logicos.org):
    - https://cpanel.hostinger.co/advanced/dns-zone-editor
  - add zones (4 zones, each one for a gcloud NS entry)
    - host: bots19
    - TXT value: <NS entry>
    - TTL: 1 day
  - switch back to gcloud console  
  
* Test
  - $ ping *external_IP*
  - $ ping *DNS_name*
  - $ ping *DNS_alias*
  - web SSH to instance
  
* SSH to instance  
  - $ gcloud auth login
  - $ gcloud compute --project "botbasic-dev-2019" ssh --zone "us-west1-b" "beta"
    ```
    Generating public/private rsa key pair.
    Enter passphrase (empty for no passphrase): 
    Enter same passphrase again: 
    Your identification has been saved in /home/gorka/.ssh/google_compute_engine.
    Your public key has been saved in /home/gorka/.ssh/google_compute_engine.pub.
    The key fingerprint is:
    .....
    Updating project ssh metadata...⠏Updated [https://www.googleapis.com/compute/v1/projects/botbasic-dev-2019].
    Updating project ssh metadata...done.                                                              
    Waiting for SSH key to propagate.
    Warning: Permanently added 'compute.7094817866695802000' (ECDSA) to the list of known hosts.
    Welcome to Ubuntu 18.04.2 LTS (GNU/Linux 4.15.0-1030-gcp x86_64)
    .....
    gorka@beta:~$
    ```  
    the generated key should appear in Compute engine / metadata / SSH keys / add SSH key
  - $ sudo su
  - $ exit
  
* Test external HTTP access
  - SSH to instance, then in the instance
    - $ sudo apt-get install -y apache2
    - $ exit
  - $ curl http://dev.bots19.logicos.org

* Download repo
  - SSH to instance, then in the instance
    - $ sudo su botbasic2019
    - $ cd
    - option1) from gitlab
      - $ git clone https://gitlab.com/botbasic/botbasic-core.git
    - option2) from github
      - $ git clone https://github.com/gllona/botbasic
    - $ ln -s *directory_name_for_cloned_repo* botbasic
    - $ cd botbasic
    - $ git checkout develop

* Download, configure & install
  - SSH to instance, then in the instance
  - Download
    - either (choose one):
      - $ git clone https://gitlab.com/botbasic/botbasic-core.git
      - $ git clone https://github.com/gllona/botbasic
      - install repo as Google Cloud Source Repository botbasic-core
    - $ cd *cloned_dir*
    - $ cd _bin
  - Configure parser user
    - open schema.sql in a text editor
    - uncomment last line (INSERT INTO parser_user ....) and set parser username/password
    - save & quit
  - Install
    - $ chmod +x install.sh
    - $ sudo su
    - $ BB_HOST=beta BB_SUBDOMAIN=bots19 BB_ENV=dev BB_REPO=gitlab BB_CODE_BRANCH=develop ENABLE_PHPMYADMIN=0 BB_MYSQL_ROOT_PASSWORD=rsqlt BB_MYSQL_BOTBASIC_PASSWORD=candela ./install.sh

## BotBasic

* See [HOW TO](./HOWTO_BBAPPS_CONFIGURATION) (parts related to BotConfig.php)

* Configure new bot in BotConfig.php

* Set webhook (in the VM instance) 
  - $ curl http://local.beta.bots19.logicos.org:8088/scripts/hooksetter/setwebhook.php
  
* Browse to parser
  - http://parser-beta.bots19.logicos.org:8080/parser_upload_form.html

* Parse & Save to DB the BotBasic code for the new bot
  - "code name" will be the name you chose for your new bot in BotConfig.php::$bbBots
  
* Monitor
  - open new shell window
  - $ cd /home/botbasic/logs
  - $ tail -f runtime.log

* Activate daemons
  - $ sudo su
  - $ crontab -e
  - uncomment lines for downloader and telegramsender
  - save and quit editor

* Test new bot in Telegram
