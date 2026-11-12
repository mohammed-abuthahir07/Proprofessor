-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: proprofessor
-- ------------------------------------------------------
-- Server version	11.8.9-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `proprofessor`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `proprofessor` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `proprofessor`;

--
-- Table structure for table `academic_events`
--

DROP TABLE IF EXISTS `academic_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `event_type` varchar(60) DEFAULT NULL,
  `event_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_events`
--

LOCK TABLES `academic_events` WRITE;
/*!40000 ALTER TABLE `academic_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(60) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_act_inst` (`institution_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,2,'logout',NULL,NULL,'::1',NULL,'2026-11-11 11:15:05'),(2,1,1,'login',NULL,NULL,'::1',NULL,'2026-11-11 11:15:25'),(3,1,1,'user_create','user',2,'::1','{\"role\":\"hod\"}','2026-11-11 11:15:51'),(4,1,1,'user_create','user',3,'::1','{\"role\":\"professor\"}','2026-11-11 11:16:13'),(5,1,1,'user_create','user',4,'::1','{\"role\":\"student\"}','2026-11-11 11:16:45'),(6,1,1,'logout',NULL,NULL,'::1',NULL,'2026-11-11 11:17:04'),(7,1,2,'login',NULL,NULL,'::1',NULL,'2026-11-11 11:17:16'),(8,1,2,'logout',NULL,NULL,'::1',NULL,'2026-11-11 11:17:52'),(9,1,3,'login',NULL,NULL,'::1',NULL,'2026-11-11 11:18:01'),(10,1,3,'login',NULL,NULL,'::1',NULL,'2026-11-11 11:25:59'),(11,1,3,'login',NULL,NULL,'::1',NULL,'2026-11-11 11:43:58'),(12,1,3,'logout',NULL,NULL,'::1',NULL,'2026-11-11 12:05:49'),(13,1,4,'login',NULL,NULL,'::1',NULL,'2026-11-11 12:06:09'),(14,1,4,'logout',NULL,NULL,'::1',NULL,'2026-11-11 12:06:26'),(15,1,3,'login',NULL,NULL,'::1',NULL,'2026-11-11 12:06:56'),(16,1,3,'logout',NULL,NULL,'::1',NULL,'2026-11-11 12:14:55'),(17,1,2,'login',NULL,NULL,'::1',NULL,'2026-11-11 12:15:03'),(18,1,2,'logout',NULL,NULL,'::1',NULL,'2026-11-11 12:15:16'),(19,1,3,'login',NULL,NULL,'::1',NULL,'2026-11-11 12:15:26');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_hod_announcements`
--

DROP TABLE IF EXISTS `admin_hod_announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_hod_announcements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `admin_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `recipient_count` int(10) unsigned NOT NULL DEFAULT 0,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_original_name` varchar(255) DEFAULT NULL,
  `attachment_mime_type` varchar(100) DEFAULT NULL,
  `attachment_size` int(10) unsigned DEFAULT NULL,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aha_inst` (`institution_id`,`created_at`),
  KEY `idx_aha_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_hod_announcements`
--

LOCK TABLES `admin_hod_announcements` WRITE;
/*!40000 ALTER TABLE `admin_hod_announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_hod_announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_chat_messages`
--

DROP TABLE IF EXISTS `ai_chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_chat_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` int(10) unsigned NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` longtext NOT NULL,
  `citations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`citations`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_msgs` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_chat_messages`
--

LOCK TABLES `ai_chat_messages` WRITE;
/*!40000 ALTER TABLE `ai_chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_chats`
--

DROP TABLE IF EXISTS `ai_chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_chats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_chats`
--

LOCK TABLES `ai_chats` WRITE;
/*!40000 ALTER TABLE `ai_chats` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_chats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_generations`
--

