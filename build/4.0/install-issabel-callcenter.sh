#!/bin/bash
yum -y install git >/dev/null

VERSION=$(asterisk -rx "core show version" | awk '{print $2}' | cut -d\. -f 1)

if [ "$VERSION" != "11" ]; then
    echo "Issabel CallCenter Community Requires Asterisk 11. Aborting."
fi

cd /usr/src
rm -rf callcenter
git clone https://github.com/IssabelFoundation/callcenter.git callcenter
cd /usr/src/callcenter
chown asterisk.asterisk modules/* -R
cp -pr modules/* /var/www/html/modules
mkdir -p /opt/issabel/
cp setup/dialer_process/dialer/ /opt/issabel/
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
chsh -s /bin/bash asterisk

# Add dialer to startup scripts, and enable it by default
chkconfig --add issabeldialer
chkconfig --level 2345 issabeldialer on
service issabeldialer start
