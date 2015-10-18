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
    Defaults to 31 days.
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
import os
import pickle

parser = argparse.ArgumentParser(
        description="Notify a GroupMe group's inactive members that they may be removed.")
parser.add_argument('group_id', 
        type=str, 
        help='The group ID to be processed')
parser.add_argument('--inactive_days', 
        default=31, 
        type=int, 
        help='The number of days someone is inactive before they get notified')
parser.add_argument('--deadline_days',
        default=7, 
        type=int, 
        help='The grace period for removal')
parser.add_argument("--ya_rly",
        action='store_true',
        help='Really send notifications')

SCRIPTDIR = os.path.dirname(os.path.realpath(__file__))
DATADIR = SCRIPTDIR + '/data'

LOG = logging.getLogger('notify')
LOG.setLevel(logging.DEBUG)

PING_MESSAGE = ("I noticed you have not been active in \'{0}\' over the past {1} days. "
                'Please heart this post in the next {2} days if you still want to '
                'be in this group.  If not, you do not have to do anything and will '
                'be removed after that time.')

"""Find the group object given a group ID"""
def findGroupFromID(group_id, group_class = groupy.Group):
    target_group = None
    for g in group_class.list():
        if g.group_id == group_id:
            LOG.info('Found group id {0} "{1}"'.format(g.group_id, g.name))
            target_group = g
            break
    if not target_group:
        LOG.error('Could not find group id {0}'.format(group_id))
    return target_group


"""Construct the default member status structure for a group"""
def buildMemberStatus(group, inactive, deadline):
    status = {}
    for m in group.members():
        status[m.user_id] = {
            'active': False,
            'message_id': None,
            'message_sent': None,
            'obj': m,
            'lastSeen': None,
            'deadline': deadline
        }
    return status


"""
Generate member status from messages in the group seen since the inactive 
deadline.
"""
def buildMemberStatusFromMessages(group, inactive, deadline):
    status = buildMemberStatus(group, inactive, deadline)

    messages = group.messages()
    while True:
        for m in messages:
            if m.created_at < inactive:
                break
            
            if m.user_id == 'system':
                continue
            
            LOG.debug('Message {0} from {1} on {2}'.format(
                m.id, 
                m.user_id, 
                m.created_at
                )
            )

            if m.user_id in status:
                status[m.user_id]['active'] = True
                if not status[m.user_id]['lastSeen']:
                    status[m.user_id]['lastSeen'] = m.created_at
                elif status[m.user_id]['lastSeen'] < m.created_at:
                    status[m.user_id]['lastSeen'] = m.created_at

            for id in m.favorited_by:
                LOG.debug('Message {0} on {1} liked by {2}'.format(
                    m.id, m.created_at, id))
                if id in status:
                    status[id]['active'] = True
                    if not status[id]['lastSeen']:
                        status[id]['lastSeen'] = m.created_at
                    elif status[id]['lastSeen'] < m.created_at:
                        status[id]['lastSeen'] = m.created_at

        if messages[-1].created_at < inactive:
            break

        messages = messages.older()

    return status


"""Get the inactive members from the status structure"""
def getInactiveMembers(status):
    inactive = []
    for id in status.keys():
        if not status[id]['active']:
            inactive.append(status[id]['obj'])

    return inactive


"""Ping the inactive members"""
def pingInactiveMembers(status, group, inactive, deadline, ya_rly, msg = PING_MESSAGE):
    for m in getInactiveMembers(status):
        LOG.info('PM-ing {0} [{1}] to see if they still want to be members.'.format(
            m.nickname, m.user_id
            )
        )
        if ya_rly:
            message = m.post(msg.format(group.name, inactive, deadline))
            
            status[m.user_id]['message_id'] = message[0]['direct_message']['id']
            status[m.user_id]['message_sent'] = message[0]['direct_message']['created_at']
            LOG.debug('Sent message ID {0} at {1}'.format(
              status[m.user_id]['message_id'], status[m.user_id]['message_sent']))


"""Main program"""
def main(args):

    ch = logging.StreamHandler()
    ch.setLevel(logging.INFO)
    ch.setFormatter(logging.Formatter('%(levelname)s - %(message)s'))
    LOG.addHandler(ch)
            
    target_group = findGroupFromID(args.group_id)
    if not target_group:
        LOG.error('Could not find group ID {0}'.format(args.group_id))
        sys.exit(0)
        
    now =  datetime.now()      
    groupdir = DATADIR + '/' + target_group.group_id
    grouplink = DATADIR + '/' + target_group.name
    os.makedirs(groupdir, mode = 0o777, exist_ok = True)
    if os.path.exists(grouplink):
        os.unlink(grouplink)
    os.symlink(groupdir, grouplink)
    datafile = groupdir + '/' + now.strftime('%Y%m%d%H%M%S')
    
    fh = logging.FileHandler(datafile + '.log')
    fh.setLevel(logging.DEBUG)
    fh.setFormatter(logging.Formatter('%(asctime)s - %(levelname)s - %(message)s'))
    LOG.addHandler(fh)    
    
    LOG.info("Getting membership and reading {0} days of messages...".format(args.inactive_days))
    
    inactive_datetime = now - timedelta(args.inactive_days)
    deadline_datetime = now + timedelta(args.deadline_days)
    member_status = buildMemberStatusFromMessages(
            target_group, 
            inactive_datetime, 
            deadline_datetime
            )
    
    
    with open(datafile, 'wb') as f:
        pingInactiveMembers(member_status,
                            target_group,
                            args.inactive_days,
                            args.deadline_days,
                            args.ya_rly)
    
        LOG.info('Saving member status as {0}'.format(datafile))
        pickle.dump(member_status, f)

if __name__ == "__main__":
    args = parser.parse_args()
    main(args)


