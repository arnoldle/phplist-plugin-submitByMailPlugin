phplist-plugin-submitByMailPlugin
=================================

Elements of a plugin to submit messages to Phplist by mail. This is meant to replace the earlier mail2list plugin.

I have renamed my plugin the submitByMail plugin to avoid confusion with Sawey’s mail2list. It’s a completely independent development based on the PEAR classes and the PHP iMap extension.

There is an edit page allowing each individual list to be configured for submission of messages through email. This page allows the administrator to enter an email address for message submission, as well as a password an the domain of the POP server. In addition an option will be given allowing the mail to be entered through a UNIX pipe (if Phplist is running on the same server). Each list is required to have a different address for submission of messages. Messages collected by POP must be use SSL/TLS and must use the standard port 995.

Unlike Sawey's mail2list plugin, the submitByMailPlugin allows for attachments to messages if Phplist is configured to allow attachments. The MIME types acceptable for attachment are configurable in the plugin settings.

For each list there are radio buttons specifying whether or not submitted messages should be processed immediately or escrowed for confirmation by the purported sender. Escrowing the message requires a subdirectory for the message file and a database table containing information allowing the message to be retrieved after it is confirmed, or deleted if it is not.

For each list there are radio buttons specifying whether the message should be saved for editing or queued immediately.

Messages submitted are rejected if they do not come from a superuser or the list administrator.

The schedule and template for messages queued immediately are the default values.

There will be page available to each list administrator to allow her/him to collect and process messages for their lists. Optionally this function can be carried out through a command line command, allowing the message collection and processing to be scheduled through cron.

Messages submitted via a UNIX pipe than through POP will be escrowed or processed immediately.

Plugins are given access to submitted messages at the time submitted messages are saved and when such messages are queued.

Please note that certain prerequisites are necessary in order to activate the plugin.
*1. You must be running on a secure connection (https: instead of http:).
*2. The imap extension for PHP must be available and turned on.
*3. The PEAR MIME decoder must be installed.

Again, a secure connection is required to use this plugin. A subsequent version will provide operations suitable for use on an unsecured connection.

A Late Update

May 10, 2015

The code is now complete. The code is complete and thoroughly debugged. What is available here can be considered a candidate for release. It has not yet been tested in actual use, but you are welcome to try it. I would appreciate the help with testing!

The docs are not yet written, but you should be able to figure out things. After installation the plugin settings are available at the bottom of the campaign category. You will find the plugin pages in the plugin menu.

f you are running a later version of Phplist (3.07+), you should be able to install the plugin using the plugin installer provided by Phplist. 

Don't try to use this plugin for anything critical! Use at your own risk!

Current target dates

June 30 (or before): the first release, 1.0a1

