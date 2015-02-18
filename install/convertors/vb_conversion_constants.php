<?php

// This is the list of the bitfields used by vBulletin

// Forum permissions
define("fp_canview", 1);					// f_list, f_subscribe
define("fp_canviewothers", 2);				// -
define("fp_cansearch", 4);					// f_search
define("fp_canemail", 8);					// f_email
define("fp_canpostnew", 16);				// f_post, f_sigs, f_postcount
define("fp_canreplyown", 32);				// f_reply
define("fp_canreplyothers", 64);			// f_reply
define("fp_caneditpost", 128);				// f_edit
define("fp_candeletepost", 256);			// f_delete, f_softdelete
define("fp_candeletethread", 512);			// f_delete, f_softdelete
define("fp_canopenclose", 1024);			// f_user_lock
define("fp_canmove", 2048);					// -
define("fp_cangetattachment", 4096);		// f_download
define("fp_canpostattachment", 8192);		// f_attach, f_download
define("fp_canpostpoll", 16384);			// f_poll
define("fp_canvote", 32768);				// f_vote, f_votechg
define("fp_canthreadrate", 65536);			// -
define("fp_followforummoderation", 131072);	// -
define("fp_canseedelnotice", 262144);		// -
define("fp_canviewthreads", 524288);		// f_read, f_report, f_subscribe, f_print
define("fp_cantagown", 1048576);			// -
define("fp_cantagothers", 2097152);			// -
define("fp_candeletetagown", 4194304);		// -

// Options
define("o_active", 1);							// -
define("o_allowposting", 2);					// f_post
define("o_cancontainthreads", 4);				// -
define("o_moderatenewpost", 8);					// f_noapprove
define("o_moderatenewthread", 16);				// f_noapprove
define("o_moderateattach", 32);					// f_noapprove
define("o_allowbbcode", 64);					// f_bbcode
define("o_allowimages", 128);					// f_img
define("o_allowhtml", 256);						// -
define("o_allowsmilies", 512);					// f_smilies
define("o_allowicons", 1024);					// f_icons
define("o_allowratings", 2048);					// -
define("o_countposts", 4096);					// ? f_postcount ?
define("o_canhavepassword", 8192);				// -
define("o_indexposts", 16384);					// -
define("o_styleoverride", 32768);				// -
define("o_showonforumjump", 65536);				// -
define("o_prefixrequired", 131072);				// -

// Admin permissions
define("a_ismoderator", 1);						// -
define("a_cancontrolpanel", 2);					// a_board
define("a_canadminsettings", 4);				// a_server
define("a_canadminstyles", 8);					// a_styles
define("a_canadminlanguages", 16);				// a_language
define("a_canadminforums", 32);					// a_forum, a_forumadd, a_forumdel, a_prune
define("a_canadminthreads", 64);				// a_icons (Can alter topic/post icons and smilies)
define("a_canadmincalendars", 128);				// -
define("a_canadminusers", 256);					// a_user, a_userdel, a_authusers, a_ban, a_group, a_groupadd, a_groupdel, a_names, a_profile, a_ranks
define("a_canadminpermissions", 512);			// a_roles, a_authgroups, a_uauth, a_fauth, a_mauth, a_aauth, a_switchperm, a_viewauth
define("a_canadminfaq", 1024);					// -
define("a_canadminimages", 2048);				// a_attach
define("a_canadminbbcodes", 4096);				// a_bbcode
define("a_canadmincron", 8192);					// -
define("a_canadminmaintain", 16384);			// a_reasons, a_search, a_email, a_bots, a_backup
define("a_canadminplugins", 65536);				// a_extensions, a_modules
define("a_canadminnotices", 131072);			// -
define("a_canadminmodlog", 262144);				// a_viewlogs, a_clearlogs

// Private messages permissions
define("pm_cantrackpm", 1);
define("pm_candenypmreceipts", 2);
define("pm_canignorequota", 4);

// Generic permissions
define("gp_canviewmembers", 1);					// u_viewprofile
define("gp_canmodifyprofile", 2);
define("gp_caninvisible", 4);					// u_hideonline
define("gp_canviewothersusernotes", 8);
define("gp_canmanageownusernotes", 16);
define("gp_canseehidden", 32);
define("gp_canbeusernoted", 64);
define("gp_canprofilepic", 128);
define("gp_cananimateprofilepic", 134217728);
define("gp_canuseavatar", 512);					// u_chgavatar
define("gp_cananimateavatar", 67108864);
define("gp_canusesignature", 1024);				// u_sig
define("gp_canusecustomtitle", 2048);
define("gp_canseeprofilepic", 4096);			// u_viewprofile
define("gp_canviewownusernotes", 8192);
define("gp_canmanageothersusernotes", 16384);
define("gp_canpostownusernotes", 32768);
define("gp_canpostothersusernotes", 65536);
define("gp_caneditownusernotes", 131072);
define("gp_canseehiddencustomfields", 262144);
define("gp_canseeownrep", 256);
define("gp_canuserep", 524288);
define("gp_canhiderep", 1048576);
define("gp_cannegativerep", 2097152);
define("gp_cangiveinfraction", 4194304);
define("gp_canseeinfraction", 8388608);
define("gp_cangivearbinfraction", 536870912);
define("gp_canreverseinfraction", 16777216);
define("gp_cansearchft_bool", 33554432);
define("gp_canemailmember", 268435456);			// u_sendmail
define("gp_cancreatetag", 1073741824);

