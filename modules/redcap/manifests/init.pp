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
	
	::mysql::db { 'redcap':
		user     => 'redcapuser',
		password => 'redcappass',
	}
	
	file { '/var/www/html':
		source	=> 'puppet:///modules/redcap',
		recurse	=> true,
		require	=> Class['::apache'],
	}

}