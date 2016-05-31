SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `existing_brisskit_id` (
  `bid` varchar(16) NOT NULL DEFAULT '0',
  PRIMARY KEY (`bid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

SET FOREIGN_KEY_CHECKS=1;




# So we can support two components based on CiviCase simultaneously, one of the things we do is to use a mysql view 
# to replace the direct access to the civicrm_case table. It's not ideal but it means the changes to the core 
# are more manageable when civicrm is updated.
# 
# Then as long as the application code knows the current context, 'study' or 'recruitment', the proper civicrm_cases can be queried
# 
# As there is no hook currently available before a query runs, we'll need to make sure the component is specified in an
# alternative way. It could be done soon after DB connection perhaps?
#
# The mysql command that needs to be run is one of the following
#
# set @component_name='study'
# set @component_name='recruitment'
# set @component_name='all'



CREATE TABLE IF NOT EXISTS brisskit_components (
  component_id integer NOT NULL,
  component_name varchar(255) NOT NULL,
  PRIMARY KEY (`component_id`)
);

INSERT INTO brisskit_components VALUES (1, 'study')
  ON DUPLICATE KEY UPDATE component_name = 'study';
INSERT INTO brisskit_components VALUES (2, 'recruitment')
  ON DUPLICATE KEY UPDATE component_name = 'recruitment';

CREATE TABLE IF NOT EXISTS brisskit_case_mappings (
  case_id integer NOT NULL,
  component_id integer NOT NULL,
  PRIMARY KEY (`component_id`, `case_id`)
);

CREATE TABLE IF NOT EXISTS brisskit_case_type_mappings (
  case_type_id integer NOT NULL,
  component_id integer NOT NULL,
  PRIMARY KEY (`component_id`, `case_type_id`)
);


DROP FUNCTION IF EXISTS get_component_name;


# If we want to test in the mysql client we will need the following code that handles the delimiter
# 
# delimiter $$
# 
# CREATE FUNCTION get_component_name() RETURNS VARCHAR(255) DETERMINISTIC NO SQL
# BEGIN RETURN @component_name; END$$
# 
# delimiter ;


CREATE FUNCTION get_component_name() RETURNS VARCHAR(255) DETERMINISTIC NO SQL
BEGIN RETURN @component_name; END;


DROP VIEW IF EXISTS civicrm_brisskit_case;

CREATE VIEW civicrm_brisskit_case AS
  SELECT * 
  FROM civicrm_case cc 
  WHERE cc.id IN (
    SELECT case_id
    FROM brisskit_case_mappings bcm, brisskit_components bc
    WHERE (bc.component_name = get_component_name()
          AND bc.component_id = bcm.component_id)
    OR get_component_name() = 'all'
);

DROP VIEW IF EXISTS civicrm_brisskit_case_type;

CREATE VIEW civicrm_brisskit_case_type AS
  SELECT * 
  FROM civicrm_case_type cct 
  WHERE cct.id IN (
    SELECT case_type_id
    FROM brisskit_case_type_mappings bctm, brisskit_components bc
    WHERE (bc.component_name = get_component_name()
          AND bc.component_id = bctm.component_id)
    OR get_component_name() = 'all'
);


DELETE FROM `civicrm_word_replacement`;

INSERT INTO `civicrm_word_replacement` VALUES (0,'study|Case','Study',1,'exactMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'study|case','study',1,'exactMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'study|Client','Principal Investigator',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'study|cases','studies',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'study|Cases','Studies',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'study|CiviCase','Research Studies',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'study|Case Types','Study Types',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'study|Case Statuses','Study Statuses',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'study|New Case','Register new study',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'study|Add Case','Register new study',1,'wildcardMatch',1);

INSERT INTO `civicrm_word_replacement` VALUES (0,'recruitment|Case','Recruitment',1,'exactMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'recruitment|case','recruitment',1,'exactMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'recruitment|Client','Patient',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'recruitment|cases','recruitments',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'recruitment|Cases','Recruitments',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'recruitment|CiviCase','Study Recruitments',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'recruitment|Case Types','Studies',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'recruitment|Case Statuses','Recruitment Statuses',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'recruitment|New Case','Add new recruitment',1,'wildcardMatch',1);
INSERT INTO `civicrm_word_replacement` VALUES (0,'recruitment|Add Case','Add new recruitment',1,'wildcardMatch',1);


