This set of scripts helps manage the population of your 
GroupMe group; ensuring that group members are active
and still interested in being members.

It is strongly recommended you use a role account for this.
The role account has to be a member of the group in question.

list.py simply lists the groups the current role account
is in, and their IDs.  This is an input into the subsequent
scripts.

notify.py is used to send notifications to members.  It
will:

1) Enumerate all current members
2) Ignore members who have been active in the group in
   the past n days (default 14 days).  
3) Send a PM to all others asking if they are still
   intersted in being in the group
4) Write out a data file (for use by subsequent scripts)
   recording which of these it did to which accounts.
5) Log all this in notify.log

prune.py is used to prune inactive members.  It depends
on the results of notify.py.  It will:

1) Use the output of notify.py to determine which members
   were automatically considered active and which were
   PMed.
2) Check the response to the PMs - if the person hearted
   the message then they stay in, otherwise they do not.
3) PM the people who will be removed, telling them why.
4) PM the group telling whem why people are being removed.
5) Remove the people from the group.
6) Log all this in prune.log

unprune.py is used to revert the changes made by prune.py
if necessary.
