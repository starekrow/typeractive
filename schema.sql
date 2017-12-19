-- MySQL dump 10.13  Distrib 5.7.17, for macos10.12 (x86_64)
--
-- Host: localhost    Database: typeractive
-- ------------------------------------------------------
-- Server version	5.5.5-10.2.10-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `auth`
--

DROP TABLE IF EXISTS `auth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth` (
  `authid` bigint(20) NOT NULL AUTO_INCREMENT,
  `userid` bigint(20) DEFAULT NULL,
  `method` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `methodkey` varchar(128) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `token1` varbinary(1024) DEFAULT NULL,
  `token2` varbinary(255) DEFAULT NULL,
  `token3` varbinary(255) DEFAULT NULL,
  `expires` datetime DEFAULT NULL,
  PRIMARY KEY (`authid`),
  UNIQUE KEY `method` (`method`,`methodkey`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blogs`
--

DROP TABLE IF EXISTS `blogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blogs` (
  `blogid` bigint(20) NOT NULL AUTO_INCREMENT,
  `authorid` bigint(20) DEFAULT NULL,
  `biotext` bigint(20) DEFAULT NULL,
  `rootpost` bigint(20) DEFAULT NULL,
  `titletext` bigint(20) DEFAULT NULL,
  `headertext` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`blogid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `commentid` bigint(20) NOT NULL AUTO_INCREMENT,
  `authorid` bigint(20) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `textid` bigint(20) DEFAULT NULL,
  `state` varchar(16) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `postid` bigint(20) DEFAULT NULL,
  `parentid` bigint(20) DEFAULT NULL,
  `ctime` datetime DEFAULT NULL,
  `score` bigint(20) DEFAULT NULL,
  `ip` varbinary(16) DEFAULT NULL,
  PRIMARY KEY (`commentid`),
  KEY `post` (`postid`,`ctime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `eventid` bigint(20) NOT NULL AUTO_INCREMENT,
  `event` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `timestamp` datetime NOT NULL,
  `userid` bigint(20) DEFAULT NULL,
  `otherid` bigint(20) DEFAULT NULL,
  `data` varbinary(255) DEFAULT NULL,
  PRIMARY KEY (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `jobid` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `command` varchar(1024) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `data` blob DEFAULT NULL,
  `start` datetime NOT NULL,
  `repeat` int(11) DEFAULT NULL,
  `runlimit` int(11) DEFAULT NULL,
  `state` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires` datetime DEFAULT NULL,
  PRIMARY KEY (`jobid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `links`
--

DROP TABLE IF EXISTS `links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `links` (
  `linkid` bigint(20) NOT NULL AUTO_INCREMENT,
  `type` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `otherid` bigint(20) DEFAULT NULL,
  `link` varchar(255) NOT NULL,
  `parentid` bigint(20) DEFAULT NULL,
  `ownerid` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`linkid`),
  UNIQUE KEY `link` (`link`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `pageid` bigint(20) NOT NULL AUTO_INCREMENT,
  `ownerid` bigint(20) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `bodyid` bigint(20) DEFAULT NULL,
  `redirect` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`pageid`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `posts` (
  `postid` bigint(8) NOT NULL AUTO_INCREMENT,
  `blogid` bigint(8) NOT NULL,
  `textid` bigint(8) DEFAULT NULL,
  `postdate` datetime DEFAULT NULL,
  `authorid` bigint(8) DEFAULT NULL,
  `published` tinyint(4) DEFAULT 0,
  `titleid` bigint(8) DEFAULT NULL,
  `datelineid` bigint(8) DEFAULT NULL,
  `deleted` tinyint(4) DEFAULT 0,
  `draftid` bigint(8) DEFAULT NULL,
  `linkid` bigint(8) DEFAULT NULL,
  PRIMARY KEY (`postid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `priv_grant`
--

DROP TABLE IF EXISTS `priv_grant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `priv_grant` (
  `grantid` bigint(12) NOT NULL AUTO_INCREMENT,
  `grantor` bigint(12) NOT NULL,
  `grantee` bigint(12) NOT NULL,
  `priv` bigint(12) NOT NULL,
  `ctime` datetime NOT NULL,
  PRIMARY KEY (`grantid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `priv_lookup`
--

DROP TABLE IF EXISTS `priv_lookup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `priv_lookup` (
  `privid` bigint(12) NOT NULL AUTO_INCREMENT,
  `text` varchar(255) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  PRIMARY KEY (`privid`),
  UNIQUE KEY `text` (`text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `styles`
--

DROP TABLE IF EXISTS `styles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `styles` (
  `styleid` bigint(8) NOT NULL,
  `fortype` varchar(8) NOT NULL,
  `forid` bigint(8) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`styleid`),
  UNIQUE KEY `lookup` (`fortype`,`forid`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `text`
--

DROP TABLE IF EXISTS `text`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `text` (
  `textid` bigint(8) NOT NULL AUTO_INCREMENT,
  `authorid` bigint(8) DEFAULT NULL,
  `type` varchar(255) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `ctime` datetime NOT NULL,
  `mtime` datetime NOT NULL,
  `text` mediumtext NOT NULL,
  `historyid` bigint(8) DEFAULT NULL,
  `editorid` bigint(8) DEFAULT NULL,
  PRIMARY KEY (`textid`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `userid` bigint(8) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(45) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`userid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `views`
--

DROP TABLE IF EXISTS `views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `views` (
  `viewid` bigint(12) NOT NULL AUTO_INCREMENT,
  `type` varchar(16) NOT NULL,
  `subject` bigint(12) DEFAULT NULL,
  `subject2` bigint(12) DEFAULT NULL,
  `time` datetime NOT NULL,
  `url` bigint(12) DEFAULT NULL,
  `query` bigint(12) DEFAULT NULL,
  `referrer` bigint(12) DEFAULT NULL,
  `ip` varbinary(16) DEFAULT NULL,
  PRIMARY KEY (`viewid`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `viewtext`
--

DROP TABLE IF EXISTS `viewtext`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `viewtext` (
  `vtid` bigint(12) NOT NULL AUTO_INCREMENT,
  `text` varchar(2048) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  PRIMARY KEY (`vtid`),
  UNIQUE KEY `index2` (`text`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2017-12-18 17:38:20
