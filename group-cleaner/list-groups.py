#!/usr/bin/python3

# https://github.com/rhgrant10/Groupy
import groupy

groups = groupy.Group.list()
for group in groups:
  print("{0} {1}".format(group.group_id, group.name))
