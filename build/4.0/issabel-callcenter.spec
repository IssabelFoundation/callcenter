%define modname callcenter

Summary: Issabel Call Center
Name:    issabel-%{modname}
Version: 4.0.0
Release: 4
License: GPL
Group:   Applications/System
Source0: %{modname}_%{version}-%{release}.tgz
BuildRoot: %{_tmppath}/%{name}-%{version}-root
BuildArch: noarch
Requires(pre): issabel-framework >= 2.4.0-1
Requires: asterisk11
Requires: issabelPBX
Requires: php-mbstring

Obsoletes: elastix-callcenter

%description
Issabel Call Center

%prep
%setup -n %{name}_%{version}-%{release}

%install
rm -rf $RPM_BUILD_ROOT

# Files provided by all Issabel modules
mkdir -p    $RPM_BUILD_ROOT/var/www/html/
mv modules/ $RPM_BUILD_ROOT/var/www/html/

# Additional (module-specific) files that can be handled by RPM
mkdir -p $RPM_BUILD_ROOT/opt/issabel/
mv setup/dialer_process/dialer/ $RPM_BUILD_ROOT/opt/issabel/
chmod +x $RPM_BUILD_ROOT/opt/issabel/dialer/dialerd
mkdir -p $RPM_BUILD_ROOT/etc/rc.d/init.d/
mv setup/dialer_process/issabeldialer $RPM_BUILD_ROOT/etc/rc.d/init.d/
chmod +x $RPM_BUILD_ROOT/etc/rc.d/init.d/issabeldialer
rmdir setup/dialer_process
mkdir -p $RPM_BUILD_ROOT/etc/logrotate.d/
mv setup/issabeldialer.logrotate $RPM_BUILD_ROOT/etc/logrotate.d/issabeldialer
mv setup/usr $RPM_BUILD_ROOT/usr

# The following folder should contain all the data that is required by the installer,
# that cannot be handled by RPM.
mkdir -p    $RPM_BUILD_ROOT/usr/share/issabel/module_installer/%{name}-%{version}-%{release}/
mv setup/   $RPM_BUILD_ROOT/usr/share/issabel/module_installer/%{name}-%{version}-%{release}/
mv menu.xml $RPM_BUILD_ROOT/usr/share/issabel/module_installer/%{name}-%{version}-%{release}/
mv CHANGELOG $RPM_BUILD_ROOT/usr/share/issabel/module_installer/%{name}-%{version}-%{release}/

%post

# Run installer script to fix up ACLs and add module to Issabel menus.
issabel-menumerge /usr/share/issabel/module_installer/%{name}-%{version}-%{release}/menu.xml

# The installer script expects to be in /tmp/new_module
mkdir -p /tmp/new_module/%{modname}
cp -r /usr/share/issabel/module_installer/%{name}-%{version}-%{release}/* /tmp/new_module/%{modname}/
chown -R asterisk.asterisk /tmp/new_module/%{modname}

php /tmp/new_module/%{modname}/setup/installer.php
rm -rf /tmp/new_module

# Be sure to set shell for user asterisk
chsh -s /bin/bash asterisk

# Add dialer to startup scripts, and enable it by default
chkconfig --add issabeldialer
chkconfig --level 2345 issabeldialer on

# Fix incorrect permissions left by earlier versions of RPM
chown -R asterisk.asterisk /opt/issabel/dialer

# To update smarty (tpl updates)
rm -rf /var/www/html/var/templates_c/*

# Remove obsolete modules
issabel-menuremove rep_agent_connection_time

if [ $1 -eq 1  ] ; then # install
    if [ x`pidof mysqld` == "x"  ] ; then
        # mysql is not running, delay db creation
        cp /usr/share/issabel/module_installer/%{name}-%{version}-%{release}/setup/firstboot_call_center.sql /var/spool/issabel-mysqldbscripts/08-call_center.sql
    fi
fi

%clean
rm -rf $RPM_BUILD_ROOT

%preun
if [ $1 -eq 0 ] ; then # Check to tell apart update and uninstall
  # Workaround for missing issabel-menuremove in old Elastix versions (before 2.0.0-20)
  if [ -e /usr/bin/issabel-menuremove ] ; then
    echo "Removing CallCenter menus..."
    issabel-menuremove "call_center"
  else
    echo "No issabel-menuremove found, might have stale menu in web interface."
  fi
  chkconfig --del issabeldialer
fi

%files
%defattr(-, asterisk, asterisk)
/opt/issabel/dialer
%defattr(-, root, root)
%{_localstatedir}/www/html/*
%{_datadir}/issabel/module_installer/*
/opt/issabel/dialer/*
%{_sysconfdir}/rc.d/init.d/issabeldialer
%{_sysconfdir}/logrotate.d/issabeldialer
%defattr(0775, root, root)
%{_bindir}/issabel-callcenter-local-dnc

%changelog