DROP TABLE IF EXISTS `ai_generations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_generations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `module` varchar(64) NOT NULL,
  `prompt_code` varchar(64) DEFAULT NULL,
  `input_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`input_payload`)),
  `output_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`output_payload`)),
  `model` varchar(80) DEFAULT NULL,
  `tokens_in` int(10) unsigned DEFAULT NULL,
  `tokens_out` int(10) unsigned DEFAULT NULL,
  `latency_ms` int(10) unsigned DEFAULT NULL,
  `status` enum('success','error','partial') DEFAULT 'success',
  `error_message` text DEFAULT NULL,
  `ref_type` varchar(40) DEFAULT NULL,
  `ref_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_user` (`user_id`),
  KEY `idx_ai_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_generations`
--

LOCK TABLES `ai_generations` WRITE;
/*!40000 ALTER TABLE `ai_generations` DISABLE KEYS */;
INSERT INTO `ai_generations` VALUES (1,1,3,'course_plan','course_plan','{\"subject\":\"Database Management Systems\",\"credits\":\"4\",\"university\":\"Autonomous\",\"template\":\"standard\",\"provider\":\"gemini\"}','{\"title\":\"Database Management Systems\",\"learning_outcomes\":[\"CO1: Understand and explain fundamental database concepts, architecture, and conceptual modeling using ER\\/EER diagrams (Bloom Level: K2).\",\"CO2: Apply relational algebra, tuple calculus, and SQL to write optimized queries for complex data retrieval and manipulation (Bloom Level: K3).\",\"CO3: Analyze relational database schemas and apply normalization techniques (1NF to BCNF\\/4NF) to eliminate anomalies (Bloom Level: K4).\",\"CO4: Evaluate transaction processing, concurrency control mechanisms, and crash recovery algorithms to ensure ACID properties (Bloom Level: K4).\",\"CO5: Design and implement physical storage organizations, indexing structures (B\\/B+ Trees), and analyze query execution plans alongside modern NoSQL architectures (Bloom Level: K5).\"],\"units\":[{\"unit_number\":1,\"title\":\"Introduction to DBMS and Conceptual Data Modeling\",\"hours\":10,\"topics\":[\"Data vs Information, Database System Concepts and Architecture\",\"Three-Schema Architecture and Data Independence\",\"Database Languages, Interfaces, and Overall System Structure\",\"Data Models: Hierarchical, Network, and Relational Foundations\",\"Entity-Relationship (ER) Modeling: Entities, Attributes, Relationships, Constraints\",\"Extended ER (EER) Modeling: Specialization, Generalization, and Categorization\",\"Mapping ER\\/EER Diagrams to Relational Schemas\"],\"outcomes\":[\"Illustrate the role of DBMS components and 3-schema architecture in abstraction.\",\"Construct comprehensive ER and EER diagrams for real-world enterprise applications.\",\"Transform conceptual ER schemas into normalized relational schemas.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Interactive Lecture\",\"Flipped Classroom\",\"Case-based Problem Solving (Enterprise Scenario Modeling)\"],\"assessment\":[\"Continuous Internal Assessment (CIA-1) Quiz\",\"ER Diagram Modeling Assignment on an Industry Case Study\",\"Formative Tutorial Worksheets\"]},{\"unit_number\":2,\"title\":\"Relational Model, Formal Languages, and Advanced SQL\",\"hours\":11,\"topics\":[\"Relational Model Concepts, Integrity Constraints (Domain, Key, Referential, Operational)\",\"Formal Relational Query Languages: Relational Algebra (Fundamental and Extended Operations)\",\"Tuple Relational Calculus and Domain Relational Calculus\",\"SQL Fundamentals: DDL, DML, DCL, and TCL Commands\",\"Complex SQL Queries: Nested Subqueries, Correlated Queries, Set Operations, Group By, Having\",\"Views, Materialized Views, Assertions, and Triggers\",\"Stored Procedures, Functions, and Embedded SQL\\/Cursor Management\"],\"outcomes\":[\"Formulate relational algebraic expressions for procedural data retrieval.\",\"Develop complex, performant SQL queries using nested subqueries, joins, and aggregates.\",\"Implement declarative integrity rules, triggers, and stored routines to automate business logic.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Live Coding Demonstrations\",\"Collaborative Lab Sprints\",\"Think-Pair-Share on Query Optimization\"],\"assessment\":[\"Hands-on SQL Coding Lab Exam (PostgreSQL\\/MySQL)\",\"Mid-Term Test Problem on Relational Algebra\",\"Peer-reviewed Database Scripting Assignment\"]},{\"unit_number\":3,\"title\":\"Relational Database Design and Normalization Theory\",\"hours\":10,\"topics\":[\"Informal Design Guidelines for Relation Schemas and Data Redundancy Pitfalls\",\"Functional Dependencies (FDs): Definition, Trivial\\/Non-trivial, Closure of FDs\",\"Armstrong\'s Axioms and Canonical Minimal Cover Algorithm\",\"Normal Forms based on Primary Keys: 1NF, 2NF, 3NF\",\"Boyce-Codd Normal Form (BCNF): Decomposition, Testing, and Limitations\",\"Lossless Join Decomposition and Dependency Preservation Properties\",\"Multi-valued Dependencies and 4NF; Join Dependencies and 5NF Overview\"],\"outcomes\":[\"Compute closure of attribute sets and derive minimal canonical covers of functional dependencies.\",\"Evaluate database schemas against normal form criteria (1NF through BCNF).\",\"Synthesize lossless-join and dependency-preserving decompositions for enterprise relations.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Chalk-and-Board Derivations\",\"Algorithmic Walkthroughs\",\"Guided Error-Analysis Sessions on Schema Anomalies\"],\"assessment\":[\"Class Test on Normalization & Dependency Preservation\",\"Graded Analytical Homework on Canonical Cover and BCNF Decomposition\",\"Open-Book Schema Refactoring Challenge\"]},{\"unit_number\":4,\"title\":\"Transaction Processing, Concurrency Control, and Recovery\",\"hours\":10,\"topics\":[\"Transaction Concepts: ACID Properties, Transaction States, and Concurrent Executions\",\"Schedules: Serializability, Conflict Serializability, and View Serializability\",\"Testing for Conflict Serializability using Precedence Graphs\",\"Recoverability, Cascadelessness, and Strict Schedules\",\"Concurrency Control Protocols: Lock-based (2PL, Strict 2PL, Rigorous 2PL), Deadlock Handling\",\"Timestamp-based Protocols and Thomas\' Write Rule; Multiple Granularity Locking\",\"Database Recovery Techniques: Write-Ahead Logging (WAL), Checkpointing, ARIES Recovery Algorithm\"],\"outcomes\":[\"Analyze transaction schedules for conflict serializability and recoverability.\",\"Compare locking and timestamping concurrency protocols under varied workload contentions.\",\"Design crash-recovery strategies utilizing WAL and ARIES algorithm mechanics.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Scenario-based Conflict Tracing\",\"Interactive Simulation Tools for Lock Contention\",\"Socratic Seminar on Isolation vs Performance Trade-offs\"],\"assessment\":[\"Mid-Term Exam Evaluation\",\"Serializability Precedence Graph Analytical Assignment\",\"Online Diagnostic Quiz on Deadlock Resolution Techniques\"]},{\"unit_number\":5,\"title\":\"Storage, Indexing, Query Optimization, and Modern Databases\",\"hours\":9,\"topics\":[\"Physical Storage Organization: File Organization, Record Formats, Buffer Pool Management\",\"Index Structures for Files: Primary, Secondary, Clustering Indexes\",\"Multi-level Indexing, Dynamic Multilevel Indexes using B-Trees and B+ Trees\",\"Static and Dynamic Hashing Techniques (Extendible and Linear Hashing)\",\"Query Processing Overview: Translation, Heuristic Optimization, Cost-based Estimation\",\"Modern Database Architectures: Distributed DBs, CAP Theorem, NoSQL Data Stores (Key-Value, Document, Columnar, Graph)\",\"Cloud-Native Database Systems and HTAP Engines Introduction\"],\"outcomes\":[\"Calculate storage, tree-height, and block access costs for B+ tree indexing structures.\",\"Demonstrate relational query optimization using algebraic transformation heuristics.\",\"Appraise modern NoSQL models against traditional RDBMS based on CAP theorem constraints.\"],\"bloom_k_level\":\"K5\",\"teaching_methods\":[\"Project-Based Learning (Capstone Schema Implementation)\",\"Comparative Architecture Seminar\",\"Query Execution Plan Analysis using EXPLAIN ANALYZE\"],\"assessment\":[\"End-Semester Project Submission (Full-Stack Relational\\/NoSQL Application)\",\"Technical Seminar Presentation on NoSQL vs Relational Paradigms\",\"Comprehensive End-Semester Theory & Practical Examination\"]}],\"weekly_plan\":[\"Week 1: Introduction to DBMS Architecture, 3-Schema Architecture, Data Independence, System Catalogs.\",\"Week 2: ER Modeling, Entity Types, Relationship Constraints, Cardinality Ratios, Weak Entities.\",\"Week 3: Enhanced ER (EER) Modeling, Generalization\\/Specialization, Schema mapping algorithms.\",\"Week 4: Relational Model Constraints, Relational Algebra fundamental & composite operations.\",\"Week 5: SQL DDL, DML, Complex Queries, Grouping, Aggregate operators, Nested Subqueries.\",\"Week 6: Views, Triggers, Stored Procedures, PL\\/SQL blocks, and SQL performance considerations.\",\"Week 7: Functional Dependencies, Attribute Closures, Canonical Covers, and Anomalies demonstration.\",\"Week 8: Relational Normalization: 1NF, 2NF, 3NF, and BCNF testing with decomposition rules.\",\"Week 9: Lossless Decomposition, Dependency Preservation proofs, Introduction to 4NF\\/5NF.\",\"Week 10: Transaction Management, ACID properties, Conflict and View Serializability analysis.\",\"Week 11: Concurrency Control: Two-Phase Locking (2PL), Deadlock Detection and Prevention.\",\"Week 12: Timestamp Ordering, Granularity of Data Items, Recovery concepts, WAL, and Checkpoints.\",\"Week 13: Physical File Storage, Primary, Clustering, Secondary Indexes, Static & Dynamic Hashing.\",\"Week 14: Search Trees, B-Tree and B+ Tree insert\\/delete algorithms and disk block cost modeling.\",\"Week 15: Query Processing and Optimization, Modern Distributed NoSQL architectures and CAP Theorem review.\"],\"resources\":[\"Abraham Silberschatz, Henry F. Korth, S. Sudarshan, \'Database System Concepts\', 7th Edition, McGraw-Hill, 2019.\",\"Ramez Elmasri, Shamkant B. Navathe, \'Fundamentals of Database Systems\', 7th Edition, Pearson, 2016.\",\"Raghu Ramakrishnan, Johannes Gehrke, \'Database Management Systems\', 3rd Edition, McGraw-Hill, 2003.\",\"PostgreSQL Documentation & Enterprise Execution Plan Manuals (Online Resource).\",\"NPTEL Course: Database Management System by Prof. Partha Pratim Das (IIT Kharagpur).\"],\"expert_advice\":[\"Emphasize rigorous mathematical foundations in Unit 2 (Relational Algebra) to build solid intuitions for SQL query engines.\",\"In Unit 3, avoid mechanical decomposition; mandate that students trace actual data anomalies (insertion, deletion, update) before and after normalization.\",\"Utilize EXPLAIN and EXPLAIN ANALYZE commands in PostgreSQL during Unit 5 to bridge the gap between theoretical query cost models and practical disk execution plans.\",\"Align student term projects with NBA Criterion 3 (Complex Engineering Problems) by enforcing concurrent access requirements, trigger-based audits, and foreign key cascading constraints.\"],\"bloom_distribution\":{\"K1\":5,\"K2\":15,\"K3\":30,\"K4\":35,\"K5\":15,\"K6\":0},\"ai_score\":{\"obe_compliance_rating\":0.98,\"nba_gapc_v4_alignment\":0.96,\"naac_binary_readiness\":0.97,\"rigor_index\":0.94}}','gemini-3.8-flash',NULL,NULL,138209,'success',NULL,NULL,NULL,'2026-11-11 11:48:11'),(2,1,3,'course_plan','course_plan','{\"subject\":\"Database Management Systems\",\"credits\":\"4\",\"university\":\"Autonomous\",\"template\":\"standard\",\"provider\":\"gemini\"}','{\"title\":\"Database Management Systems\",\"learning_outcomes\":[\"Understand the fundamental concepts of Database Management Systems and data models.\",\"Design entity-relationship diagrams and map them to relational database schemas.\",\"Formulate complex SQL queries using DDL, DML, DCL, and TCL commands.\",\"Apply normalization techniques to reduce data redundancy and maintain integrity.\",\"Analyze transaction management, concurrency control protocols, and recovery techniques.\"],\"units\":[{\"unit_number\":1,\"title\":\"Introduction to DBMS and ER Model\",\"hours\":12,\"topics\":[\"Database System Applications\",\"Purpose of Database Systems\",\"View of Data\",\"Data Independence\",\"Database Languages\",\"Data Models\",\"Database Architecture\",\"Entity-Relationship (ER) Model\",\"Mapping Cardinalities\",\"Keys\",\"ER Diagrams\",\"Reduction to Relational Schemas\"],\"outcomes\":[\"Explain the role of DBMS in modern enterprise applications.\",\"Construct ER diagrams for real-world scenarios.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Chalk and Talk\",\"PowerPoint Presentation\",\"Case Study Discussion\"],\"assessment\":[\"Quiz 1\",\"Assignment 1\",\"Internal Test 1\"]},{\"unit_number\":2,\"title\":\"Relational Model and Structured Query Language (SQL)\",\"hours\":15,\"topics\":[\"Structure of Relational Databases\",\"Fundamental Relational Algebra Operations\",\"Extended Relational Algebra Operations\",\"SQL Data Definition\",\"Basic Structure of SQL Queries\",\"Set Operations\",\"Aggregate Functions\",\"Null Values\",\"Nested Subqueries\",\"Complex Queries\",\"Views\",\"Joined Relations\"],\"outcomes\":[\"Execute relational algebra operations.\",\"Write efficient SQL queries for data retrieval and manipulation.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Interactive Lab Sessions\",\"Live SQL Coding Demonstrations\"],\"assessment\":[\"Lab Practical 1\",\"SQL Coding Assignment\",\"Internal Test 1\"]},{\"unit_number\":3,\"title\":\"Database Design and Normalization\",\"hours\":12,\"topics\":[\"Features of Good Relational Design\",\"Atomic Domains and First Normal Form\",\"Decomposition Using Functional Dependencies\",\"Functional Dependency Theory\",\"Algorithms for Functional Dependencies\",\"Closure of Set of Functional Dependencies\",\"Closure of Attribute Sets\",\"Canonical Cover\",\"Boyer-Codd Normal Form (BCNF)\",\"Third Normal Form (3NF)\",\"Fourth Normal Form (4NF)\",\"Multi-valued Dependencies\"],\"outcomes\":[\"Analyze functional dependencies and determine attribute closures.\",\"Normalize relational schemas up to BCNF.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Problem-Solving Sessions\",\"Peer Teaching\"],\"assessment\":[\"Assignment 2\",\"Quiz 2\",\"Internal Test 2\"]},{\"unit_number\":4,\"title\":\"Transactions and Concurrency Control\",\"hours\":11,\"topics\":[\"Transaction Concept\",\"Simple Transaction Model\",\"Storage Structure\",\"Transaction Atomicity and Durability\",\"Transaction Isolation\",\"Serializability\",\"Recoverable Schedules\",\"Cascadeless Schedules\",\"Concurrency Control\",\"Lock-Based Protocols\",\"Timestamp-Based Protocols\",\"Validation-Based Protocols\",\"Deadlock Handling\"],\"outcomes\":[\"Verify the serializability of transaction schedules.\",\"Compare concurrency control protocols and deadlock management techniques.\"],\"bloom_k_level\":\"K5\",\"teaching_methods\":[\"Lecture\",\"Simulations\",\"Case Study Analysis\"],\"assessment\":[\"Assignment 3\",\"Internal Test 2\",\"End Semester Exam\"]},{\"unit_number\":5,\"title\":\"Recovery System and Advanced Databases\",\"hours\":10,\"topics\":[\"Failure Classification\",\"Storage\",\"Recovery and Atomicity\",\"Log-Based Recovery\",\"Checkpoints\",\"Shadow Paging\",\"Recovery with Concurrent Transactions\",\"Introduction to NoSQL Databases\",\"CAP Theorem\",\"Introduction to Distributed Databases\"],\"outcomes\":[\"Explain log-based recovery and checkpointing mechanisms.\",\"Compare traditional RDBMS with NoSQL and distributed databases.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Flipped Classroom\",\"Technical Seminar\"],\"assessment\":[\"Quiz 3\",\"End Semester Exam\"]}],\"weekly_plan\":[\"Week 1: Introduction to Database System Applications and Architecture\",\"Week 2: ER Modeling, Mapping Cardinalities, and ER Diagrams\",\"Week 3: Reduction of ER to Relational Schemas and Relational Algebra Basics\",\"Week 4: Extended Relational Algebra Operations and Introduction to SQL\",\"Week 5: SQL DDL, DML, Basic Queries, and Aggregate Functions\",\"Week 6: Nested Subqueries, Joins, and Views in SQL\",\"Week 7: Database Design Goals, Functional Dependencies, and Attribute Closures\",\"Week 8: Normalization - 1NF, 2NF, 3NF, and BCNF\",\"Week 9: Advanced Normalization, Multi-valued Dependencies, and 4NF\",\"Week 10: Transaction Concept, ACID Properties, and Serializability\",\"Week 11: Concurrency Control - Lock-based and Timestamp-based Protocols\",\"Week 12: Deadlock Handling and Recovery System Basics\",\"Week 13: Log-based Recovery, Checkpoints, and Shadow Paging\",\"Week 14: Introduction to NoSQL Databases and CAP Theorem\",\"Week 15: Revision, Doubt Clearing, and Mock Practical Exams\"],\"resources\":[\"Silberschatz, A., Korth, H. F., & Sudarshan, S., Database System Concepts, McGraw-Hill.\",\"Elmasri, R., & Navathe, S. B., Fundamentals of Database Systems, Pearson.\",\"Ramakrishnan, R., & Gehrke, J., Database Management Systems, McGraw-Hill.\"],\"expert_advice\":[\"Emphasize hands-on SQL practice in a dedicated database lab to reinforce theoretical concepts.\",\"Use real-world database design case studies to explain normalization and functional dependencies effectively.\",\"Align continuous internal assessments with Bloom\'s taxonomy levels K1 through K5.\"],\"bloom_distribution\":{\"K1\":10,\"K2\":20,\"K3\":35,\"K4\":25,\"K5\":10,\"K6\":0},\"ai_score\":95.5}','gemini-3.5-flash-lite',NULL,NULL,9578,'success',NULL,NULL,NULL,'2026-11-11 11:59:08'),(3,1,3,'lesson','lesson','{\"plan_id\":1,\"provider\":\"gemini\"}','{\"sessions\":[{\"session_number\":1,\"title\":\"Database System Applications & Purpose\",\"duration_mins\":60,\"objectives\":[\"Explain the role of DBMS in modern enterprise applications.\",\"Understand the purpose of database systems compared to file systems.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Discussion on real-world data management challenges.\"],\"formative_assessment\":[\"Quick Q&A session\"],\"engagement\":[\"Real-world scenario brainstorming\"]},{\"session_number\":2,\"title\":\"View of Data and Data Independence\",\"duration_mins\":60,\"objectives\":[\"Describe data abstraction levels.\",\"Differentiate between physical and logical data independence.\"],\"teaching_method\":\"PowerPoint Presentation\",\"activities\":[\"Architecture diagram walkthrough\"],\"formative_assessment\":[\"Concept check quiz\"],\"engagement\":[\"Think-pair-share\"]},{\"session_number\":3,\"title\":\"Database Languages and Data Models\",\"duration_mins\":60,\"objectives\":[\"Identify DDL and DML commands.\",\"Compare different data models.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Categorizing database language statements\"],\"formative_assessment\":[\"Short written check\"],\"engagement\":[\"Interactive board work\"]},{\"session_number\":4,\"title\":\"Database Architecture\",\"duration_mins\":60,\"objectives\":[\"Understand 2-tier and 3-tier client-server architectures.\"],\"teaching_method\":\"PowerPoint Presentation\",\"activities\":[\"Drawing architectural layers\"],\"formative_assessment\":[\"Labeling diagram exercise\"],\"engagement\":[\"Group discussion\"]},{\"session_number\":5,\"title\":\"Introduction to Entity-Relationship (ER) Model\",\"duration_mins\":60,\"objectives\":[\"Define entities, attributes, and relationships.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Identifying entities from a mini-case study\"],\"formative_assessment\":[\"Quiz 1\"],\"engagement\":[\"Live sketching on board\"]},{\"session_number\":6,\"title\":\"Mapping Cardinalities and Keys\",\"duration_mins\":60,\"objectives\":[\"Determine mapping cardinalities (1:1, 1:N, N:M).\",\"Identify primary, candidate, and foreign keys.\"],\"teaching_method\":\"Case Study Discussion\",\"activities\":[\"Key identification exercise\"],\"formative_assessment\":[\"Assignment 1 distribution and review\"],\"engagement\":[\"Peer review of keys\"]},{\"session_number\":7,\"title\":\"ER Diagrams - Part 1\",\"duration_mins\":60,\"objectives\":[\"Construct ER diagrams for simple scenarios.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Step-by-step ER diagram construction\"],\"formative_assessment\":[\"In-class drawing check\"],\"engagement\":[\"Collaborative whiteboard\"]},{\"session_number\":8,\"title\":\"ER Diagrams - Part 2\",\"duration_mins\":60,\"objectives\":[\"Handle complex relationships and weak entities in ER diagrams.\"],\"teaching_method\":\"Case Study Discussion\",\"activities\":[\"University database schema design\"],\"formative_assessment\":[\"Design critique\"],\"engagement\":[\"Small group design challenge\"]},{\"session_number\":9,\"title\":\"Reduction of ER Schemas to Relational Schemas - Part 1\",\"duration_mins\":60,\"objectives\":[\"Map strong and weak entity sets to tables.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Translation exercise from ER to relational tables\"],\"formative_assessment\":[\"Schema verification check\"],\"engagement\":[\"Guided practice\"]},{\"session_number\":10,\"title\":\"Reduction of ER Schemas to Relational Schemas - Part 2\",\"duration_mins\":60,\"objectives\":[\"Map relationship sets and multivalued attributes.\"],\"teaching_method\":\"PowerPoint Presentation\",\"activities\":[\"Mapping N:M relationships\"],\"formative_assessment\":[\"Internal Test 1 preparation quiz\"],\"engagement\":[\"Q&A session\"]},{\"session_number\":11,\"title\":\"Unit 1 Review and Problem Solving\",\"duration_mins\":60,\"objectives\":[\"Review concepts from Unit 1.\",\"Solve comprehensive ER modeling problems.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Solving past exam questions\"],\"formative_assessment\":[\"Internal Test 1\"],\"engagement\":[\"Competitive quiz\"]},{\"session_number\":12,\"title\":\"Unit 1 Wrap-up & Conceptual Clearance\",\"duration_mins\":60,\"objectives\":[\"Address doubts from Internal Test 1.\"],\"teaching_method\":\"Case Study Discussion\",\"activities\":[\"Test debrief and paper review\"],\"formative_assessment\":[\"Self-assessment rubric\"],\"engagement\":[\"Open forum discussion\"]},{\"session_number\":13,\"title\":\"Structure of Relational Databases\",\"duration_mins\":60,\"objectives\":[\"Understand relational model concepts and integrity constraints.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Exploring table structures\"],\"formative_assessment\":[\"Lab check\"],\"engagement\":[\"Hands-on terminal work\"]},{\"session_number\":14,\"title\":\"Fundamental Relational Algebra Operations\",\"duration_mins\":60,\"objectives\":[\"Execute Select, Project, Union, Set Difference, and Cartesian Product.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Writing relational algebra expressions\"],\"formative_assessment\":[\"Worksheet evaluation\"],\"engagement\":[\"Board problem solving\"]},{\"session_number\":15,\"title\":\"Extended Relational Algebra Operations\",\"duration_mins\":60,\"objectives\":[\"Apply Join, Division, and Assignment operations.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Complex relational algebra derivation\"],\"formative_assessment\":[\"Pop quiz\"],\"engagement\":[\"Pair programming equivalent logic\"]},{\"session_number\":16,\"title\":\"SQL Data Definition and Basic Queries\",\"duration_mins\":60,\"objectives\":[\"Use DDL commands to create databases and tables.\",\"Write basic SELECT-FROM-WHERE queries.\"],\"teaching_method\":\"Live SQL Coding Demonstrations\",\"activities\":[\"Creating tables in SQL environment\"],\"formative_assessment\":[\"Lab Practical 1\"],\"engagement\":[\"Live coding along with instructor\"]},{\"session_number\":17,\"title\":\"SQL Set Operations and Aggregate Functions\",\"duration_mins\":60,\"objectives\":[\"Use UNION, INTERSECT, EXCEPT.\",\"Apply COUNT, SUM, AVG, MAX, MIN with GROUP BY and HAVING.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Writing aggregation queries\"],\"formative_assessment\":[\"SQL Coding Assignment task 1\"],\"engagement\":[\"Troubleshooting syntax errors in pairs\"]},{\"session_number\":18,\"title\":\"Null Values and Handling Missing Data\",\"duration_mins\":60,\"objectives\":[\"Understand the impact of NULL in SQL operations and three-valued logic.\"],\"teaching_method\":\"Live SQL Coding Demonstrations\",\"activities\":[\"Querying datasets with null values\"],\"formative_assessment\":[\"Query output prediction check\"],\"engagement\":[\"Interactive polling\"]},{\"session_number\":19,\"title\":\"Nested Subqueries - Part 1\",\"duration_mins\":60,\"objectives\":[\"Formulate subqueries in WHERE clause using IN, NOT IN, EXISTS.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Solving nested query exercises\"],\"formative_assessment\":[\"Lab assessment\"],\"engagement\":[\"Guided coding\"]},{\"session_number\":20,\"title\":\"Nested Subqueries - Part 2\",\"duration_mins\":60,\"objectives\":[\"Use scalar subqueries and correlated subqueries.\"],\"teaching_method\":\"Live SQL Coding Demonstrations\",\"activities\":[\"Writing correlated subqueries\"],\"formative_assessment\":[\"SQL Coding Assignment task 2\"],\"engagement\":[\"Code optimization challenge\"]},{\"session_number\":21,\"title\":\"Complex SQL Queries\",\"duration_mins\":60,\"objectives\":[\"Combine joins, subqueries, and grouping for advanced data retrieval.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Enterprise reporting query design\"],\"formative_assessment\":[\"Peer review of queries\"],\"engagement\":[\"Hackathon style query writing\"]},{\"session_number\":22,\"title\":\"Views in SQL\",\"duration_mins\":60,\"objectives\":[\"Create, update, and drop virtual tables (views).\"],\"teaching_method\":\"Live SQL Coding Demonstrations\",\"activities\":[\"Implementing security and abstraction via views\"],\"formative_assessment\":[\"Lab evaluation\"],\"engagement\":[\"Hands-on practice\"]},{\"session_number\":23,\"title\":\"Joined Relations in SQL\",\"duration_mins\":60,\"objectives\":[\"Master Inner, Left Outer, Right Outer, and Full Outer Joins.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Visualizing join outputs with sample data\"],\"formative_assessment\":[\"Join query quiz\"],\"engagement\":[\"Diagramming join sets\"]},{\"session_number\":24,\"title\":\"Advanced SQL Practice\",\"duration_mins\":60,\"objectives\":[\"Reinforce all SQL concepts learned so far.\"],\"teaching_method\":\"Live SQL Coding Demonstrations\",\"activities\":[\"Comprehensive lab problem set\"],\"formative_assessment\":[\"SQL Coding Assignment submission\"],\"engagement\":[\"Speed coding challenge\"]},{\"session_number\":25,\"title\":\"Unit 2 Review and Lab Test Preparation\",\"duration_mins\":60,\"objectives\":[\"Summarize relational algebra and SQL commands.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Mock lab exam\"],\"formative_assessment\":[\"Internal Test 1 review component\"],\"engagement\":[\"Doubt clearing session\"]},{\"session_number\":26,\"title\":\"Unit 2 Assessment Review\",\"duration_mins\":60,\"objectives\":[\"Evaluate SQL coding solutions and common errors.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Code walkthrough and optimization\"],\"formative_assessment\":[\"Internal Test 1 SQL evaluation\"],\"engagement\":[\"Code refactoring workshop\"]},{\"session_number\":27,\"title\":\"Features of Good Relational Design\",\"duration_mins\":60,\"objectives\":[\"Identify anomalies (insertion, deletion, update) in poorly designed schemas.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Analyzing bad database designs\"],\"formative_assessment\":[\"Short quiz\"],\"engagement\":[\"Case study discussion\"]},{\"session_number\":28,\"title\":\"Atomic Domains and First Normal Form (1NF)\",\"duration_mins\":60,\"objectives\":[\"Understand atomicity of values and convert unnormalized tables to 1NF.\"],\"teaching_method\":\"Peer Teaching\",\"activities\":[\"Converting nested tables to flat relations\"],\"formative_assessment\":[\"Worksheet check\"],\"engagement\":[\"Peer explanation activity\"]},{\"session_number\":29,\"title\":\"Functional Dependency Theory\",\"duration_mins\":60,\"objectives\":[\"Define functional dependencies (FDs) and inference rules (Armstrong\'s axioms).\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Deriving FDs from business rules\"],\"formative_assessment\":[\"Assignment 2 release\"],\"engagement\":[\"Guided problem solving\"]},{\"session_number\":30,\"title\":\"Closure of a Set of Functional Dependencies\",\"duration_mins\":60,\"objectives\":[\"Compute the closure of a set of FDs ($F^+$).\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Computing FD closures on the board\"],\"formative_assessment\":[\"Board work check\"],\"engagement\":[\"Interactive derivation\"]},{\"session_number\":31,\"title\":\"Closure of Attribute Sets\",\"duration_mins\":60,\"objectives\":[\"Compute attribute closures ($X^+$) to test superkeys and candidate keys.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Finding candidate keys using attribute closure algorithm\"],\"formative_assessment\":[\"Quiz 2\"],\"engagement\":[\"Step-by-step group calculation\"]},{\"session_number\":32,\"title\":\"Canonical Cover\",\"duration_mins\":60,\"objectives\":[\"Find the canonical cover ($Fc$) for a set of functional dependencies.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Extraneous attribute elimination exercises\"],\"formative_assessment\":[\"Problem set check\"],\"engagement\":[\"Peer checking\"]},{\"session_number\":33,\"title\":\"Decomposition Using Functional Dependencies\",\"duration_mins\":60,\"objectives\":[\"Understand lossless-join and dependency-preserving decompositions.\"],\"teaching_method\":\"Peer Teaching\",\"activities\":[\"Testing decompositions for losslessness\"],\"formative_assessment\":[\"Formative test\"],\"engagement\":[\"Small group teaching\"]},{\"session_number\":34,\"title\":\"Third Normal Form (3NF)\",\"duration_mins\":60,\"objectives\":[\"Define 3NF and test schemas for 3NF compliance.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Synthesizing 3NF relations\"],\"formative_assessment\":[\"Assignment 2 submission\"],\"engagement\":[\"Guided design exercise\"]},{\"session_number\":35,\"title\":\"Boyer-Codd Normal Form (BCNF)\",\"duration_mins\":60,\"objectives\":[\"Define BCNF and decompose schemas that violate BCNF.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"BCNF decomposition algorithm practice\"],\"formative_assessment\":[\"Internal Test 2 preparation quiz\"],\"engagement\":[\"Board race problem solving\"]},{\"session_number\":36,\"title\":\"Multi-valued Dependencies and Fourth Normal Form (4NF)\",\"duration_mins\":60,\"objectives\":[\"Identify multi-valued dependencies and apply 4NF.\"],\"teaching_method\":\"Peer Teaching\",\"activities\":[\"Analyzing multi-valued anomaly cases\"],\"formative_assessment\":[\"Concept check\"],\"engagement\":[\"Discussion panel\"]},{\"session_number\":37,\"title\":\"Normalization Practice Workshop\",\"duration_mins\":60,\"objectives\":[\"Practice end-to-end normalization from unnormalized forms to BCNF\\/4NF.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Comprehensive case study normalization\"],\"formative_assessment\":[\"Internal Test 2\"],\"engagement\":[\"Collaborative problem solving\"]},{\"session_number\":38,\"title\":\"Unit 3 Review and Doubt Clearing\",\"duration_mins\":60,\"objectives\":[\"Review normalization and functional dependency algorithms.\"],\"teaching_method\":\"Peer Teaching\",\"activities\":[\"Student-led review of difficult topics\"],\"formative_assessment\":[\"Internal Test 2 review\"],\"engagement\":[\"Q&A session\"]},{\"session_number\":39,\"title\":\"Transaction Concept and Simple Transaction Model\",\"duration_mins\":60,\"objectives\":[\"Understand ACID properties of transactions.\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Bank transfer transaction walkthrough\"],\"formative_assessment\":[\"Assignment 3 release\"],\"engagement\":[\"Real-world analogy discussion\"]},{\"session_number\":40,\"title\":\"Transaction Atomicity, Durability, and Storage Structure\",\"duration_mins\":60,\"objectives\":[\"Examine how atomicity and durability are maintained via storage management.\"],\"teaching_method\":\"Simulations\",\"activities\":[\"Transaction failure simulation\"],\"formative_assessment\":[\"Short quiz\"],\"engagement\":[\"Interactive simulation tool\"]},{\"session_number\":41,\"title\":\"Transaction Isolation and Serializability\",\"duration_mins\":60,\"objectives\":[\"Define conflict and view serializability.\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Drawing precedence graphs to test serializability\"],\"formative_assessment\":[\"Worksheet check\"],\"engagement\":[\"Pair graph validation\"]},{\"session_number\":42,\"title\":\"Recoverable and Cascadeless Schedules\",\"duration_mins\":60,\"objectives\":[\"Distinguish between recoverable, cascadeless, and non-recoverable schedules.\"],\"teaching_method\":\"Case Study Analysis\",\"activities\":[\"Evaluating schedule logs\"],\"formative_assessment\":[\"Schedule analysis check\"],\"engagement\":[\"Group categorization activity\"]},{\"session_number\":43,\"title\":\"Concurrency Control and Lock-Based Protocols\",\"duration_mins\":60,\"objectives\":[\"Understand shared and exclusive locks.\",\"Apply Two-Phase Locking (2PL) protocol.\"],\"teaching_method\":\"Simulations\",\"activities\":[\"Lock compatibility matrix application\"],\"formative_assessment\":[\"Assignment 3 check\"],\"engagement\":[\"Interactive locking simulation\"]},{\"session_number\":44,\"title\":\"Timestamp-Based and Validation-Based Protocols\",\"duration_mins\":60,\"objectives\":[\"Implement timestamp ordering protocol.\",\"Understand validation-based concurrency control.\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Timestamp conflict resolution exercises\"],\"formative_assessment\":[\"Pop quiz\"],\"engagement\":[\"Step-by-step walk-through\"]},{\"session_number\":45,\"title\":\"Deadlock Handling\",\"duration_mins\":60,\"objectives\":[\"Analyze deadlock prevention, detection, and recovery mechanisms.\"],\"teaching_method\":\"Case Study Analysis\",\"activities\":[\"Wait-for graph construction and cycle detection\"],\"formative_assessment\":[\"Deadlock quiz\"],\"engagement\":[\"Group scenario analysis\"]},{\"session_number\":46,\"title\":\"Transactions & Concurrency Control Problem Solving\",\"duration_mins\":60,\"objectives\":[\"Solve complex scheduling, locking, and deadlock problems.\"],\"teaching_method\":\"Simulations\",\"activities\":[\"Comprehensive transaction problem set\"],\"formative_assessment\":[\"Assignment 3 submission\"],\"engagement\":[\"Problem-solving competition\"]},{\"session_number\":47,\"title\":\"Unit 4 Review and Transaction Scenarios\",\"duration_mins\":60,\"objectives\":[\"Review all transaction management and concurrency control topics.\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Review game \\/ structured Q&A\"],\"formative_assessment\":[\"End Semester Exam preparation check\"],\"engagement\":[\"Interactive trivia\"]},{\"session_number\":48,\"title\":\"Unit 4 Assessment Review\",\"duration_mins\":60,\"objectives\":[\"Review solutions for transaction management assessments.\"],\"teaching_method\":\"Case Study Analysis\",\"activities\":[\"Detailed paper review\"],\"formative_assessment\":[\"Internal test review\"],\"engagement\":[\"Open discussion\"]},{\"session_number\":49,\"title\":\"Failure Classification, Storage, and Recovery Atomicity\",\"duration_mins\":60,\"objectives\":[\"Classify database failures and understand stable storage principles.\"],\"teaching_method\":\"Flipped Classroom\",\"activities\":[\"Student presentations on types of database failures\"],\"formative_assessment\":[\"Quiz 3\"],\"engagement\":[\"Student-led presentation\"]},{\"session_number\":50,\"title\":\"Log-Based Recovery\",\"duration_mins\":60,\"objectives\":[\"Understand deferred-database modification and immediate-database modification recovery using logs.\"],\"teaching_method\":\"Technical Seminar\",\"activities\":[\"Tracing undo and redo operations from log files\"],\"formative_assessment\":[\"Log tracing worksheet\"],\"engagement\":[\"Step-by-step code\\/log tracing\"]},{\"session_number\":51,\"title\":\"Checkpoints and Shadow Paging\",\"duration_mins\":60,\"objectives\":[\"Implement checkpointing to reduce recovery overhead.\",\"Compare log-based recovery with shadow paging.\"],\"teaching_method\":\"Flipped Classroom\",\"activities\":[\"Comparing recovery overheads\"],\"formative_assessment\":[\"Short quiz\"],\"engagement\":[\"Comparative analysis debate\"]},{\"session_number\":52,\"title\":\"Recovery with Concurrent Transactions\",\"duration_mins\":60,\"objectives\":[\"Analyze advanced recovery techniques involving concurrent transaction execution.\"],\"teaching_method\":\"Technical Seminar\",\"activities\":[\"Advanced log analysis case study\"],\"formative_assessment\":[\"Seminar feedback rubric\"],\"engagement\":[\"Technical Q&A\"]},{\"session_number\":53,\"title\":\"Introduction to NoSQL Databases\",\"duration_mins\":60,\"objectives\":[\"Understand the shift from relational to NoSQL databases.\",\"Explore NoSQL data models (document, key-value, column, graph).\"],\"teaching_method\":\"Flipped Classroom\",\"activities\":[\"Exploring MongoDB\\/Redis basic concepts\"],\"formative_assessment\":[\"NoSQL concept check\"],\"engagement\":[\"Hands-on browser shell exploration\"]},{\"session_number\":54,\"title\":\"CAP Theorem\",\"duration_mins\":60,\"objectives\":[\"Analyze Consistency, Availability, and Partition Tolerance trade-offs in distributed systems.\"],\"teaching_method\":\"Technical Seminar\",\"activities\":[\"CAP theorem trade-off analysis for real systems\"],\"formative_assessment\":[\"Short written assessment\"],\"engagement\":[\"Case study debate\"]},{\"session_number\":55,\"title\":\"Introduction to Distributed Databases\",\"duration_mins\":60,\"objectives\":[\"Understand distributed database design, fragmentation, and replication.\"],\"teaching_method\":\"Flipped Classroom\",\"activities\":[\"Mapping distributed data storage architectures\"],\"formative_assessment\":[\"End Semester Exam preparation check\"],\"engagement\":[\"Group mapping activity\"]},{\"session_number\":56,\"title\":\"Advanced Databases and Recovery Review\",\"duration_mins\":60,\"objectives\":[\"Review log-based recovery, NoSQL, and distributed databases.\"],\"teaching_method\":\"Technical Seminar\",\"activities\":[\"Comprehensive review discussion\"],\"formative_assessment\":[\"End Semester Exam mock test component\"],\"engagement\":[\"Open floor review\"]},{\"session_number\":57,\"title\":\"Comprehensive Course Review - Part 1\",\"duration_mins\":60,\"objectives\":[\"Review Units 1 and 2 (ER Modeling, Relational Algebra, SQL).\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Rapid-fire problem solving across core modules\"],\"formative_assessment\":[\"Mock exam practice\"],\"engagement\":[\"Team quiz challenge\"]},{\"session_number\":58,\"title\":\"Comprehensive Course Review - Part 2\",\"duration_mins\":60,\"objectives\":[\"Review Unit 3 (Normalization and Functional Dependencies).\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Solving complex normalization and closure problems\"],\"formative_assessment\":[\"Practice problem review\"],\"engagement\":[\"Board work review\"]},{\"session_number\":59,\"title\":\"Comprehensive Course Review - Part 3\",\"duration_mins\":60,\"objectives\":[\"Review Units 4 and 5 (Transactions, Concurrency, Recovery, NoSQL).\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Final doubt clearing and key formula\\/algorithm recap\"],\"formative_assessment\":[\"End Semester Exam final prep check\"],\"engagement\":[\"Comprehensive Q&A\"]},{\"session_number\":60,\"title\":\"Final Course Wrap-Up and Exam Strategy\",\"duration_mins\":60,\"objectives\":[\"Discuss final exam structure, expectations, and answer presentation strategies.\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Sample question paper discussion and time management tips\"],\"formative_assessment\":[\"End Semester Exam\"],\"engagement\":[\"Motivational closing discussion\"]}]}','gemini-3.5-flash-lite',NULL,NULL,20419,'success',NULL,'course_plan',1,'2026-11-11 11:59:34'),(4,1,3,'ppt','ppt','{\"title\":\"DBMS\",\"subject\":\"Database Management Systems\",\"unit\":1,\"provider\":\"gemini\"}','{\"slides\":[{\"number\":1,\"title\":\"Database Management Systems (DBMS)\",\"layout\":\"content\",\"bullets\":[\"Institution: K.L.N College of Engineering\",\"Department: Computer Science and Engineering\",\"Course: Database Management Systems\",\"Comprehensive Full Course Coverage (Units 1 to 5)\"],\"speaker_notes\":\"Welcome to the comprehensive course on Database Management Systems. Today we cover the entire curriculum from foundational data models to transaction processing and NoSQL databases.\",\"unit_tag\":\"Overview\"},{\"number\":2,\"title\":\"Course Learning Objectives\",\"layout\":\"content\",\"bullets\":[\"Design Entity-Relationship models and map them to normalized relational schemas.\",\"Formulate complex relational algebra expressions and SQL queries for data retrieval.\",\"Analyze functional dependencies to achieve optimal database normalization up to BCNF and 4NF.\",\"Evaluate transaction schedules using serializability and concurrency control protocols.\",\"Implement recovery mechanisms and distinguish relational databases from NoSQL architectures.\"],\"speaker_notes\":\"Review these core objectives carefully. They guide our progression from conceptual design all the way to physical recovery and modern distributed systems.\",\"unit_tag\":\"Overview\"},{\"number\":3,\"title\":\"Unit 1: Data Models, Architecture & ER Design\",\"layout\":\"content\",\"bullets\":[\"Data Independence: Separation of ANSI-SPARC 3-schema architecture (Internal, Conceptual, External) isolating applications from physical storage changes.\",\"Database Languages: DDL (Data Definition Language) for schema definition, DML (Data Manipulation Language) for queries and updates.\",\"ER Modeling: Entities, attributes, mapping cardinalities (1:1, 1:N, N:M), and primary\\/foreign keys.\",\"Schema Reduction: Systematic transformation of ER diagrams into relational tables preserving referential integrity.\"],\"speaker_notes\":\"Unit 1 establishes how real-world domains are modeled conceptually and translated into structured schemas while maintaining data independence.\",\"unit_tag\":\"Unit 1\"},{\"number\":4,\"title\":\"Unit 2: Relational Algebra & Advanced SQL\",\"layout\":\"content\",\"bullets\":[\"Fundamental Operations: Selection (sigma), Projection (pi), Union, Set Difference, Cartesian Product, and Rename.\",\"Extended Operations: Outer joins, left\\/right\\/full joins, and aggregate functions (SUM, AVG, COUNT).\",\"SQL Subqueries & Views: Nested subqueries for complex filtering, correlated subqueries, and virtual tables (Views) for security and abstraction.\",\"Null Logic: Three-valued logic handling of unknown or missing data values during evaluations.\"],\"speaker_notes\":\"Unit 2 bridges theory and practice. Relational algebra provides the formal foundation, while SQL executes these operations on real database instances.\",\"unit_tag\":\"Unit 2\"},{\"number\":5,\"title\":\"Unit 3: Functional Dependencies & Normalization\",\"layout\":\"content\",\"bullets\":[\"Functional Dependencies (FD): Constraints where attribute set X uniquely determines attribute set Y (X -> Y).\",\"Axioms & Closure: Armstrong\'s axioms, computing attribute closures (X+), and canonical covers to minimize redundant FDs.\",\"Normal Forms: 1NF (atomic domains), 2NF (full functional dependency), and 3NF (eliminating transitive dependencies).\",\"Advanced Normal Forms: BCNF (every determinant is a candidate key) and 4NF (eliminating multi-valued dependencies).\"],\"speaker_notes\":\"Normalization eliminates redundancy and insertion, update, and deletion anomalies. We use attribute closures and canonical covers to mathematically verify normal forms.\",\"unit_tag\":\"Unit 3\"},{\"number\":6,\"title\":\"Unit 4: Transactions & Concurrency Control\",\"layout\":\"content\",\"bullets\":[\"ACID Properties: Atomicity, Consistency, Isolation, and Durability ensuring reliable execution of units of work.\",\"Serializability: Conflict and view serializability ensuring concurrent execution yields identical results to serial execution.\",\"Lock-Based Protocols: Two-Phase Locking (2PL) and strict\\/rigorous 2PL guaranteeing conflict serializability.\",\"Deadlock Management: Deadlock prevention schemes (wait-die, wound-wait) and detection via wait-for graphs.\"],\"speaker_notes\":\"Transactions must maintain database consistency despite concurrent access and system failures. Isolation levels and locking protocols manage this concurrency.\",\"unit_tag\":\"Unit 4\"},{\"number\":7,\"title\":\"Unit 5: Crash Recovery & NoSQL Databases\",\"layout\":\"content\",\"bullets\":[\"Log-Based Recovery: Write-Ahead Logging (WAL), undo\\/redo operations, and checkpointing for crash recovery.\",\"Alternative Recovery: Shadow paging maintaining two page tables during transaction execution.\",\"CAP Theorem: Distributed systems must choose at most two among Consistency, Availability, and Partition Tolerance.\",\"NoSQL Architecture: Introduction to key-value, document, and columnar stores for horizontal scalability.\"],\"speaker_notes\":\"Unit 5 addresses what happens when systems fail and how modern distributed architectures scale beyond traditional relational limits using the CAP theorem.\",\"unit_tag\":\"Unit 5\"},{\"number\":8,\"title\":\"Unit Summary & Review Questions\",\"layout\":\"summary\",\"bullets\":[\"Summary: Covered full database lifecycle from ER modeling and SQL querying to normalization, concurrency control, and recovery.\",\"Review Question 1: Given a relation R(A,B,C,D) with FDs {A->B, B->C}, decompose into 3NF and BCNF.\",\"Review Question 2: Explain how Two-Phase Locking guarantees conflict serializability.\",\"Review Question 3: Discuss the implications of the CAP theorem when designing a distributed e-commerce database.\"],\"speaker_notes\":\"Review these questions to test your grasp of the core concepts across all five units. Be prepared to solve similar problems in examinations.\",\"unit_tag\":\"Overview\"},{\"number\":9,\"title\":\"Thank You\",\"layout\":\"close\",\"bullets\":[\"Department of Computer Science and Engineering\",\"K.L.N College of Engineering\",\"Open for Questions and Discussion\"],\"speaker_notes\":\"Thank you for your attention. We will now open the floor for any questions regarding the DBMS curriculum.\",\"unit_tag\":\"Overview\"}]}','gemini-3.5-flash-lite',NULL,NULL,17565,'success',NULL,'presentation',1,'2026-11-11 12:00:36'),(5,1,3,'assignment','assignment','{\"type\":\"essay\",\"subject\":\"Database Management Systems\",\"class_id\":1,\"provider\":\"gemini\"}','{\"title\":\"Advanced Database Management Systems Essay Assignment\",\"description\":\"A comprehensive essay assignment focusing on two major conceptual and architectural paradigms in Database Management Systems: Transaction Processing & Concurrency Control, and Distributed Databases & NoSQL Systems.\",\"instructions\":[\"Write a well-structured essay addressing both major questions provided.\",\"Ensure each section includes clear theoretical explanations, real-world examples, and comparative analysis.\",\"The essay must be between 1500 to 2000 words.\",\"Properly cite academic sources and textbooks used for the arguments.\",\"Submit the assignment in PDF format by the stipulated deadline.\"],\"max_marks\":50,\"rubric\":[{\"criterion\":\"Concurrency Control and ACID Properties\",\"description\":\"Explanation of transaction management, ACID properties, and how concurrency control protocols (like 2PL and Timestamp Ordering) prevent anomalies.\",\"marks\":15,\"clo\":\"CLO2\",\"bloom\":\"K3\",\"levels\":[\"Poor (0-5 marks): Inaccurate or incomplete explanation of transactions.\",\"Average (6-10 marks): Basic understanding of ACID and locking without deep analysis.\",\"Excellent (11-15 marks): Comprehensive and accurate application of concurrency control mechanisms.\"]},{\"criterion\":\"Distributed Databases and CAP Theorem\",\"description\":\"Analysis of distributed database architectures, data fragmentation, replication strategies, and trade-offs defined by the CAP theorem.\",\"marks\":15,\"clo\":\"CLO4\",\"bloom\":\"K3\",\"levels\":[\"Poor (0-5 marks): Lacks clarity on distributed systems and CAP theorem.\",\"Average (6-10 marks): Mentions CAP theorem and distribution with limited depth.\",\"Excellent (11-15 marks): Deep critical analysis of distributed data management and CAP trade-offs.\"]},{\"criterion\":\"Critical Analysis and Comparison\",\"description\":\"Ability to compare relational DBMS scalability challenges with NoSQL\\/Distributed solutions using concrete scenarios.\",\"marks\":10,\"clo\":\"CLO3\",\"bloom\":\"K4\",\"levels\":[\"Poor (0-3 marks): No meaningful comparison provided.\",\"Average (4-7 marks): Surface-level comparison of SQL vs NoSQL.\",\"Excellent (8-10 marks): Insightful comparative evaluation based on architectural needs.\"]},{\"criterion\":\"Structure, Clarity, and Citations\",\"description\":\"Overall organization, academic tone, logical flow of arguments, and proper referencing.\",\"marks\":10,\"clo\":\"CLO1\",\"bloom\":\"K2\",\"levels\":[\"Poor (0-3 marks): Poor grammar, disorganized structure, missing citations.\",\"Average (4-7 marks): Adequate structure and readability with minor flaws.\",\"Excellent (8-10 marks): Flawless academic writing, clear structure, and robust citations.\"]}]}','gemini-3.5-flash-lite',NULL,NULL,5269,'success',NULL,'assignment',1,'2026-11-11 12:01:12'),(6,1,3,'questions','questions','{\"type\":\"short\",\"unit\":1,\"klevel\":\"K2\",\"count\":5,\"subject\":\"Database Management Systems\",\"provider\":\"gemini\"}','{\"questions\":[{\"stem\":\"Explain the concept of physical data independence in a database management system, and provide a brief illustrative example of how it protects application programs.\",\"correct_answer\":\"Physical data independence is the capacity to modify the internal schema (e.g., changing file organizations, indexing strategies, or storage structures) without having to alter the conceptual schema or existing application programs. For example, if a DBA decides to switch the underlying storage of a large employee table from an unindexed sequential file to a B+-tree indexed file, the application queries that retrieve employee records remain completely unaffected because the logical view provided by the conceptual schema does not change.\",\"explanation\":\"K2 level requires explaining a concept in one\'s own words and providing an example. This question targets physical data independence, explaining both the mechanism and its practical protective effect on applications.\",\"marks\":5,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"Contrast the roles of the conceptual schema and the physical schema in the three-schema database architecture, using a practical database scenario to clarify their differences.\",\"correct_answer\":\"The conceptual schema describes the overall database structure for all users, hiding the details of physical storage structures and focusing on entities, data types, relationships, and constraints. In contrast, the physical schema describes how the data is actually stored on secondary storage devices, including details regarding file structures, record placements, and compression or indexing techniques. For example, in a university database, the conceptual schema defines the \'Student\' entity with attributes like ID, Name, and GPA, whereas the physical schema specifies that the Student relation is stored as a clustered index on the student_id field within a specific disk block allocation.\",\"explanation\":\"Tests the understanding of schema layers in database architecture by asking for a comparison and a concrete scenario.\",\"marks\":5,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"Explain the significance of mapping cardinalities in an Entity-Relationship (ER) model, and describe how a one-to-many relationship is typically translated during the reduction to a relational schema.\",\"correct_answer\":\"Mapping cardinalities express the maximum number of relationship instances that an entity can participate in, categorizing relationships as one-to-one, one-to-many, or many-to-many. During the reduction of a binary one-to-many relationship set from an ER diagram to a relational schema, the entity on the \'many\' side of the relationship is augmented with a foreign key that references the primary key of the entity on the \'one\' side. For example, in a Department-to-Employee one-to-many relationship, the Employee relation receives \'dept_id\' as a foreign key pointing to the Department relation.\",\"explanation\":\"Evaluates comprehension of ER modeling constraints and the algorithmic translation (reduction) process into relational schemas.\",\"marks\":5,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"Discuss the primary purpose of database languages, specifically differentiating between Data Definition Language (DDL) and Data Manipulation Language (DML) with functional examples.\",\"correct_answer\":\"Database languages are specialized languages used by database administrators and users to specify database schemas, express queries, and update data. DDL is used to define and modify the database structure and constraints, such as creating tables or altering columns (e.g., \'CREATE TABLE Instructor (ID INT PRIMARY KEY, name VARCHAR(50));\'). DML, on the other hand, enables users to access or manipulate data organized by the appropriate data model, handling operations such as retrieval, insertion, deletion, and modification of actual records (e.g., \'INSERT INTO Instructor VALUES (101, \\\\\'Alice\\\\\');\').\",\"explanation\":\"Assesses the understanding of database system languages and the distinct functions of DDL versus DML.\",\"marks\":5,\"difficulty\":\"medium\",\"bloom_k_level\":\"K2\",\"unit_number\":1},{\"stem\":\"Explain why weak entity sets require a supervising strong entity set, and describe how this dependency is represented in an ER diagram and resolved in a relational schema.\",\"correct_answer\":\"A weak entity set is one that does not possess sufficient attributes to form a primary key of its own; thus, its existence depends on a strong entity set (referred to as the identifying owner). In an ER diagram, a weak entity is depicted with a double-lined rectangle, and its identifying relationship is shown with a double-lined diamond. When reducing this to a relational schema, the weak entity\'s relation includes its own partial discriminator attributes along with the primary key attributes of the strong entity set as a foreign key, with the combined set forming the primary key of the weak entity\'s relation.\",\"explanation\":\"Checks comprehension of weak entities, their notation in ER diagrams, and their structural conversion into relational tables.\",\"marks\":5,\"difficulty\":\"hard\",\"bloom_k_level\":\"K2\",\"unit_number\":1}]}','gemini-3.5-flash-lite',NULL,NULL,5816,'success',NULL,'question_bank',1,'2026-11-11 12:03:12');
/*!40000 ALTER TABLE `ai_generations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_prompt_templates`
--

DROP TABLE IF EXISTS `ai_prompt_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_prompt_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `module` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `system_prompt` longtext NOT NULL,
  `user_template` longtext DEFAULT NULL,
  `output_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`output_schema`)),
  `model` varchar(80) DEFAULT 'gemini-2.0-flash',
  `version` int(10) unsigned DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_prompt_templates`
