

1) Stop postfix and courier

/etc/init.d/postfix stop
/etc/init.d/courier-authdaemon stop
/etc/init.d/courier-imap stop
/etc/init.d/courier-imap-ssl stop
/etc/init.d/courier-pop stop
/etc/init.d/courier-pop-ssl stop

2) Install dovecot

apt-get install dovecot-imapd dovecot-pop3d dovecot-common
apt-get remove courier-authdaemon

This will remove the courier packages automatically

3) Login to ISPConfig, go to System > Server config > mail and set:

POP3/IMAP Daemon: dovecot
Mailfilter Syntax: sieve

and click on save.

4) Create a remoting user, downlod the ispconfig courier to dovecot 
  migration script, adjust the values in it and run the script.
  
  php courier_to_dovecot.php
  
5) Run a ispconfig update and select to reconfigure services.

6) Reconfigure squirrelmail








