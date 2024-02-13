
#
# Table structure for table 'sys_tag'
#
CREATE TABLE sys_tag (
	title tinytext NOT NULL,
	items int(11) DEFAULT '0' NOT NULL,

	KEY category_list (pid,deleted,sys_language_uid)
);

CREATE TABLE tt_content
(
    tags int(11) DEFAULT '0' NOT NULL
);

CREATE TABLE fe_users
(
    tags int(11) DEFAULT '0' NOT NULL
);
