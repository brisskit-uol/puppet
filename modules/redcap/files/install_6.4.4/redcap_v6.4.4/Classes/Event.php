<?php

class Event
{
	public static function getEventsByProject($projectId)
	{
		$eventList = array();
		
		$sql = "SELECT * 
				FROM redcap_events_metadata rem 
					JOIN redcap_events_arms rea ON rem.arm_id = rea.arm_id
				WHERE project_id = $projectId";
		$events = db_query($sql);
		
		while ($row = db_fetch_array($events))
		{
			$eventList[$row['event_id']] = $row['descrip'];
		}
		
		return $eventList;
	}
	
	public static function getEventIdByName($projectId, $name)
	{
		$idList = getEventIdByKey($projectId, array($name));
		$id = (count($idList) > 0) ? $idList[0] : 0;
		
		return $id;
	}
	
	public static function getUniqueKeys($projectId)
	{
		global $Proj;
		if (empty($Proj)) {
			$Proj2 = new Project($projectId);
			return $Proj2->getUniqueEventNames();
		} else {
			return $Proj->getUniqueEventNames();
		}
	}
	
	public static function getEventNameById($projectId, $id)
	{
		$uniqueKeys = array_flip(Event::getUniqueKeys($projectId));
		
		$name = array_search($id, $uniqueKeys);
		
		return $name;
	}
	
	public static function getEventIdByKey($projectId, $keys)
	{
		$uniqueKeys = Event::getUniqueKeys($projectId);
		$idList = array();
		
		foreach($keys as $key)
		{
			$idList[] = array_search($key, $uniqueKeys);
		}
		
		return $idList;
	}
}
