/*
Navicat MySQL Data Transfer
*/

SET FOREIGN_KEY_CHECKS=0;
-- ----------------------------
-- Table structure for `beta_emails`
-- ----------------------------
DROP TABLE IF EXISTS `beta_emails`;
CREATE TABLE `beta_emails` (
  `id` int(11) NOT NULL auto_increment,
  `email` varchar(1024) NOT NULL,
  `landing` varchar(255) default NULL,
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=76 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of beta_emails
-- ----------------------------

-- ----------------------------
-- Table structure for `guestbook`
-- ----------------------------
DROP TABLE IF EXISTS `guestbook`;
CREATE TABLE `guestbook` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(250) NOT NULL,
  `content` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of guestbook
-- ----------------------------
INSERT INTO `guestbook` VALUES ('1', 'test line 1', 'test line 2');

-- ----------------------------
-- Table structure for `sys_abtests`
-- ----------------------------
DROP TABLE IF EXISTS `sys_abtests`;
CREATE TABLE `sys_abtests` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `domain` varchar(255) default NULL,
  `url` varchar(1024) default NULL,
  `lastupdate` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `sample_control` int(11) NOT NULL default '0',
  `sample_exp` int(11) NOT NULL default '0',
  `cr_control` int(11) NOT NULL default '0',
  `cr_exp` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=26 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of sys_abtests
-- ----------------------------

-- ----------------------------
-- Table structure for `sys_logs`
-- ----------------------------
DROP TABLE IF EXISTS `sys_logs`;
CREATE TABLE `sys_logs` (
  `id` int(11) NOT NULL auto_increment,
  `ip` varchar(18) NOT NULL,
  `agent` varchar(1024) NOT NULL,
  `referer` text,
  `url` text NOT NULL,
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `runtime` float NOT NULL,
  `permsid` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=299762 DEFAULT CHARSET=utf8;
