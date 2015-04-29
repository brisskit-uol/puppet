class redcap {

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
		source	=> 'puppet:///modules/redcap/sql_6.4.4/initial_DB.sql',
		ensure	=> present,
	}
	
	::mysql::db { 'redcap':
		user		=> 'redcapuser',
		password 	=> 'redcappass',
		sql			=> '/tmp/initial_DB.sql',
		require		=> File['/tmp/initial_DB.sql'],
	}
	
	file { '/var/www/html':
		source	=> 'puppet:///modules/redcap/install_6.4.4',
		recurse	=> true,
		require	=> Class['::apache'],
	}
	
	file { '/var/www/html/index.html':
		ensure	=> absent,
		require	=> Class['::apache'],
	}

}