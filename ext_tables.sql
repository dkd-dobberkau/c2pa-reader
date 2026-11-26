CREATE TABLE sys_file_metadata (
	tx_c2pareader_status varchar(20) DEFAULT '' NOT NULL,
	tx_c2pareader_ai tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_c2pareader_agents varchar(255) DEFAULT '' NOT NULL,
	tx_c2pareader_checked int(11) unsigned DEFAULT 0 NOT NULL,
	tx_c2pareader_ai_manual tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_c2pareader_issues varchar(255) DEFAULT '' NOT NULL
);
