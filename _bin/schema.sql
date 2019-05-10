-- DATABASE CREATION
DROP DATABASE IF EXISTS botbasic;
CREATE DATABASE botbasic;
ALTER DATABASE botbasic CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
USE botbasic;

-- CORE TABLES
DROP TABLE IF EXISTS bbchannel;CREATE TABLE IF NOT EXISTS bbchannel ( id INT NOT NULL AUTO_INCREMENT, call_stack TEXT NOT NULL, route TEXT NOT NULL, runtime_id INT NOT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS bbcode;CREATE TABLE IF NOT EXISTS bbcode ( id INT NOT NULL AUTO_INCREMENT, botbasic_version VARCHAR(16) NOT NULL, code_name VARCHAR(64) NOT NULL, code_major_version VARCHAR(8) NOT NULL, code_minor_version VARCHAR(8) NOT NULL, code_subminor_version VARCHAR(32) NOT NULL, bots VARCHAR(255) NOT NULL, messages TEXT NOT NULL, menus TEXT NOT NULL, magicvars TEXT NOT NULL, primitives TEXT NOT NULL, program LONGTEXT NOT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY botbasic_version (botbasic_version), KEY code_name (code_name), KEY code_major_version (code_major_version), KEY code_minor_version (code_minor_version), KEY code_subminor_version (code_subminor_version), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS bbtunnel;CREATE TABLE IF NOT EXISTS bbtunnel ( id INT NOT NULL AUTO_INCREMENT, src_bbchannel_id INT NOT NULL, tgt_bbchannel_id INT NOT NULL, resource_type SMALLINT NOT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY src_bbchannel_id (src_bbchannel_id), KEY tgt_bbchannel_id (tgt_bbchannel_id), KEY resource_type (resource_type), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS bbvars;CREATE TABLE IF NOT EXISTS bbvars ( id INT NOT NULL AUTO_INCREMENT, bbruntime_id INT DEFAULT NULL, bbchannel_id INT DEFAULT NULL, source ENUM('runtime','bizmodeladapter') NOT NULL DEFAULT 'runtime', name VARCHAR(255) NOT NULL, value TEXT NOT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY bbruntime_id (bbruntime_id), KEY bbchannel_id (bbchannel_id), KEY source (source), KEY name (name), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS cmchannel;CREATE TABLE IF NOT EXISTS cmchannel ( id INT NOT NULL AUTO_INCREMENT, cm_type INT NOT NULL, cm_user_id VARCHAR(64) NOT NULL, cm_bot_name VARCHAR(64) NOT NULL, cm_chat_info VARCHAR(255) NOT NULL, bbchannel_id INT NOT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY cm_type (cm_type), KEY cm_user_id (cm_user_id), KEY cm_bot_name (cm_bot_name), KEY bbchannel_id (bbchannel_id), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS daemons_log_stamps;CREATE TABLE IF NOT EXISTS daemons_log_stamps ( id INT NOT NULL AUTO_INCREMENT, daemon ENUM('message','download') NOT NULL, cm_type TINYINT NOT NULL, stamp DATETIME NOT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY stamp (stamp), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS datahelper_data;CREATE TABLE IF NOT EXISTS datahelper_data ( id INT NOT NULL AUTO_INCREMENT, bbcode_cmid INT NOT NULL, bmuser_id INT NOT NULL, name VARCHAR(255) NOT NULL, value TEXT NOT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY bbcode_cmid (bbcode_cmid), KEY bmuser_id (bmuser_id), KEY name (name), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS interaction;CREATE TABLE IF NOT EXISTS interaction ( id INT NOT NULL AUTO_INCREMENT, type SMALLINT NOT NULL, subtype SMALLINT DEFAULT NULL, cm_type SMALLINT DEFAULT NULL, cm_bot_name VARCHAR(64) DEFAULT NULL, cm_sequence_id VARCHAR(64) DEFAULT NULL, cm_chat_info VARCHAR(255) DEFAULT NULL, cm_user_id VARCHAR(255) DEFAULT NULL, cm_user_name VARCHAR(255) DEFAULT NULL, cm_user_login VARCHAR(64) DEFAULT NULL, cm_user_language VARCHAR(64) DEFAULT NULL, cm_user_phone VARCHAR(255) DEFAULT NULL, bbchannel_id INT DEFAULT NULL, bizmodel_user_id INT DEFAULT NULL, text TEXT DEFAULT NULL, menu_hook VARCHAR(255) DEFAULT NULL, options TEXT DEFAULT NULL, created DATETIME NOT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY type (type), KEY cm_type (cm_type), KEY cm_bot_name (cm_bot_name), KEY cm_sequence_id (cm_sequence_id), KEY cm_chat_info (cm_chat_info), KEY created (created), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS menuhook_signature;CREATE TABLE IF NOT EXISTS menuhook_signature ( id INT NOT NULL AUTO_INCREMENT, data VARCHAR(255) NOT NULL, signature VARCHAR(32) NOT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS parser_user;CREATE TABLE IF NOT EXISTS parser_user ( id INT NOT NULL AUTO_INCREMENT, user_id VARCHAR(16) NOT NULL, password VARCHAR(64) NOT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS resource;CREATE TABLE IF NOT EXISTS resource ( id INT NOT NULL AUTO_INCREMENT, type SMALLINT NOT NULL, cloned_from_id INT DEFAULT NULL, chatmedium_type SMALLINT DEFAULT NULL, file_id VARCHAR(255) DEFAULT NULL, chatmedium_authinfo VARCHAR(255) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, metainfo VARCHAR(255) DEFAULT NULL, interaction_id INT DEFAULT NULL, download_state ENUM('nonapplicable','avoided','pending','doing','done','error') NOT NULL DEFAULT 'avoided', try_count TINYINT NOT NULL DEFAULT 0, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY type (type), KEY cloned_from_id (cloned_from_id), KEY chatmedium_type (chatmedium_type), KEY file_id (file_id), KEY interaction_id (interaction_id), KEY download_state (download_state), KEY try_count (try_count), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS runtime;CREATE TABLE IF NOT EXISTS runtime ( id INT NOT NULL AUTO_INCREMENT, bbcode_cmid INT NOT NULL, code_major_version VARCHAR(8) NOT NULL, code_minor_version VARCHAR(8) NOT NULL, code_subminor_version VARCHAR(8) NOT NULL, locale VARCHAR(8) NOT NULL, word VARCHAR(64), trace SMALLINT NOT NULL, bizmodel_user_id INT DEFAULT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY bbcode_cmid (bbcode_cmid), KEY code_major_version (code_major_version), KEY code_minor_version (code_minor_version), KEY bizmodel_user_id (bizmodel_user_id), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS telegram_logbot;CREATE TABLE IF NOT EXISTS telegram_logbot ( id INT NOT NULL AUTO_INCREMENT, bb_bot_id INT NOT NULL, cm_full_user_name VARCHAR(64) NOT NULL, cmchannel_id INT NOT NULL, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY bb_bot_id (bb_bot_id), KEY cm_full_user_name (cm_full_user_name), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
DROP TABLE IF EXISTS telegram_queue;CREATE TABLE IF NOT EXISTS telegram_queue ( id INT NOT NULL AUTO_INCREMENT, text TEXT, menu_options TEXT, resource TEXT, special_order TINYINT, special_order_arg TEXT, cmchannel_id INT NOT NULL, state ENUM('pending','sending','sent','error') NOT NULL DEFAULT 'pending', try_count TINYINT NOT NULL DEFAULT 0, deleted DATETIME DEFAULT NULL, updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, rand VARCHAR(8) NOT NULL DEFAULT 12345678 , PRIMARY KEY (id), KEY cmchannel_id (cmchannel_id), KEY state (state), KEY try_count (try_count), KEY deleted (deleted), KEY updated (updated) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- ADDITIONAL INDEXES
CREATE UNIQUE INDEX datahelper_data_comboidx ON datahelper_data (bbcode_cmid, bmuser_id, name);
CREATE UNIQUE INDEX telegram_logbot_comboidx ON telegram_logbot (bb_bot_id, cm_full_user_name);

-- MBSTRINGS
ALTER TABLE bbchannel CHANGE call_stack call_stack TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbchannel CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbchannel CHANGE route route TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbchannel CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE botbasic_version botbasic_version VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE bots bots VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE code_major_version code_major_version VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbvars CHANGE name name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbvars CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbvars CHANGE value value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbvars CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE cmchannel CHANGE cm_bot_name cm_bot_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE cmchannel CHANGE cm_chat_info cm_chat_info VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE cmchannel CHANGE cm_user_id cm_user_id VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE cmchannel CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE cmchannel CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE daemons_log_stamps CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE daemons_log_stamps CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE datahelper_data CHANGE name name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE datahelper_data CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE datahelper_data CHANGE value value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE datahelper_data CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE cm_bot_name cm_bot_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE cm_chat_info cm_chat_info VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE cm_sequence_id cm_sequence_id VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE cm_user_id cm_user_id VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE cm_user_language cm_user_language VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE cm_user_login cm_user_login VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE cm_user_name cm_user_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE cm_user_phone cm_user_phone VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE menu_hook menu_hook VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE options options TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CHANGE text text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE interaction CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE menuhook_signature CHANGE data data VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE menuhook_signature CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE menuhook_signature CHANGE signature signature VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE menuhook_signature CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE parser_user CHANGE password password VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE parser_user CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE parser_user CHANGE user_id user_id VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE parser_user CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE resource CHANGE chatmedium_authinfo chatmedium_authinfo VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE resource CHANGE file_id file_id VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE resource CHANGE filename filename VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE resource CHANGE metainfo metainfo VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE resource CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE resource CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE runtime CHANGE code_major_version code_major_version VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE runtime CHANGE code_minor_version code_minor_version VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE runtime CHANGE code_subminor_version code_subminor_version VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE runtime CHANGE locale locale VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE runtime CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE runtime CHANGE word word VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE runtime CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE telegram_logbot CHANGE cm_full_user_name cm_full_user_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE telegram_logbot CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE telegram_logbot CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE telegram_queue CHANGE menu_options menu_options TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE telegram_queue CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE telegram_queue CHANGE resource resource TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE telegram_queue CHANGE special_order_arg special_order_arg TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE telegram_queue CHANGE text text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE telegram_queue CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE code_minor_version code_minor_version VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE code_name code_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE code_subminor_version code_subminor_version VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE magicvars magicvars TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE menus menus TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE messages messages TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE primitives primitives TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE program program LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbcode CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbtunnel CHANGE rand rand VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bbtunnel CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- MIGRATIONS
ALTER TABLE telegram_queue ADD COLUMN thumb_resource TEXT NULL;
ALTER TABLE telegram_queue CHANGE thumb_resource thumb_resource TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- INITIAL VALUES
INSERT INTO daemons_log_stamps (daemon, cm_type, stamp, rand) VALUES ('download', 111, NOW(), ''), ('message', 111, NOW(), '');
-- INSERT INTO parser_user (user_id, password, rand) VALUES ('username', PASSWORD('password'), '');
