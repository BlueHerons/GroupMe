#!/usr/bin/python3

"""List the GroupMe members that you've seen"""

# https://github.com/rhgrant10/Groupy
import groupy

members = groupy.Member.list()
for member in members:
    print("{0} ({1})".format(member.nickname, member.user_id))
