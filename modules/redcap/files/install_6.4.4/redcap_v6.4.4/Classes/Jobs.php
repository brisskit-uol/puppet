<?php

/**
 * JOBS
 * This class will be instatiated by the Cron class. 
 * All functions listed in this class correspond to a specific job to be run.
 */
class Jobs
{
	/**
	 * CLEAR IP CACHE
	 * Clear all IP addresses older than 15 minutes from the redcap_ip_cache table
	 */
	public function ClearIPCache() 
	{	
		// Delete any rows older than 15 minutes
		$fifteenMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-15,date("s"),date("m"),date("d"),date("Y")));
		db_query("delete from redcap_ip_cache where timestamp < '$fifteenMinAgo'");
		$rowsDeleted = db_affected_rows();
		// Set cron job message
		if ($rowsDeleted > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$rowsDeleted rows deleted from redcap_ip_cache";
		}
	}
	
	
	/**
	 * CLEAR LOG VIEW REQUESTS
	 * Clear all items from redcap_log_view_requests table older than X hours.
	 */
	public function ClearLogViewRequests() 
	{	
		// Delete any rows older than 24 hours
		$xHoursAgo = date("Y-m-d H:i:s", mktime(date("H")-24,date("i"),date("s"),date("m"),date("d"),date("Y")));
		$sql = "select max(r.lvr_id) from redcap_log_view_requests r, redcap_log_view v 
				where v.log_view_id = r.log_view_id and v.ts < '$xHoursAgo'";
		$q = db_query($sql);
		if (db_num_rows($q)) {
			$max_lvr_id = db_result($q, 0);
			$sql = "delete from redcap_log_view_requests where lvr_id <= $max_lvr_id";
			db_query($sql);
			$rowsDeleted = db_affected_rows();
			// Set cron job message
			if ($rowsDeleted > 0) {
				$GLOBALS['redcapCronJobReturnMsg'] = "$rowsDeleted rows deleted from redcap_log_view_requests ";
			}
		}
	}
	
		
	/**
	 * CLEAR SURVEY SHORT CODES
	 * Clear all survey short codes older than X minutes from the redcap_surveys_short_codes table
	 */
	public function ClearSurveyShortCodes() 
	{	
		// Delete any rows older than X minutes
		$xMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-Survey::SHORT_CODE_EXPIRE,date("s"),date("m"),date("d"),date("Y")));
		db_query("delete from redcap_surveys_short_codes where ts < '$xMinAgo'");
		$rowsDeleted = db_affected_rows();
		// Set cron job message
		if ($rowsDeleted > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$rowsDeleted rows deleted from redcap_surveys_short_codes";
		}	
	}

	
	/**
	 * REMOVE TEMP/DELETED FILES
	 * Removes any old files in /temp directory and removes from server any files marked for deletion
	 */
	public function RemoveTempAndDeletedFiles() 
	{
		$docsDeleted = remove_temp_deleted_files(true);
		// Set cron job message
		if ($docsDeleted > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$docsDeleted documents deleted";
		}
	}	
	
	/**
	 * PUBMED AUTHOR
	 * Send web service request to PubMed to get PubMed IDs for an author within a time period
	 */
	public function PubMed() 
	{
		// Determine if this functionality is enabled
		global $pub_matching_enabled, $pub_matching_emails;
		if (!$pub_matching_enabled) return;
		// Instantiate the class to interface with PubMed
		$PubMed = new PubMedRedcap();
		// Query PubMed for all project PIs in REDCap
		$PubMed->searchPubMedByAuthors();
		// Fill in article details/authors for articles that are missing such things
		$PubMed->updateArticleDetails();
		// Update MeSH terms for *all* articles
		$PubMed->updateAllMeshTerms();
		// Update the last time this publication source was crawled
		$db = new RedCapDB();
		$db->updatePubCrawlTime(RedCapDB::PUBSRC_PUBMED);
		// If enabled, email the PIs about their publications
		if ($pub_matching_emails) $PubMed->emailPIs();
		// Set cron job message
		$GLOBALS['redcapCronJobReturnMsg'] = 
			"Added {$PubMed->articlesAdded} new pubs; " .
			"Added {$PubMed->matchesAdded} new project-pub matches; " .
			"Added {$PubMed->meshTermsAdded} new MeSH terms.";
		// Output details of job execution
		print $GLOBALS['redcapCronJobReturnMsg'];
	}
	
	/**
	 * EXPIRE SURVEYS
	 * For any surveys where an expiration timestamp is set, if the timestamp <= NOW, then make the survey inactive.
	 */
	public function ExpireSurveys() 
	{
		$sql = "update redcap_surveys set survey_enabled = 0, survey_expiration = null where survey_enabled = 1 
				and survey_expiration is not null and survey_expiration <= '" . date('Y-m-d H:i') . "'";
		$q = db_query($sql);
		$numSurveysExpired = db_affected_rows();
		db_free_result($q);
		// Set cron job message
		if ($numSurveysExpired > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$numSurveysExpired surveys were expired";
		}
	}
	
	
	/**
	 * REMIND USERS VIA EMAIL TO VISIT THE USER ACCESS DASHBOARD
	 * On the first weekday of every month, email all users to remind them to visit the User Access Dashboard page.
	 */
	public function ReminderUserAccessDashboard() 
	{
		global $project_contact_email, $lang, $user_access_dashboard_enable;
		// If feature is not enabled for sending emails, then return
		if ($user_access_dashboard_enable != '3') return;
		// Get the first weekday of the current month. Loop through all weekdays and compare their dates to determine.
		$weekdays = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday');
		$weekdays_dates = array();
		foreach ($weekdays as $this_weekday) {
			$weekdays_dates[$this_weekday] = date("Y-m-d", strtotime(date("Y-m")." $this_weekday"));
		}
		$firstWeekday = min($weekdays_dates);
		// Only continue if TODAY is the first weekday of the month
		if (TODAY != $firstWeekday) return;
		// Reset the queued status for all users (just in case)
		$sql = "update redcap_user_information set user_access_dashboard_email_queued = null";
		$q = db_query($sql);
		// Queue the email reminder for all users with access to the User Rights page in at least one project 
		// (exclude suspended users and users w/o email addresses)
		$sql = "update redcap_user_information i2, (select min(i.ui_id) as ui_id, i.user_email from redcap_user_information i, 
				redcap_user_rights u left join redcap_user_roles r on r.role_id = u.role_id 
				where i.username = u.username and ((u.user_rights = 1 and r.user_rights is null) or r.user_rights = 1) 
				and i.user_email is not null and i.user_email != '' and i.user_suspended_time is null group by i.user_email) x
				set i2.user_access_dashboard_email_queued = 'QUEUED' where x.ui_id = i2.ui_id";
		$q = db_query($sql);
		$numUsersReminded = db_affected_rows();
		// Now enable the email cron to send the emails to all the queued users. It will disable itself when finished.
		$sql = "update redcap_crons set cron_enabled = 'ENABLED' where cron_name = 'ReminderUserAccessDashboardEmail'";
		$q = db_query($sql);
		// Set cron job message
		if ($numUsersReminded > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$numUsersReminded users reminded to visit the User Access Dashboard";
		}
	}
	
	
	/**
	 * REMIND USERS VIA EMAIL BATCHES TO VISIT THE USER ACCESS DASHBOARD (enabled by ReminderUserAccessDashboard cron job)
	 * Email all users in batches to remind them to visit the User Access Dashboard page. Will disable itself when done.
	 */
	public function ReminderUserAccessDashboardEmail() 
	{
		global $project_contact_email, $lang, $user_access_dashboard_enable;
		// If feature is not enabled for sending emails, then return
		if ($user_access_dashboard_enable != '3') return;
		// Determine number of emails to send in this batch (use SurveyScheduler function)
		$sqllimit = SurveyScheduler::determineEmailsPerBatch();
		// Get all queued users
		$sql = "select ui_id, user_email from redcap_user_information where user_access_dashboard_email_queued = 'QUEUED'
				order by ui_id limit $sqllimit";
		$q = db_query($sql);
		$numEmailsToSend = db_num_rows($q);
		if ($numEmailsToSend > 0) 
		{
			## EMAILS TO SEND
			// Initialize email
			$email = new Message();
			$email->setFrom($project_contact_email);			
			$email->setSubject("[REDCap] {$lang['cron_08']}");				
			$emailContents = "{$lang['cron_02']}<br><br>{$lang['cron_12']}<br><br>
							 <a href=\"".APP_PATH_WEBROOT_FULL."index.php?action=user_access_dashboard\">".$lang['cron_21']."</a>";
			$email->setBody($emailContents, true);
			// Get all ui_id's and put in array
			$ui_ids = array();
			while ($row = db_fetch_assoc($q)) {
				$ui_ids[$row['ui_id']] = $row['user_email'];
			}			
			// Set all those ui_id's status as SENDING
			$sql = "update redcap_user_information set user_access_dashboard_email_queued = 'SENDING' 
					where ui_id in (" . prep_implode(array_keys($ui_ids)) . ")";
			db_query($sql);
			// Loop through users and send emails
			foreach ($ui_ids as $ui_id=>$user_email)
			{
				// Send the email
				$email->setTo($user_email);
				$email->send();
				// Remove user from the email queue
				$sql = "update redcap_user_information set user_access_dashboard_email_queued = null where ui_id = $ui_id";
				db_query($sql);
			}
			// Now check if there are any more emails to send in next cron. If not, then shut off the cron.
			$sql = "select count(1) from redcap_user_information where user_access_dashboard_email_queued = 'QUEUED'";
			$q = db_query($sql);
			$numEmailsToSendNext = db_result($q, 0);
			if ($numEmailsToSendNext < 1) {
				// DONE SENDING EMAILS, SO DISABLE THIS CRON JOB
				$sql = "update redcap_crons set cron_enabled = 'DISABLED' where cron_name = 'ReminderUserAccessDashboardEmail'";
				$q = db_query($sql);
			}			
			// Set cron job message
			$GLOBALS['redcapCronJobReturnMsg'] = "$numEmailsToSend users reminded via email (in this batch)";
		}
	}
	
	
	/**
	 * SUSPEND INACTIVE USERS
	 * For any users whose last login time or last API activity exceeds the defined max days of inactivity, 
	 * auto-suspend their account (if setting enabled).
	 */
	public function SuspendInactiveUsers() 
	{
		global $project_contact_email, $lang, $auth_meth_global, $suspend_users_inactive_type, $suspend_users_inactive_days,
			   $suspend_users_inactive_send_email;
		// If feature is not enabled, then return
		if ($suspend_users_inactive_type == '' || !is_numeric($suspend_users_inactive_days) || $suspend_users_inactive_days < 1) return;
		// Instantiate email object
		$email = new Message();
		$email->setFrom($project_contact_email);
		// Set current time for this batch
		$local_now = date('Y-m-d H:i:s');
		// Set date of x days ago
		$x_days_ago = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-$suspend_users_inactive_days,date("Y")));
		// Query users that we need to suspend (if never logged in, then use their user_creation time, else use last login time)
		if ($auth_meth_global == 'ldap_table' && $suspend_users_inactive_type == 'table') {
			// Table-based users only
			$sql = "select i.ui_id, i.username, i.user_email, i.user_sponsor, i.user_firstname, i.user_lastname 
					from redcap_user_information i, redcap_auth a 
					where i.username = a.username and i.user_suspended_time is null 
					and (
						(i.user_lastactivity is not null and i.user_lastactivity <= '$x_days_ago' and i.user_lastactivity > i.user_lastlogin)
						or ((i.user_lastactivity is null or i.user_lastactivity < i.user_lastlogin) and i.user_lastlogin is not null and i.user_lastlogin <= '$x_days_ago')
						or (i.user_lastactivity is null and i.user_lastlogin is null and i.user_creation is not null and i.user_creation <= '$x_days_ago')
					)";
		} else {
			// All users
			$sql = "select i.ui_id, i.username, i.user_email, i.user_sponsor, i.user_firstname, i.user_lastname 
					from redcap_user_information i where i.user_suspended_time is null 
					and (
						(i.user_lastactivity is not null and i.user_lastactivity <= '$x_days_ago' and i.user_lastactivity > i.user_lastlogin)
						or ((i.user_lastactivity is null or i.user_lastactivity < i.user_lastlogin) and i.user_lastlogin is not null and i.user_lastlogin <= '$x_days_ago')
						or (i.user_lastactivity is null and i.user_lastlogin is null and i.user_creation is not null and i.user_creation <= '$x_days_ago')
					)";
		}
		$q = db_query($sql);
		$numUsersInactive = 0;
		while ($row = db_fetch_assoc($q)) 
		{
			// Set user values
			$user = $row['username'];
			$ui_id = $row['ui_id'];
			$user_email = $row['user_email'];
			// Make sure user hasn't been UNsuspended in past X days (if so, then give them X more days before they get suspended). 
			// This ensures that an unsuspended user doesn't get suspended again due to inactivity just because they didn't log into REDCap
			// within 24 hours of being unsuspended (since the suspension cron runs every 24 hours).
			$sql = "SELECT 1 FROM redcap_log_event ".(db_get_version() > 5.0 ? "force index for order by (PRIMARY)" : "")."
					where event = 'MANAGE' and description = 'Unsuspend user from REDCap' and pk = '".prep($user)."' 
					and ts > ".str_replace(array(' ',':','-'), array('','',''), $x_days_ago)." 
					order by log_event_id desc limit 1";
			$q2 = db_query($sql);
			$user_unsuspended_in_past_x_days = db_num_rows($q2);
			if ($user_unsuspended_in_past_x_days) continue;
			// Set expiration to NULL and set suspended time to NOW
			$sql = "update redcap_user_information set user_suspended_time = '$local_now' where ui_id = $ui_id";
			db_query($sql);
			// Logging
			log_event($sql, "redcap_user_information", "MANAGE", $user, "username = '$user'", "Suspend user (via user inactivity)", "", "SYSTEM");
			// Email the user to let them know
			if ($user_email != '' && $suspend_users_inactive_send_email) 
			{
				// Determine if user has a sponsor with a valid email address
				$hasSponsor = false;
				if ($row['user_sponsor'] != '') {
					// Get sponsor's email address
					$sponsorUserInfo = User::getUserInfo($row['user_sponsor']);
					if ($sponsorUserInfo !== false && $sponsorUserInfo['user_email'] != '') {
						$hasSponsor = true;
					}
				}
				// Send email to user and/or user+sponsor
				if (!$hasSponsor) {
					// EMAIL USER ONLY
					$emailContents = 	"{$lang['cron_02']}<br><br>{$lang['cron_03']} \"<b>$user</b>\" 
										(<b>{$row['user_firstname']} {$row['user_lastname']}</b>) {$lang['cron_09']} 
										$suspend_users_inactive_days {$lang['cron_10']} 
										<a href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a>{$lang['period']} {$lang['cron_11']}";
				} else {
					// EMAIL USER AND CC SPONSOR
					$email->setCc($sponsorUserInfo['user_email']);
					$emailContents =   "{$lang['cron_02']}<br><br>{$lang['cron_13']} \"<b>{$row['username']}</b>\" 
										(<b>{$row['user_firstname']} {$row['user_lastname']}</b>) {$lang['cron_20']}
										$suspend_users_inactive_days {$lang['scheduling_25']}{$lang['period']} 
										{$lang['cron_14']} \"<b>{$sponsorUserInfo['username']}</b>\" 
										(<b>{$sponsorUserInfo['user_firstname']} {$sponsorUserInfo['user_lastname']}</b>){$lang['cron_18']} 
										<a href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a>{$lang['period']} {$lang['cron_11']}";				
				}
				// Send the email
				$email->setTo($user_email);
				$email->setSubject("[REDCap] {$row['username']}{$lang['cron_22']}");				
				$email->setBody($emailContents, true);
				$email->send();
			}
			// Increment counter
			$numUsersInactive++;
		}
		// Set cron job message
		if ($numUsersInactive > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$numUsersInactive user accounts were suspended (via user inactivity)";
		}
	}
	
	
	/**
	 * EXPIRE USERS
	 * For any users whose expiration timestamp is set, if the timestamp <= NOW, then suspend the user's 
	 * account and set expiration time back to NULL.
	 */
	public function ExpireUsers() 
	{
		global $project_contact_email, $lang;
		// Instantiate email object
		$email = new Message();
		$email->setFrom($project_contact_email);
		// Set current time for this batch
		$local_now = date('Y-m-d H:i:s');
		// Query users that we need to expire
		$sql = "select ui_id, username, user_email, user_sponsor, user_firstname, user_lastname 	
				from redcap_user_information where user_expiration is not null and user_expiration <= '$local_now'";
		$q = db_query($sql);
		$numUsersExpired = db_num_rows($q);
		while ($row = db_fetch_assoc($q)) {
			// Set user values
			$user = $row['username'];
			$ui_id = $row['ui_id'];
			$user_email = $row['user_email'];
			// Set expiration to NULL and set suspended time to NOW
			$sql = "update redcap_user_information set user_expiration = null, user_suspended_time = '$local_now' where ui_id = $ui_id";
			db_query($sql);
			// Logging
			log_event($sql, "redcap_user_information", "MANAGE", $user, "username = '$user'", "Suspend user (via user expiration)", "", "SYSTEM");
			// Email the user to let them know
			if ($user_email != '') 
			{
				// Determine if user has a sponsor with a valid email address
				$hasSponsor = false;
				if ($row['user_sponsor'] != '') {
					// Get sponsor's email address
					$sponsorUserInfo = User::getUserInfo($row['user_sponsor']);
					if ($sponsorUserInfo !== false && $sponsorUserInfo['user_email'] != '') {
						$hasSponsor = true;
					}
				}
				// Send email to user and/or user+sponsor
				if (!$hasSponsor) {
					// EMAIL USER ONLY
					$emailContents =   "{$lang['cron_02']}<br><br>{$lang['cron_03']} \"<b>$user</b>\" 
										(<b>{$row['user_firstname']} {$row['user_lastname']}</b>) {$lang['cron_04']} 
										<a href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a> {$lang['cron_05']}";
				} else {
					// EMAIL USER AND CC SPONSOR
					$email->setCc($sponsorUserInfo['user_email']);
					$emailContents =   "{$lang['cron_02']}<br><br>{$lang['cron_13']} \"<b>{$row['username']}</b>\" 
										(<b>{$row['user_firstname']} {$row['user_lastname']}</b>) {$lang['cron_17']}
										{$lang['cron_14']} \"<b>{$sponsorUserInfo['username']}</b>\" 
										(<b>{$sponsorUserInfo['user_firstname']} {$sponsorUserInfo['user_lastname']}</b>){$lang['cron_18']}
										<a href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a> {$lang['cron_05']}";					
				}
				// Send the email
				$email->setTo($user_email);
				$email->setSubject("[REDCap] {$row['username']}{$lang['cron_19']}");				
				$email->setBody($emailContents, true);
				$email->send();				
			}
		}
		// Set cron job message
		if ($numUsersExpired > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$numUsersExpired user accounts were suspended (via user expiration)";
		}
	}
	
	
	/**
	 * EMAIL USERS ABOUT UPCOMING ACCOUNT EXPIRATION
	 * For any users whose expiration timestamp is set, if the expiration time is less than X days from now, 
	 * then email the user to warn them of their impending account expiration.
	 */
	public function WarnUsersAccountExpiration() 
	{
		global $project_contact_email, $lang;
		// Static number of days before expiration occurs to warn them (first warning, then second warning)
		$warning_days = array(User::USER_EXPIRE_FIRST_WARNING_DAYS, User::USER_EXPIRE_SECOND_WARNING_DAYS); // e.g. 14 days, then 2 days
		// Initialize count
		$numUsersEmailed = 0;
		// Loop through each warning cycle (first/second) and send warning emails for each
		foreach ($warning_days as $days_before_expiration)
		{
			// Set date of x days from now
			$x_days_from_now = date("Y-m-d", mktime(date("H"),date("i"),date("s"),date("m"),date("d")+$days_before_expiration,date("Y")));
			// Instantiate email object
			$email = new Message();
			$email->setFrom($project_contact_email);
			// Query users that wille expire *exactly* x days from today (since this will only run once per day)
			$sql = "select username, user_email, user_expiration, user_sponsor, user_firstname, user_lastname 
					from redcap_user_information where user_expiration is not null and left(user_expiration, 10) = '$x_days_from_now'";
			$q = db_query($sql);
			$numUsersEmailed += db_num_rows($q);
			while ($row = db_fetch_assoc($q)) 
			{
				// Email the user to warn them
				if ($row['user_email'] != '') 
				{
					// Set date and time x days from now
					$mktime = strtotime($row['user_expiration']);
					$x_days_from_now_friendly = date("l, F j, Y", $mktime);
					$x_time_from_now_friendly = date("g:i A", $mktime);
					// Determine if user has a sponsor with a valid email address
					$hasSponsor = false;
					if ($row['user_sponsor'] != '') {
						// Get sponsor's email address
						$sponsorUserInfo = User::getUserInfo($row['user_sponsor']);
						if ($sponsorUserInfo !== false && $sponsorUserInfo['user_email'] != '') {
							$hasSponsor = true;
						}
					}
					// Send email to user and/or user+sponsor
					if (!$hasSponsor) {
						// EMAIL USER ONLY
						$emailContents =   "{$lang['cron_02']}<br><br>{$lang['cron_03']} \"<b>{$row['username']}</b>\" 
											(<b>{$row['user_firstname']} {$row['user_lastname']}</b>) {$lang['cron_06']} 
											<b>$x_days_from_now_friendly ($x_time_from_now_friendly)</b>{$lang['period']} 
											{$lang['cron_23']} {$lang['cron_24']} <a href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a> {$lang['cron_05']}";
					} else {
						// EMAIL USER AND CC SPONSOR
						$email->setCc($sponsorUserInfo['user_email']);
						$emailContents =   "{$lang['cron_02']}<br><br>{$lang['cron_13']} \"<b>{$row['username']}</b>\" 
											(<b>{$row['user_firstname']} {$row['user_lastname']}</b>) {$lang['cron_06']} 
											<b>$x_days_from_now_friendly ($x_time_from_now_friendly)</b>{$lang['period']} 
											{$lang['cron_23']} {$lang['cron_14']} \"<b>{$sponsorUserInfo['username']}</b>\" 
											(<b>{$sponsorUserInfo['user_firstname']} {$sponsorUserInfo['user_lastname']}</b>){$lang['cron_15']} 
											<a href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a> {$lang['cron_05']}";
						
					}
					// Send the email
					$email->setTo($row['user_email']);
					$email->setSubject("[REDCap] {$row['username']}{$lang['cron_16']} $days_before_expiration {$lang['scheduling_25']}");
					$email->setBody($emailContents, true);
					$email->send();
				}
			}
		}
		// Set cron job message
		if ($numUsersEmailed > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$numUsersEmailed users were emailed to warn them of their upcoming account expiration";
		}
	}
	
	/**
	 * SURVEY INVITATION EMAILER
	 * For any surveys having survey invitations that have been scheduled, send any invitations that are ready to be sent.
	 */
	public function SurveyInvitationEmailer() 
	{
		list ($emailCountSuccess, $emailCountFail) = SurveyScheduler::emailInvitations();
		// Set email-sending success/fail count message
		if ($emailCountSuccess + $emailCountFail > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$emailCountSuccess survey invitations sent successfully, " .
												 "\n$emailCountFail survey invitations failed to send";
		}
	}
	
	/**
	 * DELETE PROJECTS
	 * Permanently delete projects that were "deleted" by users X days ago
	 */
	public function DeleteProjects() 
	{
		// Get timestamp of PROJECT_DELETE_DAY_LAG days ago
		$thirtyDaysAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-PROJECT_DELETE_DAY_LAG,date("Y")));
		// Get all projects scheduled for deletion
		$sql = "select project_id from redcap_projects where date_deleted is not null 
				and date_deleted != '0000-00-00 00:00:00' and date_deleted <= '$thirtyDaysAgo'";
		$q = db_query($sql);
		$numProjDeleted = db_num_rows($q);
		while ($row = db_fetch_assoc($q)) 
		{
			// Permanently delete the project from all db tables right now (as opposed to flagging it for deletion later)
			deleteProjectNow($row['project_id']);
		}
		db_free_result($q);
		// Set cron job message
		if ($numProjDeleted > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$numProjDeleted projects were deleted";
		}
	}
	

	/**
	 * REAL-TIME WEB SERVICE DATA IMPORT
	 * Seed mr_id's for all records in all projects utilizing DDP service and also queue records that 
	 * are ready to be fetched from the source system (excludes archived/inactive projects).
	 */
	public function DDPQueueRecordsAllProjects() 
	{
		// Perform the seeding
		$recordsSeeded = DynamicDataPull::seedMrIdsAllProjects();
		// Set records as queued for those ready to be fetched from the source system
		$recordsQueued = DynamicDataPull::setQueuedFetchStatusAllProjects();
		// Set cron job message
		if ($recordsSeeded + $recordsQueued > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "DDP - $recordsSeeded records were seeded and $recordsQueued records were queued";
		}
	}
	

	/**
	 * REAL-TIME WEB SERVICE DATA IMPORT
	 * Fetch source system data for records in all projects utilizing DDP service.
	 * Perform fetch one project at a time (via HTTP Get request to DynamicDataPull/cron.php due to limitations with project-level methods
	 * used in the fetch method).
	 */
	public function DDPFetchRecordsAllProjects() 
	{
		// Fetch data for queued records
		$num_records_fetched = DynamicDataPull::fetchQueuedRecordsFromSource();
		// Set cron job message
		if ($num_records_fetched > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "DDP - $num_records_fetched records had data fetched from the external source system";
		}
	}
	

	/**
	 * PURGE CRON HISTORY
	 * Purges all rows from the cron history table that are older than one week.
	 */
	public function PurgeCronHistory() 
	{
		// Get timestamp of 7 days ago
		$sevenDaysAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-7,date("Y")));
		// Delete all rows older than 7 days old
		$sql = "delete from redcap_crons_history where (cron_run_end is not null and cron_run_end < '$sevenDaysAgo') 
				or (cron_run_end is null and cron_run_start < '$sevenDaysAgo')";
		$q = db_query($sql);
		$num_rows_deleted = db_affected_rows();
		// Set cron job message
		if ($num_rows_deleted > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$num_rows_deleted rows were deleted from the crons history table";
		}
	}
	

	/**
	 * SEND EMAIL TO ALL TABLE-BASED USERS TELLING THEM TO LOG IN FOR THE PURPOSE OF UPGRADING THEIR PASSWORD SECURITY (ONE TIME ONLY)
	 */
	public function UpdateUserPasswordAlgo() 
	{
		global $lang, $homepage_contact_email;
		// Initialize email object
		$email = new Message();
		$email->setFrom($homepage_contact_email);
		// Now loop through ALL table-based users and reset their password
		$sql = "select a.username, i.user_email from redcap_auth a, redcap_user_information i 
				where a.username = i.username and a.legacy_hash = 1 and i.user_suspended_time is null 
				and i.user_email is not null order by a.username";
		$q = db_query($sql);
		$num_emailed = db_num_rows($q);
		while ($row = db_fetch_assoc($q))
		{
			// Send email to user notifying them of their password reset (if user has an associate primary email address listed
			// AND if they are not a suspended user).
			$email->setTo($row['user_email']);
			$email->setSubject($lang['rights_282']);
			$emailContents = "{$lang['cron_02']}<br /><br />{$lang['rights_283']} \"<b>{$row['username']}</b>\"{$lang['period']}
				{$lang['rights_284']}<br /><br />{$lang['rights_285']} 
				<a href=\"mailto:$homepage_contact_email\">$homepage_contact_email</a>{$lang['period']}<br /><br />
				<b>REDCap</b> - <a href=\"".APP_PATH_WEBROOT_FULL."\">".APP_PATH_WEBROOT_FULL."</a>";
			$email->setBody($emailContents, true);
			$email->send();	
		}
		// When done, disable the cron job
		$sql = "update redcap_crons set cron_enabled = 'DISABLED' where cron_name = 'UpdateUserPasswordAlgo'";
		$q = db_query($sql);
		// Set cron job message
		if ($num_emailed > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$num_emailed users were sent an email to tell them to log in, which will upgrade the security standard of their account.";
		}
	}
	
	
	/**
	 * CHECK ALL DATEDIFF CONDITION LOGIC IN AUTOMATED SURVEYS INVITATIONS
	 * If any project uses "today" variable inside a datediff() for ASI conditional logic, 
	 * then check EVERY record in the project to see if it need invitations to be scheduled.
	 * This is done separately from the regular scheduler because using datediff() with "today" means that
	 * the data can change every day without a person trigger the change, so it needs to be triggered automatically
	 * by the system each day to check.
	 */
	public function AutomatedSurveyInvitationsDatediffChecker() 
	{
		global $Proj;
		// Keep count of all invitations that get scheduled
		$num_scheduled_total = 0;
		// Get a list of all projects that are using active, time-based conditional logic for automated notifications
		$sql = "SELECT distinct s.project_id FROM redcap_surveys_scheduler ss, redcap_surveys s, redcap_projects p 
				WHERE ss.active = 1 AND p.status <= 1 AND s.survey_id = ss.survey_id AND p.project_id = s.project_id
				AND ss.condition_logic like '%datediff%(%today%,%)%' order by s.project_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) 
		{
			// Instantiate Project object for this project and make sure it gets set as global (so that we don't have to recreate the object for EACH RECORD fetched)
			$Proj = new Project($row['project_id']);
			// Get a list of all records for the project
			$data = Records::getData($row['project_id'], 'array', null, $Proj->table_pk);
			// If project has no records, then go to next project
			if (empty($data)) continue;
			// Instantiate SurveyScheduler object for this project
			$surveyScheduler = new SurveyScheduler($row['project_id']);
			// Go through each record and check if each has any invitations that need to be scheduled
			foreach (array_keys($data) as $id) {
				// Check if record needs any schedulings done, and increment count of invitations scheduled, if any
				$num_scheduled_total += $surveyScheduler->checkToScheduleParticipantInvitation($id);
			}
		}
		// Free up memory
		unset($data, $Proj);
		// Set cron job message
		if ($num_scheduled_total > 0) {
			$GLOBALS['redcapCronJobReturnMsg'] = "$num_scheduled_total survey invitations were successfully scheduled via datediff(...today...) function.";
		}
	}
}
