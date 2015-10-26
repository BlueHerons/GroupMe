#!/usr/bin/python3

"""List the groups that you're a member of"""

# https://github.com/rhgrant10/Groupy
import groupy

groups = groupy.Group.list()
for group in groups:
    print("{0} {1}".format(group.group_id, group.name))