--

LOCK TABLES `ai_prompt_templates` WRITE;
/*!40000 ALTER TABLE `ai_prompt_templates` DISABLE KEYS */;
INSERT INTO `ai_prompt_templates` VALUES (1,'course_plan','course_plan','Course Plan Generator','You are an expert Indian higher-education curriculum designer specializing in OBE, Bloom\'s taxonomy (K1-K6), NAAC Binary 2025 and NBA GAPC v4. Return ONLY valid JSON.','Subject: {{subject}}\nCredits: {{credits}}\nUniversity: {{university}}\nSyllabus:\n{{syllabus}}','{\"type\": \"object\"}','gemini-2.0-flash',1,1,NULL,'2026-08-08 17:07:02'),(2,'bloom_map','bloom','Bloom Mapper','Map each unit/topic to Bloom K1-K6. Return ONLY valid JSON with units array and distribution percentages.','{{plan_json}}','{\"type\": \"object\"}','gemini-2.0-flash',1,1,NULL,'2026-08-08 17:07:02'),(3,'ai_review','review','Curriculum Review','Evaluate the course plan on 12 parameters (NAAC, industry, OBE, resources, hours, K-balance, etc). Return JSON with score 0-100 and recommendations.','{{plan_json}}','{\"type\": \"object\"}','gemini-2.0-flash',1,1,NULL,'2026-08-08 17:07:02'),(4,'lesson_plan','lesson','Lesson Planner','Generate session-by-session lesson plans from the course plan. Return JSON array of sessions.','{{plan_json}}','{\"type\": \"object\"}','gemini-2.0-flash',1,1,NULL,'2026-08-08 17:07:02'),(5,'question_bank','questions','Question Bank','Generate exam questions (MCQ, short, long) tagged by Bloom K-level and unit. Return ONLY JSON.','Type: {{type}}\nUnit: {{unit}}\nK-level: {{klevel}}\nCount: {{count}}\nSyllabus context:\n{{context}}','{\"type\": \"object\"}','gemini-2.0-flash',1,1,NULL,'2026-08-08 17:07:02'),(6,'ppt_gen','ppt','PPT Generator','Generate a professional 12-20 slide teaching presentation as JSON slides with title, bullets, speaker_notes, unit_tag.','{{context}}','{\"type\": \"object\"}','gemini-2.0-flash',1,1,NULL,'2026-08-08 17:07:02'),(7,'assignment_gen','assignment','Assignment Generator','Generate a NAAC-compliant assignment with rubric for the given type. Return ONLY JSON.','Type: {{type}}\nSubject: {{subject}}\nContext:\n{{context}}','{\"type\": \"object\"}','gemini-2.0-flash',1,1,NULL,'2026-08-08 17:07:02'),(8,'formula_nlp','marks','Formula NLP Parser','Parse plain-English internal marks formula used in Indian universities into structured JSON components and expression.','{{formula_text}}','{\"type\": \"object\"}','gemini-2.0-flash',1,1,NULL,'2026-08-08 17:07:02'),(9,'study_assistant','ask_ai','Student Study Assistant','Answer using ONLY the provided course materials. Cite sources. If unknown, say so.','Materials:\n{{materials}}\n\nQuestion: {{question}}',NULL,'gemini-2.0-flash',1,1,NULL,'2026-08-08 17:07:02'),(10,'improve_plan','improve','Improve with AI','Apply the professor instruction to improve the course plan. Return full updated plan JSON and a change summary.','Instruction: {{instruction}}\nCurrent plan:\n{{plan_json}}','{\"type\": \"object\"}','gemini-2.0-flash',1,1,NULL,'2026-08-08 17:07:02');
/*!40000 ALTER TABLE `ai_prompt_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `announcement_type` enum('general','exam','event','holiday','circular','deadline') DEFAULT 'general',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned DEFAULT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`setting_value`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting` (`institution_id`,`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_settings`
--

LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignment_extension_requests`
--

DROP TABLE IF EXISTS `assignment_extension_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_extension_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `reason` text NOT NULL,
  `requested_deadline` datetime NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `professor_note` text DEFAULT NULL,
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `approved_deadline` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asg_ext_asg` (`assignment_id`),
  KEY `idx_asg_ext_stu` (`student_id`),
  KEY `idx_asg_ext_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignment_extension_requests`
--

LOCK TABLES `assignment_extension_requests` WRITE;
/*!40000 ALTER TABLE `assignment_extension_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `assignment_extension_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignment_submissions`
--

DROP TABLE IF EXISTS `assignment_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `content_text` longtext DEFAULT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `grade` decimal(6,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(10) unsigned DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  `status` enum('draft','submitted','late','graded','returned') DEFAULT 'draft',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_submission` (`assignment_id`,`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignment_submissions`
--

LOCK TABLES `assignment_submissions` WRITE;
/*!40000 ALTER TABLE `assignment_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `assignment_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignment_templates`
--

DROP TABLE IF EXISTS `assignment_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `assignment_type` varchar(40) NOT NULL DEFAULT 'essay',
  `description` longtext DEFAULT NULL,
  `rubric` longtext DEFAULT NULL,
  `max_marks` decimal(6,2) NOT NULL DEFAULT 25.00,
  `instructions` longtext DEFAULT NULL,
  `context_text` longtext DEFAULT NULL,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asg_tpl_prof` (`professor_id`),
  KEY `idx_asg_tpl_inst` (`institution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignment_templates`
--

LOCK TABLES `assignment_templates` WRITE;
/*!40000 ALTER TABLE `assignment_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `assignment_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `class_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `assignment_type` enum('essay','case_study','research_review','problem_solving','mini_project','mixed','lab','reflection','group_presentation') NOT NULL DEFAULT 'essay',
  `description` longtext DEFAULT NULL,
  `rubric` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rubric`)),
  `max_marks` decimal(6,2) DEFAULT 25.00,
  `deadline` datetime DEFAULT NULL,
  `instructions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`instructions`)),
  `ai_generated` tinyint(1) DEFAULT 0,
  `status` enum('draft','published','closed') DEFAULT 'draft',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asg_prof` (`professor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
INSERT INTO `assignments` VALUES (1,1,NULL,3,1,1,'Advanced Database Management Systems Essay Assignment','essay','A comprehensive essay assignment focusing on two major conceptual and architectural paradigms in Database Management Systems: Transaction Processing & Concurrency Control, and Distributed Databases & NoSQL Systems.','[{\"criterion\":\"Concurrency Control and ACID Properties\",\"description\":\"Explanation of transaction management, ACID properties, and how concurrency control protocols (like 2PL and Timestamp Ordering) prevent anomalies.\",\"marks\":15,\"clo\":\"CLO2\",\"bloom\":\"K3\",\"levels\":\"Poor (0-5 marks): Inaccurate or incomplete explanation of transactions.; Average (6-10 marks): Basic understanding of ACID and locking without deep analysis.; Excellent (11-15 marks): Comprehensive and accurate application of concurrency control mechanisms.\",\"weight\":null},{\"criterion\":\"Distributed Databases and CAP Theorem\",\"description\":\"Analysis of distributed database architectures, data fragmentation, replication strategies, and trade-offs defined by the CAP theorem.\",\"marks\":15,\"clo\":\"CLO4\",\"bloom\":\"K3\",\"levels\":\"Poor (0-5 marks): Lacks clarity on distributed systems and CAP theorem.; Average (6-10 marks): Mentions CAP theorem and distribution with limited depth.; Excellent (11-15 marks): Deep critical analysis of distributed data management and CAP trade-offs.\",\"weight\":null},{\"criterion\":\"Critical Analysis and Comparison\",\"description\":\"Ability to compare relational DBMS scalability challenges with NoSQL\\/Distributed solutions using concrete scenarios.\",\"marks\":10,\"clo\":\"CLO3\",\"bloom\":\"K4\",\"levels\":\"Poor (0-3 marks): No meaningful comparison provided.; Average (4-7 marks): Surface-level comparison of SQL vs NoSQL.; Excellent (8-10 marks): Insightful comparative evaluation based on architectural needs.\",\"weight\":null},{\"criterion\":\"Structure, Clarity, and Citations\",\"description\":\"Overall organization, academic tone, logical flow of arguments, and proper referencing.\",\"marks\":10,\"clo\":\"CLO1\",\"bloom\":\"K2\",\"levels\":\"Poor (0-3 marks): Poor grammar, disorganized structure, missing citations.; Average (4-7 marks): Adequate structure and readability with minor flaws.; Excellent (8-10 marks): Flawless academic writing, clear structure, and robust citations.\",\"weight\":null}]',50.00,'2026-12-05 04:00:00','[\"Write a well-structured essay addressing both major questions provided.\",\"Ensure each section includes clear theoretical explanations, real-world examples, and comparative analysis.\",\"The essay must be between 1500 to 2000 words.\",\"Properly cite academic sources and textbooks used for the arguments.\",\"Submit the assignment in PDF format by the stipulated deadline.\"]',1,'published','{\"bulk_group\":null,\"from_template_id\":null,\"context\":\"generate the top 2 big question of DBMS\"}','2026-11-11 12:01:12','2026-11-11 12:01:12');
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_qr_tokens`
--

DROP TABLE IF EXISTS `attendance_qr_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_qr_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `institution_id` int(10) unsigned NOT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned NOT NULL,
  `session_id` int(10) unsigned DEFAULT NULL,
  `session_date` date NOT NULL,
  `period` varchar(20) NOT NULL DEFAULT '1',
  `topic` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `geofence_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att_qr_token` (`token`),
  KEY `idx_att_qr_session` (`session_id`),
  KEY `idx_att_qr_class` (`class_id`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_qr_tokens`
--

LOCK TABLES `attendance_qr_tokens` WRITE;
/*!40000 ALTER TABLE `attendance_qr_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_qr_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_records`
--

DROP TABLE IF EXISTS `attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned DEFAULT NULL,
  `register_no` varchar(60) NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  PRIMARY KEY (`id`),
  KEY `idx_att_student` (`student_id`),
  KEY `idx_att_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_records`
--

LOCK TABLES `attendance_records` WRITE;
/*!40000 ALTER TABLE `attendance_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_regularization_requests`
--

DROP TABLE IF EXISTS `attendance_regularization_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_regularization_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `session_id` int(10) unsigned NOT NULL,
  `record_id` int(10) unsigned DEFAULT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `register_no` varchar(40) NOT NULL,
  `original_status` varchar(20) NOT NULL,
  `requested_status` varchar(20) NOT NULL,
  `reason` text NOT NULL,
  `proof_url` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `professor_note` text DEFAULT NULL,
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_att_reg_sess` (`session_id`),
  KEY `idx_att_reg_stu` (`student_id`),
  KEY `idx_att_reg_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_regularization_requests`
--

LOCK TABLES `attendance_regularization_requests` WRITE;
/*!40000 ALTER TABLE `attendance_regularization_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_regularization_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_sessions`
--

DROP TABLE IF EXISTS `attendance_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `session_date` date NOT NULL,
  `period` varchar(40) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `records` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '[{student_id/reg, status}]' CHECK (json_valid(`records`)),
  `present_count` int(10) unsigned DEFAULT 0,
  `absent_count` int(10) unsigned DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att_session` (`class_id`,`subject_id`,`session_date`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_sessions`
--

LOCK TABLES `attendance_sessions` WRITE;
/*!40000 ALTER TABLE `attendance_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budgets`
--

DROP TABLE IF EXISTS `budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budgets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `fiscal_year` varchar(20) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `allocated` decimal(12,2) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budgets`
--

LOCK TABLES `budgets` WRITE;
/*!40000 ALTER TABLE `budgets` DISABLE KEYS */;
/*!40000 ALTER TABLE `budgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned NOT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `section` varchar(20) DEFAULT NULL,
  `year` tinyint(3) unsigned DEFAULT NULL,
  `semester` varchar(40) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,1,1,NULL,'CSE','A',1,NULL,'2026-27','{\"level\":\"UG\"}',1);
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compliance_alerts`
--

DROP TABLE IF EXISTS `compliance_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compliance_alerts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `alert_type` varchar(60) NOT NULL,
  `severity` enum('low','medium','high') DEFAULT 'medium',
  `message` text NOT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compliance_alerts`
--

LOCK TABLES `compliance_alerts` WRITE;
/*!40000 ALTER TABLE `compliance_alerts` DISABLE KEYS */;
/*!40000 ALTER TABLE `compliance_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_plan_versions`
--

DROP TABLE IF EXISTS `course_plan_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_plan_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot`)),
  `change_note` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_ver` (`plan_id`,`version`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_plan_versions`
--

LOCK TABLES `course_plan_versions` WRITE;
/*!40000 ALTER TABLE `course_plan_versions` DISABLE KEYS */;
INSERT INTO `course_plan_versions` VALUES (1,1,1,'{\"title\":\"Database Management Systems\",\"learning_outcomes\":[\"Understand the fundamental concepts of Database Management Systems and data models.\",\"Design entity-relationship diagrams and map them to relational database schemas.\",\"Formulate complex SQL queries using DDL, DML, DCL, and TCL commands.\",\"Apply normalization techniques to reduce data redundancy and maintain integrity.\",\"Analyze transaction management, concurrency control protocols, and recovery techniques.\"],\"units\":[{\"unit_number\":1,\"title\":\"Introduction to DBMS and ER Model\",\"hours\":12,\"topics\":[\"Database System Applications\",\"Purpose of Database Systems\",\"View of Data\",\"Data Independence\",\"Database Languages\",\"Data Models\",\"Database Architecture\",\"Entity-Relationship (ER) Model\",\"Mapping Cardinalities\",\"Keys\",\"ER Diagrams\",\"Reduction to Relational Schemas\"],\"outcomes\":[\"Explain the role of DBMS in modern enterprise applications.\",\"Construct ER diagrams for real-world scenarios.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Chalk and Talk\",\"PowerPoint Presentation\",\"Case Study Discussion\"],\"assessment\":[\"Quiz 1\",\"Assignment 1\",\"Internal Test 1\"]},{\"unit_number\":2,\"title\":\"Relational Model and Structured Query Language (SQL)\",\"hours\":15,\"topics\":[\"Structure of Relational Databases\",\"Fundamental Relational Algebra Operations\",\"Extended Relational Algebra Operations\",\"SQL Data Definition\",\"Basic Structure of SQL Queries\",\"Set Operations\",\"Aggregate Functions\",\"Null Values\",\"Nested Subqueries\",\"Complex Queries\",\"Views\",\"Joined Relations\"],\"outcomes\":[\"Execute relational algebra operations.\",\"Write efficient SQL queries for data retrieval and manipulation.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Interactive Lab Sessions\",\"Live SQL Coding Demonstrations\"],\"assessment\":[\"Lab Practical 1\",\"SQL Coding Assignment\",\"Internal Test 1\"]},{\"unit_number\":3,\"title\":\"Database Design and Normalization\",\"hours\":12,\"topics\":[\"Features of Good Relational Design\",\"Atomic Domains and First Normal Form\",\"Decomposition Using Functional Dependencies\",\"Functional Dependency Theory\",\"Algorithms for Functional Dependencies\",\"Closure of Set of Functional Dependencies\",\"Closure of Attribute Sets\",\"Canonical Cover\",\"Boyer-Codd Normal Form (BCNF)\",\"Third Normal Form (3NF)\",\"Fourth Normal Form (4NF)\",\"Multi-valued Dependencies\"],\"outcomes\":[\"Analyze functional dependencies and determine attribute closures.\",\"Normalize relational schemas up to BCNF.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Problem-Solving Sessions\",\"Peer Teaching\"],\"assessment\":[\"Assignment 2\",\"Quiz 2\",\"Internal Test 2\"]},{\"unit_number\":4,\"title\":\"Transactions and Concurrency Control\",\"hours\":11,\"topics\":[\"Transaction Concept\",\"Simple Transaction Model\",\"Storage Structure\",\"Transaction Atomicity and Durability\",\"Transaction Isolation\",\"Serializability\",\"Recoverable Schedules\",\"Cascadeless Schedules\",\"Concurrency Control\",\"Lock-Based Protocols\",\"Timestamp-Based Protocols\",\"Validation-Based Protocols\",\"Deadlock Handling\"],\"outcomes\":[\"Verify the serializability of transaction schedules.\",\"Compare concurrency control protocols and deadlock management techniques.\"],\"bloom_k_level\":\"K5\",\"teaching_methods\":[\"Lecture\",\"Simulations\",\"Case Study Analysis\"],\"assessment\":[\"Assignment 3\",\"Internal Test 2\",\"End Semester Exam\"]},{\"unit_number\":5,\"title\":\"Recovery System and Advanced Databases\",\"hours\":10,\"topics\":[\"Failure Classification\",\"Storage\",\"Recovery and Atomicity\",\"Log-Based Recovery\",\"Checkpoints\",\"Shadow Paging\",\"Recovery with Concurrent Transactions\",\"Introduction to NoSQL Databases\",\"CAP Theorem\",\"Introduction to Distributed Databases\"],\"outcomes\":[\"Explain log-based recovery and checkpointing mechanisms.\",\"Compare traditional RDBMS with NoSQL and distributed databases.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Flipped Classroom\",\"Technical Seminar\"],\"assessment\":[\"Quiz 3\",\"End Semester Exam\"]}],\"weekly_plan\":[{\"week\":\"1\",\"focus\":\"Introduction to DBMS and ER Model (12 hours)\"},{\"week\":\"2\",\"focus\":\"Relational Model and Structured Query Language (SQL) (15 hours)\"},{\"week\":\"3\",\"focus\":\"Database Design and Normalization (12 hours)\"},{\"week\":\"4\",\"focus\":\"Transactions and Concurrency Control (11 hours)\"},{\"week\":\"5\",\"focus\":\"Recovery System and Advanced Databases (10 hours)\"}],\"resources\":[\"Silberschatz, A., Korth, H. F., & Sudarshan, S., Database System Concepts, McGraw-Hill.\",\"Elmasri, R., & Navathe, S. B., Fundamentals of Database Systems, Pearson.\",\"Ramakrishnan, R., & Gehrke, J., Database Management Systems, McGraw-Hill.\"],\"expert_advice\":[\"Emphasize hands-on SQL practice in a dedicated database lab to reinforce theoretical concepts.\",\"Use real-world database design case studies to explain normalization and functional dependencies effectively.\",\"Align continuous internal assessments with Bloom\'s taxonomy levels K1 through K5.\"],\"bloom_distribution\":{\"K1\":10,\"K2\":20,\"K3\":35,\"K4\":25,\"K5\":10,\"K6\":0},\"ai_score\":95.5,\"accreditation_template\":\"standard\"}','Initial AI generation · template Standard',3,'2026-11-11 11:59:08');
/*!40000 ALTER TABLE `course_plan_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_plans`
--

DROP TABLE IF EXISTS `course_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `class_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `subject_name` varchar(200) NOT NULL,
  `subject_code` varchar(40) DEFAULT NULL,
  `credits` decimal(4,1) DEFAULT 3.0,
  `semester` varchar(40) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `university` varchar(150) DEFAULT NULL,
  `syllabus_input` longtext DEFAULT NULL,
  `status` enum('draft','submitted','under_review','approved','returned') DEFAULT 'draft',
  `ai_score` decimal(5,2) DEFAULT NULL,
  `ai_review` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_review`)),
  `bloom_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bloom_data`)),
  `weekly_plan` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weekly_plan`)),
  `resources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resources`)),
  `expert_advice` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`expert_advice`)),
  `plan_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Full structured plan payload' CHECK (json_valid(`plan_data`)),
  `version` int(10) unsigned DEFAULT 1,
  `parent_plan_id` int(10) unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `hod_comments` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `share_token` varchar(64) DEFAULT NULL COMMENT 'Public read-only share token',
  `share_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=public read-only link active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_plans_share_token` (`share_token`),
  KEY `idx_plan_prof` (`professor_id`),
  KEY `idx_plan_status` (`status`),
  KEY `idx_plan_dept` (`department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_plans`
--

LOCK TABLES `course_plans` WRITE;
/*!40000 ALTER TABLE `course_plans` DISABLE KEYS */;
INSERT INTO `course_plans` VALUES (1,1,1,3,1,1,'Database Management Systems','Database Management Systems',NULL,4.0,NULL,NULL,'Autonomous','genearte the course plan for unit 1 to unit 5','draft',95.50,NULL,'{\"K1\":10,\"K2\":20,\"K3\":35,\"K4\":25,\"K5\":10,\"K6\":0}','[{\"week\":\"1\",\"focus\":\"Introduction to DBMS and ER Model (12 hours)\"},{\"week\":\"2\",\"focus\":\"Relational Model and Structured Query Language (SQL) (15 hours)\"},{\"week\":\"3\",\"focus\":\"Database Design and Normalization (12 hours)\"},{\"week\":\"4\",\"focus\":\"Transactions and Concurrency Control (11 hours)\"},{\"week\":\"5\",\"focus\":\"Recovery System and Advanced Databases (10 hours)\"}]','[\"Silberschatz, A., Korth, H. F., & Sudarshan, S., Database System Concepts, McGraw-Hill.\",\"Elmasri, R., & Navathe, S. B., Fundamentals of Database Systems, Pearson.\",\"Ramakrishnan, R., & Gehrke, J., Database Management Systems, McGraw-Hill.\"]','[\"Emphasize hands-on SQL practice in a dedicated database lab to reinforce theoretical concepts.\",\"Use real-world database design case studies to explain normalization and functional dependencies effectively.\",\"Align continuous internal assessments with Bloom\'s taxonomy levels K1 through K5.\"]','{\"title\":\"Database Management Systems\",\"learning_outcomes\":[\"Understand the fundamental concepts of Database Management Systems and data models.\",\"Design entity-relationship diagrams and map them to relational database schemas.\",\"Formulate complex SQL queries using DDL, DML, DCL, and TCL commands.\",\"Apply normalization techniques to reduce data redundancy and maintain integrity.\",\"Analyze transaction management, concurrency control protocols, and recovery techniques.\"],\"units\":[{\"unit_number\":1,\"title\":\"Introduction to DBMS and ER Model\",\"hours\":12,\"topics\":[\"Database System Applications\",\"Purpose of Database Systems\",\"View of Data\",\"Data Independence\",\"Database Languages\",\"Data Models\",\"Database Architecture\",\"Entity-Relationship (ER) Model\",\"Mapping Cardinalities\",\"Keys\",\"ER Diagrams\",\"Reduction to Relational Schemas\"],\"outcomes\":[\"Explain the role of DBMS in modern enterprise applications.\",\"Construct ER diagrams for real-world scenarios.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Chalk and Talk\",\"PowerPoint Presentation\",\"Case Study Discussion\"],\"assessment\":[\"Quiz 1\",\"Assignment 1\",\"Internal Test 1\"]},{\"unit_number\":2,\"title\":\"Relational Model and Structured Query Language (SQL)\",\"hours\":15,\"topics\":[\"Structure of Relational Databases\",\"Fundamental Relational Algebra Operations\",\"Extended Relational Algebra Operations\",\"SQL Data Definition\",\"Basic Structure of SQL Queries\",\"Set Operations\",\"Aggregate Functions\",\"Null Values\",\"Nested Subqueries\",\"Complex Queries\",\"Views\",\"Joined Relations\"],\"outcomes\":[\"Execute relational algebra operations.\",\"Write efficient SQL queries for data retrieval and manipulation.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Interactive Lab Sessions\",\"Live SQL Coding Demonstrations\"],\"assessment\":[\"Lab Practical 1\",\"SQL Coding Assignment\",\"Internal Test 1\"]},{\"unit_number\":3,\"title\":\"Database Design and Normalization\",\"hours\":12,\"topics\":[\"Features of Good Relational Design\",\"Atomic Domains and First Normal Form\",\"Decomposition Using Functional Dependencies\",\"Functional Dependency Theory\",\"Algorithms for Functional Dependencies\",\"Closure of Set of Functional Dependencies\",\"Closure of Attribute Sets\",\"Canonical Cover\",\"Boyer-Codd Normal Form (BCNF)\",\"Third Normal Form (3NF)\",\"Fourth Normal Form (4NF)\",\"Multi-valued Dependencies\"],\"outcomes\":[\"Analyze functional dependencies and determine attribute closures.\",\"Normalize relational schemas up to BCNF.\"],\"bloom_k_level\":\"K4\",\"teaching_methods\":[\"Problem-Solving Sessions\",\"Peer Teaching\"],\"assessment\":[\"Assignment 2\",\"Quiz 2\",\"Internal Test 2\"]},{\"unit_number\":4,\"title\":\"Transactions and Concurrency Control\",\"hours\":11,\"topics\":[\"Transaction Concept\",\"Simple Transaction Model\",\"Storage Structure\",\"Transaction Atomicity and Durability\",\"Transaction Isolation\",\"Serializability\",\"Recoverable Schedules\",\"Cascadeless Schedules\",\"Concurrency Control\",\"Lock-Based Protocols\",\"Timestamp-Based Protocols\",\"Validation-Based Protocols\",\"Deadlock Handling\"],\"outcomes\":[\"Verify the serializability of transaction schedules.\",\"Compare concurrency control protocols and deadlock management techniques.\"],\"bloom_k_level\":\"K5\",\"teaching_methods\":[\"Lecture\",\"Simulations\",\"Case Study Analysis\"],\"assessment\":[\"Assignment 3\",\"Internal Test 2\",\"End Semester Exam\"]},{\"unit_number\":5,\"title\":\"Recovery System and Advanced Databases\",\"hours\":10,\"topics\":[\"Failure Classification\",\"Storage\",\"Recovery and Atomicity\",\"Log-Based Recovery\",\"Checkpoints\",\"Shadow Paging\",\"Recovery with Concurrent Transactions\",\"Introduction to NoSQL Databases\",\"CAP Theorem\",\"Introduction to Distributed Databases\"],\"outcomes\":[\"Explain log-based recovery and checkpointing mechanisms.\",\"Compare traditional RDBMS with NoSQL and distributed databases.\"],\"bloom_k_level\":\"K3\",\"teaching_methods\":[\"Flipped Classroom\",\"Technical Seminar\"],\"assessment\":[\"Quiz 3\",\"End Semester Exam\"]}],\"weekly_plan\":[{\"week\":\"1\",\"focus\":\"Introduction to DBMS and ER Model (12 hours)\"},{\"week\":\"2\",\"focus\":\"Relational Model and Structured Query Language (SQL) (15 hours)\"},{\"week\":\"3\",\"focus\":\"Database Design and Normalization (12 hours)\"},{\"week\":\"4\",\"focus\":\"Transactions and Concurrency Control (11 hours)\"},{\"week\":\"5\",\"focus\":\"Recovery System and Advanced Databases (10 hours)\"}],\"resources\":[\"Silberschatz, A., Korth, H. F., & Sudarshan, S., Database System Concepts, McGraw-Hill.\",\"Elmasri, R., & Navathe, S. B., Fundamentals of Database Systems, Pearson.\",\"Ramakrishnan, R., & Gehrke, J., Database Management Systems, McGraw-Hill.\"],\"expert_advice\":[\"Emphasize hands-on SQL practice in a dedicated database lab to reinforce theoretical concepts.\",\"Use real-world database design case studies to explain normalization and functional dependencies effectively.\",\"Align continuous internal assessments with Bloom\'s taxonomy levels K1 through K5.\"],\"bloom_distribution\":{\"K1\":10,\"K2\":20,\"K3\":35,\"K4\":25,\"K5\":10,\"K6\":0},\"ai_score\":95.5,\"accreditation_template\":\"standard\"}',1,NULL,NULL,NULL,NULL,NULL,'{\"accreditation_template\":\"standard\"}',NULL,0,'2026-11-11 11:59:08','2026-11-11 11:59:08');
/*!40000 ALTER TABLE `course_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `hod_user_id` int(10) unsigned DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dept_inst` (`institution_id`),
  CONSTRAINT `fk_dept_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,1,'Computer Science and Engineering','CSE',2,NULL,1,'2026-11-11 11:15:32');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_chunks`
--

DROP TABLE IF EXISTS `document_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_chunks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `institution_id` int(10) unsigned NOT NULL,
  `chunk_index` int(10) unsigned NOT NULL,
  `content_chunk` text NOT NULL,
  `embedding_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Gemini embedding vector (expandable)' CHECK (json_valid(`embedding_json`)),
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  PRIMARY KEY (`id`),
  KEY `idx_chunk_doc` (`document_id`),
  KEY `idx_chunk_inst` (`institution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_chunks`
--

LOCK TABLES `document_chunks` WRITE;
/*!40000 ALTER TABLE `document_chunks` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_chunks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `owner_id` int(10) unsigned NOT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `doc_type` enum('note','ppt','syllabus','circular','naac','other') DEFAULT 'note',
  `title` varchar(255) NOT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `unit_number` int(10) unsigned DEFAULT NULL,
  `content_text` longtext DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` varchar(40) DEFAULT NULL,
  `status` enum('active','dropped','completed') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enroll` (`student_id`,`subject_id`,`academic_year`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (1,4,1,1,'2026-27','Odd Semester','active');
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_papers`
--

DROP TABLE IF EXISTS `exam_papers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_papers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `total_marks` decimal(6,1) NOT NULL DEFAULT 50.0,
  `config` longtext DEFAULT NULL,
  `sets_data` longtext DEFAULT NULL,
  `answer_key` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exam_prof` (`professor_id`),
  KEY `idx_exam_inst` (`institution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_papers`
--

LOCK TABLES `exam_papers` WRITE;
/*!40000 ALTER TABLE `exam_papers` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_papers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_timetable`
--

DROP TABLE IF EXISTS `exam_timetable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_timetable` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `subject_name` varchar(200) NOT NULL,
  `academic_level` enum('UG','PG') NOT NULL DEFAULT 'UG',
  `year_level` tinyint(3) unsigned NOT NULL,
  `class_id` int(10) unsigned DEFAULT NULL,
  `section` varchar(20) DEFAULT NULL,
  `semester` varchar(40) NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `exam_type` enum('end_semester','internal','practical','lab') NOT NULL DEFAULT 'end_semester',
  `is_lab` tinyint(1) NOT NULL DEFAULT 0,
  `exam_hall` varchar(100) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exam_inst_date` (`institution_id`,`exam_date`),
  KEY `idx_exam_scope` (`institution_id`,`department_id`,`year_level`,`semester`),
  KEY `fk_exam_tt_dept` (`department_id`),
  CONSTRAINT `fk_exam_tt_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_timetable`
--

LOCK TABLES `exam_timetable` WRITE;
/*!40000 ALTER TABLE `exam_timetable` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_timetable` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `title` varchar(200) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `vendor` varchar(150) DEFAULT NULL,
  `payment_mode` varchar(40) DEFAULT NULL,
  `added_by` int(10) unsigned NOT NULL,
  `receipt_url` varchar(255) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exp_inst` (`institution_id`,`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faculty_salaries`
--

DROP TABLE IF EXISTS `faculty_salaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faculty_salaries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `faculty_user_id` int(10) unsigned NOT NULL,
  `salary_month` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date DEFAULT NULL,
  `status` enum('paid','pending','on_hold') NOT NULL DEFAULT 'pending',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_faculty_salary_month` (`faculty_user_id`,`salary_month`),
  KEY `idx_salary_inst_month` (`institution_id`,`salary_month`),
  CONSTRAINT `fk_salary_faculty` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faculty_salaries`
--

LOCK TABLES `faculty_salaries` WRITE;
/*!40000 ALTER TABLE `faculty_salaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `faculty_salaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feature_flags`
--

DROP TABLE IF EXISTS `feature_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feature_flags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(64) NOT NULL DEFAULT 'core',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `default_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`default_config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feature_flags`
--

LOCK TABLES `feature_flags` WRITE;
/*!40000 ALTER TABLE `feature_flags` DISABLE KEYS */;
INSERT INTO `feature_flags` VALUES (1,'ai_course_plan','AI Course Plan Generator','Generate structured course plans from syllabus','professor',1,'{\"model\": \"gemini-2.0-flash\"}','2026-08-08 17:07:02','2026-08-08 17:07:02'),(2,'bloom_mapper','Bloom\'s Taxonomy Auto-Mapper','Map units/topics to K1-K6','professor',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(3,'ai_review','AI Curriculum Review','12-parameter quality review','professor',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(4,'improve_ai','Improve with AI','Instruction-based plan improvement','professor',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(5,'lesson_planner','AI Lesson Planner','Session-wise lesson plans','professor',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(6,'question_bank','Question Bank Generator','MCQ / short / long by K-level','professor',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(7,'ppt_generator','AI PPT Generator','Generate presentation slides','professor',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(8,'assignment_ai','AI Assignment Generator','Multi-type assignments + rubrics','professor',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(9,'attendance','Smart Attendance','Attendance with 75% alerts','professor',1,'{\"min_pct\": 75}','2026-08-08 17:07:02','2026-08-08 17:07:02'),(10,'internal_marks','Configurable Internal Marks','Formula-driven CIA marks','professor',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(11,'version_control','Plan Version Control','Draft to approved workflow','professor',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(12,'notifications','Notifications Centre','In-app notifications','core',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(13,'student_portal','Student Portal','Courses, notes, submissions','student',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(14,'ask_ai','Ask AI Study Assistant','RAG-style study chatbot','student',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(15,'hod_approvals','HOD Approvals','Course plan review queue','hod',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(16,'dept_analytics','Department Analytics','Bloom & quality analytics','hod',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(17,'naac_reports','NAAC/NBA Reports','Accreditation document builder','admin',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(18,'finance','Finance & Expenses','Operational cost tracking','admin',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(19,'user_management','Role & User Management','Bulk import & roles','admin',1,NULL,'2026-08-08 17:07:02','2026-08-08 17:07:02'),(20,'api_hub','API & Integration Hub','External integrations','admin',0,'{\"coming_soon\": true}','2026-08-08 17:07:02','2026-08-08 17:07:02');
/*!40000 ALTER TABLE `feature_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fee_payments`
--

DROP TABLE IF EXISTS `fee_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fee_record_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_reference` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fee_pay_record` (`fee_record_id`,`payment_date`),
  CONSTRAINT `fk_fee_pay_record` FOREIGN KEY (`fee_record_id`) REFERENCES `fee_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fee_payments`
--

LOCK TABLES `fee_payments` WRITE;
/*!40000 ALTER TABLE `fee_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `fee_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fee_records`
--

DROP TABLE IF EXISTS `fee_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `fee_type` enum('tuition','bus','hostel') NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_student_year_type` (`student_id`,`academic_year`,`fee_type`),
  KEY `idx_fee_inst_year` (`institution_id`,`academic_year`),
  CONSTRAINT `fk_fee_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fee_records`
--

LOCK TABLES `fee_records` WRITE;
/*!40000 ALTER TABLE `fee_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `fee_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institution_features`
--

DROP TABLE IF EXISTS `institution_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institution_features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `feature_code` varchar(64) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `enabled_at` timestamp NULL DEFAULT NULL,
  `disabled_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inst_feature` (`institution_id`,`feature_code`),
  KEY `idx_feature_code` (`feature_code`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institution_features`
--

LOCK TABLES `institution_features` WRITE;
/*!40000 ALTER TABLE `institution_features` DISABLE KEYS */;
INSERT INTO `institution_features` VALUES (1,1,'ai_course_plan',1,'{\"model\": \"gemini-2.0-flash\"}','2026-08-08 17:07:02',NULL),(2,1,'bloom_mapper',1,NULL,'2026-08-08 17:07:02',NULL),(3,1,'ai_review',1,NULL,'2026-08-08 17:07:02',NULL),(4,1,'improve_ai',1,NULL,'2026-08-08 17:07:02',NULL),(5,1,'lesson_planner',1,NULL,'2026-08-08 17:07:02',NULL),(6,1,'question_bank',1,NULL,'2026-08-08 17:07:02',NULL),(7,1,'ppt_generator',1,NULL,'2026-08-08 17:07:02',NULL),(8,1,'assignment_ai',1,NULL,'2026-08-08 17:07:02',NULL),(9,1,'attendance',1,'{\"min_pct\": 75}','2026-08-08 17:07:02',NULL),(10,1,'internal_marks',1,NULL,NULL,'2026-08-12 07:43:15'),(11,1,'version_control',1,NULL,'2026-08-08 17:07:02',NULL),(12,1,'notifications',1,NULL,'2026-08-08 17:07:02',NULL),(13,1,'student_portal',1,NULL,'2026-08-08 17:07:02',NULL),(14,1,'ask_ai',1,NULL,'2026-08-08 17:07:02',NULL),(15,1,'hod_approvals',1,NULL,'2026-08-08 17:07:02',NULL),(16,1,'dept_analytics',1,NULL,'2026-08-08 17:07:02',NULL),(17,1,'naac_reports',1,NULL,'2026-08-08 17:07:02',NULL),(18,1,'finance',1,NULL,'2026-08-08 17:07:02',NULL),(19,1,'user_management',1,NULL,'2026-08-08 17:07:02',NULL),(20,1,'api_hub',1,NULL,'2026-08-21 10:58:33',NULL);
/*!40000 ALTER TABLE `institution_features` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institutions`
--

DROP TABLE IF EXISTS `institutions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institutions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `affiliation_university` varchar(200) DEFAULT NULL,
  `naac_grade` varchar(20) DEFAULT NULL,
  `nba_status` varchar(40) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `state` varchar(80) DEFAULT NULL,
  `pincode` varchar(12) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `subscription_tier` enum('starter','professional','enterprise','trial') DEFAULT 'trial',
  `licensed_seats` int(10) unsigned DEFAULT 60,
  `academic_year` varchar(20) DEFAULT NULL,
  `current_semester` varchar(40) DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Expandable institution settings' CHECK (json_valid(`settings`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institutions`
--

LOCK TABLES `institutions` WRITE;
/*!40000 ALTER TABLE `institutions` DISABLE KEYS */;
INSERT INTO `institutions` VALUES (1,'K.L.N College of Engineering',NULL,'Autonomous','A',NULL,NULL,'madurai','Tamilnadu',NULL,NULL,NULL,'https://klnce.edu/images/Common_wrapper4.gif','trial',60,'2026-27','Odd','{\"attendance_min\":75,\"brand_primary\":\"1E3A8A\",\"brand_secondary\":\"0F172A\",\"brand_accent\":\"D97706\",\"geofence_required_for_qr\":false}',1,'2026-08-08 17:07:02','2026-10-01 05:17:29');
/*!40000 ALTER TABLE `institutions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internal_marks`
--

DROP TABLE IF EXISTS `internal_marks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internal_marks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `academic_year` varchar(20) NOT NULL DEFAULT '' COMMENT 'Institution academic year snapshot',
  `formula_id` int(10) unsigned DEFAULT NULL,
  `student_id` int(10) unsigned DEFAULT NULL,
  `register_no` varchar(60) NOT NULL,
  `student_name` varchar(160) NOT NULL,
  `marks_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'component => value' CHECK (json_valid(`marks_data`)),
  `attendance_pct` decimal(5,2) DEFAULT NULL,
  `assignment_total` decimal(6,2) DEFAULT NULL,
  `computed_total` decimal(6,2) DEFAULT NULL,
  `grade_letter` varchar(5) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marks` (`subject_id`,`class_id`,`register_no`,`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internal_marks`
--

LOCK TABLES `internal_marks` WRITE;
/*!40000 ALTER TABLE `internal_marks` DISABLE KEYS */;
/*!40000 ALTER TABLE `internal_marks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_plans`
--

DROP TABLE IF EXISTS `lesson_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned NOT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `unit_id` int(10) unsigned DEFAULT NULL,
  `session_number` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `duration_mins` int(10) unsigned DEFAULT 60,
  `objectives` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`objectives`)),
  `teaching_method` varchar(120) DEFAULT NULL,
  `activities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`activities`)),
  `formative_assessment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`formative_assessment`)),
  `engagement` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`engagement`)),
  `materials` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`materials`)),
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`content`)),
  `session_date` date DEFAULT NULL,
  `session_status` varchar(20) NOT NULL DEFAULT 'planned' COMMENT 'planned|completed|delayed',
  `planned_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `bloom_k_level` varchar(10) DEFAULT NULL,
  `unit_number` int(10) unsigned DEFAULT NULL,
  `suggested_method` varchar(200) DEFAULT NULL,
  `resources` longtext DEFAULT NULL COMMENT 'JSON resource suggestions',
  `calendar_event_id` int(10) unsigned DEFAULT NULL,
  `period_label` varchar(40) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lesson_plan` (`plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_plans`
--

LOCK TABLES `lesson_plans` WRITE;
/*!40000 ALTER TABLE `lesson_plans` DISABLE KEYS */;
INSERT INTO `lesson_plans` VALUES (1,1,3,NULL,1,'Database System Applications & Purpose',60,'[\"Explain the role of DBMS in modern enterprise applications.\",\"Understand the purpose of database systems compared to file systems.\"]','Chalk and Talk','[\"Discussion on real-world data management challenges.\"]','[\"Quick Q&A session\"]','[\"Real-world scenario brainstorming\"]',NULL,'{\"session_number\":1,\"unit_id\":null,\"title\":\"Database System Applications & Purpose\",\"duration_mins\":60,\"objectives\":[\"Explain the role of DBMS in modern enterprise applications.\",\"Understand the purpose of database systems compared to file systems.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Discussion on real-world data management challenges.\"],\"formative_assessment\":[\"Quick Q&A session\"],\"engagement\":[\"Real-world scenario brainstorming\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(2,1,3,NULL,2,'View of Data and Data Independence',60,'[\"Describe data abstraction levels.\",\"Differentiate between physical and logical data independence.\"]','PowerPoint Presentation','[\"Architecture diagram walkthrough\"]','[\"Concept check quiz\"]','[\"Think-pair-share\"]',NULL,'{\"session_number\":2,\"unit_id\":null,\"title\":\"View of Data and Data Independence\",\"duration_mins\":60,\"objectives\":[\"Describe data abstraction levels.\",\"Differentiate between physical and logical data independence.\"],\"teaching_method\":\"PowerPoint Presentation\",\"activities\":[\"Architecture diagram walkthrough\"],\"formative_assessment\":[\"Concept check quiz\"],\"engagement\":[\"Think-pair-share\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(3,1,3,NULL,3,'Database Languages and Data Models',60,'[\"Identify DDL and DML commands.\",\"Compare different data models.\"]','Chalk and Talk','[\"Categorizing database language statements\"]','[\"Short written check\"]','[\"Interactive board work\"]',NULL,'{\"session_number\":3,\"unit_id\":null,\"title\":\"Database Languages and Data Models\",\"duration_mins\":60,\"objectives\":[\"Identify DDL and DML commands.\",\"Compare different data models.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Categorizing database language statements\"],\"formative_assessment\":[\"Short written check\"],\"engagement\":[\"Interactive board work\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(4,1,3,NULL,4,'Database Architecture',60,'[\"Understand 2-tier and 3-tier client-server architectures.\"]','PowerPoint Presentation','[\"Drawing architectural layers\"]','[\"Labeling diagram exercise\"]','[\"Group discussion\"]',NULL,'{\"session_number\":4,\"unit_id\":null,\"title\":\"Database Architecture\",\"duration_mins\":60,\"objectives\":[\"Understand 2-tier and 3-tier client-server architectures.\"],\"teaching_method\":\"PowerPoint Presentation\",\"activities\":[\"Drawing architectural layers\"],\"formative_assessment\":[\"Labeling diagram exercise\"],\"engagement\":[\"Group discussion\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(5,1,3,NULL,5,'Introduction to Entity-Relationship (ER) Model',60,'[\"Define entities, attributes, and relationships.\"]','Chalk and Talk','[\"Identifying entities from a mini-case study\"]','[\"Quiz 1\"]','[\"Live sketching on board\"]',NULL,'{\"session_number\":5,\"unit_id\":null,\"title\":\"Introduction to Entity-Relationship (ER) Model\",\"duration_mins\":60,\"objectives\":[\"Define entities, attributes, and relationships.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Identifying entities from a mini-case study\"],\"formative_assessment\":[\"Quiz 1\"],\"engagement\":[\"Live sketching on board\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(6,1,3,NULL,6,'Mapping Cardinalities and Keys',60,'[\"Determine mapping cardinalities (1:1, 1:N, N:M).\",\"Identify primary, candidate, and foreign keys.\"]','Case Study Discussion','[\"Key identification exercise\"]','[\"Assignment 1 distribution and review\"]','[\"Peer review of keys\"]',NULL,'{\"session_number\":6,\"unit_id\":null,\"title\":\"Mapping Cardinalities and Keys\",\"duration_mins\":60,\"objectives\":[\"Determine mapping cardinalities (1:1, 1:N, N:M).\",\"Identify primary, candidate, and foreign keys.\"],\"teaching_method\":\"Case Study Discussion\",\"activities\":[\"Key identification exercise\"],\"formative_assessment\":[\"Assignment 1 distribution and review\"],\"engagement\":[\"Peer review of keys\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(7,1,3,NULL,7,'ER Diagrams - Part 1',60,'[\"Construct ER diagrams for simple scenarios.\"]','Chalk and Talk','[\"Step-by-step ER diagram construction\"]','[\"In-class drawing check\"]','[\"Collaborative whiteboard\"]',NULL,'{\"session_number\":7,\"unit_id\":null,\"title\":\"ER Diagrams - Part 1\",\"duration_mins\":60,\"objectives\":[\"Construct ER diagrams for simple scenarios.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Step-by-step ER diagram construction\"],\"formative_assessment\":[\"In-class drawing check\"],\"engagement\":[\"Collaborative whiteboard\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(8,1,3,NULL,8,'ER Diagrams - Part 2',60,'[\"Handle complex relationships and weak entities in ER diagrams.\"]','Case Study Discussion','[\"University database schema design\"]','[\"Design critique\"]','[\"Small group design challenge\"]',NULL,'{\"session_number\":8,\"unit_id\":null,\"title\":\"ER Diagrams - Part 2\",\"duration_mins\":60,\"objectives\":[\"Handle complex relationships and weak entities in ER diagrams.\"],\"teaching_method\":\"Case Study Discussion\",\"activities\":[\"University database schema design\"],\"formative_assessment\":[\"Design critique\"],\"engagement\":[\"Small group design challenge\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(9,1,3,NULL,9,'Reduction of ER Schemas to Relational Schemas - Part 1',60,'[\"Map strong and weak entity sets to tables.\"]','Chalk and Talk','[\"Translation exercise from ER to relational tables\"]','[\"Schema verification check\"]','[\"Guided practice\"]',NULL,'{\"session_number\":9,\"unit_id\":null,\"title\":\"Reduction of ER Schemas to Relational Schemas - Part 1\",\"duration_mins\":60,\"objectives\":[\"Map strong and weak entity sets to tables.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Translation exercise from ER to relational tables\"],\"formative_assessment\":[\"Schema verification check\"],\"engagement\":[\"Guided practice\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(10,1,3,NULL,10,'Reduction of ER Schemas to Relational Schemas - Part 2',60,'[\"Map relationship sets and multivalued attributes.\"]','PowerPoint Presentation','[\"Mapping N:M relationships\"]','[\"Internal Test 1 preparation quiz\"]','[\"Q&A session\"]',NULL,'{\"session_number\":10,\"unit_id\":null,\"title\":\"Reduction of ER Schemas to Relational Schemas - Part 2\",\"duration_mins\":60,\"objectives\":[\"Map relationship sets and multivalued attributes.\"],\"teaching_method\":\"PowerPoint Presentation\",\"activities\":[\"Mapping N:M relationships\"],\"formative_assessment\":[\"Internal Test 1 preparation quiz\"],\"engagement\":[\"Q&A session\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(11,1,3,NULL,11,'Unit 1 Review and Problem Solving',60,'[\"Review concepts from Unit 1.\",\"Solve comprehensive ER modeling problems.\"]','Chalk and Talk','[\"Solving past exam questions\"]','[\"Internal Test 1\"]','[\"Competitive quiz\"]',NULL,'{\"session_number\":11,\"unit_id\":null,\"title\":\"Unit 1 Review and Problem Solving\",\"duration_mins\":60,\"objectives\":[\"Review concepts from Unit 1.\",\"Solve comprehensive ER modeling problems.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Solving past exam questions\"],\"formative_assessment\":[\"Internal Test 1\"],\"engagement\":[\"Competitive quiz\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(12,1,3,NULL,12,'Unit 1 Wrap-up & Conceptual Clearance',60,'[\"Address doubts from Internal Test 1.\"]','Case Study Discussion','[\"Test debrief and paper review\"]','[\"Self-assessment rubric\"]','[\"Open forum discussion\"]',NULL,'{\"session_number\":12,\"unit_id\":null,\"title\":\"Unit 1 Wrap-up & Conceptual Clearance\",\"duration_mins\":60,\"objectives\":[\"Address doubts from Internal Test 1.\"],\"teaching_method\":\"Case Study Discussion\",\"activities\":[\"Test debrief and paper review\"],\"formative_assessment\":[\"Self-assessment rubric\"],\"engagement\":[\"Open forum discussion\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(13,1,3,NULL,13,'Structure of Relational Databases',60,'[\"Understand relational model concepts and integrity constraints.\"]','Interactive Lab Sessions','[\"Exploring table structures\"]','[\"Lab check\"]','[\"Hands-on terminal work\"]',NULL,'{\"session_number\":13,\"unit_id\":null,\"title\":\"Structure of Relational Databases\",\"duration_mins\":60,\"objectives\":[\"Understand relational model concepts and integrity constraints.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Exploring table structures\"],\"formative_assessment\":[\"Lab check\"],\"engagement\":[\"Hands-on terminal work\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(14,1,3,NULL,14,'Fundamental Relational Algebra Operations',60,'[\"Execute Select, Project, Union, Set Difference, and Cartesian Product.\"]','Chalk and Talk','[\"Writing relational algebra expressions\"]','[\"Worksheet evaluation\"]','[\"Board problem solving\"]',NULL,'{\"session_number\":14,\"unit_id\":null,\"title\":\"Fundamental Relational Algebra Operations\",\"duration_mins\":60,\"objectives\":[\"Execute Select, Project, Union, Set Difference, and Cartesian Product.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Writing relational algebra expressions\"],\"formative_assessment\":[\"Worksheet evaluation\"],\"engagement\":[\"Board problem solving\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(15,1,3,NULL,15,'Extended Relational Algebra Operations',60,'[\"Apply Join, Division, and Assignment operations.\"]','Chalk and Talk','[\"Complex relational algebra derivation\"]','[\"Pop quiz\"]','[\"Pair programming equivalent logic\"]',NULL,'{\"session_number\":15,\"unit_id\":null,\"title\":\"Extended Relational Algebra Operations\",\"duration_mins\":60,\"objectives\":[\"Apply Join, Division, and Assignment operations.\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Complex relational algebra derivation\"],\"formative_assessment\":[\"Pop quiz\"],\"engagement\":[\"Pair programming equivalent logic\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(16,1,3,NULL,16,'SQL Data Definition and Basic Queries',60,'[\"Use DDL commands to create databases and tables.\",\"Write basic SELECT-FROM-WHERE queries.\"]','Live SQL Coding Demonstrations','[\"Creating tables in SQL environment\"]','[\"Lab Practical 1\"]','[\"Live coding along with instructor\"]',NULL,'{\"session_number\":16,\"unit_id\":null,\"title\":\"SQL Data Definition and Basic Queries\",\"duration_mins\":60,\"objectives\":[\"Use DDL commands to create databases and tables.\",\"Write basic SELECT-FROM-WHERE queries.\"],\"teaching_method\":\"Live SQL Coding Demonstrations\",\"activities\":[\"Creating tables in SQL environment\"],\"formative_assessment\":[\"Lab Practical 1\"],\"engagement\":[\"Live coding along with instructor\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(17,1,3,NULL,17,'SQL Set Operations and Aggregate Functions',60,'[\"Use UNION, INTERSECT, EXCEPT.\",\"Apply COUNT, SUM, AVG, MAX, MIN with GROUP BY and HAVING.\"]','Interactive Lab Sessions','[\"Writing aggregation queries\"]','[\"SQL Coding Assignment task 1\"]','[\"Troubleshooting syntax errors in pairs\"]',NULL,'{\"session_number\":17,\"unit_id\":null,\"title\":\"SQL Set Operations and Aggregate Functions\",\"duration_mins\":60,\"objectives\":[\"Use UNION, INTERSECT, EXCEPT.\",\"Apply COUNT, SUM, AVG, MAX, MIN with GROUP BY and HAVING.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Writing aggregation queries\"],\"formative_assessment\":[\"SQL Coding Assignment task 1\"],\"engagement\":[\"Troubleshooting syntax errors in pairs\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(18,1,3,NULL,18,'Null Values and Handling Missing Data',60,'[\"Understand the impact of NULL in SQL operations and three-valued logic.\"]','Live SQL Coding Demonstrations','[\"Querying datasets with null values\"]','[\"Query output prediction check\"]','[\"Interactive polling\"]',NULL,'{\"session_number\":18,\"unit_id\":null,\"title\":\"Null Values and Handling Missing Data\",\"duration_mins\":60,\"objectives\":[\"Understand the impact of NULL in SQL operations and three-valued logic.\"],\"teaching_method\":\"Live SQL Coding Demonstrations\",\"activities\":[\"Querying datasets with null values\"],\"formative_assessment\":[\"Query output prediction check\"],\"engagement\":[\"Interactive polling\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(19,1,3,NULL,19,'Nested Subqueries - Part 1',60,'[\"Formulate subqueries in WHERE clause using IN, NOT IN, EXISTS.\"]','Interactive Lab Sessions','[\"Solving nested query exercises\"]','[\"Lab assessment\"]','[\"Guided coding\"]',NULL,'{\"session_number\":19,\"unit_id\":null,\"title\":\"Nested Subqueries - Part 1\",\"duration_mins\":60,\"objectives\":[\"Formulate subqueries in WHERE clause using IN, NOT IN, EXISTS.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Solving nested query exercises\"],\"formative_assessment\":[\"Lab assessment\"],\"engagement\":[\"Guided coding\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(20,1,3,NULL,20,'Nested Subqueries - Part 2',60,'[\"Use scalar subqueries and correlated subqueries.\"]','Live SQL Coding Demonstrations','[\"Writing correlated subqueries\"]','[\"SQL Coding Assignment task 2\"]','[\"Code optimization challenge\"]',NULL,'{\"session_number\":20,\"unit_id\":null,\"title\":\"Nested Subqueries - Part 2\",\"duration_mins\":60,\"objectives\":[\"Use scalar subqueries and correlated subqueries.\"],\"teaching_method\":\"Live SQL Coding Demonstrations\",\"activities\":[\"Writing correlated subqueries\"],\"formative_assessment\":[\"SQL Coding Assignment task 2\"],\"engagement\":[\"Code optimization challenge\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(21,1,3,NULL,21,'Complex SQL Queries',60,'[\"Combine joins, subqueries, and grouping for advanced data retrieval.\"]','Interactive Lab Sessions','[\"Enterprise reporting query design\"]','[\"Peer review of queries\"]','[\"Hackathon style query writing\"]',NULL,'{\"session_number\":21,\"unit_id\":null,\"title\":\"Complex SQL Queries\",\"duration_mins\":60,\"objectives\":[\"Combine joins, subqueries, and grouping for advanced data retrieval.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Enterprise reporting query design\"],\"formative_assessment\":[\"Peer review of queries\"],\"engagement\":[\"Hackathon style query writing\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(22,1,3,NULL,22,'Views in SQL',60,'[\"Create, update, and drop virtual tables (views).\"]','Live SQL Coding Demonstrations','[\"Implementing security and abstraction via views\"]','[\"Lab evaluation\"]','[\"Hands-on practice\"]',NULL,'{\"session_number\":22,\"unit_id\":null,\"title\":\"Views in SQL\",\"duration_mins\":60,\"objectives\":[\"Create, update, and drop virtual tables (views).\"],\"teaching_method\":\"Live SQL Coding Demonstrations\",\"activities\":[\"Implementing security and abstraction via views\"],\"formative_assessment\":[\"Lab evaluation\"],\"engagement\":[\"Hands-on practice\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(23,1,3,NULL,23,'Joined Relations in SQL',60,'[\"Master Inner, Left Outer, Right Outer, and Full Outer Joins.\"]','Interactive Lab Sessions','[\"Visualizing join outputs with sample data\"]','[\"Join query quiz\"]','[\"Diagramming join sets\"]',NULL,'{\"session_number\":23,\"unit_id\":null,\"title\":\"Joined Relations in SQL\",\"duration_mins\":60,\"objectives\":[\"Master Inner, Left Outer, Right Outer, and Full Outer Joins.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Visualizing join outputs with sample data\"],\"formative_assessment\":[\"Join query quiz\"],\"engagement\":[\"Diagramming join sets\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(24,1,3,NULL,24,'Advanced SQL Practice',60,'[\"Reinforce all SQL concepts learned so far.\"]','Live SQL Coding Demonstrations','[\"Comprehensive lab problem set\"]','[\"SQL Coding Assignment submission\"]','[\"Speed coding challenge\"]',NULL,'{\"session_number\":24,\"unit_id\":null,\"title\":\"Advanced SQL Practice\",\"duration_mins\":60,\"objectives\":[\"Reinforce all SQL concepts learned so far.\"],\"teaching_method\":\"Live SQL Coding Demonstrations\",\"activities\":[\"Comprehensive lab problem set\"],\"formative_assessment\":[\"SQL Coding Assignment submission\"],\"engagement\":[\"Speed coding challenge\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(25,1,3,NULL,25,'Unit 2 Review and Lab Test Preparation',60,'[\"Summarize relational algebra and SQL commands.\"]','Interactive Lab Sessions','[\"Mock lab exam\"]','[\"Internal Test 1 review component\"]','[\"Doubt clearing session\"]',NULL,'{\"session_number\":25,\"unit_id\":null,\"title\":\"Unit 2 Review and Lab Test Preparation\",\"duration_mins\":60,\"objectives\":[\"Summarize relational algebra and SQL commands.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Mock lab exam\"],\"formative_assessment\":[\"Internal Test 1 review component\"],\"engagement\":[\"Doubt clearing session\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(26,1,3,NULL,26,'Unit 2 Assessment Review',60,'[\"Evaluate SQL coding solutions and common errors.\"]','Interactive Lab Sessions','[\"Code walkthrough and optimization\"]','[\"Internal Test 1 SQL evaluation\"]','[\"Code refactoring workshop\"]',NULL,'{\"session_number\":26,\"unit_id\":null,\"title\":\"Unit 2 Assessment Review\",\"duration_mins\":60,\"objectives\":[\"Evaluate SQL coding solutions and common errors.\"],\"teaching_method\":\"Interactive Lab Sessions\",\"activities\":[\"Code walkthrough and optimization\"],\"formative_assessment\":[\"Internal Test 1 SQL evaluation\"],\"engagement\":[\"Code refactoring workshop\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(27,1,3,NULL,27,'Features of Good Relational Design',60,'[\"Identify anomalies (insertion, deletion, update) in poorly designed schemas.\"]','Problem-Solving Sessions','[\"Analyzing bad database designs\"]','[\"Short quiz\"]','[\"Case study discussion\"]',NULL,'{\"session_number\":27,\"unit_id\":null,\"title\":\"Features of Good Relational Design\",\"duration_mins\":60,\"objectives\":[\"Identify anomalies (insertion, deletion, update) in poorly designed schemas.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Analyzing bad database designs\"],\"formative_assessment\":[\"Short quiz\"],\"engagement\":[\"Case study discussion\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(28,1,3,NULL,28,'Atomic Domains and First Normal Form (1NF)',60,'[\"Understand atomicity of values and convert unnormalized tables to 1NF.\"]','Peer Teaching','[\"Converting nested tables to flat relations\"]','[\"Worksheet check\"]','[\"Peer explanation activity\"]',NULL,'{\"session_number\":28,\"unit_id\":null,\"title\":\"Atomic Domains and First Normal Form (1NF)\",\"duration_mins\":60,\"objectives\":[\"Understand atomicity of values and convert unnormalized tables to 1NF.\"],\"teaching_method\":\"Peer Teaching\",\"activities\":[\"Converting nested tables to flat relations\"],\"formative_assessment\":[\"Worksheet check\"],\"engagement\":[\"Peer explanation activity\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(29,1,3,NULL,29,'Functional Dependency Theory',60,'[\"Define functional dependencies (FDs) and inference rules (Armstrong\'s axioms).\"]','Problem-Solving Sessions','[\"Deriving FDs from business rules\"]','[\"Assignment 2 release\"]','[\"Guided problem solving\"]',NULL,'{\"session_number\":29,\"unit_id\":null,\"title\":\"Functional Dependency Theory\",\"duration_mins\":60,\"objectives\":[\"Define functional dependencies (FDs) and inference rules (Armstrong\'s axioms).\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Deriving FDs from business rules\"],\"formative_assessment\":[\"Assignment 2 release\"],\"engagement\":[\"Guided problem solving\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(30,1,3,NULL,30,'Closure of a Set of Functional Dependencies',60,'[\"Compute the closure of a set of FDs ($F^+$).\"]','Problem-Solving Sessions','[\"Computing FD closures on the board\"]','[\"Board work check\"]','[\"Interactive derivation\"]',NULL,'{\"session_number\":30,\"unit_id\":null,\"title\":\"Closure of a Set of Functional Dependencies\",\"duration_mins\":60,\"objectives\":[\"Compute the closure of a set of FDs ($F^+$).\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Computing FD closures on the board\"],\"formative_assessment\":[\"Board work check\"],\"engagement\":[\"Interactive derivation\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(31,1,3,NULL,31,'Closure of Attribute Sets',60,'[\"Compute attribute closures ($X^+$) to test superkeys and candidate keys.\"]','Problem-Solving Sessions','[\"Finding candidate keys using attribute closure algorithm\"]','[\"Quiz 2\"]','[\"Step-by-step group calculation\"]',NULL,'{\"session_number\":31,\"unit_id\":null,\"title\":\"Closure of Attribute Sets\",\"duration_mins\":60,\"objectives\":[\"Compute attribute closures ($X^+$) to test superkeys and candidate keys.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Finding candidate keys using attribute closure algorithm\"],\"formative_assessment\":[\"Quiz 2\"],\"engagement\":[\"Step-by-step group calculation\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(32,1,3,NULL,32,'Canonical Cover',60,'[\"Find the canonical cover ($Fc$) for a set of functional dependencies.\"]','Problem-Solving Sessions','[\"Extraneous attribute elimination exercises\"]','[\"Problem set check\"]','[\"Peer checking\"]',NULL,'{\"session_number\":32,\"unit_id\":null,\"title\":\"Canonical Cover\",\"duration_mins\":60,\"objectives\":[\"Find the canonical cover ($Fc$) for a set of functional dependencies.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Extraneous attribute elimination exercises\"],\"formative_assessment\":[\"Problem set check\"],\"engagement\":[\"Peer checking\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(33,1,3,NULL,33,'Decomposition Using Functional Dependencies',60,'[\"Understand lossless-join and dependency-preserving decompositions.\"]','Peer Teaching','[\"Testing decompositions for losslessness\"]','[\"Formative test\"]','[\"Small group teaching\"]',NULL,'{\"session_number\":33,\"unit_id\":null,\"title\":\"Decomposition Using Functional Dependencies\",\"duration_mins\":60,\"objectives\":[\"Understand lossless-join and dependency-preserving decompositions.\"],\"teaching_method\":\"Peer Teaching\",\"activities\":[\"Testing decompositions for losslessness\"],\"formative_assessment\":[\"Formative test\"],\"engagement\":[\"Small group teaching\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(34,1,3,NULL,34,'Third Normal Form (3NF)',60,'[\"Define 3NF and test schemas for 3NF compliance.\"]','Problem-Solving Sessions','[\"Synthesizing 3NF relations\"]','[\"Assignment 2 submission\"]','[\"Guided design exercise\"]',NULL,'{\"session_number\":34,\"unit_id\":null,\"title\":\"Third Normal Form (3NF)\",\"duration_mins\":60,\"objectives\":[\"Define 3NF and test schemas for 3NF compliance.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Synthesizing 3NF relations\"],\"formative_assessment\":[\"Assignment 2 submission\"],\"engagement\":[\"Guided design exercise\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(35,1,3,NULL,35,'Boyer-Codd Normal Form (BCNF)',60,'[\"Define BCNF and decompose schemas that violate BCNF.\"]','Problem-Solving Sessions','[\"BCNF decomposition algorithm practice\"]','[\"Internal Test 2 preparation quiz\"]','[\"Board race problem solving\"]',NULL,'{\"session_number\":35,\"unit_id\":null,\"title\":\"Boyer-Codd Normal Form (BCNF)\",\"duration_mins\":60,\"objectives\":[\"Define BCNF and decompose schemas that violate BCNF.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"BCNF decomposition algorithm practice\"],\"formative_assessment\":[\"Internal Test 2 preparation quiz\"],\"engagement\":[\"Board race problem solving\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(36,1,3,NULL,36,'Multi-valued Dependencies and Fourth Normal Form (4NF)',60,'[\"Identify multi-valued dependencies and apply 4NF.\"]','Peer Teaching','[\"Analyzing multi-valued anomaly cases\"]','[\"Concept check\"]','[\"Discussion panel\"]',NULL,'{\"session_number\":36,\"unit_id\":null,\"title\":\"Multi-valued Dependencies and Fourth Normal Form (4NF)\",\"duration_mins\":60,\"objectives\":[\"Identify multi-valued dependencies and apply 4NF.\"],\"teaching_method\":\"Peer Teaching\",\"activities\":[\"Analyzing multi-valued anomaly cases\"],\"formative_assessment\":[\"Concept check\"],\"engagement\":[\"Discussion panel\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(37,1,3,NULL,37,'Normalization Practice Workshop',60,'[\"Practice end-to-end normalization from unnormalized forms to BCNF\\/4NF.\"]','Problem-Solving Sessions','[\"Comprehensive case study normalization\"]','[\"Internal Test 2\"]','[\"Collaborative problem solving\"]',NULL,'{\"session_number\":37,\"unit_id\":null,\"title\":\"Normalization Practice Workshop\",\"duration_mins\":60,\"objectives\":[\"Practice end-to-end normalization from unnormalized forms to BCNF\\/4NF.\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Comprehensive case study normalization\"],\"formative_assessment\":[\"Internal Test 2\"],\"engagement\":[\"Collaborative problem solving\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(38,1,3,NULL,38,'Unit 3 Review and Doubt Clearing',60,'[\"Review normalization and functional dependency algorithms.\"]','Peer Teaching','[\"Student-led review of difficult topics\"]','[\"Internal Test 2 review\"]','[\"Q&A session\"]',NULL,'{\"session_number\":38,\"unit_id\":null,\"title\":\"Unit 3 Review and Doubt Clearing\",\"duration_mins\":60,\"objectives\":[\"Review normalization and functional dependency algorithms.\"],\"teaching_method\":\"Peer Teaching\",\"activities\":[\"Student-led review of difficult topics\"],\"formative_assessment\":[\"Internal Test 2 review\"],\"engagement\":[\"Q&A session\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(39,1,3,NULL,39,'Transaction Concept and Simple Transaction Model',60,'[\"Understand ACID properties of transactions.\"]','Lecture','[\"Bank transfer transaction walkthrough\"]','[\"Assignment 3 release\"]','[\"Real-world analogy discussion\"]',NULL,'{\"session_number\":39,\"unit_id\":null,\"title\":\"Transaction Concept and Simple Transaction Model\",\"duration_mins\":60,\"objectives\":[\"Understand ACID properties of transactions.\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Bank transfer transaction walkthrough\"],\"formative_assessment\":[\"Assignment 3 release\"],\"engagement\":[\"Real-world analogy discussion\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(40,1,3,NULL,40,'Transaction Atomicity, Durability, and Storage Structure',60,'[\"Examine how atomicity and durability are maintained via storage management.\"]','Simulations','[\"Transaction failure simulation\"]','[\"Short quiz\"]','[\"Interactive simulation tool\"]',NULL,'{\"session_number\":40,\"unit_id\":null,\"title\":\"Transaction Atomicity, Durability, and Storage Structure\",\"duration_mins\":60,\"objectives\":[\"Examine how atomicity and durability are maintained via storage management.\"],\"teaching_method\":\"Simulations\",\"activities\":[\"Transaction failure simulation\"],\"formative_assessment\":[\"Short quiz\"],\"engagement\":[\"Interactive simulation tool\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(41,1,3,NULL,41,'Transaction Isolation and Serializability',60,'[\"Define conflict and view serializability.\"]','Lecture','[\"Drawing precedence graphs to test serializability\"]','[\"Worksheet check\"]','[\"Pair graph validation\"]',NULL,'{\"session_number\":41,\"unit_id\":null,\"title\":\"Transaction Isolation and Serializability\",\"duration_mins\":60,\"objectives\":[\"Define conflict and view serializability.\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Drawing precedence graphs to test serializability\"],\"formative_assessment\":[\"Worksheet check\"],\"engagement\":[\"Pair graph validation\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(42,1,3,NULL,42,'Recoverable and Cascadeless Schedules',60,'[\"Distinguish between recoverable, cascadeless, and non-recoverable schedules.\"]','Case Study Analysis','[\"Evaluating schedule logs\"]','[\"Schedule analysis check\"]','[\"Group categorization activity\"]',NULL,'{\"session_number\":42,\"unit_id\":null,\"title\":\"Recoverable and Cascadeless Schedules\",\"duration_mins\":60,\"objectives\":[\"Distinguish between recoverable, cascadeless, and non-recoverable schedules.\"],\"teaching_method\":\"Case Study Analysis\",\"activities\":[\"Evaluating schedule logs\"],\"formative_assessment\":[\"Schedule analysis check\"],\"engagement\":[\"Group categorization activity\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(43,1,3,NULL,43,'Concurrency Control and Lock-Based Protocols',60,'[\"Understand shared and exclusive locks.\",\"Apply Two-Phase Locking (2PL) protocol.\"]','Simulations','[\"Lock compatibility matrix application\"]','[\"Assignment 3 check\"]','[\"Interactive locking simulation\"]',NULL,'{\"session_number\":43,\"unit_id\":null,\"title\":\"Concurrency Control and Lock-Based Protocols\",\"duration_mins\":60,\"objectives\":[\"Understand shared and exclusive locks.\",\"Apply Two-Phase Locking (2PL) protocol.\"],\"teaching_method\":\"Simulations\",\"activities\":[\"Lock compatibility matrix application\"],\"formative_assessment\":[\"Assignment 3 check\"],\"engagement\":[\"Interactive locking simulation\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(44,1,3,NULL,44,'Timestamp-Based and Validation-Based Protocols',60,'[\"Implement timestamp ordering protocol.\",\"Understand validation-based concurrency control.\"]','Lecture','[\"Timestamp conflict resolution exercises\"]','[\"Pop quiz\"]','[\"Step-by-step walk-through\"]',NULL,'{\"session_number\":44,\"unit_id\":null,\"title\":\"Timestamp-Based and Validation-Based Protocols\",\"duration_mins\":60,\"objectives\":[\"Implement timestamp ordering protocol.\",\"Understand validation-based concurrency control.\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Timestamp conflict resolution exercises\"],\"formative_assessment\":[\"Pop quiz\"],\"engagement\":[\"Step-by-step walk-through\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(45,1,3,NULL,45,'Deadlock Handling',60,'[\"Analyze deadlock prevention, detection, and recovery mechanisms.\"]','Case Study Analysis','[\"Wait-for graph construction and cycle detection\"]','[\"Deadlock quiz\"]','[\"Group scenario analysis\"]',NULL,'{\"session_number\":45,\"unit_id\":null,\"title\":\"Deadlock Handling\",\"duration_mins\":60,\"objectives\":[\"Analyze deadlock prevention, detection, and recovery mechanisms.\"],\"teaching_method\":\"Case Study Analysis\",\"activities\":[\"Wait-for graph construction and cycle detection\"],\"formative_assessment\":[\"Deadlock quiz\"],\"engagement\":[\"Group scenario analysis\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(46,1,3,NULL,46,'Transactions & Concurrency Control Problem Solving',60,'[\"Solve complex scheduling, locking, and deadlock problems.\"]','Simulations','[\"Comprehensive transaction problem set\"]','[\"Assignment 3 submission\"]','[\"Problem-solving competition\"]',NULL,'{\"session_number\":46,\"unit_id\":null,\"title\":\"Transactions & Concurrency Control Problem Solving\",\"duration_mins\":60,\"objectives\":[\"Solve complex scheduling, locking, and deadlock problems.\"],\"teaching_method\":\"Simulations\",\"activities\":[\"Comprehensive transaction problem set\"],\"formative_assessment\":[\"Assignment 3 submission\"],\"engagement\":[\"Problem-solving competition\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(47,1,3,NULL,47,'Unit 4 Review and Transaction Scenarios',60,'[\"Review all transaction management and concurrency control topics.\"]','Lecture','[\"Review game \\/ structured Q&A\"]','[\"End Semester Exam preparation check\"]','[\"Interactive trivia\"]',NULL,'{\"session_number\":47,\"unit_id\":null,\"title\":\"Unit 4 Review and Transaction Scenarios\",\"duration_mins\":60,\"objectives\":[\"Review all transaction management and concurrency control topics.\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Review game \\/ structured Q&A\"],\"formative_assessment\":[\"End Semester Exam preparation check\"],\"engagement\":[\"Interactive trivia\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(48,1,3,NULL,48,'Unit 4 Assessment Review',60,'[\"Review solutions for transaction management assessments.\"]','Case Study Analysis','[\"Detailed paper review\"]','[\"Internal test review\"]','[\"Open discussion\"]',NULL,'{\"session_number\":48,\"unit_id\":null,\"title\":\"Unit 4 Assessment Review\",\"duration_mins\":60,\"objectives\":[\"Review solutions for transaction management assessments.\"],\"teaching_method\":\"Case Study Analysis\",\"activities\":[\"Detailed paper review\"],\"formative_assessment\":[\"Internal test review\"],\"engagement\":[\"Open discussion\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(49,1,3,NULL,49,'Failure Classification, Storage, and Recovery Atomicity',60,'[\"Classify database failures and understand stable storage principles.\"]','Flipped Classroom','[\"Student presentations on types of database failures\"]','[\"Quiz 3\"]','[\"Student-led presentation\"]',NULL,'{\"session_number\":49,\"unit_id\":null,\"title\":\"Failure Classification, Storage, and Recovery Atomicity\",\"duration_mins\":60,\"objectives\":[\"Classify database failures and understand stable storage principles.\"],\"teaching_method\":\"Flipped Classroom\",\"activities\":[\"Student presentations on types of database failures\"],\"formative_assessment\":[\"Quiz 3\"],\"engagement\":[\"Student-led presentation\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(50,1,3,NULL,50,'Log-Based Recovery',60,'[\"Understand deferred-database modification and immediate-database modification recovery using logs.\"]','Technical Seminar','[\"Tracing undo and redo operations from log files\"]','[\"Log tracing worksheet\"]','[\"Step-by-step code\\/log tracing\"]',NULL,'{\"session_number\":50,\"unit_id\":null,\"title\":\"Log-Based Recovery\",\"duration_mins\":60,\"objectives\":[\"Understand deferred-database modification and immediate-database modification recovery using logs.\"],\"teaching_method\":\"Technical Seminar\",\"activities\":[\"Tracing undo and redo operations from log files\"],\"formative_assessment\":[\"Log tracing worksheet\"],\"engagement\":[\"Step-by-step code\\/log tracing\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(51,1,3,NULL,51,'Checkpoints and Shadow Paging',60,'[\"Implement checkpointing to reduce recovery overhead.\",\"Compare log-based recovery with shadow paging.\"]','Flipped Classroom','[\"Comparing recovery overheads\"]','[\"Short quiz\"]','[\"Comparative analysis debate\"]',NULL,'{\"session_number\":51,\"unit_id\":null,\"title\":\"Checkpoints and Shadow Paging\",\"duration_mins\":60,\"objectives\":[\"Implement checkpointing to reduce recovery overhead.\",\"Compare log-based recovery with shadow paging.\"],\"teaching_method\":\"Flipped Classroom\",\"activities\":[\"Comparing recovery overheads\"],\"formative_assessment\":[\"Short quiz\"],\"engagement\":[\"Comparative analysis debate\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(52,1,3,NULL,52,'Recovery with Concurrent Transactions',60,'[\"Analyze advanced recovery techniques involving concurrent transaction execution.\"]','Technical Seminar','[\"Advanced log analysis case study\"]','[\"Seminar feedback rubric\"]','[\"Technical Q&A\"]',NULL,'{\"session_number\":52,\"unit_id\":null,\"title\":\"Recovery with Concurrent Transactions\",\"duration_mins\":60,\"objectives\":[\"Analyze advanced recovery techniques involving concurrent transaction execution.\"],\"teaching_method\":\"Technical Seminar\",\"activities\":[\"Advanced log analysis case study\"],\"formative_assessment\":[\"Seminar feedback rubric\"],\"engagement\":[\"Technical Q&A\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(53,1,3,NULL,53,'Introduction to NoSQL Databases',60,'[\"Understand the shift from relational to NoSQL databases.\",\"Explore NoSQL data models (document, key-value, column, graph).\"]','Flipped Classroom','[\"Exploring MongoDB\\/Redis basic concepts\"]','[\"NoSQL concept check\"]','[\"Hands-on browser shell exploration\"]',NULL,'{\"session_number\":53,\"unit_id\":null,\"title\":\"Introduction to NoSQL Databases\",\"duration_mins\":60,\"objectives\":[\"Understand the shift from relational to NoSQL databases.\",\"Explore NoSQL data models (document, key-value, column, graph).\"],\"teaching_method\":\"Flipped Classroom\",\"activities\":[\"Exploring MongoDB\\/Redis basic concepts\"],\"formative_assessment\":[\"NoSQL concept check\"],\"engagement\":[\"Hands-on browser shell exploration\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(54,1,3,NULL,54,'CAP Theorem',60,'[\"Analyze Consistency, Availability, and Partition Tolerance trade-offs in distributed systems.\"]','Technical Seminar','[\"CAP theorem trade-off analysis for real systems\"]','[\"Short written assessment\"]','[\"Case study debate\"]',NULL,'{\"session_number\":54,\"unit_id\":null,\"title\":\"CAP Theorem\",\"duration_mins\":60,\"objectives\":[\"Analyze Consistency, Availability, and Partition Tolerance trade-offs in distributed systems.\"],\"teaching_method\":\"Technical Seminar\",\"activities\":[\"CAP theorem trade-off analysis for real systems\"],\"formative_assessment\":[\"Short written assessment\"],\"engagement\":[\"Case study debate\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(55,1,3,NULL,55,'Introduction to Distributed Databases',60,'[\"Understand distributed database design, fragmentation, and replication.\"]','Flipped Classroom','[\"Mapping distributed data storage architectures\"]','[\"End Semester Exam preparation check\"]','[\"Group mapping activity\"]',NULL,'{\"session_number\":55,\"unit_id\":null,\"title\":\"Introduction to Distributed Databases\",\"duration_mins\":60,\"objectives\":[\"Understand distributed database design, fragmentation, and replication.\"],\"teaching_method\":\"Flipped Classroom\",\"activities\":[\"Mapping distributed data storage architectures\"],\"formative_assessment\":[\"End Semester Exam preparation check\"],\"engagement\":[\"Group mapping activity\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(56,1,3,NULL,56,'Advanced Databases and Recovery Review',60,'[\"Review log-based recovery, NoSQL, and distributed databases.\"]','Technical Seminar','[\"Comprehensive review discussion\"]','[\"End Semester Exam mock test component\"]','[\"Open floor review\"]',NULL,'{\"session_number\":56,\"unit_id\":null,\"title\":\"Advanced Databases and Recovery Review\",\"duration_mins\":60,\"objectives\":[\"Review log-based recovery, NoSQL, and distributed databases.\"],\"teaching_method\":\"Technical Seminar\",\"activities\":[\"Comprehensive review discussion\"],\"formative_assessment\":[\"End Semester Exam mock test component\"],\"engagement\":[\"Open floor review\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(57,1,3,NULL,57,'Comprehensive Course Review - Part 1',60,'[\"Review Units 1 and 2 (ER Modeling, Relational Algebra, SQL).\"]','Chalk and Talk','[\"Rapid-fire problem solving across core modules\"]','[\"Mock exam practice\"]','[\"Team quiz challenge\"]',NULL,'{\"session_number\":57,\"unit_id\":null,\"title\":\"Comprehensive Course Review - Part 1\",\"duration_mins\":60,\"objectives\":[\"Review Units 1 and 2 (ER Modeling, Relational Algebra, SQL).\"],\"teaching_method\":\"Chalk and Talk\",\"activities\":[\"Rapid-fire problem solving across core modules\"],\"formative_assessment\":[\"Mock exam practice\"],\"engagement\":[\"Team quiz challenge\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(58,1,3,NULL,58,'Comprehensive Course Review - Part 2',60,'[\"Review Unit 3 (Normalization and Functional Dependencies).\"]','Problem-Solving Sessions','[\"Solving complex normalization and closure problems\"]','[\"Practice problem review\"]','[\"Board work review\"]',NULL,'{\"session_number\":58,\"unit_id\":null,\"title\":\"Comprehensive Course Review - Part 2\",\"duration_mins\":60,\"objectives\":[\"Review Unit 3 (Normalization and Functional Dependencies).\"],\"teaching_method\":\"Problem-Solving Sessions\",\"activities\":[\"Solving complex normalization and closure problems\"],\"formative_assessment\":[\"Practice problem review\"],\"engagement\":[\"Board work review\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(59,1,3,NULL,59,'Comprehensive Course Review - Part 3',60,'[\"Review Units 4 and 5 (Transactions, Concurrency, Recovery, NoSQL).\"]','Lecture','[\"Final doubt clearing and key formula\\/algorithm recap\"]','[\"End Semester Exam final prep check\"]','[\"Comprehensive Q&A\"]',NULL,'{\"session_number\":59,\"unit_id\":null,\"title\":\"Comprehensive Course Review - Part 3\",\"duration_mins\":60,\"objectives\":[\"Review Units 4 and 5 (Transactions, Concurrency, Recovery, NoSQL).\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Final doubt clearing and key formula\\/algorithm recap\"],\"formative_assessment\":[\"End Semester Exam final prep check\"],\"engagement\":[\"Comprehensive Q&A\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34'),(60,1,3,NULL,60,'Final Course Wrap-Up and Exam Strategy',60,'[\"Discuss final exam structure, expectations, and answer presentation strategies.\"]','Lecture','[\"Sample question paper discussion and time management tips\"]','[\"End Semester Exam\"]','[\"Motivational closing discussion\"]',NULL,'{\"session_number\":60,\"unit_id\":null,\"title\":\"Final Course Wrap-Up and Exam Strategy\",\"duration_mins\":60,\"objectives\":[\"Discuss final exam structure, expectations, and answer presentation strategies.\"],\"teaching_method\":\"Lecture\",\"activities\":[\"Sample question paper discussion and time management tips\"],\"formative_assessment\":[\"End Semester Exam\"],\"engagement\":[\"Motivational closing discussion\"]}',NULL,'planned',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-11 11:59:34');
/*!40000 ALTER TABLE `lesson_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marks_formulas`
--

DROP TABLE IF EXISTS `marks_formulas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marks_formulas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `subject_type` varchar(20) DEFAULT NULL COMMENT 'theory|lab|NULL=all types',
  `subject_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL=department/type default; set=subject override',
  `name` varchar(120) NOT NULL,
  `pattern` varchar(80) DEFAULT NULL COMMENT 'Anna, Madurai, CBCS, etc.',
  `plain_english` text NOT NULL,
  `components` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '[{code,label,max,weight}]' CHECK (json_valid(`components`)),
  `expression` text DEFAULT NULL COMMENT 'Parsed calculation expression',
  `total_max` decimal(6,2) DEFAULT 25.00,
  `ai_parsed` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_parsed`)),
  `is_default` tinyint(1) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marks_formulas`
--

LOCK TABLES `marks_formulas` WRITE;
/*!40000 ALTER TABLE `marks_formulas` DISABLE KEYS */;
/*!40000 ALTER TABLE `marks_formulas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `institution_id` int(10) unsigned DEFAULT NULL COMMENT 'Tenant isolation',
  `type` varchar(60) NOT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'medium' COMMENT 'high|medium|low',
  `title` varchar(200) NOT NULL,
  `body` text DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `action_type` varchar(60) DEFAULT NULL,
  `action_payload` longtext DEFAULT NULL COMMENT 'JSON action metadata',
  `delivery_status` longtext DEFAULT NULL COMMENT 'JSON channel delivery statuses',
  `is_read` tinyint(1) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`is_read`),
  KEY `idx_notif_inst` (`institution_id`),
  KEY `idx_notif_priority` (`user_id`,`priority`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,4,1,'announcement','medium','Announcement from Professor','hii\n\nCSE301 · Database Management Systems\n1st Year · UG · Year 1 · Sec A (CSE)\nFrom: sandra','/student/notifications.php','OPEN_NOTIFICATIONS','{\"type\":\"OPEN_NOTIFICATIONS\"}','{\"in_app\":{\"status\":\"delivered\",\"at\":\"2026-11-11 17:35:47\"},\"email\":{\"status\":\"disabled\",\"at\":null},\"whatsapp\":{\"status\":\"not_configured\",\"at\":null},\"sms\":{\"status\":\"not_configured\",\"at\":null}}',0,'{\"announcement_id\":1,\"professor_id\":3,\"subject_id\":1,\"class_id\":1,\"year\":1,\"course_label\":\"CSE301 · Database Management Systems\",\"class_label\":\"UG · Year 1 · Sec A (CSE)\",\"kind\":\"professor_student_message\",\"has_attachment\":false,\"attachment_original_name\":null,\"category\":\"system\",\"digest_mode\":\"immediate\"}','2026-11-11 12:05:47');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plan_reviews`
--

DROP TABLE IF EXISTS `plan_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan_reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned NOT NULL,
  `reviewer_id` int(10) unsigned NOT NULL,
  `action` enum('approve','reject','request_changes','comment') NOT NULL,
  `comments` text DEFAULT NULL,
  `checklist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plan_reviews`
--

LOCK TABLES `plan_reviews` WRITE;
/*!40000 ALTER TABLE `plan_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `plan_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plan_units`
--

DROP TABLE IF EXISTS `plan_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan_units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned NOT NULL,
  `unit_number` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `hours` decimal(5,1) DEFAULT 0.0,
  `topics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`topics`)),
  `outcomes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`outcomes`)),
  `bloom_k_level` varchar(10) DEFAULT NULL,
  `bloom_map` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bloom_map`)),
  `teaching_methods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`teaching_methods`)),
  `assessment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assessment`)),
  `resources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resources`)),
  `sort_order` int(11) DEFAULT 0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  PRIMARY KEY (`id`),
  KEY `idx_unit_plan` (`plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plan_units`
--

LOCK TABLES `plan_units` WRITE;
/*!40000 ALTER TABLE `plan_units` DISABLE KEYS */;
INSERT INTO `plan_units` VALUES (1,1,1,'Introduction to DBMS and ER Model',12.0,'[\"Database System Applications\",\"Purpose of Database Systems\",\"View of Data\",\"Data Independence\",\"Database Languages\",\"Data Models\",\"Database Architecture\",\"Entity-Relationship (ER) Model\",\"Mapping Cardinalities\",\"Keys\",\"ER Diagrams\",\"Reduction to Relational Schemas\"]','[\"Explain the role of DBMS in modern enterprise applications.\",\"Construct ER diagrams for real-world scenarios.\"]','K3',NULL,'[\"Chalk and Talk\",\"PowerPoint Presentation\",\"Case Study Discussion\"]','[\"Quiz 1\",\"Assignment 1\",\"Internal Test 1\"]',NULL,0,NULL),(2,1,2,'Relational Model and Structured Query Language (SQL)',15.0,'[\"Structure of Relational Databases\",\"Fundamental Relational Algebra Operations\",\"Extended Relational Algebra Operations\",\"SQL Data Definition\",\"Basic Structure of SQL Queries\",\"Set Operations\",\"Aggregate Functions\",\"Null Values\",\"Nested Subqueries\",\"Complex Queries\",\"Views\",\"Joined Relations\"]','[\"Execute relational algebra operations.\",\"Write efficient SQL queries for data retrieval and manipulation.\"]','K4',NULL,'[\"Interactive Lab Sessions\",\"Live SQL Coding Demonstrations\"]','[\"Lab Practical 1\",\"SQL Coding Assignment\",\"Internal Test 1\"]',NULL,1,NULL),(3,1,3,'Database Design and Normalization',12.0,'[\"Features of Good Relational Design\",\"Atomic Domains and First Normal Form\",\"Decomposition Using Functional Dependencies\",\"Functional Dependency Theory\",\"Algorithms for Functional Dependencies\",\"Closure of Set of Functional Dependencies\",\"Closure of Attribute Sets\",\"Canonical Cover\",\"Boyer-Codd Normal Form (BCNF)\",\"Third Normal Form (3NF)\",\"Fourth Normal Form (4NF)\",\"Multi-valued Dependencies\"]','[\"Analyze functional dependencies and determine attribute closures.\",\"Normalize relational schemas up to BCNF.\"]','K4',NULL,'[\"Problem-Solving Sessions\",\"Peer Teaching\"]','[\"Assignment 2\",\"Quiz 2\",\"Internal Test 2\"]',NULL,2,NULL),(4,1,4,'Transactions and Concurrency Control',11.0,'[\"Transaction Concept\",\"Simple Transaction Model\",\"Storage Structure\",\"Transaction Atomicity and Durability\",\"Transaction Isolation\",\"Serializability\",\"Recoverable Schedules\",\"Cascadeless Schedules\",\"Concurrency Control\",\"Lock-Based Protocols\",\"Timestamp-Based Protocols\",\"Validation-Based Protocols\",\"Deadlock Handling\"]','[\"Verify the serializability of transaction schedules.\",\"Compare concurrency control protocols and deadlock management techniques.\"]','K5',NULL,'[\"Lecture\",\"Simulations\",\"Case Study Analysis\"]','[\"Assignment 3\",\"Internal Test 2\",\"End Semester Exam\"]',NULL,3,NULL),(5,1,5,'Recovery System and Advanced Databases',10.0,'[\"Failure Classification\",\"Storage\",\"Recovery and Atomicity\",\"Log-Based Recovery\",\"Checkpoints\",\"Shadow Paging\",\"Recovery with Concurrent Transactions\",\"Introduction to NoSQL Databases\",\"CAP Theorem\",\"Introduction to Distributed Databases\"]','[\"Explain log-based recovery and checkpointing mechanisms.\",\"Compare traditional RDBMS with NoSQL and distributed databases.\"]','K3',NULL,'[\"Flipped Classroom\",\"Technical Seminar\"]','[\"Quiz 3\",\"End Semester Exam\"]',NULL,4,NULL);
/*!40000 ALTER TABLE `plan_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presentations`
--

DROP TABLE IF EXISTS `presentations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `presentations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slide_count` int(10) unsigned DEFAULT 0,
  `slides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`slides`)),
  `status` enum('draft','ready','published') DEFAULT 'draft',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presentations`
--

LOCK TABLES `presentations` WRITE;
/*!40000 ALTER TABLE `presentations` DISABLE KEYS */;
INSERT INTO `presentations` VALUES (1,1,3,1,'DBMS',9,'[{\"number\":1,\"title\":\"Database Management Systems (DBMS)\",\"bullets\":[\"Institution: K.L.N College of Engineering\",\"Department: Computer Science and Engineering\",\"Course: Database Management Systems\",\"Comprehensive Full Course Coverage (Units 1 to 5)\"],\"speaker_notes\":\"Welcome to the comprehensive course on Database Management Systems. Today we cover the entire curriculum from foundational data models to transaction processing and NoSQL databases.\",\"unit_tag\":\"Unit 1\",\"layout\":\"content\"},{\"number\":2,\"title\":\"Course Learning Objectives\",\"bullets\":[\"Design Entity-Relationship models and map them to normalized relational schemas.\",\"Formulate complex relational algebra expressions and SQL queries for data retrieval.\",\"Analyze functional dependencies to achieve optimal database normalization up to BCNF and 4NF.\",\"Evaluate transaction schedules using serializability and concurrency control protocols.\",\"Implement recovery mechanisms and distinguish relational databases from NoSQL architectures.\"],\"speaker_notes\":\"Review these core objectives carefully. They guide our progression from conceptual design all the way to physical recovery and modern distributed systems.\",\"unit_tag\":\"Unit 1\",\"layout\":\"content\"},{\"number\":3,\"title\":\"Unit 1: Data Models, Architecture & ER Design\",\"bullets\":[\"Data Independence: Separation of ANSI-SPARC 3-schema architecture (Internal, Conceptual, External) isolating applications from physical storage changes.\",\"Database Languages: DDL (Data Definition Language) for schema definition, DML (Data Manipulation Language) for queries and updates.\",\"ER Modeling: Entities, attributes, mapping cardinalities (1:1, 1:N, N:M), and primary\\/foreign keys.\",\"Schema Reduction: Systematic transformation of ER diagrams into relational tables preserving referential integrity.\"],\"speaker_notes\":\"Unit 1 establishes how real-world domains are modeled conceptually and translated into structured schemas while maintaining data independence.\",\"unit_tag\":\"Unit 1\",\"layout\":\"content\"},{\"number\":4,\"title\":\"Unit 2: Relational Algebra & Advanced SQL\",\"bullets\":[\"Fundamental Operations: Selection (sigma), Projection (pi), Union, Set Difference, Cartesian Product, and Rename.\",\"Extended Operations: Outer joins, left\\/right\\/full joins, and aggregate functions (SUM, AVG, COUNT).\",\"SQL Subqueries & Views: Nested subqueries for complex filtering, correlated subqueries, and virtual tables (Views) for security and abstraction.\",\"Null Logic: Three-valued logic handling of unknown or missing data values during evaluations.\"],\"speaker_notes\":\"Unit 2 bridges theory and practice. Relational algebra provides the formal foundation, while SQL executes these operations on real database instances.\",\"unit_tag\":\"Unit 1\",\"layout\":\"content\"},{\"number\":5,\"title\":\"Unit 3: Functional Dependencies & Normalization\",\"bullets\":[\"Functional Dependencies (FD): Constraints where attribute set X uniquely determines attribute set Y (X -> Y).\",\"Axioms & Closure: Armstrong\'s axioms, computing attribute closures (X+), and canonical covers to minimize redundant FDs.\",\"Normal Forms: 1NF (atomic domains), 2NF (full functional dependency), and 3NF (eliminating transitive dependencies).\",\"Advanced Normal Forms: BCNF (every determinant is a candidate key) and 4NF (eliminating multi-valued dependencies).\"],\"speaker_notes\":\"Normalization eliminates redundancy and insertion, update, and deletion anomalies. We use attribute closures and canonical covers to mathematically verify normal forms.\",\"unit_tag\":\"Unit 1\",\"layout\":\"content\"},{\"number\":6,\"title\":\"Unit 4: Transactions & Concurrency Control\",\"bullets\":[\"ACID Properties: Atomicity, Consistency, Isolation, and Durability ensuring reliable execution of units of work.\",\"Serializability: Conflict and view serializability ensuring concurrent execution yields identical results to serial execution.\",\"Lock-Based Protocols: Two-Phase Locking (2PL) and strict\\/rigorous 2PL guaranteeing conflict serializability.\",\"Deadlock Management: Deadlock prevention schemes (wait-die, wound-wait) and detection via wait-for graphs.\"],\"speaker_notes\":\"Transactions must maintain database consistency despite concurrent access and system failures. Isolation levels and locking protocols manage this concurrency.\",\"unit_tag\":\"Unit 1\",\"layout\":\"content\"},{\"number\":7,\"title\":\"Unit 5: Crash Recovery & NoSQL Databases\",\"bullets\":[\"Log-Based Recovery: Write-Ahead Logging (WAL), undo\\/redo operations, and checkpointing for crash recovery.\",\"Alternative Recovery: Shadow paging maintaining two page tables during transaction execution.\",\"CAP Theorem: Distributed systems must choose at most two among Consistency, Availability, and Partition Tolerance.\",\"NoSQL Architecture: Introduction to key-value, document, and columnar stores for horizontal scalability.\"],\"speaker_notes\":\"Unit 5 addresses what happens when systems fail and how modern distributed architectures scale beyond traditional relational limits using the CAP theorem.\",\"unit_tag\":\"Unit 1\",\"layout\":\"content\"},{\"number\":8,\"title\":\"Unit Summary & Review Questions\",\"bullets\":[\"Summary: Covered full database lifecycle from ER modeling and SQL querying to normalization, concurrency control, and recovery.\",\"Review Question 1: Given a relation R(A,B,C,D) with FDs {A->B, B->C}, decompose into 3NF and BCNF.\",\"Review Question 2: Explain how Two-Phase Locking guarantees conflict serializability.\",\"Review Question 3: Discuss the implications of the CAP theorem when designing a distributed e-commerce database.\"],\"speaker_notes\":\"Review these questions to test your grasp of the core concepts across all five units. Be prepared to solve similar problems in examinations.\",\"unit_tag\":\"Unit 1\",\"layout\":\"summary\"},{\"number\":9,\"title\":\"Thank You\",\"bullets\":[\"Department of Computer Science and Engineering\",\"K.L.N College of Engineering\",\"Open for Questions and Discussion\"],\"speaker_notes\":\"Thank you for your attention. We will now open the floor for any questions regarding the DBMS curriculum.\",\"unit_tag\":\"Unit 1\",\"layout\":\"close\"}]','ready','{\"subject\":\"Database Management Systems\",\"unit\":1,\"plan_id\":1,\"branding\":{\"institution_id\":1,\"name\":\"K.L.N College of Engineering\",\"logo_url\":\"https:\\/\\/klnce.edu\\/images\\/Common_wrapper4.gif\",\"primary\":\"1E3A8A\",\"secondary\":\"0F172A\",\"accent\":\"D97706\"},\"speaker_notes\":true}','2026-11-11 12:00:36','2026-11-11 12:00:36');
/*!40000 ALTER TABLE `presentations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `professor_ai_settings`
--

DROP TABLE IF EXISTS `professor_ai_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `professor_ai_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `professor_id` int(10) unsigned NOT NULL,
  `provider` varchar(32) NOT NULL,
  `model` varchar(120) NOT NULL,
  `encrypted_api_key` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prof_ai` (`professor_id`),
  KEY `idx_prof_ai_active` (`professor_id`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `professor_ai_settings`
--

LOCK TABLES `professor_ai_settings` WRITE;
/*!40000 ALTER TABLE `professor_ai_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `professor_ai_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `professor_announcements`
--

DROP TABLE IF EXISTS `professor_announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `professor_announcements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `year` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `recipient_count` int(10) unsigned NOT NULL DEFAULT 0,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_original_name` varchar(255) DEFAULT NULL,
  `attachment_mime_type` varchar(100) DEFAULT NULL,
  `attachment_size` int(10) unsigned DEFAULT NULL,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ann_prof` (`professor_id`,`created_at`),
  KEY `idx_ann_inst` (`institution_id`),
  KEY `idx_ann_scope` (`subject_id`,`class_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `professor_announcements`
--

LOCK TABLES `professor_announcements` WRITE;
/*!40000 ALTER TABLE `professor_announcements` DISABLE KEYS */;
INSERT INTO `professor_announcements` VALUES (1,1,1,3,1,1,1,'Announcement from Professor','hii',1,NULL,NULL,NULL,NULL,'{\"course_label\":\"CSE301 · Database Management Systems\",\"class_label\":\"UG · Year 1 · Sec A (CSE)\",\"year_label\":\"1st Year\",\"sender_name\":\"sandra\",\"has_attachment\":false,\"attachment_original_name\":null}','2026-11-11 12:05:47');
/*!40000 ALTER TABLE `professor_announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `professor_hod_messages`
--

DROP TABLE IF EXISTS `professor_hod_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `professor_hod_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned NOT NULL,
  `thread_id` int(10) unsigned DEFAULT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `hod_id` int(10) unsigned NOT NULL,
  `sender_role` enum('professor','hod') NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_original_name` varchar(255) DEFAULT NULL,
  `attachment_mime_type` varchar(100) DEFAULT NULL,
  `attachment_size` int(10) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phm_dept` (`department_id`,`created_at`),
  KEY `idx_phm_prof` (`professor_id`,`created_at`),
  KEY `idx_phm_hod` (`hod_id`,`created_at`),
  KEY `idx_phm_thread` (`thread_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `professor_hod_messages`
--

LOCK TABLES `professor_hod_messages` WRITE;
/*!40000 ALTER TABLE `professor_hod_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `professor_hod_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `level` enum('UG','PG','Diploma','PhD','Other') DEFAULT 'UG',
  `duration_years` decimal(3,1) DEFAULT 3.0,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_prog_dept` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_attempts`
--

DROP TABLE IF EXISTS `question_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `question_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `score` decimal(6,2) DEFAULT NULL,
  `meta` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_qa_q` (`question_id`),
  KEY `idx_qa_inst` (`institution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_attempts`
--

LOCK TABLES `question_attempts` WRITE;
/*!40000 ALTER TABLE `question_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `question_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_banks`
--

DROP TABLE IF EXISTS `question_banks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_banks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_banks`
--

LOCK TABLES `question_banks` WRITE;
/*!40000 ALTER TABLE `question_banks` DISABLE KEYS */;
INSERT INTO `question_banks` VALUES (1,1,3,NULL,'SHORT · Database Management Systems · Unit 1 · K2','{\"type\":\"short\",\"unit\":1,\"klevel\":\"K2\",\"count\":5,\"subject\":\"Database Management Systems\"}','2026-11-11 12:03:12');
/*!40000 ALTER TABLE `question_banks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_id` int(10) unsigned NOT NULL,
  `unit_number` int(10) unsigned DEFAULT NULL,
  `question_type` enum('mcq','short','long','essay','case') NOT NULL,
  `bloom_k_level` varchar(10) DEFAULT NULL,
  `clo_code` varchar(20) DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `marks` decimal(5,1) DEFAULT 1.0,
  `stem` text NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `correct_answer` text DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `marking_scheme` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_q_bank` (`bank_id`),
  KEY `idx_q_bloom` (`bloom_k_level`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES (1,1,1,'short','K2','CLO1','medium',5.0,'Explain the concept of physical data independence in a database management system, and provide a brief illustrative example of how it protects application programs.',NULL,'Physical data independence is the capacity to modify the internal schema (e.g., changing file organizations, indexing strategies, or storage structures) without having to alter the conceptual schema or existing application programs. For example, if a DBA decides to switch the underlying storage of a large employee table from an unindexed sequential file to a B+-tree indexed file, the application queries that retrieve employee records remain completely unaffected because the logical view provided by the conceptual schema does not change.','K2 level requires explaining a concept in one\'s own words and providing an example. This question targets physical data independence, explaining both the mechanism and its practical protective effect on applications.','Question: Explain the concept of physical data independence in a database management system, and provide a brief illustrative exam (5 marks)\n\nMarking Scheme:\nKey concept / definition → 1 mark(s)\nCorrect explanation / reasoning → 1 mark(s)\nExample or application → 1 mark(s)\nStructure / completeness → 1 mark(s)\nConclusion / accuracy → 1 mark(s)','{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}','2026-11-11 12:03:12'),(2,1,1,'short','K2','CLO1','medium',5.0,'Contrast the roles of the conceptual schema and the physical schema in the three-schema database architecture, using a practical database scenario to clarify their differences.',NULL,'The conceptual schema describes the overall database structure for all users, hiding the details of physical storage structures and focusing on entities, data types, relationships, and constraints. In contrast, the physical schema describes how the data is actually stored on secondary storage devices, including details regarding file structures, record placements, and compression or indexing techniques. For example, in a university database, the conceptual schema defines the \'Student\' entity with attributes like ID, Name, and GPA, whereas the physical schema specifies that the Student relation is stored as a clustered index on the student_id field within a specific disk block allocation.','Tests the understanding of schema layers in database architecture by asking for a comparison and a concrete scenario.','Question: Contrast the roles of the conceptual schema and the physical schema in the three-schema database architecture, using a p (5 marks)\n\nMarking Scheme:\nKey concept / definition → 1 mark(s)\nCorrect explanation / reasoning → 1 mark(s)\nExample or application → 1 mark(s)\nStructure / completeness → 1 mark(s)\nConclusion / accuracy → 1 mark(s)','{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}','2026-11-11 12:03:12'),(3,1,1,'short','K2','CLO1','medium',5.0,'Explain the significance of mapping cardinalities in an Entity-Relationship (ER) model, and describe how a one-to-many relationship is typically translated during the reduction to a relational schema.',NULL,'Mapping cardinalities express the maximum number of relationship instances that an entity can participate in, categorizing relationships as one-to-one, one-to-many, or many-to-many. During the reduction of a binary one-to-many relationship set from an ER diagram to a relational schema, the entity on the \'many\' side of the relationship is augmented with a foreign key that references the primary key of the entity on the \'one\' side. For example, in a Department-to-Employee one-to-many relationship, the Employee relation receives \'dept_id\' as a foreign key pointing to the Department relation.','Evaluates comprehension of ER modeling constraints and the algorithmic translation (reduction) process into relational schemas.','Question: Explain the significance of mapping cardinalities in an Entity-Relationship (ER) model, and describe how a one-to-many r (5 marks)\n\nMarking Scheme:\nKey concept / definition → 1 mark(s)\nCorrect explanation / reasoning → 1 mark(s)\nExample or application → 1 mark(s)\nStructure / completeness → 1 mark(s)\nConclusion / accuracy → 1 mark(s)','{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}','2026-11-11 12:03:12'),(4,1,1,'short','K2','CLO1','medium',5.0,'Discuss the primary purpose of database languages, specifically differentiating between Data Definition Language (DDL) and Data Manipulation Language (DML) with functional examples.',NULL,'Database languages are specialized languages used by database administrators and users to specify database schemas, express queries, and update data. DDL is used to define and modify the database structure and constraints, such as creating tables or altering columns (e.g., \'CREATE TABLE Instructor (ID INT PRIMARY KEY, name VARCHAR(50));\'). DML, on the other hand, enables users to access or manipulate data organized by the appropriate data model, handling operations such as retrieval, insertion, deletion, and modification of actual records (e.g., \'INSERT INTO Instructor VALUES (101, \\\'Alice\\\');\').','Assesses the understanding of database system languages and the distinct functions of DDL versus DML.','Question: Discuss the primary purpose of database languages, specifically differentiating between Data Definition Language (DDL) a (5 marks)\n\nMarking Scheme:\nKey concept / definition → 1 mark(s)\nCorrect explanation / reasoning → 1 mark(s)\nExample or application → 1 mark(s)\nStructure / completeness → 1 mark(s)\nConclusion / accuracy → 1 mark(s)','{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}','2026-11-11 12:03:12'),(5,1,1,'short','K2','CLO1','hard',5.0,'Explain why weak entity sets require a supervising strong entity set, and describe how this dependency is represented in an ER diagram and resolved in a relational schema.',NULL,'A weak entity set is one that does not possess sufficient attributes to form a primary key of its own; thus, its existence depends on a strong entity set (referred to as the identifying owner). In an ER diagram, a weak entity is depicted with a double-lined rectangle, and its identifying relationship is shown with a double-lined diamond. When reducing this to a relational schema, the weak entity\'s relation includes its own partial discriminator attributes along with the primary key attributes of the strong entity set as a foreign key, with the combined set forming the primary key of the weak entity\'s relation.','Checks comprehension of weak entities, their notation in ER diagrams, and their structural conversion into relational tables.','Question: Explain why weak entity sets require a supervising strong entity set, and describe how this dependency is represented in (5 marks)\n\nMarking Scheme:\nKey concept / definition → 1 mark(s)\nCorrect explanation / reasoning → 1 mark(s)\nExample or application → 1 mark(s)\nStructure / completeness → 1 mark(s)\nConclusion / accuracy → 1 mark(s)','{\"subject\":\"Database Management Systems\",\"clo_code\":\"CLO1\"}','2026-11-11 12:03:12');
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students_roster`
--

DROP TABLE IF EXISTS `students_roster`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students_roster` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `register_no` varchar(60) NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `email` varchar(160) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roster` (`class_id`,`register_no`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students_roster`
--

LOCK TABLES `students_roster` WRITE;
/*!40000 ALTER TABLE `students_roster` DISABLE KEYS */;
INSERT INTO `students_roster` VALUES (1,1,1,4,'224026','Mohammed Abuthahir','abuthahirmohammed6@gmail.com',NULL,NULL,1);
/*!40000 ALTER TABLE `students_roster` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `study_materials`
--

DROP TABLE IF EXISTS `study_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `study_materials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `material_type` enum('notes','ppt') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_original_name` varchar(255) NOT NULL,
  `file_mime_type` varchar(120) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sm_prof` (`professor_id`,`created_at`),
  KEY `idx_sm_scope` (`institution_id`,`class_id`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `study_materials`
--

LOCK TABLES `study_materials` WRITE;
/*!40000 ALTER TABLE `study_materials` DISABLE KEYS */;
/*!40000 ALTER TABLE `study_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subject_assignments`
--

DROP TABLE IF EXISTS `subject_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subject_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` int(10) unsigned NOT NULL,
  `professor_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` varchar(40) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subj_prof_class` (`subject_id`,`professor_id`,`class_id`,`academic_year`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subject_assignments`
--

LOCK TABLES `subject_assignments` WRITE;
/*!40000 ALTER TABLE `subject_assignments` DISABLE KEYS */;
INSERT INTO `subject_assignments` VALUES (1,1,3,1,'2026-27','Odd Semester',NULL);
/*!40000 ALTER TABLE `subject_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(200) NOT NULL,
  `credits` decimal(4,1) DEFAULT 3.0,
  `contact_hours` int(10) unsigned DEFAULT 45,
  `semester` varchar(40) DEFAULT NULL,
  `syllabus_text` longtext DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subj_code_inst` (`institution_id`,`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,1,1,'CSE301','Database Management Systems',4.0,60,'Odd Semester',NULL,'{\"year\":1,\"course_type\":\"theory\"}',1);
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `role` enum('professor','student','hod','admin','superadmin') NOT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `employee_id` varchar(60) DEFAULT NULL,
  `qualification` varchar(120) DEFAULT NULL,
  `register_no` varchar(60) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `class_id` int(10) unsigned DEFAULT NULL COMMENT 'For students',
  `academic_year_level` tinyint(3) unsigned DEFAULT NULL COMMENT 'Student academic year 1-4',
  `semester` varchar(40) DEFAULT NULL COMMENT 'Student Odd/Even semester',
  `designation` varchar(100) DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Expandable user attributes' CHECK (json_valid(`extra`)),
  `is_active` tinyint(1) DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_user_inst_role` (`institution_id`,`role`),
  KEY `idx_user_dept` (`department_id`),
  CONSTRAINT `fk_user_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,NULL,'admin','admin@proprofessor.local','$2y$10$xAyEPAOZAEOEbYNTxh5wTO9iBbQ2HrmpogYsezccaXsld0Je4Z0/a','College Admin','ADM001',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'{\"weekly_digest_cache\":{\"key\":\"W202646\",\"source\":\"stats\",\"lines\":[\"You conducted 0 class session(s) this week.\",\"0 assignment submission(s) were graded.\",\"0 assignment(s) were created.\",\"0 internal mark record(s) were updated.\",\"0 student(s) are currently at attendance risk.\",\"0 submission(s) are pending grading.\",\"0 course plan(s) were updated.\"]}}',NULL,1,'2026-11-11 16:45:25','2026-08-08 17:07:02','2026-11-11 11:15:25'),(2,1,1,'hod','csehod@test.com','$2y$10$ckdNYN7xzzdxxE5.G25a2enLouD4jkf0Le3ipx1Q0TfIetTpK0wDi','Venketa lakshmi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-11 17:45:03','2026-11-11 11:15:51','2026-11-11 12:15:03'),(3,1,1,'professor','sandra@gmail.com','$2y$10$nBTOXDCkvg6uew2lgMKb.Ojt9YGMD570tYAV7aa8bIx0DaMepKF.m','sandra',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'{\"weekly_digest_cache\":{\"key\":\"W202646\",\"source\":\"stats\",\"lines\":[\"You conducted 0 class session(s) this week.\",\"0 assignment submission(s) were graded.\",\"1 assignment(s) were created.\",\"0 internal mark record(s) were updated.\",\"0 student(s) are currently at attendance risk.\",\"0 submission(s) are pending grading.\",\"1 course plan(s) were updated.\"]}}',NULL,1,'2026-11-11 17:45:26','2026-11-11 11:16:13','2026-11-11 12:15:26'),(4,1,1,'student','abuthahirmohammed6@gmail.com','$2y$10$WnToGPYbSZKB4DWeIFMm2uIWLoHrwns.g/osLFGucXRX4wksDz9My','Mohammed Abuthahir',NULL,NULL,'224026',NULL,NULL,1,1,'Odd Semester',NULL,NULL,NULL,1,'2026-11-11 17:36:09','2026-11-11 11:16:45','2026-11-11 12:06:09');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'proprofessor'
--

--
-- Dumping routines for database 'proprofessor'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-11 17:50:01
