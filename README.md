# vb3-to-phpBB3.1
vBulletin 3 converter for phpBB 3.1

Please refer to the phpBB converters documentation first: [Convert How-to](https://www.phpbb.com/community/viewtopic.php?f=486&t=2278536).

This is a new converter written from scratch that I needed to convert my board under vBulletin 3.7.2 to phpBB 3.1.2. Now that the work is finished, I'm happy to give it to the phpBB community.

Please let me know if the converter worked for you (or not) so I can maintain the list of compatibilities!

Here's the list of what is actually converted from vBulletin:
- the obvious: forums (and categories), topics, posts, polls, attachments, users (with passwords) and user groups.
- some of the board configuration
- the default user groups are mapped to phpBB default user groups
- custom BBcodes
- post icons (the default phpBB ones are not removed)
- smilies (the default phpBB ones are not removed)
- censored words
- infractions (warnings)
- profile custom fields (some type can't be converted though: like the multiple choice lists)
- friends and foes
- custom avatars
- custom profile pictures (you will need to install an extension to display them in phpBB)
- custom signature pictures (you will need to install an extension to display them in phpBB)
- forum permissions
- moderator permissions
- administrator permissions (and founders)
- banned users and emails
- ranks
- subscribed forums and topics
- private messages
- moderator logs (but not the administrator logs)
