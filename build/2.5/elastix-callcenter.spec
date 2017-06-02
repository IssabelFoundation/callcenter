%define modname callcenter

Summary: Elastix Call Center
Name:    elastix-callcenter
Version: 2.2.0
Release: 17
License: GPL
Group:   Applications/System
Source0: %{modname}_%{version}-%{release}.tgz
BuildRoot: %{_tmppath}/%{name}-%{version}-root
BuildArch: noarch
# TODO: usar getCurrentElastixModule() desde elastix-framework-2.4.0-3
Prereq: elastix-framework >= 2.4.0-1
Requires: asterisk
Requires: freePBX
Requires: php-mbstring

%description
Elastix Call Center

%prep
%setup -n %{modname}

%install
rm -rf $RPM_BUILD_ROOT

# Files provided by all Elastix modules
mkdir -p    $RPM_BUILD_ROOT/var/www/html/
mv modules/ $RPM_BUILD_ROOT/var/www/html/

# Additional (module-specific) files that can be handled by RPM
mkdir -p $RPM_BUILD_ROOT/opt/elastix/
mv setup/dialer_process/dialer/ $RPM_BUILD_ROOT/opt/elastix/
chmod +x $RPM_BUILD_ROOT/opt/elastix/dialer/dialerd
mkdir -p $RPM_BUILD_ROOT/etc/rc.d/init.d/
mv setup/dialer_process/elastixdialer $RPM_BUILD_ROOT/etc/rc.d/init.d/
chmod +x $RPM_BUILD_ROOT/etc/rc.d/init.d/elastixdialer
rmdir setup/dialer_process
mkdir -p $RPM_BUILD_ROOT/etc/logrotate.d/
mv setup/elastixdialer.logrotate $RPM_BUILD_ROOT/etc/logrotate.d/elastixdialer
mv setup/usr $RPM_BUILD_ROOT/usr

# The following folder should contain all the data that is required by the installer,
# that cannot be handled by RPM.
mkdir -p    $RPM_BUILD_ROOT/usr/share/elastix/module_installer/%{name}-%{version}-%{release}/
mv setup/   $RPM_BUILD_ROOT/usr/share/elastix/module_installer/%{name}-%{version}-%{release}/
mv menu.xml $RPM_BUILD_ROOT/usr/share/elastix/module_installer/%{name}-%{version}-%{release}/
mv CHANGELOG $RPM_BUILD_ROOT/usr/share/elastix/module_installer/%{name}-%{version}-%{release}/

%post

# Run installer script to fix up ACLs and add module to Elastix menus.
elastix-menumerge /usr/share/elastix/module_installer/%{name}-%{version}-%{release}/menu.xml

