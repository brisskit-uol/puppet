<?php 
class BK_Constants {

  const ACTIVITY_POSITIVE_REPLY = "Positive reply received";
  const ACTIVITY_CHECK_STATUS = "Check participant status";
  const ACTIVITY_DATA_TRANSFER = "BRISSkit data transfer";
  const ACTIVITY_OPEN_CASE = "Open Case";

  const ACT_STATUS_COMPLETED = "Completed";
  const ACT_STATUS_ACCEPTED = "Accepted";
  const ACT_STATUS_SCHEDULED = "Scheduled";
  const ACT_STATUS_PENDING = "Pending";
  const ACT_STATUS_FAILED = "Failed";
  const ACT_STATUS_REJECTED = "Rejected";

  const POSITIVE_REPLY_SUBJECT = "Added upon positive participant reply";
  const CASE_BASE_STUDY = "Base";
  const CONTACT_STATUS_AVAILABLE = "Available";
  const CONTACT_STATUS_DECEASED = "Deceased";
  const CONTACT_STATUS_NOTAVAILABLE = "Not available";
  const CONTACT_STATUS_INSTUDY = "In study";

  const INSTITUTION_PREFIX = "UOL";

  const LETTER_DATE_INTERVAL = "P4D";
  const DEMO_CASE_TYPE = "Demo";
  const CASE_LOCATION = "/var/local/brisskit/civicases";

  const CIVICRM_MYSQL_CONFIG = "/etc/brisskit/mysql/civicrm.cfg";
  const BRISSKIT_CONFIG = "/etc/brisskit/settings.cfg";

  //const CIVISTUDY = "CiviStudy";
  //const CIVIRECRUITMENT = "CiviRecruitment";
  const CIVISTUDY = "study";
  const CIVIRECRUITMENT = "recruitment";

  const DATACOL_CONSENT = 'Consent to collect data';
  const TISSUE_CONSENT = 'Consent to extract tissue';
  const USEINFO_CONSENT = 'Consent to use information';

  const APPOINTMENT = 'Phone Call';

  const ACTION_DELETE = "delete";
  const ACTION_CREATE = "create";

	const STUDY_TYPE_PREFIX = 'Study Type: ';
	const STUDY_TEMPLATE_PREFIX = 'Template for studies of type #';
}
?>
