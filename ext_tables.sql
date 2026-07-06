CREATE TABLE tx_livereload_broadcast (
    uid int(11) unsigned NOT NULL auto_increment,
    tags text,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    PRIMARY KEY (uid),
    KEY crdate (crdate)
);
