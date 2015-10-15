#!/usr/bin/python3

"""Migrate all active users from group a to group b"""

import datetime.datetime
import datetime.timespan
# https://github.com/rhgrant10/Groupy
import groupy

OLDGROUP_ID = '123'
NEWGROUP_ID = '456'
DAYS_LOOKBACK = 31

# Find the old and new group objects
groups = groupy.Group.list()
oldgroup = None
newgroup = None
for g in groups:
    if not oldgroup:
        if g.group_id == OLDGROUP_ID:
            print('Found old group')
            oldgroup = g
            continue
    if not newgroup:
        if g.group_id == NEWGROUP_ID:
            print('Found new group')
            newgroup = g
            continue

    if oldgroup and newgroup:
        break

if not oldgroup:
    print("ERROR: Could not find the old group")
    quit()
if not newgroup:
    print("ERROR: Could not find the new group")
    quit()

members_by_id = {}
for m in oldgroup.members():
    members_by_id[m.user_id] = m

# Read through the past DAYS_LOOKBACK days of messages
# in the old groups.  We'll keep any current members
# who posted or favorited a message.
members_to_move = []
max_msg_age = datetime.new() - timespan(DAYS_LOOKBACK)
messages = oldgroup.messages()
while True:
    for msg in messages:
        if msg.created_at < max_msg_age:
            break

        if msg.user_id in members_by_id:
            members_to_move.append(members_by_id[msg.user_id])

        for user_id in msg.favorited_by:
            if user_id in members_by_id:
                members_to_move.append(members_by_id[user_id])

    if messages[-1].created_at < max_msg_age:
        break

    messages = messages.older()

# Add the active members to the new group and remove them from the old
print("Migrating group members...")
for m in members_to_move:
    print(member.nickname)
    newgroup.add(m)
    oldgroup.remove(m)

