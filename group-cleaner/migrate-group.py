#!/usr/bin/python3

"""Migrate all active users from group a to group b"""

import argparse
from datetime import datetime
from datetime import timedelta
# https://github.com/rhgrant10/Groupy
import groupy
import logging

logging.getLogger().setLevel(logging.INFO)
urllib3log = logging.getLogger('requests.packages.urllib3.connectionpool')
urllib3log.setLevel(logging.ERROR)

parser = argparse.ArgumentParser(
        description="Migrate all active users from group a to group b.")
parser.add_argument('-f', '--from_group', 
        type=str,
        required = True,
        help='The source group ID')
parser.add_argument('-t', '--to_group', 
        type=str,
        required = True,
        help='The destination group ID')
parser.add_argument('-l', '--lookback_days',
        default=31, 
        type=int, 
        help='How many days back to look for member activity')
parser.add_argument("--ya_rly",
        action='store_true',
        help='Really migrate users')
args = parser.parse_args()

# Find the old and new group objects
groups = groupy.Group.list()
oldgroup = None
newgroup = None
for g in groups:
    if not oldgroup:
        if g.group_id == args.from_group:
            logging.info('Found old group {0}'.format(g.name))
            oldgroup = g
            continue
    if not newgroup:
        if g.group_id == args.to_group:
            logging.info('Found new group {0}'.format(g.name))
            newgroup = g
            continue

    if oldgroup and newgroup:
        break

if not oldgroup:
    logging.critical("Could not find the source group")
    sys.exit(1)
if not newgroup:
    logging.critical("ERROR: Could not find the destination group")
    sys.exit(2)

members_by_id = {}
for m in oldgroup.members():
    members_by_id[m.user_id] = m

# Read through the past DAYS_LOOKBACK days of messages
# in the old groups.  We'll keep any current members
# who posted or favorited a message
active_members_by_id = {}
max_msg_age = datetime.now() - timedelta(args.lookback_days)
messages = oldgroup.messages()
while True:
    for msg in messages:
        if msg.created_at < max_msg_age:
            break

        if msg.user_id in members_by_id:
            active_members_by_id[msg.user_id] = True

        for user_id in msg.favorited_by:
            if user_id in members_by_id:
                active_members_by_id[user_id] = True

    if messages[-1].created_at < max_msg_age:
        break

    messages = messages.older()

# Add the active members to the new group and remove them from the old
logging.info("Migrating group members...")
for id in active_members_by_id.keys():
    
    if args.ya_rly:
        logging.info('Moving {0}'.format(members_by_id[id].nickname))        
        newgroup.add(members_by_id[id])
        oldgroup.remove(members_by_id[id])
    else:
        logging.info('Would move {0}'.format(members_by_id[id].nickname))  
        

