phplist-plugin-submitByMailPlugin
=================================

Elements of a plugin to submit messages to Phplist by mail. This is meant to replace the earlier mail2list plugin.

I have renamed my plugin the submitByMail plugin to avoid confusion with Sawey’s mail2list. It’s a completely independent development based on Manuel Lemos’s mimeparser and Pop3 class. I chose not to use the PEAR classes, because not everyone has PEAR installed. Nor did I want it to be necessary to install items from PEAR in order to use the plugin. I will distribute Manuel Lemos’s open source code with my plugin.

I am adding fields to the edit list page allowing each mailing list to be given a separate submission address. You will find an image of the edit list page with the added fields at http://domeofthesky.com/images3/editList.jpg . That will allow username and password to be entered for gathering the mail via POP. In addition an option will be given allowing the mail to be entered through a UNIX pipe (if Phplist is running on the same server). Each list is required to have a different address for submission of messages.

For each list there is a checkbox specifying whether or not submitted messages should be processed immediately or escrowed for confirmation by the purported sender. Escrowing the message requires a subdirectory for the message file and a database table containing information allowing the message to be retrieved after it is confirmed, or deleted if it is not.

For each list there is a checkbox specifying whether the message should be saved for editing or queued immediately.

These data a collected in an object, which is serialized and save in the Phplist config table of the database.

Messages submitted are rejected if they do not come from a superuser or the list administrator.

The schedule and template for messages queued immediately are the default values.

There will be page available to each list administrator to allow her/him to collect and process messages for their lists. Optionally this function can be carried out through a command line command, allowing the message collection and processing to be scheduled through cron.

Messages submitted via a UNIX pipe than through POP will be escrowed or processed immediately.

The above design for the user has been mostly implemented. However, the bulk of the code to actually carry out the operations of the plugin remains to be written.

At this time I am giving up this project, as I have little time and energy available to complete it.

This project should be a useful contribution to the functionality of Phplist. I hope that there will be someone out there who might complete it.