# The installer script expects to be in /tmp/new_module
mkdir -p /tmp/new_module/%{modname}
cp -r /usr/share/elastix/module_installer/%{name}-%{version}-%{release}/* /tmp/new_module/%{modname}/
chown -R asterisk.asterisk /tmp/new_module/%{modname}

php /tmp/new_module/%{modname}/setup/installer.php
rm -rf /tmp/new_module

# Add dialer to startup scripts, and enable it by default
chkconfig --add elastixdialer
chkconfig --level 2345 elastixdialer on

# Fix incorrect permissions left by earlier versions of RPM
chown -R asterisk.asterisk /opt/elastix/dialer

# To update smarty (tpl updates)
rm -rf /var/www/html/var/templates_c/*

# Remove obsolete modules
elastix-menuremove rep_agent_connection_time

%clean
rm -rf $RPM_BUILD_ROOT

%preun
if [ $1 -eq 0 ] ; then # Check to tell apart update and uninstall
  # Workaround for missing elastix-menuremove in old Elastix versions (before 2.0.0-20)
  if [ -e /usr/bin/elastix-menuremove ] ; then
    echo "Removing CallCenter menus..."
    elastix-menuremove "call_center"
  else
    echo "No elastix-menuremove found, might have stale menu in web interface."
  fi
  chkconfig --del elastixdialer
fi

%files
%defattr(-, asterisk, asterisk)
/opt/elastix/dialer
%defattr(-, root, root)
%{_localstatedir}/www/html/*
%{_datadir}/elastix/module_installer/*
/opt/elastix/dialer/*
%{_sysconfdir}/rc.d/init.d/elastixdialer
%{_sysconfdir}/logrotate.d/elastixdialer
%defattr(0775, root, root)
%{_bindir}/elastix-callcenter-load-dnc

%changelog
* Fri Jul 29 2016 Alex Villacís Lasso <a_villacis@palosanto.com> 2.2.0-17
- Bump version for release

* Fri Jun 24 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: remove unused attribute "channel" from sqlinsertcalls message
  sent to SQLWorkerProcess. Ported from CallCenterPRO.
  SVN Rev[7639]
- CHANGED: Dialer: implement explicit request flag for AMI credentials message.
  This flag is used to discard credentials that were explicitly requested if the
  initial credentials arrived in the meantime, to prevent unnecessary AMI
  reconnections. Ported from CallCenterPRO.
  SVN Rev[7638]
- FIXED: Dialer: discard queue/agent enumeration if AMI connection is lost. This
  prevents a stale enumeration from blocking valid future enumerations until
  dialer restart. Ported from CallCenterPRO.
  SVN Rev[7637]

* Mon Jun 20 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: prevent AMIEventProcess crash due to NULL object if a request
  to refresh agents comes while in the middle of AMI disconnection. Ported from
  CallCenterPRO.
  SVN Rev[7630]
- FIXED: Dialer: fix AMIEventProcess inability to reconnect to AMI after a
  process crash, since SQLWorkerProcess had no way to be notified that the
  instance needed to be sent the AMI credentials. Ported from CallCenterPRO.
  SVN Rev[7629]

* Thu May 26 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Added Turkish translations for Agent Console, Callcenter Config,
  Ingoing Calls Success, Campaign Out, Campaign Monitoring.
  SVN Rev[7624]

* Wed May 25 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: implement abort of pending but undialed calls in case of
  database deadlock. This prevents undialed calls from accumulating in
  AMIEventProcess and preventing future retries due to dialstring duplication.
  Additionally update the campaign revision timestamp more often to prevent a
  busy CPU loop in case of a persistent database error that prevents updates.
  Ported from CallCenterPRO.
  SVN Rev[7621]
- FIXED: Agent Console: pick up and use agentconsole ECCP password if different
  from the default. This prevents the console from breaking if eccp_users module
  changes the password for the "agentconsole" user. Ported from CallCenterPRO.
  SVN Rev[7620]

* Wed Apr 27 2016 Alex Villacís Lasso <a_villacis@palosanto.com> 2.2.0-16
- Bump version for release
- Update CHANGELOG
  SVN Rev[7606]

* Fri Apr 22 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: check whether /etc/localtime is a symlink and use it as an
  additional way to find out the current timezone. Fixes part 2 of Elastix
  bug #2507.
  SVN Rev[7598]
- CHANGED: Dialer: do not call stream_select() with only empty socket arrays.
  SVN Rev[7597]

* Thu Apr 21 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Added Russian translations by user Russian.
  SVN Rev[7589]

* Wed Apr 20 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Break Report: prune all translations that are actually the same value
  as the English one.
  SVN Rev[7588]
- CHANGED: Trunks per Hour: prune all translations that are actually the same
  value as the English one.
  SVN Rev[7587]
- CHANGED: Incoming Call Monitoring: prune all translations that are actually
  the same value as the English one.
  SVN Rev[7586]
- CHANGED: Agent Monitoring: prune all translations that are actually the same
  value as the English one.
  SVN Rev[7585]
- CHANGED: Agent Information: prune all translations that are actually the same
  value as the English one.
  SVN Rev[7584]
- CHANGED: Queues: prune all translations that are actually the same value as
  the English one.
  SVN Rev[7583]
- CHANGED: Login Logout: prune all translations that are actually the same value
  as the English one.
  SVN Rev[7582]
- CHANGED: Ingoing Calls Success: prune all translations that are actually the
  same value as the English one.
  SVN Rev[7581]
- CHANGED: Hold Time: prune all translations that are actually the same value as
  the English one.
  SVN Rev[7580]
- CHANGED: Graphic Calls: prune all translations that are actually the same
  value as the English one.
  SVN Rev[7579]
- CHANGED: Form List: prune all translations that are actually the same value as
  the English one.
  SVN Rev[7578]
- CHANGED: Form Designer: prune all translations that are actually the same
  value as the English one.
  SVN Rev[7577]
- CHANGED: Dont Call List: prune all translations that are actually the same
  value as the English one.
  SVN Rev[7576]
- CHANGED: Clients: prune all translations that are actually the same value as
  the English one.
  SVN Rev[7575]
- CHANGED: Callback Extensions: prune all translations that are actually the
  same value as the English one.
  SVN Rev[7574]
- CHANGED: Calls Per Hour: prune all translations that are actually the same
  value as the English one.
  SVN Rev[7573]
- CHANGED: Calls Per Agent: prune all translations that are actually the same
  value as the English one.
  SVN Rev[7572]
- CHANGED: Calls Detail: prune all translations that are actually the same value
  as the English one.
  SVN Rev[7571]
- CHANGED: Break Administrator: prune all translations that are actually the
  same value as the English one.
  SVN Rev[7570]
- CHANGED: Agents: prune all translations that are actually the same value
  as the English one.
  SVN Rev[7569]

* Mon Apr 11 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: remove event parameter not appearing in CallCenter Community.
  SVN Rev[7567]

* Thu Apr  7 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Out: add documentation on the API for custom uploaders.
  SVN Rev[7564]

* Mon Apr  4 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Out: move translations used only by CSV upload to CSV
  plugin. Fix incorrect uploader translation path.
  SVN Rev[7562]
- CHANGED: Campaign Out: prune all translations that are actually the same value
  as the English one.
  SVN Rev[7561]

* Sun Apr  3 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Out: factor out contact loading via CSV as a plugin and a
  reference implementation of contact loader plugins.
  SVN Rev[7559]

* Fri Apr  1 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: restore periodic read of configuration in CampaignProcess.
  SVN Rev[7558]
- CHANGED: Dialer: add timing for each SQL action in SQLWorkerProcess. Ported
  from CallCenterPRO.
  SVN Rev[7557]
- CHANGED: Dialer: perform SQL commit before removing the action and delivering
  events, not after. Ported from CallCenterPRO.
  SVN Rev[7556]
- CHANGED: Dialer: handle QueueCallerAbandon event. This is required to properly
  mark as abandoned when a call times out in a queue and is transferred to a
  second queue without immediately hanging up. Fixes Elastix bug #2500. Ported
  from CallCenterPRO.
  SVN Rev[7555]
- CHANGED: Dialer: minor optimization to save 1 RPC on idle queue. Ported from
  CallCenterPRO.
  SVN Rev[7554]
- CHANGED: Dialer: assume queue events for Asterisk 12 and later. Ported from
  CallCenterPRO.
  SVN Rev[7553]
- CHANGED: Dialer: make construirEventoProgresoLlamada private to
  SQLWorkerProcess. Ported from CallCenterPRO.
  SVN Rev[7552]
- CHANGED: Dialer: remove unused database connection code from ECCPProcess.
  Ported from CallCenterPRO.
  SVN Rev[7551]
- CHANGED: Dialer: move audit fixing to SQLWorkerProcess. Ported from
  CallCenterPRO.
  SVN Rev[7549]
- CHANGED: Dialer (ECCP): move call update events to SQLWorkerProcess. Ported
  from CallCenterPRO.
  SVN Rev[7548]
- CHANGED: Dialer (ECCP): move SQL code for call progress notification to
  SQLWorkerProcess. This introduces a message handler to forward events from
  SQLWorkerProcess, and uses it to forward call progress messages. Ported from
  CallCenterPRO.
  SVN Rev[7547]
- CHANGED: Dialer: remove debugging messages on code involving Hold. Ported
  from CallCenterPRO.
  SVN Rev[7546]
- CHANGED: Dialer: update UniqueidAgente when entering and leaving Hold. This
  prevents the call from being mis-identified as a transferred call. Ported from
  CallCenterPRO.
  SVN Rev[7545]
- CHANGED: Dialer: use ParkedCallGiveUp event instead of a Hangup special case
  to detect a call hangup while on a parking lot. Ported from CallCenterPRO.
  SVN Rev[7544]
- CHANGED: Dialer: store new Uniqueid when parking call. This allows removal of
  the Unlink handler. This also allows identification of the call that returns
  from Hold without having to lookup the call by channel. Ported from
  CallCenterPRO.
  SVN Rev[7543]
- CHANGED: Dialer: store parkinglot extension from ParkedCall event. This allows
  removal of the "parkedcalls show" command invocation in ECCPConn. Ported from
  CallCenterPRO.
  SVN Rev[7542]
- CHANGED: Dialer: reimplement Hold using Park instead of Redirect. This moves
  responsibility for agent pause/unpause to Agente class, just like the other
  pauses. Last use of AMI by ECCPProcess class is now removed. Ported from
  CallCenterPRO.
  SVN Rev[7541]
- CHANGED: Dialer: remove unused _cb_QueuePause callback from AMIEventProcess.
  Ported from CallCenterPRO.
  SVN Rev[7540]
- CHANGED: Dialer: add call parking commands to AMIClientConn. Ported from
  CallCenterPRO.
  SVN Rev[7539]
- CHANGED: Dialer: move QueuePause from ECCPProcess to AMIEventProcess. Step 2
  of removal of AMI connection from ECCPProcess. Ported from CallCenterPRO.
  SVN Rev[7538]
- CHANGED: Dialer: each Agente object has its own reference to message pipe.
  Ported from CallCenterPRO.
  SVN Rev[7537]
- CHANGED: Dialer: move msg_ECCPProcess_AgentLogoff to Agente class. This
  message is always paired to the Agente::terminarLoginAgente method. Ported
  from CallCenterPRO.
  SVN Rev[7536]
- CHANGED: Dialer: move QueuePause from ECCPProcess to AMIEventProcess. Step 1
  of removal of AMI connection from ECCPProcess. Ported from CallCenterPRO.
  SVN Rev[7535]
- CHANGED: Dialer: remove unused message canalRemotoAgente from AMIEventProcess.
  Ported from CallCenterPRO.
  SVN Rev[7534]
- CHANGED: Dialer: move call state update to SQLWorkerProcess. With this change
  call updates will no longer block on heavy reads from calls table. Ported from
  CallCenterPRO.
  SVN Rev[7533]
- CHANGED: Dialer: introduce SQLWorkerProcess. This class will handle all
  database writes required by state changes that were previously executed in
  CampaignProcess. This should allow updates to be processed even when
  CampaignProcess is busy reading calls for outgoing campaigns. All operations
  are wrapped in transactions and will be restarted on errors without retry
  limits. This commit migrates the query of active agents.
  SVN Rev[7532]

* Thu Mar 31 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: move Originate call into AMIEventProcess. This puts all
  responsibility of database state changes on AMIEventProcess, preventing races
  when implementing SQLWorkerProcess.
  SVN Rev[7531]
- FIXED: Campaign Monitoring: fix lingering errors in event refactoring. Ported
  from CallCenterPRO.
  SVN Rev[7530]
- CHANGED: Dialer: use is_callable() instead of open-coding it. Ported from
  CallCenterPRO.
  SVN Rev[7529]
- CHANGED: Dialer: move execution of "database show QPENALTY" to AMIEventProcess
  using asynchronous AMI. This removes the last AMI use from event handlers in
  CampaignProcess as preparation for SQLWorkerProcess. Ported from
  CallCenterPRO.
  SVN Rev[7528]
- CHANGED: Dialer: factor out parse_database_data from AMI functions for a
  future commit. Ported from CallCenterPRO.
  SVN Rev[7527]
- CHANGED: Dialer: use asynchronous AMI for GetVar to retrieve the channel
  variable MIXMONITOR_FILENAME instead of going through CampaignProcess, thus
  removing one use of AMI from CampaignProcess. Ported from CallCenterPRO.
  SVN Rev[7526]
- CHANGED: Dialer: remove unused parameter in _verificarFinLlamadasAgendables
  since now schedule unpause is done by AMIEventProcess. Ported from
  CallCenterPRO.
  SVN Rev[7525]

* Wed Mar 30 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: retry call update operation on deadlock or timeout. This
  reduces the chances of an exception interruting the Predictor enumeration
  wait. Ported from CallCenterPRO.
  SVN Rev[7524]
- FIXED: Dialer: synchronize pause state when updating queue membership.
  Otherwise a static agent added to a new queue while paused will not inherit
  the paused state and will receive calls through the new membership. Ported
  from CallCenterPRO.
  SVN Rev[7523]
- CHANGED: Dialer: encapsulate check for restartable exception. Ported from
  CallCenterPRO.
  SVN Rev[7522]
- FIXED: Dialer: re-throw exception after removing event handlers. Fixes failure
  to install event handlers on next iteration. Ported from CallCenterPRO.
  SVN Rev[7521]

* Mon Mar 14 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Do Not Call: switch to use of PDO for DNC number inserts. This is
  3x as fast as the same operations using the paloDB class.
  SVN Rev[7520]

* Tue Mar  8 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: instead of blanking the call information panel, it is
  now colored in a "disabled" color.
  SVN Rev[7513]

* Sun Mar  6 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Out: isolate database inserts for outgoing campaign from
  CSV import code. Phase 2 of pluggable contact import support.
  SVN Rev[7506]

* Fri Mar  4 2016 Alex Villacís Lasso <a_villacis@palosanto.com> 2.2.0-15
- Bump version for release

* Tue Mar  1 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: fix incomplete parameter replacement on call prediction after
  QueueShadow patch that caused newly created outgoing campaigns to remain
  stalled even with free agents.
  SVN Rev[7500]
- FIXED: Agent Console: ensure that the inactivity timeout value is never lower
  than the AJAX poll interval, to prevent inactivity logouts.
  SVN Rev[7499]
- FIXED: Agent Console: work around missing Array.map() on IE8.
  SVN Rev[7498]
- CHANGED: Dialer (ECCP): do not depend on currentcallid for call verification,
  and explicitly test for outgoing campaign on hold. Ported from CallCenterPRO.
  SVN Rev[7497]
- FIXED: Dialer: reject channel assigment if agent channel is prefix of it.
  This is a stronger check than equality and is required to handle dynamic
  agents. Ported from CallCenterPRO.
  SVN Rev[7496]

* Mon Feb 29 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): synchronize login process with CallCenterPRO and add
  warning when dynamic agent is already in some queues at login time.
  SVN Rev[7495]
- CHANGED: Dialer: show PID of process that writes log message. Ported from
  CallCenterPRO.
  SVN Rev[7494]
- CHANGED: Dialer: attempt to load queue configuration flags from the file
  queues_additional.conf. This saves AMIEventProcess from having to reject calls
  from CampaignProcess until events are received to validate the queue flags.
  Ported from CallCenterPRO.
  SVN Rev[7493]
- CHANGED: Dialer: refactor event firing on agent login to Agente class. Ported
  from CallCenterPRO.
  SVN Rev[7492]

* Sat Feb 27 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: implement opportunistic use of Agent* and QueueMemberStatus
  messages if enabled. These messages allow keeping a shadow status of the
  queues so that CampaignProcess does not have to invoke AND parse the output
  of CoreShowChannels and QueueStatus every 3 seconds, and burden the asterisk
  process on the way.
  SVN Rev[7491]

* Mon Feb 22 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: silence useless messages that appear on EAGAIN when not all
  8K chunks of data can be written to socket.
  SVN Rev[7487]

* Sun Feb 21 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Monitoring: fix agent formatting. Now oncall status has
  priority over paused status. Also, pause name is now displayed next to the
  "On Break" status label.
  SVN Rev[7486]
- CHANGED: Campaign Monitoring: refactoring of agent extraction into a single
  method, used by campaign and agent formatting. The main affected module is
  Campaign Monitoring.
  SVN Rev[7485]

* Sat Feb 20 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer (ECCP): oncall status must take priority over paused status.
  SVN Rev[7484]

* Fri Feb 19 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: part 3 of refactoring between getcampaignstatus and getagentstatus.
  The _agregarAgentStatusInfo method is reimplemented through the method
  getcampaignstatus_setagent after unifying the use of resumenLlamada to get
  the connected call information. Ported from CallCenterPRO.
  SVN Rev[7483]
- CHANGED: part 2 of refactoring between getcampaignstatus and getagentstatus.
  Llamada::resumenLlamada() now reports additional fields in order to replace
  Agente::resumenSeguimientoLlamada(). In this process, a few bugs were
  uncovered and fixed. Also, the fields reported were unified to allow for
  XML conversion via loops instead of successive calls. Ported from
  CallCenterPRO.
  SVN Rev[7482]

* Thu Feb 18 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: part 1 of refactoring between getcampaignstatus and getagentstatus.
  These two requests expose the agent status in different formats using separate
  code paths. This commit unifies the use of a single method to load the break
  name for both code paths without external API changes. Ported from
  CallCenterPRO.
  SVN Rev[7481]

* Wed Feb 17 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: fix typo that broke call prediction since AMI rewrite.
  SVN Rev[7480]
- FIXED: Dialer: fix incorrect parameter ordering of AMI Atxfer call.
  SVN Rev[7479]

* Sat Feb 13 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): refactoring of common code: get[multiple]agentstatus.
  SVN Rev[7478]
- CHANGED: Agent Console: i18n for scheduled call waiting state.
  SVN Rev[7477]

* Fri Feb 12 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Out: i18n for contact loading.
  SVN Rev[7475]

* Wed Feb 10 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Agent Console: fixed scenario in which events received in the same
  buffer as a <response> packet would remain undelivered if a wait_response()
  call is issued immediately after.
  SVN Rev[7474]
- CHANGED: Dialer: zero out wait interval in MultiplexServer when pending
  unparsed data is present right before the select() call. This removes a
  nonzero wait before dealing with the unparsed data.
  SVN Rev[7470]

* Sun Feb  7 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Out: move contact loading to a separate action. Phase one of
  pluggable contact source changeset. Also display all campaigns by default,
  instead of active only, since campaigns now are created in inactive state.
  SVN Rev[7469]
- FIXED: Dialer: allow Dialing as a valid state for Llamada->status property.
  Otherwise Llamada->status might remain NULL, which propagates to the
  call_progress_log insertion and causes a SQL failure.
  SVN Rev[7468]
- CHANGED: Dialer: tweak MultiplexServer class to perform reading and writing
  of buffers at every possible opportunity, and therefore reduce the chance of
  remote available data to read lingering too long.
  SVN Rev[7467]

* Fri Feb  5 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- DELETED: remove formpause support.
  SVN Rev[7464]

* Wed Feb  3 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: fix race in which ECCPWorkerProcess sent the response to the last ECCP
  request and immediately terminated but HubProcess caught the process
  termination before having a chance to receive and route the ECCP response.
  SVN Rev[7458]
- FIXED: fix incorrect logging-out of static agent when msg_AgentsComplete is
  received while agent is AGENT_LOGGEDIN but state is "logging" because the
  Agentlogin message was not yet received.
- FIXED: fix condition in which actualchannel is incorrectly assigned with the
  same value as channel when different from actualchannel.
  SVN Rev[7457]

* Mon Feb  1 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: do not use CampaignProcess to invoke "agent show online" from
  AMIEventProcess. Instead, piggyback on existing AgentsComplete callback to
  check every call associated to a logged-in agent when one call is missing
  actualchannel.
  SVN Rev[7454]

* Sat Jan 30 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: by using asynchronous AMI requests, agent reservation for scheduled
  calls is now reimplemented race-free in AMIEventProcess.
  SVN Rev[7453]
- CHANGED: Dialer: bind the act of QueuePause to setBreak and clearBreak.
  SVN Rev[7452]
- CHANGED: Dialer: bind the act of QueueUnpause to clearFormPause. Also prepare
  methods to increment and decrement pause counts with associated
  asyncQueuePause for later refactoring.
  SVN Rev[7450]
- CHANGED: Dialer (ECCP): move responsibility to unpause agent from
  ECCPWorkerProcess to AMIEventProcess to expose a pattern of asyncQueuePause
  right after clearFormPause.
  SVN Rev[7449]
- CHANGED: Dialer: remove CampaignProcess_asyncQueuePause.
  SVN Rev[7448]
- CHANGED: Dialer: bind the act of QueuePause to setFormPause. This also makes
  the CampaignProcess_asyncQueuePause message unnecessary.
  SVN Rev[7447]
- CHANGED: Dialer: add QueuePause callback to Agent object for future commits.
  SVN Rev[7446]
- CHANGED: Dialer: the Agent object now needs a reference to the log.
  SVN Rev[7445]
- CHANGED: Dialer: by using asynchronous AMI requests, form pause removal is now
  reimplemented without bouncing off CampaignProcess.
  SVN Rev[7444]

* Fri Jan 29 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: CallCenter: add index to calls table for queries by schedule date.
  SVN Rev[7443]
- CHANGED: Agent Console: add campaign name and call ID to the call information
  panel. Ported from CallCenterPRO.
  SVN Rev[7442]
- CHANGED: Dialer: repeat signal propagation on shutdown in case child process
  has trouble processing it. Also silence warning message on shutdown of core
  process when the shutdown is intended. Ported from CallCenterPRO.
  SVN Rev[7441]

* Thu Jan 28 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: remove logged-off agent from all known queues on which it is
  present outside dialer control. This prevents calls from being assigned from
  the queue to the logged-off agent.
  SVN Rev[7440]

* Wed Jan 27 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): reimplement pauseagent in order to recover from TOCTOU
  condition. Agent status is verified in AMIEventProcess a second time in order
  to signal ECCPWorkerProcess to remove just-inserted pause audit if agent state
  becomes invalid.
  SVN Rev[7439]
- CHANGED: Dialer: add timestamp fields to AMI Response packets in order to find
  out which AMI requests are more costly.
  SVN Rev[7438]
- CHANGED: Dialer (ECCP): optimize agent existence verification in DB. This
  change removes a number of cases in which the entire list of active agents is
  read just to check for one of them.
  SVN Rev[7437]
- CHANGED: CallCenter: add index to agent table for queries by agent number.
  SVN Rev[7436]

* Tue Jan 26 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: fix scenario in which an agent refresh right in the middle of
  a static agent login would undo the login attempt and leave the dialer in
  an inconsistent state.
  SVN Rev[7435]
- CHANGED: Dialer: by using asynchronous AMI requests, channel unmuting is now
  reimplemented without bouncing off CampaignProcess.
  SVN Rev[7434]
- CHANGED: Dialer: by using asynchronous AMI requests, dynamic addition and
  removal of dynamic agents from queues on configuration refresh is now
  reimplemented without bouncing off CampaignProcess.
  SVN Rev[7433]
- CHANGED: Dialer: by using asynchronous AMI requests, forced agent logoff is
  now reimplemented without bouncing off CampaignProcess.
  SVN Rev[7431]

* Mon Jan 25 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: big rewrite of AMIClientConn. This rewrite allows for
  asynchronous AMI requests. Instead of waiting for the response, the code can
  register a callback to be run once the response to the command arrives. In
  this way Queue operations from AMIEventProcess do not need to be bounced off
  CampaignProcess anymore.
  SVN Rev[7430]

* Fri Jan 22 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Campaign In: also delete from call_recording as done in outgoing
  campaigns. Fixes Elastix bug #2436.
  SVN Rev[7428]

* Mon Jan 18 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: remove duplicate SQL in call update after generation.
  SVN Rev[7427]
- CHANGED: Dialer: set ticks=8 for slightly better performance.
  SVN Rev[7426]
- CHANGED: Dialer: blacklist high-frequency events not necessary for the dialer.
  SVN Rev[7425]

* Sat Jan 16 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: implement static Agent enumeration from Asterisk in order to
  catch instances where the in-memory login status falls out of sync with the
  actual Asterisk status.
  SVN Rev[7421]

* Thu Jan 14 2016 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: fix SysV script to wait for daemon by NOT specifying signal
  to use. Otherwise the system might shut down before the daemon cleans up.
  SVN Rev[7418]
- CHANGED: Dialer: disable AMI event notification on CampaignProcess. The events
  emitted in response to QueueStatus are still received.
  SVN Rev[7417]
- CHANGED: Dialer: disable AMI event notification on ECCPWorkerProcess and
  ECCPProcess. These two do not have any event handlers installed, and disabling
  events saves on CPU time and network bandwidth to Asterisk.
  SVN Rev[7416]

* Mon Dec 28 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: fix undefined variable error introduced in previous commit.
  SVN Rev[7409]

* Wed Dec 23 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: evaluate queue membership for agents that are not mentioned
  in queue enumeration.
  SVN Rev[7408]
- FIXED: Dialer: allow refreshing agents from worker process. Ported from
  CallCenterPRO.
  SVN Rev[7407]

* Mon Dec 21 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Added Russian translations by user Russian.
  SVN Rev[7406]

* Tue Dec 15 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: add proper error handling support when invoking database_*
  methods through AMI. This prevents an error condition from being mistaken for
  an empty response, as observed in a production system. Ported from
  CallCenterPRO.
  SVN Rev[7400]

* Sat Dec 12 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Monitoring: display pause name when agent enters a break.
  Ported from CallCenterPRO.
  SVN Rev[7399]
- CHANGED: Dialer (ECCP): extend the getmultipleagentstatus to report the pause
  information for the agent, using the structure already used for
  getagentstatus. Ported from CallCenterPRO.
  SVN Rev[7398]
- CHANGED: Dialer (ECCP): add new column calls.scheduled and use it to mark
  scheduled calls as distinct from calls loaded through CSV. Ported from
  CallCenterPRO.
  SVN Rev[7397]

* Wed Dec  9 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: introduce partial waitingenter/waitingexit support
  from CallCenterPRO in order to show visual notification of scheduled call
  activity.
  SVN Rev[7391]
- CHANGED: Dialer (ECCP): introduce schedulecallstart and schedulecallfailed
  events to notify that a scheduled call has been started on behalf of a
  particular agent. This is required for the next commit on the agent console
  to implement visual notification of scheduled call start.
  SVN Rev[7390]
- FIXED: Dialer: fix incorrect validation on number of agent pauses.
- FIXED: Dialer: reset reservation flag to FALSE on agent logout.
  SVN Rev[7389]

* Sun Dec  6 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): implement form pause timeout when campaign.formpause
  is nonzero. As part of this change, the ability to cancel internal alarms is
  implemented.
  SVN Rev[7388]

* Sat Dec  5 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): implement end of form pause when successfully saving
  form data.
  SVN Rev[7386]
- FIXED: Dialer: fix error recovery using invalid object due to copy/paste error
  when trying to start audit without having a Form pause created.
  SVN Rev[7385]
- FIXED: Dialer (ECCP): when unpausing an agent, the agent was issued a
  QueuePause(FALSE) when the pause count was nonzero. This is incorrect because
  the agent might be simultaneously in other states that require the pause to
  be maintained. Instead, unpause when the agent count is exactly one, as was
  already done on return from hold.
  SVN Rev[7384]
- CHANGED: Dialer: clear form pause state on logoff, and validate auditable
  pause states before decrementing pause count.
  SVN Rev[7383]
- CHANGED: Dialer (ECCP): remove redundant code invoked on agent logoff and
  formalize closing of multiple auditable pauses. This implicitly implements
  form unpause-on-logoff and should also implement unpause-on-shutdown.
  SVN Rev[7382]

* Fri Dec  4 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): implement initial support for form pause. This pause
  will stop call delivery until agent fills the form completely. Pause timeout
  not yet implemented. Unpause-on-logoff not yet implemented. Unpause-on-shutdown
  not yet implemented. Unpause on successful form save not yet implemented.
  SVN Rev[7381]
- CHANGED: Dialer: load formpause flag from campaigns.
  SVN Rev[7380]

* Thu Dec  3 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign In, Campaign Out: fix some i18n.
  SVN Rev[7378]

* Tue Dec  1 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: cache local_timestamp_received for entire event batch.
  SVN Rev[7377]
- CHANGED: Agent Console: optimize event wait so timeouts to select() calls are
  as long as possible (up to 30s) instead of looping at 1/10s as before. Ported
  from CallCenterPRO.
  SVN Rev[7376]

* Tue Nov 24 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: restrict schedulable agent report to the agents actually
  listed in the parameters. This prevents looking up nonexistent scheduled calls
  for agents that are scheduled for calls on a different simultaneous campaign.
  SVN Rev[7373]
- CHANGED: Dialer: undo queue optimization for outgoing calls. This introduces
  unacceptable delays and risks evaluation of campaigns with stale data.
  Additionally attempt to run some messages after each campaign evaluation to
  reduce instances of queued updates from AMIEventProcess.
  SVN Rev[7372]
- CHANGED: Dialer: silence messages about missing incoming call ID - they
  clutter the log on heavy load without actually being useful.
  SVN Rev[7371]

* Fri Nov 20 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: allow scheduling of terminated outgoing call. Ported
  from CallCenterPRO.
  SVN Rev[7358]
- CHANGED: Dialer (ECCP): implement scheduling of call to number not currently
  on-call, by specifying call ID. Ported from CallCenterPRO.
  SVN Rev[7357]

* Thu Nov 19 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): partial synchronization with CallCenterPRO for next
  commits in schedule functionality.
  SVN Rev[7356]

* Tue Nov 10 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: verify that AMI is available when receiving new agent list. If
  not, the QueueStatus run should be delayed until AMI is available. Ported from
  CallCenterPRO.
  SVN Rev[7348]

* Thu Nov  5 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Calls Details: fix page count when displaying all types of calls.
  SVN Rev[7329]

* Mon Oct 26 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Agent Console: fix External URL tab handling with older jQueryUI.
  SVN Rev[7257]

* Fri Oct 23 2015 Alex Villacís Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: ensure call status is non-NULL after handling OriginateResponse
  to prevent database failures when recording call progress.
  SVN Rev[7248]
- CHANGED: CallCenter: massive s/www.elastix.org/www.elastix.com/g
  SVN Rev[7247]
  SVN Rev[7246]

* Thu Oct 22 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: External URL: specify an icon for module.
  SVN Rev[7226]

* Thu Oct  8 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: add columns and enumerations for form pause functionality.
  SVN Rev[7178]
- FIXED: Dialer (ECCP): emit a failure condition as soon as it is evident that
  the dynamic agent trying to log in does not belong to any queue.
  SVN Rev[7177]
- FIXED: Calls Details: do not display duplicate rows when multiple recordings
  are found. Instead collect them all and display the most recent one as the
  main one, and hide the rest until clicked on. Fixes Elastix bug #2350.
  SVN Rev[7174]

* Fri Oct  2 2015 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-14
- Bump version for release.
- FIXED: Campaign Out: allow outgoing campaign without forms to be created.
  SVN Rev[7163]
- CHANGED: Dialer: (trivial) partial synchronization with CallCenterPRO.
  SVN Rev[7162]
- CHANGED: Dialer: dynamic agents now honor penalty from astdb. Previously the
  QueueAdd() call for a dynamic agent was done with penalty=0 regardless of the
  value stored in the astdb. Fixes Elastix bug #2362.
  SVN Rev[7161]

* Thu Oct  1 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: use Location instead of Name in msg_QueueMember. When
  enumerating queue members, Name and Location are identical if QueueAdd() did
  not specify a friendly name, but are now different as of SVN commit #7137.
  This breaks detection of queue membership changes for dynamic agents until
  fixed here.
  SVN Rev[7160]

* Fri Sep 25 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): implement timeout for mixmonitormute. New ECCP events
  recordingmute and recordingunmute were created.
  SVN Rev[7158]

* Thu Sep 24 2015 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-13
- Bump version for release
- FIXED: Campaign Monitoring: removed dead code that references the old
  dialer prediction class that no longer exists.
  SVN Rev[7153]
- CHANGED: Dialer (ECCP): implement initial version of mixmonitormute and
  mixmonitorunmute ECCP requests.
  SVN Rev[7151]

* Wed Sep 23 2015 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-12
- Bump version for release
- CHANGED: Agent Console: implemented a standard API for adding custom panels
  to the active agent console.
  SVN Rev[7149]

* Thu Sep 17 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: remove restriction of SIP/IAX2 as extensions to use
  for static agents. Ported from CallCenterPRO.
  SVN Rev[7143]
- CHANGED: Dialer (ECCP): remove restriction of SIP/IAX2 as extensions to use
  for login. Ported from CallCenterPRO.
  SVN Rev[7142]

* Mon Sep 14 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: lookup by uniqueid too when assiging recording filename.
  Ported from CallCenterPRO.
  SVN Rev[7141]
- FIXED: Agent Console: remove unnecessary HTML escaping that breaks URLs on
  contact information.
  SVN Rev[7140]
- CHANGED: Dialer: warn if queue snapshot for prediction could be stale.
  SVN Rev[7139]

* Sun Sep 13 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: dispatch JSON methods using function_exists instead of
  switch block. Preparation for console plugins.
  SVN Rev[7138]

* Fri Sep 11 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: use dynamic agent name as MemberName in queue.
  SVN Rev[7137]
- CHANGED: Dialer: complete rewrite of prediction class. This rewrite
  reimplements the queue analysis using AMI enumeration events instead of
  parsing the output of "queue show" and "core show channels". This rewrite
  deliberately omits an equivalent of "agent show" in order to abstract itself
  from the Agent/XXXX implementation detail. Additionally this rewrite lifts a
  restriction that prevented the querying of the talk time on a non-Agent member
  by filtering the CoreShowChannel events by AppQueue. The restriction on
  SIP/IAX2 for dynamic agents is also lifted. Finally, this rewrite allows
  setting a MemberName for a dynamic queue member without breaking the parsing.
  SVN Rev[7136]
- CHANGED: Dialer: use strtolower for remove_event_handler as done in
  add_event_handler.
  SVN Rev[7135]
- CHANGED: Dialer: add new optional parameter queue for QueueStatus command.
  SVN Rev[7134]
- CHANGED: Dialer: add new AMI command CoreShowChannels. Add optional parameter
  membername for QueueAdd command.
  SVN Rev[7133]

* Thu Sep 10 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): use Agent/NNNN as hangup channel if required for
  static agents. Ported from CallCenterPRO.
  SVN Rev[7132]
- CHANGED: Dialer: unlink scheduled call from scheduled agent on failure. Ported
  from CallCenterPro.
  SVN Rev[7131]
- CHANGED: Dialer (ECCP): report on scheduled call. Ported from CallCenterPRO.
  SVN Rev[7130]
- CHANGED: Dialer (ECCP): implement hangup of call not yet connected to agent.
  Ported from CallCenterPRO.
  SVN Rev[7129]

* Wed Sep  9 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: do not overwrite Failure state with Placing state. This could
  happen if a call is originated but immediately fails before CampaignProcess
  sends the signal that call is expected to be Placing. Ported from
  CallCenterPRO.
  SVN Rev[7128]
- FIXED: Dialer: prevent ID collision when removing incoming/outgoing call.
  Ported from CallCenterPRO.
  SVN Rev[7127]
- FIXED: Dialer: do not use id_llamada_* for calls other than incoming/outgoing.
  Ported from CallCenterPRO.
  SVN Rev[7126]
- CHANGED: Dialer (ECCP): factorize names of form tables by calltype into a new
  method. Ported from CallCenterPRO.
  SVN Rev[7125]
- CHANGED: Dialer (ECCP): factorize reading of call attributes as a new method.
  Ported from CallCenterPRO.
  SVN Rev[7124]
- FIXED: Graphic Calls: recent Elastix framework no longer embeds jpgraph. This
  breaks the graphic_calls module unless fixed by requiring the Elastix graphic
  library that already knows about the change.
  SVN Rev[7123]

* Wed Aug 26 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: make addition and removal of External URL tab
  independent of its position in the tab control.
  SVN Rev[7122]

* Tue Aug 25 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Campaign In, Campaign Out: increase timeout to 3600s for CSV download.
  SVN Rev[7121]

* Mon Aug 24 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Agent Console: fixed incorrect jQuery selector that resulted in an
  External URL tab being inserted in the Forms tablist.
  SVN Rev[7120]

* Wed Aug 19 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: fixed incorrect use of Channel instead of Uniqueid on lookup by
  auxchannel. This fixes failure to pick up the recording filename when
  recording by outbound route.
  SVN Rev[7119]
* Mon Aug 17 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Calls Details: fix special case where file appears in monitor format
  as .wav49 but is written to directory as .WAV. Ported from CallCenterPRO.
  SVN Rev[7118]

* Tue Jul  7 2015 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-11
- Bump version for release
- FIXED: Agent Console: fix tab add/remove for jQueryUI-1.11.4
  SVN Rev[7114]
- FIXED: Dialer: fix copy-paste error from synchronization with ccPRO that
  causes SQL errors on call progress events.
  SVN Rev[7113]

* Fri Jul  3 2015 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-10
- Bump version for release.
- CHANGED: add migration of call_recording table to InnoDB engine.
  SVN Rev[7111]

* Thu Jun 25 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: stop trying to decide when to increment retry count in
  Llamada.class.php. Instead, just increment it unconditionally on every
  Originate. This rule is simpler to follow and more robust. This fixes reports
  of double increment reported by some users.
  SVN Rev[7099]

* Sat Jun 13 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: add styles to resize handles to make them visible.
  SVN Rev[7095]
- CHANGED: Agent Console: reorganize layout to group all information on the call
  in a single screen. Resizable panes implemented using jquery-layout from
  http://layout.jquery-dev.com/index.cfm . Partial synchronization with
  CallCenterPRO.
  SVN Rev[7094]

* Mon Jun  8 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Incoming Calls Monitoring: display additional column for count of
  incoming calls assigned to agents but not finished. Fixes Elastix bug #2268.
  SVN Rev[7090]

* Thu Jun  4 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: make agent channel discrimination more robust. As a side effect
  this removes hardcoding of Agent/SIP/IAX2 channel types.
  SVN Rev[7087]
- FIXED: Dialer: forward Bridge with Bridgestate=Unlink to the Unlink handler.
  SVN Rev[7086]
- CHANGED: Dialer: do not handle Dial events that signal the end of Dial app.
  SVN Rev[7085]
- CHANGED: Dialer: do not hardcode from-internal in new channel recognition.
  SVN Rev[7084]
- CHANGED: Dialer: reject channel in OriginateResponse if it happens to be the
  same as the scheduled agent. Then, assign remote channel in Link if it happens
  to be NULL at that time. Partial synchronization with CallCenterPRO.
  SVN Rev[7083]
- CHANGED: Dialer: use campaign type to build message field names before
  notification. Partial synchronization with CallCenterPRO.
  SVN Rev[7082]
- CHANGED: Dialer: move call progress notification for new channel into class.
  With this, all call progress notifications are now contained inside class.
  SVN Rev[7081]
- CHANGED: Dialer (ECCP): use campaign type to build column and table names.
  Partial synchronization with CallCenterPRO.
  SVN Rev[7080]
- FIXED: Dialer (ECCP): do not blindly append Agent type to the actual agent
  number. Use the real agent type from the table instead.
  SVN Rev[7079]

* Thu May 28 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): collapse two near-identical SQL queries into a single
  function called by both clients.
  SVN Rev[7076]

* Wed May 27 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: make ECCP request handling more robust in the event of an
  unhandled worker process crash or exception while handling a request. This
  prevents the ECCP client from waiting forever for a response.
  SVN Rev[7075]

* Tue May 26 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: track IDs of calls that were untracked before knowing the
  assigned ID on the current_calls table. This prevents most messages about not
  being able to find a call on the msg_idcurrentcall message handler.
  SVN Rev[7074]
- FIXED: Dialer (ECCP): fix leftovers from the prefork migration that cause
  exceptions when events are received by ECCPProxyConn.
  SVN Rev[7073]

* Mon May 25 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): put execution of QueueAdd/QueueRemove back in
  ECCPConn. After the migration to the prefork model, it is not longer necessary
  to worry about blocking other requests while executing QueueAdd or
  QueueRemove. So bring back the queue manipulation for dynamic login into
  ECCPConn. CampaignProcess no longer has to deal with blocks while logging in
  agents, and simultaneous logins may now happen in parallel.
  SVN Rev[7072]

* Sun May 24 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): switch to a prefork process model for ECCP requests.
  The ECCPConn object is now hosted in a separate process, of which there are at
  least two, and more will be spawned if all previous processes are blocked on
  requests. This eliminates blocking delays caused by serialization of ECCP
  requests on a single process. The serialization of database access due to ECCP
  events remains to be fixed.
  SVN Rev[7070]
- CHANGED: Dialer (ECCP): introduce new class ECCPProxyConn. This class contains
  the portion of connection information that will be shared in a single process,
  and contains the packet parsing logic, moved from ECCPConn. This separation is
  required for hosting ECCPConn in a separate process.
  SVN Rev[7068]

* Sat May 23 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): move all of the methods provided by ECCPProcess class
  for use of ECCPConn class into a library, included by both classes. This cuts
  loose the dependency between the two classes in order to run them on different
  processes.
  SVN Rev[7067]
- CHANGED: Dialer (ECCP): events provided by ECCPProcess that fire events
  internally are now reworked to return required events instead, in order to
  return them as part of a response.
  SVN Rev[7066]
- CHANGED: Dialer (ECCP): add support for specifying events to fire as part of
  a ECCP response. Test it by firing the pausestart events through it. This is
  required to allow the worker process to specify these events to fire.
  SVN Rev[7065]
- CHANGED: Dialer (ECCP): separate activities to perform with the ECCP results
  into a separate method. This method will end up in a future class,
  ECCPProxyConn.
  SVN Rev[7064]
- CHANGED: Dialer: introduce support for message inspectos in HubServer. A
  message inspector, once registered, can read and modify messages before being
  routed to their destination, including changing the destination. This support
  is required for handling a message to a destination class with no specific
  destination, such as ECCP worker processes.
  SVN Rev[7063]
- CHANGED: Dialer: unify handling of static and dynamic processes. Static
  processes are now read from a separate list, instead of having their slots
  filled with NULL. In this way, all tasks are either unset or set with the
  process PID. Two methods for handling and spawning dynamic processes are
  introduced for future commits.
  SVN Rev[7062]
- CHANGED: Dialer: allow spawning of processes from a classname that differs
  from the actual name of the process. This is required to spawn multiple
  uniquely-named processes from the same worker process class.
  SVN Rev[7061]
- CHANGED: Dialer: internal messages previously sent from ECCPProcess to
  AMIEventProcess are now accepted from anyone. This is required to accept
  multiple message sources from the various ECCP workers.
  SVN Rev[7060]

* Fri May 22 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): transform the handling of ECCP requests into a form
  that can easily be translated into a message delegation to worker processes.
  SVN Rev[7059]
- CHANGED: Dialer: instead of always processing one AMI event at a time, events
  are now consumed until one is found whose handler exists and does not return
  the special string "AMI_EVENT_DISCARD". The msg_Default and msg_VarSet handlers
  make use of this string.
  SVN Rev[7058]

* Tue May 19 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: add AMI commands for MixMonitor control and SIP NOTIFY.
  SVN Rev[7057]
- FIXED: Dialer (ECCP): fix getcampaignstatus request bug that caused a failure
  to report the pending call count when not specifying a start date.
  SVN Rev[7056]

* Mon May 18 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): factor out common elements in incoming/outgoing
  campaign information into a single method.
  SVN Rev[7054]
- CHANGED: After testing, it was shown that the elastix-framework version from
  a recently-installed Elastix 2.4 is quite sufficient to run the CallCenter
  modules. The minimum elastix-framework version can be lowered so users are not
  forced to upgrade.
  SVN Rev[7053]

* Sun May 17 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: fix long-standing bug in which failure cause of failed calls
  is available but fired *after* the call object is removed from tracking by
  the OriginateResponse or Hangup handlers.
  SVN Rev[7052]

* Thu May 14 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: for static agents, queuemembership event must be fired with
  list of actual membership, since there is no dynamic queuelist. Also fix typo.
  SVN Rev[7050]

* Wed May 13 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Monitoring: make use of the new queuemembership event.
  SVN Rev[7049]

* Tue May 12 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): new event queuemembership has been created. This event
  is fired whenever a monitored agent is added or removed from campaign queues
  (not exactly the same as Asterisk queues). This is required for proper update
  of the Campaign Monitoring report when performing queue membership.
  modifications.
  SVN Rev[7048]

* Mon May 11 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: collapse notification method pattern into a single __call.
  SVN Rev[7047]
- DELETED: Agent Console, Campaign Monitoring: Elastix 2.4.0 already ships with
  jQuery 1.8.3 and jQueryUI 1.8.24. Therefore, the private copies are now
  obsolete.
  SVN Rev[7045], SVN Rev[7046]

* Fri May  8 2015 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-9
- Bump version for release.
- CHANGED: Calls Detail: add field filter controls. Partially synchronize with
  CallCenterPRO.
  SVN Rev[7043]

* Thu May  7 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: make dynamic agent login conditions more strict. Check whether
  agent already belongs to all dynamic queues, and transition directly to
  logged-in state if so. This prevents agents waiting for QueueMemberAdded
  events that will never come. Check for stuck login agents and fail them after
  5 minutes. Ignore queue membership additions for queues when not processing a
  loginagent request. Ignore queue membership additions for queues outside the
  set of dynamic queues.
  SVN Rev[7042]

* Tue May  5 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: ignore a queue membership start when another queue membership
  update is already in progress.
  SVN Rev[7041]
- FIXED: Agents Monitoring, Incoming Calls Monitoring: in case of an EventSource
  error, reload after a timeout, not immediately. This prevents race conditions
  that prevent the browser from leaving the module.
  SVN Rev[7040]

* Mon May  4 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Form Preview: synchronize partially with CallCenterPRO. Fix invalid
  HTML syntax.
  SVN Rev[7038]
- CHANGED: Calls Detail: implement access to the recording file associated with
  the call. For now, no basename transformation is performed on the file.
  SVN Rev[7036]

* Fri May  1 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: downgrade QueueStatus start/end messages to DEBUG.
  SVN Rev[7034]
- FIXED: Dialer: fix bug in incoming queue cleanup that causes an undefined
  index access in the queue array in CampaignProcess.
  SVN Rev[7033]

* Thu Apr 30 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: sometimes a failed call will report a Hangup on its channel
  before AMIEventProcess manages to receive the origination start message. This
  causes annoying messages about an originated call not found. Fix this by
  setting a flag on the call object in this case, and keeping it around until
  both the origination message AND OriginateResponse are received.
  SVN Rev[7032]
- CHANGED: Dialer: AMIEventProcess is now Single Source of Truth for queue
  membership. On customer tests, it was observed that the blocking query to
  astdb takes longer and longer with increasing asterisk load. Since the event
  handling process already contains queue membership information for each agent,
  it is now possible to query information from it without querying astdb. This
  speeds up handling of agentlogin/agentlogout. Additionally QueueAdd/QueueRemove
  are now delegated to CampaignProcess since they take much longer under heavy
  load.
  SVN Rev[7031]

* Wed Apr 29 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: fix for undocumented fwrite() behavior. According to
  http://php.net/manual/en/function.fwrite.php#96951 fwrite() returns 0, not
  FALSE, when writing to a blocking socket and encountering an error that
  prevents any data from being written. This behavior is not documented anywhere
  in the PHP manual and causes infinite loops unless taken into account.
  SVN Rev[7030]

* Tue Apr 28 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: remove astdb query in the middle of asterisk AMI event
  processing when handling PeerStatus. Instead use the queue membership cached
  in Agent objects and delegate the actual forced logoff to CampaignProcess.
  SVN Rev[7029]
- CHANGED: Dialer: clear agent list use by QueueStatus after enumeration. Check
  that expected events arising from QueueStatus enumeration have an ActionID
  and that our own value is non-null before processing.
  SVN Rev[7028]

* Mon Apr 27 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Monitoring, Agents Monitoring, Incoming Calls Monitoring:
  close (and reload if required) EventSource when getting an error. This should
  prevent retries with obsolete state hash.
  SVN Rev[7027]

* Fri Apr 24 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Monitoring, Agents Monitoring, Incoming Calls Monitoring:
  raise SSE retry interval to 5000 ms.
  SVN Rev[7025]

* Thu Apr 23 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: add tracking of recording files associated with incoming
  calls, by asking for the value of MIXMONITOR_FILENAME on the channel just
  after inserting the incoming call record.
  SVN Rev[7024]
- CHANGED: Campaign Out, Campaign In: move edit link into the campaign name as
  done in CallCenterPRO.
  SVN Rev[7023]
- CHANGED: Dialer: add tracking of recording files associated with outgoing
  calls, by monitoring assignment to MIXMONITOR_FILENAME on tracked channels.
  SVN Rev[7022]

* Tue Apr 21 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: on OriginateRespose, only evaluate CallerID when CallerIDNum
  is empty. This cuts down on unnecessary calls to AMI GetVar.
  SVN Rev[7018]

* Sat Apr 18 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: fix incorrect variable verification after GetVar call.
  SVN Rev[7011]

* Wed Apr 15 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Report Break: remove unnecessary <form> tag around grid filter which
  leads to nested <form> tag.
  SVN Rev[7006]

* Wed Apr  8 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: Originate through Local@from-internal requires setting
  CallerID in order to guarantee a nonempty src field in the CDR record. This
  is the root cause behind various reports of empty src on dialer calls.
  SVN Rev[6965]

* Mon Apr  6 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: do not attempt to fetch a caller-id from the channel of a
  failed call in OriginateRespose. Also compare against possible value of
  CallerIDNum and report any differences.
  SVN Rev[6964]
- FIXED: Dialer: remove use of print_r which risks memory exhaustion by
  recursive dumping of linked objects. Use dump function instead.
  SVN Rev[6962]

* Wed Apr  1 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: perform consistency checks to diagnose when a logged-out
  agent gets stuck with a call.
  SVN Rev[6946]
- CHANGED: Agent Console: fix second use of jscalendar icon. Also enable month
  and year selection on date field.
  SVN Rev[6945]

* Mon Mar 30 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: use framework calendar icon instead of jscalendar
  icon which might be removed soon.
  SVN Rev[6939]

* Sun Mar 29 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console - jQuery-1.11.2 migration: fix incorrect use of
  attribute instead of property.
  SVN Rev[6929]

* Wed Mar 25 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Out: change default value of max channels to 0, in order to
  allow unlimited calls by default as allowed by SVN commit #6916.
  SVN Rev[6917]
- CHANGED: Dialer: accept max channels value of 0 as equivalent to NULL, which
  in turn is equivalent to unlimited simultaneous calls per campaign.
  SVN Rev[6916]
- CHANGED: Queues: implement pagination. Use more of the PaloSantoGrid API and
  synchronize partially with Call Center PRO.
  SVN Rev[6915]

* Tue Mar 24 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: implement a periodic ping to the server in an attempt
  to keep the PHP session from expiring. The chosen interval is half the value
  reported by session.gc_maxlifetime.
  SVN Rev[6914]
- CHANGED: Dialer (ECCP): create new request refreshagents. This request causes
  CampaignProcess to reload and send the updated agent status to AMIEventProcess
  for it to add or remove queue members as required.
  SVN Rev[6913]
- CHANGED: Dialer: count number of AMI events received, classified by event, and
  dump this count in the dumpstatus handler. This count can help optimize AMI
  event filters in order to improve performance.
  SVN Rev[6912]

* Mon Mar 23 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: add tracking of queue membership by agent. For dynamic agents
  this allows detection of changes in queue membership as saved by FreePBX in
  order to update the in-memory membership in asterisk. This in turn enables
  moving of live dynamic agents between queues once the Reload event is hooked.
  SVN Rev[6911]
- CHANGED: Dialer (ECCP): create new request dumpstatus. This request causes
  AMIEventProcess to dump its internal status to the log file. This request is
  intended for debugging.
  SVN Rev[6909]

* Wed Feb  4 2015 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-8
- Bump version for release.

* Fri Jan 30 2015 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer (ECCP): implement optional filtering in the database_show AMI
  wrapper, and use it to cut down on the amount of data to parse for collecting
  queues for each agent.
  SVN Rev[6830]

* Wed Dec 31 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: any active campaign (incoming or outgoing) with end date past
  current date will now get marked as inactive.
  SVN Rev[6818]

* Tue Dec 30 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): scheduled calls now inherit any form values entered
  from the original call.
  SVN Rev[6817]

* Wed Dec 17 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Ingoing Calls Success: complete rewrite. Database queries were
  rewritten to make use of joins and foreign keys, parametrized to remove SQL
  vulnerabilities, and module logic was simplified. Updated to latest grid
  API.
  SVN Rev[6814]
- FIXED: Dialer (ECCP): emit queue number in scenario of delayed AgentLinked
  event on database async write.
  SVN Rev[6813]

* Fri Dec 12 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Queues: update paloSantoGrid API usage to latest Elastix 2.4
  SVN Rev[6809]
- CHANGED: ECCP Users: update paloSantoGrid API usage to latest Elastix 2.4
  SVN Rev[6807]
- CHANGED: External URL: update paloSantoGrid API usage to latest Elastix 2.4
  SVN Rev[6806]

* Tue Dec  8 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Form Designer: switch incorrect use of list control on button strip
  with proper dropdown filter. Add filter control.
  SVN Rev[6803]

* Thu Nov 20 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Incoming Calls Monitoring: explictly require paloSantoGrid class which
  has been previously included via spl_elastix_class_autoload and therefore
  fails to load in elastix-framework earlier than 2.4.0-7.
- CHANGED: Update minimum elastix-framework to 2.4.0-7 or later.
  SVN Rev[6776]

* Thu Nov  6 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Form Designer: add placeholder content for new field name.
  SVN Rev[6767]
- CHANGED: Form Designer: update cursor styles on draggable rows to indicate
  draggable capability.
  SVN Rev[6766]

* Wed Oct  1 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Out: update paloSantoGrid API usage to latest Elastix 2.4
  SVN Rev[6753]
- CHANGED: Campaign In: update paloSantoGrid API usage to latest Elastix 2.4
  SVN Rev[6752]

* Tue Sep 30 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: CallCenter Config: reorganize form fields.
  SVN Rev[6751]
- CHANGED: Break Administrator: update paloSantoGrid API usage to latest
  Elastix 2.4. Fix potential undefined variable reference on validation error.
  SVN Rev[6750]

* Wed Sep 24 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Callback Extensions: update paloSantoGrid API usage to latest
  Elastix 2.4.
  SVN Rev[6749]
- CHANGED: Agents: update paloSantoGrid API usage to latest Elastix 2.4.
  SVN Rev[6748]

* Fri Sep 19 2014 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-7
- Bump version for release.

* Thu Sep 18 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): create new request getmultipleagentstatus. This
  request allows for a report of agent status for a group of agents at once.
  This cuts down again on the number of ECCP requests required for the Agent
  Monitoring report. Part of the fix for Elastix bug #1820.
  SVN Rev[6739]
- CHANGED: Dialer (ECCP): create new request getmultipleagentqueues. This
  request allows for a report of agents subscribed on queues for a group of
  agents at once. This cuts down on the number of ECCP requests required for
  the Agent Monitoring report. Part of the fix for Elastix bug #1820.
  SVN Rev[6737]

* Wed Sep 17 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Do Not Call List: complete rewrite. This rewrite uses ordinary grid
  pagination instead of loading the entire recordset in memory, and simplifies
  the support libraries. Additionally a new index is added to the dont_call
  table to speed up lookup by caller_id. Finally, a small utility is now
  provided to load a CSV file to the DNC list from the command line. Fixes
  Elastix bug #1984.
  SVN Rev[6734]
- CHANGED: Form Designer: tweak error message handling to integrate it better
  into current Elastix theme.
  SVN Rev[6733]
- FIXED: Form Designer: only field removal should be blocked if a form is used
  by a campaign. Other operations must be allowed.
  SVN Rev[6732]

* Tue Sep 16 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Form Designer: clean up grid used for form field manipulation.
  SVN Rev[6728]
- FIXED: Form Designer: re-add method that is used by both Outgoing Campaign and
  Incoming Campaign modules, as a compatibility stub.
  SVN Rev[6727]

* Mon Sep 15 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Form Designer: complete rewrite. This rewrite removes the use of
  xajax, fixes a few potential SQL injection scenarios, streamlines the form
  creation interface, and updates the report grid to the latest support.
  SVN Rev[6726]

* Fri Sep 12 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): add debugging information to getagentstatus request to
  catch inconsistent state being returned to the client.
  SVN Rev[6720]

* Wed Sep 10 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dont Call List: remove unused xajax reference
  SVN Rev[6718]
- CHANGED: Incoming Calls Monitoring: complete rewrite. This rewrite removes the
  use of xajax and periodic database reads, and replaces it with an ECCP client
  that uses Server Sent Events if available, just like the Agent Console. This
  also fixes a serious scenario where a query that takes more than 5 seconds
  would cause the server to accumulate unfinished SQL queries.
  SVN Rev[6717]
- CHANGED: Dialer (ECCP): emit queue number (if available) when linking and
  unlinking a call, to save the client the trouble of asking for it. Required
  for next commit.
  SVN Rev[6716]
- FIXED: Dialer (ECCP): fix copy/paste error in previous commit to SQL files.
  Add additional indexes for calls.
  SVN Rev[6715]

* Tue Sep 09 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Calls per Agent: replace ambiguous filtering system with a cleaner
  filter that properly validates that agent and queue are both numeric. Also
  fix Elastix bug #1976.
  SVN Rev[6714]

* Mon Sep 08 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer (ECCP): optimize the getagentactivitysummary request by inserting
  keys and restructuring queries to only run once per request, instead of once
  per agent.
  SVN Rev[6710]

* Thu Jun 12 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Monitoring: update Ember.js to 1.5.1, Handlebars to 1.3.0.
  SVN Rev[6648]

* Fri Feb 21 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: disable PDO::ATTR_EMULATE_PREPARES on all MySQL PDO connections
  in order to use native prepared statements instead of the default PHP emulation.
  Workaround for PHP bug #44639. Might fix Elastix bug #1844.
  SVN Rev[6489]

* Tue Feb 18 2014 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-6
- Bump version for release.
- CHANGED: Agent Console, Campaign Monitoring, Agent Monitoring: the Elastix
  framework sends an error in a JSON response if a rawmode request is made with
  an invalid/expired session. Check for this response and alert/redirect to
  Elastix login page if received.
  SVN Rev[6483]

* Mon Feb 17 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Hold Time: fix construction of SQL query from previous rewrite. Fixes
  Elastix bug #1858.
  SVN Rev[6480]

* Sat Feb 08 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Monitoring: update Ember.js to 1.3.2

* Thu Jan 30 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Monitoring: update Ember.js to 1.3.1, Handlebars to 1.2.1.
  SVN Rev[6453]

* Tue Jan 21 2014 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Agent List: small rewrite of repair action handling to unbreak previous
  commit. Now takes advantage of jQuery.
  SVN Rev[6397]

* Fri Jan 10 2014 Jose Briones <jbriones@elastix.com>
- CHANGED: Agent Console, Campaigns, Do not Call List, External URLs, Queues,
  Clients, Ingoing Campaigns, Agents, ECCP Users, Callback Extensions, Breaks,
  Form Designer, Form Preview, Reports Break, Calls Detail, Calls per hour,
  Calls per Agent, Hold time, Login Logout, Ingoing Calls Success, Graphic Calls
  per hour, Agent Information, Agents Monitoring, Trunks used per hour,
  Incoming calls monitoring, Campaign monitoring, Configuration: For each module
  listed here the english help file was renamed to en.hlp and a spanish help file
  called es.hlp was ADDED.
  SVN Rev[6354]

* Thu Dec 12 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Agent Console: fix corner case in which an incorrect JSON encoding in
  non-SSE mode of the logged-out event right at the start of status checking
  would lead to an endless loop of browser requests. Fixes Elastix bug #1759.
  SVN Rev[6312]

* Thu Dec 12 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): add new optional parameter datetime_start to requests
  getcampaignstatus and getincomingqueuestatus to allow specifying the start
  date of calls belonging to the object. This is groundwork for rewriting the
  Incoming Calls Monitoring report using ECCP.
  SVN Rev[6278]

* Tue Dec 10 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: CallCenter: Fix multiple modules to define their own DSN instead of
  relying on the definition in Elastix core. Fix Elastix bug #1795.
  SVN Rev[6264]

* Mon Dec 09 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Form Preview: complete rewrite. Try to take advantage of the field
  generation of PaloSantoForm as much as possible. Remove dead xajax references.
  SVN Rev[6263]
- FIXED: Dialer: during an Asterisk restart, the startup time reported by
  CoreStatus may get stuck at year 1969 for a few seconds, and break the
  detection of a restarted Asterisk instance. Fix by rejecting and retrying the
  call until a valid date is returned.
  SVN Rev[6262]

* Tue Nov 05 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-5
- Fix incorrect packaging of Ember.js

* Mon Nov 04 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-4
- Bump version for release
- FIXED: Campaign Monitoring: fix synchronization of current route with drop-down
  list of campaigns.
  SVN Rev[6060]

* Wed Oct 30 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: External URLs: fixed broken pagination in arrow links with more than
  15 items. Fixes Elastix bug #1751.
  SVN Rev[6044]

* Tue Oct 29 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Monitoring: refactor the client-side javascript code to
  conform to best Ember.js coding practices. Instead of explicitly binding to
  controllers as variables in App, controllers are now references from the
  corresponding views and setup through routes. Each campaign detail is now
  implemented as a route.
  Update Ember.js to 1.1.2, Handlebars to 1.0.0 .
  SVN Rev[6043]

* Mon Sep 23 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer (ECCP): allow setting date fields to empty strings. Apparently
  this was allowed by the old implementation of the agent console.
  SVN Rev[6029]

* Fri Sep 20 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Campaign Monitoring: fix swapped labels in display. Fixes Elastix
  bug #1707.
  SVN Rev[5917]

* Wed Sep 18 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Hold Time: complete rewrite. This rewrite reimplements the histogram
  code to be more generic as well as smaller, and to use SQL parameters. It also
  removes dead references to xajax and other unneeded libraries, and brings the
  code up to newer coding standards. The filter template is exported to a
  separate file. Fixes Elastix bug #1726.
  SVN Rev[5901]
- FIXED: Calls Per Agent: remove pagination code as this report is actually a
  summary report. Fixes Elastix bug #1710.
  SVN Rev[5896]
- CHANGED: Campaign Out: remove last traces of xajax dead code from the module.
  SVN Rev[5895]

* Mon Sep 09 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-3
- Bump version for release.
- FIXED: Dialer: an outgoing route with multiple trunks may produce multiple
  Dial events as each trunk is tried and fails in turn. Must collect actual
  channel each time the next trunk is retried. Fixes Elastix bug #1682.
  SVN Rev[5843]
- FIXED: Campaign Monitoring: emit campaign log entry ID on agentlinked and
  agentunlinked events in order to fill required ID of log event. Fixes Elastix
  bug #1681.
  SVN Rev[5842]

* Thu Aug 29 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: initialize variable to NULL in case the regexp does not match
  on msg_Dial. Also relax the regexp a bit. Attempt to fix Elastix bug #1682.
  SVN Rev[5819]

* Mon Aug  5 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Incoming Campaigns: fix missing SQL parameter to UNION query for
  campaign forms. Fixes Elastix bug #1641.
  SVN Rev[5546]

* Wed Jun 26 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-2
- FIXED: Campaign Monitoring: fix design flaw that requested very large datasets
  because of log entries on busy servers. Now log entries are not requested on
  load, but only on demand, and only 100 at a time.

* Mon Jun 24 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): extend campaignlog request to request the last N
  records instead of all of the log entries for a given date range.

* Fri Jun 21 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Campaigns In, Campaigns Out, Agents, Callback Extensions, ECCP Users:
  modernize pagination to fix zeros under elastixneo theme.

* Tue Jun 05 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Callback Extensions: remove access to variable that no longer exists
  from Agents implementation.

* Mon May 13 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer (ECCP): check that target extension for transfer is entirely
  numeric. Fixes Elastix bug #1553.

* Tue Apr 30 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-1
- Bump version for release.

* Thu Apr 24 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Agent Console: filter breaks to remove inactive breaks from list.

* Tue Apr 23 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: tag all incoming calls with no end timestamp as LostTrack on
  startup. This sanity check was lost on the multiple-process rewrite. May fix
  part of Elastix bug #1531.

* Mon Apr 22 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Incoming Campaign: fix assignment to incorrect template variable
  leading to visualization of New Campaign form when there are no incoming
  queues. Fixes Elastix bug #1533.

* Wed Apr 10 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.2.0-0
- Bump version for release.
- CHANGED: Campaign Monitoring, Dialer (ECCP): extend the getcampaignstatus and
  getincomingqueuestatus requests to report statistic values for the queried
  campaign or queue. Use these to implement display of average and maximum call
  duration in Campaign Monitoring realtime display.

* Mon Apr 01 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: allow inactivity timeout to be configurable

* Mon Mar 25 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): add support for inactivity timeout in web agent console.
- CHANGED: Agent Console: add minimal error handling to javascript ajax methods.

* Wed Mar 20 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: display VtigerCRM button only if VtigerCRM is installed
- CHANGED: Dialer: add code to repair corrupted audit entries on dialer startup.
- CHANGED: Agent Console: modify connect function in order to return result of
  login command, and use this to check whether ECCP login succeeded.

* Mon Mar 18 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Calls per Agent: make call type specification case-insensitive

* Thu Mar 07 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: Specify a proper CallerID string as 'Agent/XXXX Login' to
  prevent the softphones from displaying confusing strings as the CallerID.

* Mon Mar 04 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-11.beta
- Bump version for release

* Wed Feb 27 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Monitoring: in case of client state hash mismatch, only
  reload if the request hash is identical to the one stored by the web client.

* Tue Feb 26 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Calls per hour, Graphics Calls per hour: transpose the rows and columns
  of the report so that the report is thinner. Fixes Elastix bug #1494.
- CHANGED: Campaign Monitoring: place incoming queue calls as the last items in
  the drop-down list.
- FIXED: Campaign Monitoring: append available campaigns to report list instead
  of indexing by ID. Fixes Elastix bug #1496.
- FIXED: Dialer: Check whether the agent is on break before deciding that it
  should receive a scheduled call. Fixes Elastix bug #1497.

* Mon Feb 25 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Login Logout: fix English translations for titles. Fixes Elastix bug #1491.

* Fri Feb 15 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-10.beta
- FIXED: Agent Console: apparently a jQuery update now sends null variables in
  javascript structs as empty strings instead of not-set variables in a
  $.post() request. This causes the non-SSE mode of the Agent Console to spin
  endlessly. Fixed. Fixes Elastix bug #1479.

* Wed Jan 23 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Do not Call List: The action "Apply" had no effect if there were no
  pending calls. This scenario could happen if the DNC is loaded prior to
  defining the first outgoing call. Fixed.

* Tue Jan 22 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Calls per Hour, Graphic Calls per Hour: queue dropdown selection has
  never worked. Fixed. Also, synchronize code between the two modules. Fixes
  Elastix bug #1452.

* Mon Jan 21 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Agents, Callback Extensions: multiple fixes. Enforce uniqueness of
  agent number across Agent and Callback agents on agent creation. Verify that
  agent number that is being modified actually exists. Fix removed successful
  return from agent modification. Agent modification screens may not longer
  change the agent number onscreen, as doing so has no effect on the actual
  record.

* Thu Jan 17 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-9.beta
- FIXED: Campaign Monitoring: add cancelling of long polling when switching
  campaigns in the absence of SSE (Internet Explorer). Otherwise multiple
  pollings may accumulate. Additionally, invalidate the polling on server-side
  when the monitored campaign is changed. Loosen up one call status comparison
  that otherwise resulted in unnecessary refreshings in non-SSE mode. Update
  total counter properly when receiving incoming calls. Remove stray debugging
  statement.
- CHANGED: Campaign Monitoring: add support for monitoring of incoming calls
  without an incoming campaign, by selection of raw queue. Add some i18n to
  labels.
- CHANGED: Dialer (ECCP): add support for new requests "getincomingqueuestatus"
  and "getincomingqueuelist" required for monitoring incoming calls with no
  associated campaign.
- FIXED: Dialer (ECCP): add ECCP authentication to several requests that missed
  it.

* Mon Jan 14 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Agent Information: fix some misspellings and awkward phrasings
- FIXED: Remove now obsolete "Agent Connection Time" item from Elastix menu.

* Fri Jan 10 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Campaign Monitoring: fix javascript syntax rejected by IE. Also, work
  around IE issue that collapses JSON members that contain only empty objects or
  arrays into nulls.
- FIXED: Dialer (ECCP): getcampaignstatus: fix callstatus lowercase, use
  camelcase.

* Fri Jan 10 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-8.beta
- Bump version for release
- FIXED: Dialer (ECCP): getcampaignstatus: fix crashing bug when fetching status
  of a campaign with at least one logged-in static agent.
- FIXED: Callback Extensions: fixed multiple bugs in a single function:
  1) removed incorrect attempt to use database root user for ordinary module
  operation. 2) fixed incorrect assumption that selected database user can read
  both the 'call_center' and the 'asterisk' databases. 3) added missing error
  reporting so that database failures are not hidden.

* Thu Jan 10 2013 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-7.beta
- Bump version for release.
- ADDED: Campaign Monitoring: added new module. This module displays a
  campaign-centric view of the callcenter activity. This module displays basic
  information on the campaign, and counters of call states. Additionally there
  is a panel of agents that handle calls, with current state and the phone
  number that is being handled. Another panel shows in-progress calls that are
  being placed and do not yet have an assigned agent. A log view displays all of
  the call-related activities with timestamps. All of this information is
  updated in realtime using ECCP events.

* Wed Jan 09 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: add support for new request "callprogress".
- CHANGED: Agent Console: add support for the three new fields in agentunlinked
  event.
- CHANGED: Dialer (ECCP): add debug tracking to msg_QueueMemberRemoved.
- CHANGED: Dialer (ECCP): getcampaignstatus: callstatus is now camelcased
  (internal representation) instead of lowercase. Fix documentation to reflect
  this.
- CHANGED: Dialer (ECCP): agentunlinked event was modified with three new
  fields. Fix documentation to reflect this.

* Tue Jan 08 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- ADDED: Dialer (ECCP): add and document new request 'campaignlog'. This request
  allows to retrieve the event log for a particular campaign.
- CHANGED: Dialer (ECCP): emit queue on callprogress event.
- FIXED: Dialer (ECCP): getcampaignstatus: propagate trunk for each call.
  Display all agents, not just the ones handling a call.

* Mon Jan 07 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer (ECCP): fix validation that prevented incoming calls from
  entering OnQueue state.
- FIXED: Dialer (ECCP): fix forgotten specification of campaign type on
  implementation of getcampaignstatus request.
- CHANGED: Dialer (ECCP): modify many of the ECCP example scripts to get agent and
  password from the command line.
- FIXED: Dialer (ECCP): fix error message for hangup request when agent exists but is
  not handling a call.
- FIXED: Dialer (ECCP): add required campaign ID when starting hold.

* Sat Jan 05 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Agent Console: fix behavior when no callback extensions exist.
- FIXED: Agent Console: fix two javascript warnings in IE6.

* Wed Jan 02 2013 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Agents, Callback Extensions: do not modify the audit or call tables
  from the module. Now that the dialer has exclusive responsibility over
  auditing on logoff, modification of audit tables on forced disconnection, as
  allowed by these modules, is actually harmful.

* Fri Dec 28 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- ADDED: Dialer (ECCP): merge in-progress support for CallbackLogin-style agents.
  Currently the supported agents are SIP and IAX2 extensions. The agents must be
  declared at the Callback Extensions module under Call Center-->Agent Options,
  and also added as Dynamic Members of the queues to use for the campaigns, as
  S4321 (for extension SIP/4321) or I4321 (for extension IAX2/4321). The
  extensions will be added with QueueAdd at login and will only ring if a call
  enters the corresponding queue.

* Tue Dec 18 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- ADDED: Dialer (ECCP): add and document call progress events. Rework call
  progress logging so that the campaign ID is available. Add a new request
  'callprogress' to enable call progress events, which are disabled by default.

* Fri Dec 14 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Campaign Out: fix incorrect declaration of string constants for rexexp.
- ADDED: Dialer: add logging capability of events that happen to a call. For
  this a new database table was created. Allow campaign deletion to cope with
  the new table. This is required functionality for call progress events.

* Mon Dec 10 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- ADDED: Dialer: add new column calls.trunk to keep track of trunk used for
  outgoing call. Add outgoing trunk support to dialer code. This is required for
  planned functionality of trunk display of in-progress calls.

* Thu Dec 06 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: use the new getchanvars request to add the list of
  channel variables to the ones available in an external URL. This allows
  variables set via custom contexts or AGIs to be used to drive external URLs.

* Wed Dec 05 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- ADDED: Dialer (ECCP): add a new request 'getchanvars'. This request lists the
  channel variables of the call currently handled by the agent.

* Mon Dec 03 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: expose the Uniqueid of the linked call as an
  additional variable for External URL.

* Thu Nov 22 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Login Logout: fix issue of report export displaying only a single page.
- CHANGED: Login Logout: add consistency checks on audit records to counter the
  situation where an abnormal termination of the dialer leaves unclosed audit
  records. Inconsistent records are now displayed as CORRUPTED instead of adding
  invalid values as if they were valid ONLINE records.

* Wed Nov 21 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer: fix inability to record scheduled calls. Now the dialer will
  use a special context to enable recording if the campaign queue recorded
  calls.

* Wed Nov 14 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: set CALLERID(num) in OriginateResponse handler in order to
  ensure the CDR has a meaningful source field value. Fixes Elastix bug #1411.

* Tue Nov 13 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Trunks Used per Hour: core query method rewritten to use SQL
  parameters. SQL was reimplemented to use more efficient grouping. HTML
  formatting moved to index.php. Trunk filter now allows report on calls from
  all trunks.

* Thu Nov  8 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- DELETED: Agents Connection Time: removed. This report is now essentially
  identical to Login Logout.
- CHANGED: Login Logout: complete rewrite. This rewrite takes advantage of the
  updated Elastix API for paloSantoGrid available since elastix-framework
  2.2-18. The custom code for calendars was replaced with standard date
  controls. Most importantly, the SQL query was considerably simplified for
  readability, and in the process, fixes the calculation of total session time.
  Finally, the filtering by incoming queue from Agents Connection Time was added
  to this module. Part of fix for Elastix bug #1409.

* Thu Nov  1 2012 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-6.alpha
- Bump version for release.

* Wed Oct 31 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Out: reactivate a finished campaign if calls are added to it
- CHANGED: Campaign Out: Cleanup of outgoing campaign library:
  Replace all uses of ereg with preg_match
  Removed some dead code
  Replace uses of DBCAMPO with proper SQL parameters
  Refactor check of queue by incoming campaigns
  Remove code that reveals SQL query in several error paths
  Rewrite loading of phone numbers in order to greatly reduce memory usage

* Tue Oct 30 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Campaign Out: Campaign modification now allows upload of call file,
  which will be appended to the call list already present.
- CHANGED: Campaign In,Campaign Out: lift restriction on removing campaigns with
  connected calls. Users really do need to remove campaigns even after calls
  have been made.
- FIXED: Agent Console: fix scenario in which an agent that opens several
  instances of the agent console under the same session will find that the pause
  duration increases by the pause multiplied by the number of consoles.

* Wed Oct 24 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Switch ownership of all files to root, except for the directory
  /opt/elastix/dialer where logs are written.
- FIXED: Dialer: add a method to detect that the Asterisk server process has
  been restarted. This fixes the scenario where Asterisk crashes/restarts and
  the dialer is stuck with stale agent/call status until restarted.

* Tue Oct 23 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Campaign Out: fix broken campaign edit due to character encoding
  verification. Fixes Elastix bug #1403.

* Fri Oct 19 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer (ECCP): intercept PeerStatus message to detect whether an
  extension currently in use for an agent login has been unregistered.

* Tue Oct 16 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Dialer (ECCP): restore writing to database of transferred extension,
  lost when rewriting the Agent Console to use the ECCP protocol. Fixes Elastix
  bug #1396.

* Mon Oct 15 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Reports: fix incorrect translations in reports

* Thu Oct 04 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: fix breakage due to introduction of 'goto' as reserved word
  in PHP 5.4.

* Wed Oct 03 2012 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-5.alpha
- CHANGED: Agent Console: fix bug in which a chronometer counter that starts
  from 0, as signaled in an event might be mistakenly interpreted as an order to
  stop the chronometer. Might fix Elastix bug #1319. Also, set consistent
  behavior of stopping chronometer on idle agent case.
- CHANGED: Agent Console: if Elastix user matches an agent number, suggest this
  agent number as the default agent to use for initial agent login. Second part
  of fix for Elastix bug #1354.
- CHANGED: Agents Monitoring: replace sending of full client state with hash of
  said state. This prevents a potential issue of hitting a maximum URL length
  limit due to unbounded size of agent list.
- CHANGED: Calls Per Agent: clean up implementation to use SQL query parameters
  and simplify the API. Fix average calculation. Fixes Elastix bug #1371.

* Tue Oct 02 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agents Monitoring: improve error handling when dialer process is shut
  down in the middle of monitoring.
- CHANGED: Installer: change form_field.etiqueta and form_field.value fields to
  TEXT. Fixes Elastix bug #1250.
- CHANGED: Dialer: put workaround for PHP bug #18556 which breaks dialerd with
  Turkish locales. Fixes Elastix bug #1381.
- CHANGED: Campaign Out: implement manual choosing of character set encoding for
  CSV file upload.

* Thu Sep 13 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: fix incorrect field access syntax on trunk error output.

* Tue Aug 28 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: if Elastix user has a default extension, suggest this
  extension as the extension to use for initial agent login. Possible fix for
  Elastix bug #1354.
- CHANGED: Agents Monitoring: make use of "getagentactivitysummary" request to
  rewrite the implementation of the real-time status report. The report now uses
  long polling and Server Sent Events in a way similar to the Agent Console.
  This rewrite is expected to significantly reduce the server load over the old
  strategy of running the complete report algorithm every four seconds.
- CHANGED: Agent Console: extend library to add support for the
  "getagentactivitysummary" request.
- CHANGED: Dialer (ECCP): new request "getagentactivitysummary" that produces a
  summary of agent activity on a date range.
- CHANGED: Dialer (ECCP): extend "getagentstatus" request to report the queue
  number that assigned the call currently connected to the agent.

* Fri Jul 20 2012 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-4.alpha
- FIXED: Agent Console: fix incorrect check for schedule_use_daterange and
  schedule_use_sameagent flags in call scheduling.

* Thu Jul 12 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Agent Console: fix incorrect check for atxfer parameter in call
  transfer. This should allow attended transfer to work from the web console.

* Thu Jun 07 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: remove last call to deprecated ereg().

* Wed Jun 06 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Agent Console: add onsubmit handling to login form so that pressing
  ENTER also starts login process.

* Mon Jun 04 2012 Alex Villacis Lasso <a_villacis@palosanto.com>
- CHANGED: Dialer: remove E_STRICT from error_reporting to silence warning
  messages at daemon startup in Fedora 17.

* Tue Apr 24 2012 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-3.alpha
- Comment out menu item for not-yet-committed module in development.
- From CHANGELOG:
    - Dialer: set the timezone explictly for PHP 5.3+ compatibility.
    - Dialer: parse manager.conf manually as it may contain characters that
      choke parse_ini_file(). Fixes Elastix bug #1211.
    - Dialer: add new configuration parameter to specify the maximum number of
      seconds to wait for the Originate AMI request before timeout.

* Wed Mar 21 2012 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-2.alpha
- Third pre-release version for testing of new dialer code.
- From CHANGELOG:
    - Installer: fix invalid SQL syntax on foreign key creation for external URL
    - Reports Break: some cleanup on language loading
    - External URL: (trivial) remove useless assignment
    - Dialer: signaling termination to the process group could result in
      subprocesses terminating before receiving shutdown requests, which in turn
      caused HubProcess to hang waiting for an answer that will never come.
      Fixed.
    - Dialer: fix broken Do Not Call check for outgoing calls by checking
      whether phone number appears in DNC at time of call. Fixes Elastix
      bug #1178.
    - Campaign Out: do not allow creation of campaigns with 0 retries.

* Wed Feb 22 2012 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-1.alpha
- Second pre-release version for testing of new dialer code.
- From CHANGELOG:
    - Agent Console: expose selection of attended call transfer via "atxfercall"
      command.
    - Dialer: fix broken recovery on database loss due to dangling connection in
      configuration object.
    - Campaign Out: fix failed creation of new campaign with no external URL.
    - Campaign Out: allow free-format text as custom context. Fix for Elastix
      bug #1168.
    - Agent Console: fix opening of blank page when receiving call in campaign
      that does not have an external URL defined.
    - Dialer (ECCP): new requirement "atxfercall" that invokes the "atxfer" AMI
      action.
    - Campaign Out: Add Failure Code and Failure Cause to CSV report. This
      reports the failure cause reported when a call attempt failed.
    - Dialer: init script MUST specify signal to send to shutdown process.
      Otherwise killproc timeouts and sends SIGKILL to monitor process.

* Wed Feb 08 2012 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.99-0.alpha
- Pre-release version for testing of new dialer code.
- From CHANGELOG:
    - Agent Console: add support for opening external URLs for a connected call.
      External URLs can be opened in three different ways: as a new window, as
      an embedded frame inside a tab in the agent console, or as a JSONP call
      that loads and executes Javascript in the context of the agent console.
    - Campaign In: add support for linking external URLs to a campaign.
    - Campaign Out: add support for linking external URLs to a campaign.
    - Dialer (ECCP): extend "getcampaigninfo" request to report external URLs
    - External URL: new module for management of external URLs to be opened on
      each connected call.
    - Agent Console: when reading ECCP password for agent, only active agents
      should be considered. Fixes Elastix bug #1157.
    - Dialer (ECCP): protocol change: the agentloggedin and agentloggedout
      events no longer report agent membership to queues. The effort required to
      parse the output of "queue show" scales linearly with the number of agents
      in queues, and also with the number of queues defined, resulting in
      noticeable lags in ECCP performance. A new request "getagentqueues" has
      been added in case a client really needs this information.
    - Dialer: (almost) complete rewrite of the central code.
      During a deployment with a moderately large number of agents, several
      design flaws were uncovered in the dialer code. The largest of such design
      flaws was the attempt to handle all of the operations of the dialer in a
      single process. Coupled with this, several event handlers required
      synchronous database updates or reads, and in at least one handler, a
      database access with every single event, whether the event was from a
      handled call or not. In addition to this, the same event handler required
      a synchronous AMI request that required three seconds to parse completely.
      Furthermore, several instances were found where data was read from the
      database that could have been cached, since the dialer itself generated
      the data. All of this added up to large and ever-increasing delays in
      event processing that degraded the responsiveness of the agent console
      (any implementation) by tens of seconds or more. To fix all of these
      problems, the dialer was rewritten to make use of multiple processes. One
      process handles AMI events, and does not read or write to the database.
      All of the database updates are sent as asynchronous messages to another
      process, the process that reads outgoing campaign calls and places its
      calls. A third process handles the ECCP protocol and database accesses
      that result from ECCP requests and events. Also, I seized the opportunity
      to reorganize the code around a fully object-oriented model that caches as
      much information as possible in memory and unifies almost all of the
      handling for outgoing and incoming calls.
    - Dialer (ECCP): add new example program getcallinfo.php
    - Agent Monitoring: fix some uses of undefined variables
    - CallCenter Configuration: add new flag to completely disable dial
      prediction in dialer.
    - Agent Console: implement status refresh via Server-Sent Events. This mode
      of operation saves further bandwidth by removing a POST request after an
      event is received or after the 2-minute timeout. Long polling is kept as a
      fallback for Internet Explorer.
    - Agent Console: fix incorrect initial break state that caused console to
      immediately refresh break state if agent console is entered while agent is
      in break.
    - Agent Console: fix incorrect function reference that caused login process
      to hang if user navigates to a different module and back during login
      process.
    - Agent Console: do not loop endlessly when some other client puts *our*
      agent on hold.
    - Agent Console: fail more gracefully when shutting down dialer process, by
      logging out the agent instead of getting stuck in an infinite loop.
    - Dialer: add new function MultiplexServer::vaciarBuferesEscritura() to
      flush all output buffers.
    - Dialer: modify packet processing algorithm to prioritize the Asterisk AMI
      connection over other connections.
    - Dialer: flush output buffers after every packet processing to ensure
      events are delivered as soon as possible.
    - Dialer: add cache for output of 'agent show' and 'queue show' commands.
      These commands are expensive to execute and greatly slow down the
      'getagentstatus' ECCP procedure. The current cache lifetime is 1.5
      seconds.
    - Dialer: add utility function Predictivo::leerEstadoAgente() that will make
      use of the output cache if possible.
    - Dialer: add trimmed down function Predictivo::obtenerCanalRemotoAgente()
      that only runs 'agent show' instead of building the entire queue report,
      just to discard almost all of it.
    - Dialer (ECCP): new ECCP diagnostic script getagentstatusloop.php
    - Agent Console: fix typo that caused web console to refresh the agent
      status twice on agent link.
    - Dialer: discard AMI events for which there are no handlers upon reception.
      Also put a microsecond timestamp for benchmarking.
    - Dialer (ECCP): on hangup request, check clientchannel present instead of
      status for reporting that agent is not in call.
    - Agent Console: reorder code so that ECCP call to 'getcampaigninfo' happens
      at most once in status check.
    - Dialer (ECCP): work around https://bugs.php.net/bug.php?id=41175 which
      throws a warning on an attempt to set a XML attribute to an empty string.
      Triggered by a form with an empty description.
    - Dialer (ECCP): remove bogus copy-paste leftover in form information
      fetching.
    - Dialer: at hangup, the check for scheduled calls requires access to
      campaign data, but the campaign may already have ended, so it might not be
      in the campaign array. Read campaign data from database if required.
    - Dialer (ECCP): fix access to possibly-undefined property 'ActualChannel'
      when looping through calls - ActualChannel is set OnLink, but loop also
      visits unlinked calls in progress.
    - Dialer: subsys lock file must have the same name as the service in
      /etc/init.d in order to shutdown properly.

* Fri Nov 25 2011 Eduardo Cueva <ecueva@palosanto.com> 2.1.3-1
- Changes in Spec file in Prereq elastix to elastix-framework >= 2.2.0-18
- FIXED: Dialer (ECCP): fix copy/paste error in "getcampaignqueuewait" request
  on error path. (SVN revision 3350)

* Mon Nov 21 2011 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.3-0
- Updated version, synchronized with CallCenter 1.6.3 (SVN revision 3327)
- From CHANGELOG:
    - Agent Console: fix broken POST variable parsing when scheduling a call.
      Fixes Elastix bug #1083.
    - Agent Console: allow scrolling on right panel. Fixes Elastix bug #1082.
    - Dialer (ECCP): Do not dial to *8888 anymore for agent login. Instead, dial
      channel directly into the AgentLogin application. This frees the dialer
      from having to know a special dialstring for agent login. Fixes Elastix
      bug #1076.

* Fri Nov 11 2011 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.2-0
- Updated version, synchronized with CallCenter 1.6.2 (SVN revision 3289)
- From CHANGELOG:
    - Dialer: fix bug in which linked call could get assigned to an inactive version
      of an agent even if an active version exists.
    - Dialer (ECCP): implement "getcampaignqueuewait" request for sampling of queue
      wait times before calls are handled by an agent.
    - Agent Console: wait up to 1 second after login request in order to catch
      early login failure.
    - Form Preview: replace HTML entity in Spanish translation
    - Queues: complete rewrite, fixes potential SQL injections, makes use of
      elastix2.lib.php functions, fix title display under elastixneo theme.
    - Agent Console: disconnect from databases when performing long polling since
      open database connections are no longer used while waiting.
    - Dialer (ECCP): explicitly disconnect from DB connection to asterisk database,
      and report additional information in case of failure. Should fix Elastix bug
      #1053
    - Dialer (ECCP): add check for failure to list extensions in loginagent request
    - CallCenter Configuration, Campaigns Out, Do not Call List, Agent Console,
      Clients, Campaign In, Agents, ECCP Users, Break Administrator, Form Designer,
      Form Preview: fix title display under elastixneo theme
    - CallCenter Configuration, Campaigns Out: Do not Call List, Clients, Campaign In,
      Agents, ECCP Users, Break Administrator:
      import elastix2.lib.php instead of defining functions _tr and load_language_module
    - Do not Call List, Form Designer, Form Preview: convert module to use of _tr
    - Agent Console: add new function to check whether framework contains support
      for display of module title.

* Fri Oct 21 2011 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.1-0
- Updated version, synchronized with CallCenter 1.6.1 (SVN revision 3102)
- From CHANGELOG:
    - Agent Console: perform translation of legacy contact labels for incoming
      calls into user-friendly values subject to i18n. Fixes Elastix bug #1039.
    - Dialer (ECCP): extend "getcampaignlist" request to add extra filtering options
    - Dialer (ECCP): fix behavior when an incoming call is put on hold, and then
      is hung up by the remote side.
    - Agent Console: specify explicit font size in CSS to prevent font size from
      varying with the selected theme. Helps fix Elastix bug #1035.
    - Agent Console: rewrite script and header inclusion so that javascript.js
      from module is included exactly once. Fixes Elastix bug #1036.
    - Dialer (ECCP): allow the "agentlogout" request to cancel a login that is
      still in progress and asking for the agent numeric password.

* Mon Oct 17 2011 Alex Villacis Lasso <a_villacis@palosanto.com> 2.1.0-0
- Updated version, synchronized with CallCenter 1.6 (SVN revision 3087)
- From CHANGELOG:
    - Agent Console: complete rewrite.
      This version of the Call Center module has a completely rewritten Agent
      Console based on the Elastix CallCenter Protocol (ECCP). This rewrite is
      intended to showcase the capabilities of ECCP and become the reference
      implementation for an ECCP client. Some highlights of the rewrite:
      - The previous Agent Console polled the webserver every 4 seconds for updates
        on the agent state. This polling gets multiplied by the number of simultaneous
        agents and becomes a heavy burden on the server CPU with more than a few
        tens of agents. The new Agent Console switches to Long Polling
        (http://en.wikipedia.org/wiki/Push_technology#Long_polling) in which the
        browser is left waiting for a response for up to 2 minutes at a time while
        the server listens to state change events with very little CPU usage. This
        is made possible thanks to ECCP events. Fixes Elastix bug #114, probably
        fixes Elastix bugs #412, #489, #637.
      - Agent Console now works correctly in Internet Explorer. Tested with IE6, IE8
        and IE9. Fixes Elastix bug #30.
      - The previous console visual layout is now reimplemented using jQueryUI. This
        introduces niceties such as decent tabs, dialogs with shading, and themed
        buttons, as well as greatly simplifying and reorganizing the JavaScript
        implementation.
      - The previous Agent Console depends on the agent being willing and able to
        close the agent session correctly from within the interface. Failure to do
        this results in corrupted (stale) session and break audit records. This
        corruption is the probable root cause of Elastix bug #494. The new Agent
        Console is immune to this failure scenario, since the audit record update
        is now done by the dialer daemon process.
      - The agent audit now properly handles the case where an agent is deactivated
        and reactivated multiple times while keeping the same agent number. Fixes
        Elastix bug #990.
      - As a result of improved handling of the interface state, it is now possible
        to switch to other Elastix modules while the agent is logged in, then switch
        back to the Agent Console, which will display the correct interface state.
        It is even possible to close the browser while handling a call, then log
        back into Elastix, and choose the agent number and extension previously used,
        and "log-in" back into a correct console session, as long as the agent
        telephone call is kept open all the time.
      - The Transfer capability is expanded to any arbitrary extension/queue. Partial
        fix for Elastix bug #419.
      As a side effect of the rewrite, may also fix Elastix bugs #879, #796, #414.
    - Dialer (ECCP): implement "getcampaignlist" request
    - Dialer (ECCP): log out an agent immediately if login succeeded but audit
      record cannot be inserted.
    - Agent Information: fix division by zero on no connection time.
    - Incoming calls monitoring: fix use of undefined array index.
    - Campaigns In: new module to define campaigns for incoming calls
    - Campaigns Out: include NoAnswer and Abandoned calls in CSV report too.
    - Agents: Do not leave newly created or modified agent without an ECCP password.
      The ECCP password is autogenerated if required. Also, assign an ECCP password
      at install/update time.
    - ECCP Users: new module for administration of ECCP authorized users
    - Dialer (ECCP): fix requests for agents that are not assigned to any queue.
    - Dialer: fix bug in which a request for outgoing calls would repeatedly request
      already retried calls until the retry limit even if calls with lower retry
      numbers were available.
    - Dialer (ECCP): fix bug in which an agent that has just been called Hangup on
      would show as offline instead of online in getagentstatus request.
    - Dialer (ECCP): implement new events "pausestart" and "pauseend".
    - Dialer (ECCP): fix incorrect parameter verification in "getcallinfo" request
    - Dialer (ECCP): implement "getqueuescript" request
    - Dialer (ECCP): the "getcampaigninfo" request has been extended to return
      additional form attributes "name" and "description" in the <form> tag.
    - CHANGED: module agent_console, verify if function "obtenerClaveAMIAdmin"
      exists, if not the password is set with "elastix456"
    - CHANGED: changed the password "elastix456" of AMI to the password set in
      /etc/elastix.conf
    - Dialer (ECCP): implement "filterbyagent" request
    - Dialer (ECCP): Added the following fields to response for "getagentstatus"
      request: onhold pauseinfo remote_channel callinfo .
    - Login Logout: fix time format representation for time in calls. Fixes Elastix
      bug #705.
    - Dialer (ECCP): fixed bug that prevented the hold/schedulecall/transfercall
      requirements from working after agent entered a pause while still connected
      to a call.

* Mon Oct 10 2011 Alex Villacis Lasso <a_villacis@palosanto.com>
- Enable elastixdialer service by default.

* Tue Sep 13 2011 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-16
- Updated version, synchronized with CallCenter 1.5-4.2 (SVN revision 2972)
- From CHANGELOG:
1.5-4.2 (SVN revision 2972)
    - Dialer (ECCP): extended "transfercall" request to work with calls connected
      to an agent that were not tracked by the dialer.
    - Dialer (ECCP): fixed bug in which a transfer request of an incoming campaign
      call into an outgoing campaign queue results in a new incoming agentlinked
      message.
    - Dialer (ECCP): fixed bug in which a transfer request of an incoming campaign
      call into a incoming campaign queue (the same one the call came from or a
      different one) results in an agentunlinked event with an incomplete agent
      number.
    - Installer: add section in extensions_custom.conf that will hold required
      callcenter extensions for extra functionality.
    - Dialer (ECCP): implement "schedulecall" request
    - Dialer (ECCP): allow call to be hung up when agent is both busy and paused.
    - Dialer (ECCP): implement "getrequestlist" request
    - Dialer (ECCP): fixed a 200-byte-per-request memory leak on XML response ID
      assignment.
    - Dialer (ECCP): implement "getcampaignstatus" request
    - Dialer (ECCP): implement "transfercall" request
    - Dialer: write true outgoing channel, not Local@from-internal, to current_calls
      table. Also reuse stored channel at hold time instead of querying the channel
      again from Asterisk. May fix Elastix bug #796.
    - Dialer: remove use of deprecated ereg* functions from Predictivo.class.php
    - Dialer: fixed regular expression for parsing of 'agent show' report that
      missed DAHDI channels (and possibly other custom channels).
    - Dialer: fix accounting of parked calls (for Hold) in incoming-calls case

* Fri Jul 22 2011 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-15
- Updated version, synchronized with CallCenter 1.5-4.1 (SVN revision 2847)
- From CHANGELOG:
    1.5-4.1 (SVN revision 2847)
    - Dialer (ECCP): Added "extension" and "channel" fields to response for
      "getagentstatus" request.
    - Agent Console: display error diagnostic messages when pause/unpause fails.
      Also display attempted hangup channel when Hangup operation failed.
    - Agents, Break Administrator, Campaigns Out: undo use of <button> inside of <a>
      as this combination does not work as intended in Firefox 4.0.1. Fixes Elastix
      bug #864.
    - Agents: implement setting and changing optional ECCP password for agent
    - Agents: remove hardcoded HTML escapings in Spanish translation
    - Agent Console: When attempting to hang an incoming call, do not expose SQL on
      empty recordset. Fixes Elastix bug #841.
    - Agent Console: Remove reference to nonexistent javascript ManejadorCierre()

* Mon May  9 2011 Alex Villacis Lasso <a_villacis@palosanto.com>
- Update trunk version of specfile
- Clear smarty cache on install/update.

* Thu Apr 14 2011 Eduardo Cueva <ecueva@palosanto.com> 2.0.0-14
- Changed the documentation of ECCP.

* Wed Apr 13 2011 Eduardo Cueva <ecueva@palosanto.com> 2.0.0-13
- Agent Console: When programming calls, do not send campaign ID, phone number,
  and client name through the URL. Instead, just send the call ID and read the
  other values from the database. This sidesteps character escaping issues.
- Hold Time: fix warning on old code path for paging
- Break Administrator: fix warning on array_fill() when no breaks are defined.
- Compatibility fix: check if generated HTML for report includes a <form> tag,
  and add it if not present. Required for compatibility with very old Elastix
  versions. Should fix Elastix bug #662
- Agents: use <button> tag to make link appear as a button.
- Campaigns Out: check that multibyte functions exist before trying to use them.
  Fixes Elastix bug #650.
- Calls Detail: complete rewrite. Reorganized library methods now use SQL query
  parameters, have more readable code and a cleaner API, allows simultaneous
  filtering by more criteria, no longer requires fetching of all records to find
  the total number, and fixes several design flaws. Reorganized interface
  replaces the filtering by up to two criteria with a four-criteria filter with
  drop-down lists for Call Type, Agent, Queues. Should also contain fix for
  Elastix bug #638.
- Reports Break, Calls Detail, Calls per Agent, Hold Time, Login Logout: do not
  add HTML tags on non-HTML exports when using production versions of Elastix
  framework 2.0.
- Break Administrator: use parametrized SQL queries instead of string
  concatenation. Remove uses of construirInsert and construirUpdate. Make code
  smaller and add additional parameter validation. Remove unneeded AJAX calls.
  Expose functionality to activate/deactivate breaks in break listing. Use POST
  actions exclusively for modifications.

* Wed Dec  8 2010  Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-12
- Updated version, synchronized with CallCenter 1.5-3.9 (SVN revision 2086)
- From CHANGELOG:
1.5-3.9 (SVN revision 2086)
    - Reports: fix regression introduced by testing existence of incorrect method in
      Elastix 2.

* Tue Dec  7 2010 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-11
- Updated version, synchronized with CallCenter 1.5-3.8 (SVN revision 2084)
- From CHANGELOG:
1.5-3.8 (SVN Revision 2084)
    - Dialer (phpagi-asmanager): fix broken database_get method
    - Hold Time, Login Logout: remove unused HTML templates
    - Break Administrator, Hold Time: remove dead code.
    - CallCenter Config, Agent Console, Agents, Break Administrator, Calls Detail,
      Calls per Agent, Calls per Hour, Campaign Out, Ingoing Calls Success,
      Login Logout, Agent Information, Agents Connection Time, Trunks Used per Hour,
      Hold Time: Use methods load_language_module and _tr from Elastix framework.
      Define them if they do not exist.
    - Campaigns: improve speed and memory usage of CSV download of completed
      campaign data by replacing single big SQL query by multiple smaller ones.
      Fixes Elastix bug #600.
    - Agents, Break Administrator, Calls Detail, Calls per Agent, Calls per Hour,
      Campaign Out, Ingoing Calls Success, Login Logout, Agents Connection Time,
      Trunks Used per Hour: make module aware of url parameter in
      paloSantoGrid::fetchGrid().
    - Agents, Break Administrator, Calls Detail, Calls per Agent, Calls per Hour,
      Campaign Out, Ingoing Calls Success, Login Logout, Agent Information,
      Hold Time: remove <form> tags from the filter HTML template. They are not
      required, since the template already includes a proper <form> tag enclosing
      the grid.
    - Agent Console (programar_llamadas): restrict call reprogramming interface to
      requests with a valid Elastix session and authorization to the Agent Console.
      Also, clean up form code to use the default Elastix support for date controls.
    - Form List: fix breakage from i18n unification and remove useless code
    - Agent Console: move around conditional declaration of getParameter so that
      it is available at the moment it is required.
    - Dialer: Found probable cause of unexpected delays in outgoing calls. Asterisk
      AMI may emit events at any arbitrary frequency. If AMI manages to continuosly
      emit events with less than 1 second delay, the event loop in the dialer will
      get stuck processing events (even if not interesting) and never dispatch any
      new calls. Fixed by forcing end of event receipt inside wait_response() method
      in phpagi-asmanager-elastix.php. Might fix Elastix bugs #543, #577.
    - Web interface: cleanup of i18n files to bring them in line with the rest of
      the Elastix code.

* Fri Dec  3 2010 Alex Villacis Lasso <a_villacis@palosanto.com>
- FIXED: Add Requires for asterisk and freePBX for more modular installation

* Mon Oct 18 2010 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-10
- Requires: elastix-2.0.0-35 or later, for generic-cloexec
- Updated version, synchronized with CallCenter 1.5-3.7 (SVN revision 1843)
- From CHANGELOG:
1.5-3.7 (SVN revision 1843)
    - Dialer: Add debug notification when record has just been inserted in
      current_calls. Should help debug unexplained delays of call notifications in
      agent console.
    - Dialer: Asterisk 1.6.x expects context variables separated by commas, not
      pipes. Should fix Elastix bug #558.
    - Dialer: asterisk 1.6.2.x emits Dial event with changed property name. Account
      for the difference in order to prevent access to undefined property.
    - Reports (Agent Information): behave properly when no queues have been defined.
    - Agent Console: protect private declaration of getParameter() in call
      programming so that it does not conflict with Elastix 2 framework. Fixes
      Elastix bug #478.
    - Trunks Used per Hour: fix over-complicated query for active trunks from
      FreePBX database that resulted in misnamed trunk sources. Should fix Elastix
      bug #499.
    - Campaigns Out: set array variables holding recordsets to NULL as soon as
      possible in the CSV download codepath. This reduces memory usage and allows
      processing of a larger number of call records without exceeding the PHP
      memory limit. Also, for the same purpose, replace superfluous array
      assignment via loop, with direct recordset assignment.

* Mon Aug 23 2010 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-9
- Updated version, synchronized with CallCenter 1.5-3.6 (SVN revision 1713)
- From CHANGELOG:
1.5-3.6 (SVN revision 1713)
    - Configuration: detect and use generic-cloexec if available. Should fix issue
      of httpd failing to restart due to dialer process grabbing HTTP[S] ports
      as in Elastix bug #425. If generic-cloexec is not available, this has no
      effect.
    - Dialer: remove broken "reload" verb support from init script. Should fix
      Elastix bug #434.
    - Reports (Agent Connection Time, Agent Information, Trunks Used per Hour):
      remove hardcoded freePBX database credentials, and instead parse them from
      /etc/amportal.conf .
    - Dialer: with low-quality phone number databases with lots of repeated
      numbers, the generation of a call with the same number as one already
      being originated or monitored will confuse the dialer and mix up events.
      So skip over calls that duplicate calls already originated/in progress.
    - Dialer: if the AMI reports an OriginateResponse with a Success status, but
      the channel (or an auxiliary channel) has seen a Hangup, treat the call as
      a failure instead.
    - Dialer: when receiving a Link event before an OriginateResponse, do not wait
      until the OriginateResponse if the Uniqueid is known. Instead, fake an
      OriginateResponse event to handle the call as soon as possible.
    - Dialer: prevent event re-entrancy when originating outgoing calls and pausing
      queue agents.
    - Dialer: document more possible re-entrancy points where nested event handling
      could happen.
    - Dialer: add debugging function to dump list of current_calls to log
    - Dialer: phpagi-asmanager-elastix.php: factor out handling of queued events
      into a separate function, and add events to the queue, not only on reentrancy,
      but also when the queue is non-empty. This ensures that any events already in the
      queue when entering wait_response() will be dispatched before any new events
      that were picked up on the event loop.
    - Calls Detail: fix broken chronological ordering of call records. Spotted while
      fixing Elastix bug #373.
    - Calls Detail: when filtering by phone number, the SELECT statement for incoming
      calls failed to take into account that there might be no contact available for
      a given incoming call, but the Caller ID was available anyway. This resulted in
      missing incoming calls when filtering by telephone. Fixed. Should fix Elastix
      bug #373.

* Mon Jun 21 2010 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-8
- Updated version, synchronized with CallCenter 1.5-3.5 (SVN revision 1563)
- From CHANGELOG:
1.5-3.5 (SVN revision 1563)
    - Dialer: add and use new column to record timestamp of Originate execution for
      call. This is needed for upcoming support for outgoing monitoring
      information.
    - Reports Break: rewrite introduced a regression in date select that prevented
      data from being fetched if the start and end date are the same. Fixed. Should
      fix Elastix bug #360.
    - Dialer: fix accessing of undefined Uniqueid variable in OnDial event.
    - Agent Console: recognize call attributes that are HTTP URLs, and display them
      as hyperlinks.
    - Dialer: merge fix to prevent stale pidfile that happens to match an active
      process from making the daemon hang on startup. See Elastix bug #356.
    - Agent Console: add interface for scheduling of calls to particular agents.
      This support requires a specific context to work properly.

* Fri Jun 11 2010 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-7
- Updated version, synchronized with CallCenter 1.5-3.4
- From CHANGELOG:
    1.5-3.4
    - Installer: The ALTER TABLE embedded in the middle of the SQL script to create
      the database will not work for creating the complete database in the clean
      install case. The script stops processing on the first error, so the remainder
      of the tables are not created. Embedding the creation in a stored procedure
      works in Elastix 2.0 but not in Elastix 1.6 due to Elastix bug #71. This makes
      it necessary to duplicate the schema check and update in PHP code.

* Tue Jun 08 2010 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-6
- Updated version, synchronized with CallCenter 1.5-3.3
- From CHANGELOG:
    1.5-3.3
    - Clients: implement download of existing contact information.
    - Clients: rewrite file upload support to resemble campaign upload code, and
      detect charset encoding. Intended to fix Elastix bug #334.
    - Agent Console,Campaign Out: remove HTML codes from French translations, as
      they seep into CSV export output. Fix #2 for Elastix bug #325.
    - Forms Designer: fix default selection of field type. Should prevent instances
      of Elastix bug #206.
    - Calls per Hour: replaced implementation with newest implementation of Graphic
      Calls. Originally (deduced via diff) Graphic Calls was a fork of Calls per
      Hour, and it inherited major flaws. The fixed Graphics Calls implementation
      is now folded back into Calls per Hour. Should fix Elastix bug #336.
    - Campaigns Out: attempt to detect character encoding of uploaded CSV file in
      order to always store UTF-8 data in the database. Currently detects UTF-8
      and ISO-8859-15. Should fix Elastix bug #325.
    - Agent Information: (1/2) Replace questionable UNION statement that tries to
      fetch everything at once with three distinct SELECTs. The UNION mixed
      different datatypes in several columns, resulting in a mangling of UTF-8
      encoding for break names. This mangling required a workaround in the view
      via utf8_decode(), which did not work at all with characters outside latin-1.
      The mangling and the utf8_decode() workaround are no longer necessary.
      (2/2) Rewrite report to use the new data structure returned by (1/2). Remove
      dead code from (1/2). Fix CSV export to not insert HTML tags in download.
    - Agent Console: fix issue in which a session or break is incorrectly marked as
      belonging to an inactive agent with the same agent number as the current
      active agent. Should fix Elastix bug #329.
    - Dialer: monitor additional instances of Uniqueid that can be associated with
      a given call and might hold additional call failure information in their
      Hangup events.
    - Reports Break: complete rewrite. Replace inefficient time lookup, and fix
      no-data issue on CSV export, as well as HTML tags in CSV export. Should fix
      Elastix bug #324.
    - Dialer: add support for reopening of logs when receiving SIGHUP, and
      implement a corresponding logrotate directive to make use of this.
    - Dialer: record hangup cause code and description for a failed outgoing call
      Currently implemented only for calls sent through default dialplan.
    - Dialer: verify that enterqueue_timestamp is set
    - Campaigns Out: fix warning on line that start with a comma
    - Agent Console: verify that $_SESSION['elastix_agent_audit'] is set
    - Agent Console: report most causes of "spontaneous" agent disconnection
    - Agent Console: implement ability to save form information after call is
      disconnected. To use properly, the Wrapuptime parameter in the queue must
      be set to an appropriate value.
    - Agent Console: fix reference to string without translation
    - Agent Console: fix incorrect javascript in time counter reset
    - Agent Console: fix function call with insufficient parameters
    - Calls Details: remove reference to not-used status variable, including use
      of undefined $_POST index.

* Wed May 05 2010 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-5
- Updated version, synchronized with CallCenter 1.5-3.2
- From CHANGELOG:
    1.5-3.2
    - Form Designer: fix missing string translation
    - Agent Console: fix reference for nonexistent icon image.
    - Configuration: allow to save settings that include blank login, and
      blank out the password in that case, to use settings from manager.conf.
    - Dialer: store Asterisk 1.6.x Bridge event as Link in current_calls.
    - Dialer: newer FreePBX versions store trunk information in table
      'asterisk.trunks' instead of 'asterisk.globals' as previous versions did.
      The dialer daemon must look now on 'asterisk.trunks' if it exists.
    - Dialer: seems newer FreePBX versions store DAHDI trunk information as DAHDI
      not ZAP as previous versions. The dialer now needs to check under both names
      when supplied a DAHDI trunk.
    - Dialer: use queue show instead of show queue for free agent report.
    - Campaigns Out: modify CSV report of completed calls to add Uniqueid and all
      attributes defined for each call.
    - Campaigns Out: previous fix for new campaign selection broke on old
      Elastix versions. Fix it properly for all Elastix versions.
    - Dialer: Handle Bridge event fired by Asterisk 1.6.2.x instead of Link

* Mon Apr 26 2010 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-4
- Updated version, synchronized with CallCenter 1.5-3.1
- From CHANGELOG:
    1.5-3.1
    - Agents: Fix regression that prevented new agents from being created. Fixes
        Elastix bug #299.
    - Dialer: Join event reports caller-id as CallerID in Asterisk 1.4.x and
        CallerIDNum in Asterisk 1.6.2.x. Account for the difference.
    - Campaigns Out: greatly reduce time spent uploading a CSV phone file for
        a new campaign, by fixing an inefficient database operation. Also, set
      max_execution_time to 1 hour for the duration of the operation to prevent
      it from being aborted.
    - Campaigns Out: fix inability to select a form for a new campaign due to
      mismatch in control name in javascript code. Fixes Elastix bug #296.


* Mon Apr 05 2010 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-3
- Fix issue of /opt/elastix/dialer not being tracked by RPM and having wrong owner.

* Mon Apr 05 2010 Alex Villacis Lasso <a_villacis@palosanto.com> 2.0.0-2
- Updated version, synchronized with CallCenter 1.5-3
- Use elastix-menuremove when available to remove menus.
- Copy CHANGELOG into installer directory for reference.
- From CHANGELOG:
    1.5-3
    - Fix behavior of agent reporting to prevent mistaking no-agent case with DB error
    - Remove declarations of getParameter() that conflict with existing declaration
      included in Elastix 2.0
    - Merge new CallCenter reports into SVN:
      - Agent Information: show summary of agent, first/last disconnection, and
        received incoming calls.
      - Agents Monitoring: real-time status of agents per queue, with total login
        time and number of calls
      - Trunks used per hour: Displays calls placed/answered/abandoned per trunk
        over a specified time period.
      - Agents connection type: displays summary or detail of agent sessions, with
        percentage of agent session actually spent handling calls, per queue, over
        a specified time period.
      - Incoming calls monitoring: real-time summary of incoming calls, grouped
        by queue and status.
    - Dialer: Always save start_time when marking a call as ShortCall. Should fix
      Elastix bug #262.
    - Dialer: Remove per-queue counter of pending calls. This code is prone to get
      out of sync with the actual count of pending calls. Instead, store queue
      in call structure and count pending calls that match a given queue.
    - Dialer: Fix assumption that Link and Join events will always occur after
      the OriginateResponse event. This does not always hold for calls made through
      the dialplan (Local/XXX@from-internal). Should fix issue of some calls not
      being detected when using dialplan for campaigns.
    - Dialer: An incoming call that is transferred should result in the agent being
      marked as idle in the database, instead of incorrectly keep displaying the
      information for the transferred call. Fixes Elastix bug #213.
    - Dialer: rename a method to reflect context in which it is used.
    - Agent Console: only build VTiger link if contact information for incoming call
      actually exists.
    - Agent Console: fix case typo for reference to translated string
    - Agent Console: tweak loading of language files to have English strings as
      fallback if no localized string is available
    - Break Administrator: fix reference to nonexistent translation string. Spotted
      and fixed by Jorge Gutierrez.
    - Agents: detect and fail operation to add an agent if the agent already exists.
      Should fix Elastix bug #209
    - Agents: remove obsolete parameter from method call that references an
      undefined variable.
    - Merge improvements to templates for Campaign Out by Franck Danard
    - Display callerid as incoming number for incoming calls
    - Add more missing strings and synchronize French translations
    - Check that session variable is set before testing if not null.

    1.5-2.1
    - Dialer - do not fill log with notifications about origin of AMI credentials

    - Agents: Merge changes from http://elajonjoli.org/node/25 to provide defined
      ordering of agent report and reloading just chan_agent, not entire Asterisk,
      when agent configuration changes. Tracked at Elastix bug #191.

    - Dialer - fix check for wrong column name that led to assuming nonexistent
      support for scheduled agents on systems that lack the required column
      'calls.agent'.

    - Agents: Major rewrite:
     - Remove empty directory libs/js
     - Add missing language strings
     - Translate Spanish language strings correctly
     - Rework interface code into separate procedures for each screen
     - Merge form preparation for new agent and agent modification
     - Remove dead code from interface and module library
     - Store database connection in library object as done in other modules
     - Store message string in library object as done in other modules
     - Fix use of a session variable instead of input data for agent logoff
     - Centralize logging into Asterisk AMI into a single procedure
     - Rework library code to merge parameter validation and actual work code
       into single procedures.
     - Replace pattern of copying configuration file into temporary file
     - Improve interface to place agent removal functionality in main screen
     - Simplify loading and parsing of agent configuration file
     - Move filter HTML into separate template

    1.5-2
    - Agents, Calls Detail, Calls per Agent, Calls per Hour, Campaign Out,
      Form Designer, Hold Time, Incoming Calls Success, Login/Logout, Reports Break,
      : Tweak loading of language files to have English strings as fallback if no
      localized string is available
    - Agents: Add missing English language strings
    - Agents: Look for phpagi-asmanager.php in libs/ in addition to /var/lib/asterisk/agi-bin
    - Agents: Make re-loading of Asterisk more robust in case of failure
    - Report - Calls Detail: Initialize a variable
    - Report - Calls Detail: Add missing language string
    - Report - Calls per Agent: Add missing language string
    - Report - Calls per Hour: Fix incorrect index for internationalized strings
    - Report - Hold Time: Actually define internationalized strings that are being
      used.
    - Report - Incoming Calls Success: Fix use of undefined variables when no calls
      are present
    - Report - Login/Logout: Actually define internationalized strings that are
      being used.
    - Break Administrator: Actually define internationalized strings that are being
      used.
    - Form Designer: Actually define internationalized strings that are being used.
    - Report - Calls Details: Actually define internationalized strings that are
      being used.
    - Dialer - Fix bug in which a scheduled agent in pause would receive calls even
      when paused.
    - Dialer - Try harder to work around a bug in some Asterisk versions where
      agents are reported as busy when they are really free, by manually modifying
      the Asterisk database and restarting Asterisk.
    - Dialer - Fix use of undefined variable in some code paths.
    - Dialer - Fix bug in which an agent that belongs to both an incoming and
      outgoing campaign will cause outgoing calls to be handled as incoming.
    - Outgoing Campaigns: Major rewrite:
     - Code cleanup/refactoring to remove duplicated functionality between creation
       and modification of a campaign.
     - Use rawmode for display of CSV data instead of a separate callable PHP script.
     - Improve usability of New Campaign/Edit Campaign with links to relevant
       resource managers.
     - Display error message instead of form when trying to create an outgoing
       campaign without   defining forms or queues, or when all available queues are
       used by incoming campaigns.
     - Move out embedded HTML markup for report filter into a separate Smarty
       template.
     - Rework query for campaign CSV data to be more readable.
     - Rework campaign report to make accessible more of the available
       functionality. Now the operations for Deactivate/Delete campaign are show in
       the report instead of having to use the View link.
     - Fixed use of undefined localized strings.
    - Graphic calls: Major rewrite:
     - Remove vim swapfile unwittingly committed into repository
     - Remove unused template new.tpl and unused richedit library
     - Remove copy of jpgraph library and redirect references to Elastix embedded
       jpgraph instead
     - Rewrite code to remove write of query data to a temporary PHP file, replaced
       by rawmode and proper query
     - Refactor code to eliminate repeating code for hour processing
     - Use SQL with GROUP BY and IF conditionals to replace PHP code that built
       histogram from a direct query of calls
     - Move out HTML code for report filter into a proper template
     - Fix use of undefined localization strings
    - Dialer - Fixed improper handling of multiple Link events for monitored
      incoming calls that lead to temporary incoming call information not being
      removed from the database
    - At long last, actually include a CHANGELOG in the tarball ;-)
    - Ingoing Calls Success - remove vim swapfile unwittingly committed into repository

    1.5-svn-branch-1.6
    - Agent Console: Fix up conformance to XHTML in several templates.
    - Add missing translations for strings "Name" and "Retype Password" (Elastix bug
      #167)
    - Dialer Configuration: Add support for setting Service Percent (97 percent by
      default)
    - Report - Calls Detail: Actually use internationalized string for "End Time"
    - Report - Calls per Hour: Actually use internationalized strings for call
      states
    - Report - Calls per Hour: Expand French localization strings
    - Outgoing Campaigns: Add library support (not yet exposed in interface) to
      leave trunk blank in order to use default Asterisk dialplan through
      Local/$OUTNUM$@from-internal
    - Report - Graphic Calls per hour: fix French localization for "Total Calls"
    - Report - Hold Time: Internationalize strings for average wait time
    - Report - Hold Time: Add French localization strings
    - Report - Ingoing Calls Success: Fix French localization strings
    - Report - Login/Logout: Use internationalized strings for report type
    - Report - Login/Logout: Add French localization strings
    - Report - Reports Break: Make code more robust when no break types are defined
    - Report - Reports Break: comment out unused code in library files
    - Dialer - Add support for scheduled calls to specific agents. This code also
      automatically detects whether the database tables support the functionality.
    - Dialer - Add support for setting the probability used when calculating the
      odds that a currently-active call will hang up after a certain set time.
      Previously this value was hardcoded to 97 percent. This parameter is exposed
      on the web configuration as Service Percent.
    - Dialer - Add code to verify whether the database connection was lost and
      attempt to reconnect to the database periodically.
    - Dialer - Add code to figure out the time between originating a call and
      linking the call to an agent, and use it for predictive calculations.
      Previously this value was hardcoded to 8 seconds.
    - Dialer - Add enter/exit trace code for debugging events
    - Dialer - Record incoming trunk for incoming calls, if database column is
      available
    - Dialer - Add code to attempt to eliminate reentrancy on handling of Asterisk
      events.
    - Dialer - Remove dead code for normal distribution for call prediction.

    1.5-1
    - Callcenter for Elastix 1.5.x released.

* Tue Aug 25 2009 Bruno Macias <bmacias@palosanto.com> 2.0.0-1
- Initial version.
