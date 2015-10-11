#!/usr/bin/python3
"""
This script finds potentially inactive group members
and PMs them to see if they still want to be members.

Parameters:

  group_id: The numeric ID of the GroupMe group to 
    notify.  We use this because group names can
    change without warning.  Use list.py to see the 
    groups you're a member of.
  inactive_days: The number of days you haven't done
    anything in the group to be considered inactive.  
    Defaults to 14 days.
  deadline_days: The number of days after you're
    notified before you're removed.  Defaults to 7
    days.

Data for a given run is stored under 
  data/<group_id>/<YYMMDDHHMMSS>

Logs are under data/logs

"""
import argparse
from datetime import datetime
from datetime import timedelta
import groupy  # https://github.com/rhgrant10/Groupy
import logging

logging.basicConfig(level=20)

now =  datetime.now()

parser = argparse.ArgumentParser(description="Notify a GroupMe group's inactive members that they may be removed.")
parser.add_argument("group_id", type=str, help="The group ID to be processed")
parser.add_argument("--inactive_days", default=14, type=int, help="The number of days someone is inactive before they get notified")
parser.add_argument("--deadline_days", default=7, type=int, help="The grace period for removal")

args = parser.parse_args()

# Find the group object
groups = groupy.Group.list()
target_group = False
for group in groups:
  if group.group_id == args.group_id:
    logging.info("Found group id {0} '{1}'".format(group.group_id, group.name))
    target_group = group
    break

if not target_group:
  logging.error("Could not find group id {0}".format(args.group_id))

logging.info("Getting membership and reading {0} days of messages...".format(args.inactive_days))

inactive_datetime = now - timedelta(args.inactive_days)

# Get membership and throw into a dictionary
member_status = {}
members = target_group.members()
for member in members:
  if not member.user_id in member_status:
    member_status[member.user_id] = {'active': False, 'message_datetime': None, 'obj': member, 'lastSeen': inactive_datetime, 'deadline': now + timedelta(args.deadline_days)}

logging.info("Considering messages newer than {0}".format(inactive_datetime))

messages = target_group.messages()
while True:
  for message in messages:
    if message.created_at < inactive_datetime:
      break
    logging.info("Message {0} from {1} on {2}".format(message.id, message.user_id, message.created_at)) 
    if not message.user_id in member_status:
      logging.warning("User id {0} is not a current member, ignoring...".format(message.user_id))
    else:
      member_status[message.user_id]['active'] = True
      if member_status[message.user_id]['lastSeen'] < message.created_at:
        member_status[message.user_id]['lastSeen'] = message.created_at 
  
    for id in message.favorited_by:
      logging.info("Message {0} favorited by {1}".format(message.id, id))
      if not id in member_status:
        logging.warning("User id {0} is not a current member, ignoring...".format(id))
      else:
        member_status[id]['active'] = True
        if member_status[id]['lastSeen'] < message.created_at:
          member_status[id]['lastSeen'] = message.created_at

  if messages[-1].created_at < inactive_datetime:
    break
 
  messages = messages.older() 

inactive_members = []
for id in member_status.keys():
  if not member_status[id]['active']:
    inactive_members.append(member_status[id])
    logging.info("{0} may be inactive...".format(member_status[id]['obj'].nickname))

# Notify the group
message = "It looks like the following users may no longer be active.  I haven't seen them in {0} days:\n\n".format(args.inactive_days)
for member in inactive_members:
  message += "{0}\n".format(member['obj'].nickname)
  pm = "Hey I noticed you haven't been active in {0} over the past {1} days.  Please heart this post in the next {2} days if you're still interested in membership.".format(target_group.name, args.inactive_days, args.deadline_days)
  
  # TODO (ken): Send this message to the member
  print(pm)

message += "\nI'll PM them and see if they're still interested in the group."

# TODO (ken): Send this message to the group
print(message)

