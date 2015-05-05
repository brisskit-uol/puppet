class i2b2 {
	
	class { '::ruby':
		gems_version  => 'latest',
	}
	
	class { '::apache': 
		docroot		=> '/var/www/html',
		mpm_module	=> 'prefork',
		require		=> Class['::ruby'],
	}
	
	file { [ '/var', '/var/local', '/var/local/brisskit', '/var/local/brisskit/i2b2', ]:
		ensure	=> directory,
	}
	
	file { '/var/local/brisskit/i2b2/i2b2-1.7-install-procedures':
		source	=> 'puppet:///modules/i2b2/i2b2-1.7-install-procedures',
		recurse	=> true,
		require	=> File['/var/local/brisskit/i2b2'],
	}
	
	file { '/var/local/brisskit/i2b2/jboss-as-7.1.1.Final':
		source	=> 'puppet:///modules/i2b2/jboss-as-7.1.1.Final',
		recurse	=> true,
		require	=> File['/var/local/brisskit/i2b2'],
	}
	
	file { '/tmp/jdk1.7.0_17.tar.gz':
		source	=> 'puppet:///modules/i2b2/jdk1.7.0_17.tar.gz',
	}
	
	file { '/tmp/webclient.tar.gz':
		source	=> 'puppet:///modules/i2b2/webclient.tar.gz',
	}
	
	exec { "tar -xf /tmp/jdk1.7.0_17.tar.gz -C /var/local/brisskit/i2b2/":
		cwd     => "/var/local/brisskit/i2b2",
		creates => "/var/local/brisskit/i2b2/jdk1.7.0_17",
		path    => '/bin',
		require	=> [ File['/var/local/brisskit/i2b2'], File['/tmp/jdk1.7.0_17.tar.gz'], ],
	}
	
	exec { "tar -xf /tmp/webclient.tar.gz -C /var/www/html":
		cwd     => "/var/www/html",
		creates => "/var/www/html/i2b2",
		path    => '/bin',
		require	=> [ Class['::apache'], File['/tmp/webclient.tar.gz'], ],
	}
	
	class { "postgresql::server": }
	
	postgresql::server::db { 'i2b2':
		user     => 'i2b2',
		password => 'i2b2',
	}
	
	postgresql::server::role { "i2b2":
		password_hash 	=> postgresql_password('i2b2', 'i2b2'),
		superuser		=> true,
	}

	postgresql::server::role { "i2b2demodata":
		password_hash => postgresql_password('i2b2demodata', 'demouser'),
	}
	
	postgresql::server::role { "i2b2hive":
		password_hash => postgresql_password('i2b2hive', 'demouser'),
	}
	
	postgresql::server::role { "i2b2metadata":
		password_hash => postgresql_password('i2b2metadata', 'demouser'),
	}
	
	postgresql::server::role { "i2b2pm":
		password_hash => postgresql_password('i2b2pm', 'demouser'),
	}

	postgresql::server::role { "i2b2workdata":
		password_hash => postgresql_password('i2b2workdata', 'demouser'),
	}
	
	postgresql::server::role { "i2b2im":
		password_hash => postgresql_password('i2b2im', 'demouser'),
	}
	
	file { '/tmp/i2b2-dump.sql':
		source	=> 'puppet:///modules/i2b2/i2b2-dump.sql',
		notify	=> Exec['initialdump'],
		require	=> Postgresql::Server::Db['i2b2'],
	}
	
	exec { 'initialdump':
		command		=> 'sudo -u postgres psql -d i2b2 -f /tmp/i2b2-dump.sql && touch /tmp/.dbimport',
		path		=> ['/bin', '/usr/bin'],
		refreshonly	=> true,
		creates		=> '/tmp/.dbimport',
	}
	
}