#!/bin/bash

# Clone callcenter repository from this account. If you fork
# the repository and make your own changes, change it to yours
# so you can install or update with this script.

GITHUB_ACCOUNT='IssabelFoundation'

yum -y install git >/dev/null

RED='\033[0;31m'
NC='\033[0m' # No Color

VERSION=$(asterisk -rx "core show version" | awk '{print $2}' | cut -d\. -f 1)

if [ "$VERSION" != "11" ]; then
    echo
    echo -e "${RED}Issabel CallCenter Community Requires Asterisk 11. It most probably fail with other versions.${NC}"
    echo
fi

cd /usr/src
rm -rf callcenter
git clone https://github.com/${GITHUB_ACCOUNT}/callcenter.git callcenter 2>&1 >/dev/null
cd /usr/src/callcenter
chown asterisk.asterisk modules/* -R
cp -pr modules/* /var/www/html/modules
mkdir -p /opt/issabel/
mv setup/dialer_process/dialer/ /opt/issabel/
chmod +x /opt/issabel/dialer/dialerd
mkdir -p /etc/rc.d/init.d/
mv setup/dialer_process/issabeldialer /etc/rc.d/init.d/
chmod +x /etc/rc.d/init.d/issabeldialer
mkdir -p /etc/logrotate.d/
mv setup/issabeldialer.logrotate /etc/logrotate.d/issabeldialer
mv setup/usr/bin/issabel-callcenter-local-dnc /usr/bin
chown asterisk.asterisk /opt/issabel -R
rm -rf /usr/share/issabel/module_installer/callcenter/
mkdir -p    /usr/share/issabel/module_installer/callcenter/
mv setup/   /usr/share/issabel/module_installer/callcenter/
mv menu.xml /usr/share/issabel/module_installer/callcenter/
mv CHANGELOG /usr/share/issabel/module_installer/callcenter/

issabel-menumerge /usr/share/issabel/module_installer/callcenter/menu.xml

mkdir -p /tmp/new_module/callcenter
cp -r /usr/share/issabel/module_installer/callcenter/* /tmp/new_module/callcenter/
chown -R asterisk.asterisk /tmp/new_module/callcenter

php /tmp/new_module/callcenter/setup/installer.php
rm -rf /tmp/new_module

# Be sure to set shell for user asterisk
chsh -s /bin/bash asterisk 2>&1 >/dev/null

# Add dialer to startup scripts, and enable it by default
chkconfig --add issabeldialer
chkconfig --level 2345 issabeldialer on
service issabeldialer start
