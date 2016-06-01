class civicrm {

	class { '::ruby':
		gems_version  => 'latest',
	}

	class { '::apache': 
		docroot		=> '/var/www/html',
		mpm_module	=> 'prefork',
		require		=> Class['::ruby'],
	}
	
	class { '::apache::mod::php': }
	
	class { '::mysql::server': }
	
	class { '::mysql::bindings': 
		php_enable	=> true,
	}
	
	file { '/tmp/initial_DB.sql':
		source	=> 'puppet:///modules/civi/sql_6.4.4/initial_DB.sql',
		ensure	=> present,
	}
	
	::mysql::db { 'civicrm':
		user		=> 'civiuser',
		password 	=> 'civipass',
		sql			=> '/tmp/initial_DB.sql',
		require		=> File['/tmp/initial_DB.sql'],
	}
	
	file { '/var/www/html':
		source	=> 'puppet:///modules/civi/install_6.4.4',
		recurse	=> true,
		require	=> Class['::apache'],
	}
	
	file { '/var/www/html/index.html':
		ensure	=> absent,
		require	=> Class['::apache'],
	}

}