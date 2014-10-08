/*
Navicat MySQL Data Transfer

Source Server         : Custlabs Mainframe
Source Server Version : 50505
Source Host           : 127.0.0.1:3306
Source Database       : guestbook

Target Server Type    : MYSQL
Target Server Version : 50505
File Encoding         : 65001

Date: 2014-10-08 16:15:34
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `beta_emails`
-- ----------------------------
DROP TABLE IF EXISTS `beta_emails`;
CREATE TABLE `beta_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(1024) NOT NULL,
  `landing` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of beta_emails
-- ----------------------------

-- ----------------------------
-- Table structure for `site_guestbook`
-- ----------------------------
DROP TABLE IF EXISTS `site_guestbook`;
CREATE TABLE `site_guestbook` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  `content` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of site_guestbook
-- ----------------------------

-- ----------------------------
-- Table structure for `sys_logs`
-- ----------------------------
DROP TABLE IF EXISTS `sys_logs`;
CREATE TABLE `sys_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(18) NOT NULL,
  `agent` varchar(1024) NOT NULL,
  `referer` text,
  `url` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `runtime` float NOT NULL,
  `permsid` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of sys_logs
-- ----------------------------

-- ----------------------------
-- Table structure for `sys_sessions`
-- ----------------------------
DROP TABLE IF EXISTS `sys_sessions`;
CREATE TABLE `sys_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_cookie` varchar(64) NOT NULL,
  `session_data` text NOT NULL,
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cohort_date` datetime NOT NULL,
  `domain` varchar(64) NOT NULL DEFAULT '',
  `ishuman` int(11) NOT NULL DEFAULT '0',
  `isadmin` int(11) NOT NULL DEFAULT '0',
  `status` enum('unregistered','registered','returning') NOT NULL DEFAULT 'unregistered',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cookie_domain` (`session_cookie`,`domain`),
  KEY `idx_lastupdate` (`lastupdate`),
  KEY `idx_ishuman` (`ishuman`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of sys_sessions
-- ----------------------------

-- ----------------------------
-- Table structure for `sys_variations`
-- ----------------------------
DROP TABLE IF EXISTS `sys_variations`;
CREATE TABLE `sys_variations` (
  `test` varchar(64) NOT NULL,
  `variation` varchar(64) NOT NULL,
  `goal` varchar(64) NOT NULL,
  `url` varchar(512) NOT NULL,
  `content` mediumtext NOT NULL,
  `start_log_id` int(11) NOT NULL DEFAULT '0',
  `sample` int(11) NOT NULL DEFAULT '0',
  `conversion` int(11) NOT NULL DEFAULT '0',
  `completed` datetime DEFAULT NULL,
  PRIMARY KEY (`test`,`variation`,`goal`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Records of sys_variations
-- ----------------------------
