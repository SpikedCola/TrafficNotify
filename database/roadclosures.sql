-- phpMyAdmin SQL Dump
-- version 3.5.5
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Feb 17, 2013 at 06:55 PM
-- Server version: 5.5.24-log
-- PHP Version: 5.4.3

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `roadclosures`
--

-- --------------------------------------------------------

--
-- Table structure for table `descriptions`
--

CREATE TABLE IF NOT EXISTS `descriptions` (
  `description_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`description_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `detours`
--

CREATE TABLE IF NOT EXISTS `detours` (
  `detour_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `detour` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`detour_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `directions`
--

CREATE TABLE IF NOT EXISTS `directions` (
  `direction_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`direction_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=7 ;

-- --------------------------------------------------------

--
-- Table structure for table `freeway_incidents`
--

CREATE TABLE IF NOT EXISTS `freeway_incidents` (
  `freeway_incident_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `summary` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `highway_id` int(10) unsigned NOT NULL,
  `direction_id` int(10) unsigned NOT NULL,
  `location` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `from_at` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `to` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `lanes_affected` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `traffic_impact` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `event_start` int(10) unsigned NOT NULL,
  `event_end` int(10) unsigned NOT NULL,
  `last_change` int(10) unsigned NOT NULL,
  `last_change_reason` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`freeway_incident_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=185 ;

-- --------------------------------------------------------

--
-- Table structure for table `highways`
--

CREATE TABLE IF NOT EXISTS `highways` (
  `highway_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `highway` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`highway_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=17 ;

-- --------------------------------------------------------

--
-- Table structure for table `highway_incidents`
--

CREATE TABLE IF NOT EXISTS `highway_incidents` (
  `highway_incident_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `highway` int(10) unsigned NOT NULL,
  `location` int(10) unsigned NOT NULL,
  `traffic_impact` int(10) unsigned NOT NULL,
  `description` int(10) unsigned NOT NULL,
  `detour` int(10) unsigned NOT NULL,
  `event_start` int(10) unsigned NOT NULL,
  `event_end` int(10) unsigned NOT NULL,
  `last_updated` int(10) unsigned NOT NULL,
  `last_change` int(10) unsigned NOT NULL,
  PRIMARY KEY (`highway_incident_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE IF NOT EXISTS `incidents` (
  `incident_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('highway','freeway') COLLATE utf8_unicode_ci NOT NULL,
  `id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`incident_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE IF NOT EXISTS `locations` (
  `location_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `location` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `reasons`
--

CREATE TABLE IF NOT EXISTS `reasons` (
  `reason_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reason` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `traffic_impacts`
--

CREATE TABLE IF NOT EXISTS `traffic_impacts` (
  `traffic_impact_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `traffic_impact` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`traffic_impact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;