// Who's on line permissions
define("wol_canwhosonline", 1);					// u_viewonline
define("wol_canwhosonlineip", 2);
define("wol_canwhosonlinefull", 4);
define("wol_canwhosonlinebad", 8);
define("wol_canwhosonlinelocation", 16);

// Generic options
define("go_showgroup", 1);
define("go_showbirthday", 2);
define("go_showmemberlist", 4);					// u_viewprofile
define("go_showeditedby", 8);
define("go_allowmembergroups", 16);
define("go_isnotbannedgroup", 32);

// Signature permissions
define("sp_canbbcode", 131072);
define("sp_canbbcodebasic", 1);
define("sp_canbbcodecolor", 2);
define("sp_canbbcodesize", 4);
define("sp_canbbcodefont", 8);
define("sp_canbbcodealign", 16);
define("sp_canbbcodelist", 32);
define("sp_canbbcodelink", 64);
define("sp_canbbcodecode", 128);
define("sp_canbbcodephp", 256);
define("sp_canbbcodehtml", 512);
define("sp_canbbcodequote", 1024);
define("sp_allowimg", 2048);
define("sp_allowsmilies", 4096);
define("sp_allowhtml", 8192);
define("sp_cansigpic", 32768);
define("sp_cananimatesigpic", 65536);

// Moderator permissions
define("mp_caneditposts", 1);				// m_edit
define("mp_candeleteposts", 2);				// m_delete, m_softdelete
define("mp_canopenclose", 4);				// m_lock
define("mp_caneditthreads", 8);				// m_edit
define("mp_canmanagethreads", 16);			// m_edit, m_merge, m_move, m_chgposter, m_info, m_split
define("mp_canannounce", 32);
define("mp_canmoderateposts", 64);			// m_approve
define("mp_canmoderateattachments", 128);
define("mp_canmassmove", 256);
define("mp_canmassprune", 512);
define("mp_canviewips", 1024);
define("mp_canviewprofile", 2048);
define("mp_canbanusers", 4096);				// m_ban
define("mp_canunbanusers", 8192);			// m_ban
define("mp_newthreademail", 16384);
define("mp_newpostemail", 32768);
define("mp_cansetpassword", 65536);
define("mp_canremoveposts", 131072);		// m_softdelete
define("mp_caneditsigs", 262144);
define("mp_caneditavatar", 524288);
define("mp_caneditpoll", 1048576);
define("mp_caneditprofilepic", 2097152);
define("mp_caneditreputation", 4194304);

// Moderator permissions 2
define("mp2_caneditvisitormessages", 1);
define("mp2_candeletevisitormessages", 2);
define("mp2_canremovevisitormessages", 4);
define("mp2_canmoderatevisitormessages", 8);
define("mp2_caneditalbumpicture", 16);
define("mp2_candeletealbumpicture", 32);
define("mp2_caneditsocialgroups", 64);
define("mp2_candeletesocialgroups", 128);
define("mp2_caneditgroupmessages", 256);
define("mp2_candeletegroupmessages", 512);
define("mp2_canremovegroupmessages", 1024);
define("mp2_canmoderategroupmessages", 2048);
define("mp2_canmoderatepicturecomments", 4096);
define("mp2_candeletepicturecomments", 8192);
define("mp2_canremovepicturecomments", 16384);
define("mp2_caneditpicturecomments", 32768);
define("mp2_canmoderatepictures", 65536);

// User options
define('user_options_showsignatures', 1);
define('user_options_showavatars', 2);
define('user_options_showimages', 4);
define('user_options_coppauser', 8);
define('user_options_adminemail', 16);
define('user_options_showvcard', 32);
define('user_options_dstauto', 64);
define('user_options_dstonoff', 128);
define('user_options_showemail', 256);
define('user_options_invisible', 512);
define('user_options_showreputation', 1024);
define('user_options_receivepm', 2048);
define('user_options_emailonpm', 4096);
define('user_options_hasaccessmask', 8192);
define('user_options_postorder', 32768);
define('user_options_receivepmbuddies', 131072);
define('user_options_noactivationmails', 262144);
define('user_options_pmboxwarning', 524288);
define('user_options_showusercss', 1048576);
define('user_options_receivefriendemailrequest', 2097152);
define('user_options_vm_enable', 8388608);
define('user_options_vm_contactonly', 16777216);

